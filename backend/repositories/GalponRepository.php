<?php
/**
 * GalponRepository
 * 
 * Repositorio para gestión de galpones con modelo EAV (Entity-Attribute-Value)
 * Transforma las filas verticales de pi_dim_detalles en objetos con propiedades dinámicas
 */

class GalponRepository {
    
    private $conn;

    public function __construct($db) {
        $this->conn = $db;
    }

    /**
     * Obtener todos los galpones con sus características pivotadas
     * ADAPTADO para usar regcencosgalpones (tabla legacy) en lugar de pi_dim_maestrogranja
     * 
     * @param string|null $idGranja Filtrar por granja específica (tcencos)
     * @param string|null $fechaDesde NO USADO (regcencosgalpones no tiene fecha_hora_crea)
     * @param string|null $fechaHasta NO USADO (regcencosgalpones no tiene fecha_hora_crea)
     * @return array Array de objetos stdClass con propiedades dinámicas
     */
    public function findAll($idGranja = null, $fechaDesde = null, $fechaHasta = null) {
        try {
            // Usar regcencosgalpones como fuente de datos maestros
            // Combinar con pi_dim_detalles para características dinámicas
            $sql = "SELECT 
                        CONCAT(m.tcencos, '-', m.tcodint) as id,
                        m.tcencos as granja,
                        m.tnomcen as nombre,
                        m.tcodint as galpon,
                        NULL as fecha_hora_crea,
                        NULL as fecha_hora_modifica,
                        NULL as usuario_crea,
                        NULL as usuario_modifica,
                        c.id as caracteristica_id,
                        c.nombre as caracteristica_nombre,
                        d.dato as caracteristica_valor
                    FROM regcencosgalpones m
                    LEFT JOIN pi_dim_detalles d ON m.tcencos = d.id_granja AND m.tcodint = d.id_galpon
                    LEFT JOIN pi_dim_caracteristicas c ON d.id_caracteristica = c.id
                    WHERE 1=1";
            
            $params = [];
            
            if ($idGranja !== null) {
                $sql .= " AND m.tcencos = ?";
                $params[] = $idGranja;
            }
            
            // Nota: fechaDesde y fechaHasta ignorados porque regcencosgalpones no tiene estos campos
            
            $sql .= " ORDER BY m.tcencos, m.tcodint, c.id";
            
            $stmt = $this->conn->prepare($sql);
            $stmt->execute($params);
            
            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
            return $this->pivotResults($results);
        } catch (PDOException $e) {
            error_log("Error en GalponRepository::findAll: " . $e->getMessage());
            throw new Exception("Error al obtener galpones: " . $e->getMessage());
        }
    }

    /**
     * Buscar galpón por granja y número de galpón
     * ADAPTADO para usar regcencosgalpones en lugar de pi_dim_maestrogranja
     * 
     * @param string $granja ID de la granja (tcencos)
     * @param string $numeroGalpon Número del galpón (tcodint)
     * @return array|null Array con id, granja, galpon, nombre o null
     */
    public function findByGranjaGalpon($granja, $numeroGalpon) {
        $sql = "SELECT 
                    CONCAT(tcencos, '-', tcodint) as id,
                    tcencos as granja, 
                    tcodint as galpon, 
                    tnomcen as nombre 
                FROM regcencosgalpones 
                WHERE tcencos = ? AND tcodint = ?";
        
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([$granja, $numeroGalpon]);
        
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /**
     * Obtener un galpón por ID con características pivotadas
     * ADAPTADO para usar regcencosgalpones
     * 
     * @param string $id ID compuesto "tcencos-tcodint" (ej: "623-1")
     * @param string $idGranja ID de la granja (tcencos) - usado para compatibilidad
     * @return stdClass|null Objeto con propiedades dinámicas o null
     */
    public function findById($id, $idGranja) {
        // Extraer tcencos y tcodint del ID compuesto
        $parts = explode('-', $id);
        if (count($parts) !== 2) {
            return null;
        }
        
        $tcencos = $parts[0];
        $tcodint = $parts[1];
        
        $sql = "SELECT 
                    CONCAT(m.tcencos, '-', m.tcodint) as id,
                    m.tcencos as granja,
                    m.tnomcen as nombre,
                    m.tcodint as galpon,
                    NULL as fecha_hora_crea,
                    NULL as fecha_hora_modifica,
                    NULL as usuario_crea,
                    NULL as usuario_modifica,
                    c.id as caracteristica_id,
                    c.nombre as caracteristica_nombre,
                    d.dato as caracteristica_valor
                FROM regcencosgalpones m
                LEFT JOIN pi_dim_detalles d ON m.tcencos = d.id_granja AND m.tcodint = d.id_galpon
                LEFT JOIN pi_dim_caracteristicas c ON d.id_caracteristica = c.id
                WHERE m.tcencos = ? AND m.tcodint = ?
                ORDER BY c.id";
        
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([$tcencos, $tcodint]);
        
        $results = $this->pivotResults($stmt->fetchAll(PDO::FETCH_ASSOC));
        
        return !empty($results) ? $results[0] : null;
    }

    /**
     * "Crear" un nuevo galpón en el maestro
     * ADAPTADO para regcencosgalpones: No crea registros, solo verifica que existe
     * 
     * @param array $data Datos del galpón: granja (tcencos), galpon (tcodint), nombre, usuario_crea
     * @return string Retorna tcodint (número de galpón) si existe, o lanza excepción si no existe
     * @throws Exception Si el galpón no existe en regcencosgalpones
     */
    public function create($data) {
        // Verificar que el galpón existe en regcencosgalpones
        $sql = "SELECT tcodint 
                FROM regcencosgalpones 
                WHERE tcencos = ? AND tcodint = ?";
        
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([
            $data['granja'],
            $data['galpon']
        ]);
        
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$result) {
            throw new Exception("El galpón {$data['galpon']} no existe en la granja {$data['granja']}");
        }
        
        // Retornar el tcodint para usarlo como id_galpon en pi_dim_detalles
        return $data['galpon'];
    }

    /**
     * Actualizar datos básicos del galpón en el maestro
     * ADAPTADO para regcencosgalpones: NO actualiza la tabla legacy (solo lectura)
     * 
     * @param string $id ID compuesto "tcencos-tcodint"
     * @param string $idGranja ID de la granja (tcencos)
     * @param array $data Datos a actualizar (nombre, usuario_modifica)
     * @return bool Siempre retorna true (no hace nada)
     */
    public function updateMaestro($id, $idGranja, $data) {
        // No actualizamos regcencosgalpones porque es una tabla legacy de solo lectura
        // Los cambios solo se aplican a características en pi_dim_detalles
        return true;
    }

    /**
     * Verificar si existe un detalle específico
     * 
     * @param int $idGranja
     * @param int $idGalpon
     * @param int $idCaracteristica
     * @return bool
     */
    public function existeDetalle($idGranja, $idGalpon, $idCaracteristica) {
        $sql = "SELECT COUNT(*) as existe 
                FROM pi_dim_detalles 
                WHERE id_granja = ? AND id_galpon = ? AND id_caracteristica = ?";
        
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([$idGranja, $idGalpon, $idCaracteristica]);
        
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result['existe'] > 0;
    }

    /**
     * Insertar un nuevo detalle (característica)
     * 
     * @param array $data
     * @return bool
     */
    public function insertDetalle($data) {
        $sql = "INSERT INTO pi_dim_detalles 
                (id_granja, id_galpon, id_caracteristica, dato, usuario_crea, fecha_hora_crea) 
                VALUES (?, ?, ?, ?, ?, NOW())";
        
        $stmt = $this->conn->prepare($sql);
        return $stmt->execute([
            $data['id_granja'],
            $data['id_galpon'],
            $data['id_caracteristica'],
            $data['dato'],
            $data['usuario_crea']
        ]);
    }

    /**
     * Actualizar un detalle existente
     * 
     * @param array $data
     * @return bool
     */
    public function updateDetalle($data) {
        $sql = "UPDATE pi_dim_detalles 
                SET dato = ?,
                    usuario_modifica = ?,
                    fecha_hora_modifica = NOW()
                WHERE id_granja = ? AND id_galpon = ? AND id_caracteristica = ?";
        
        $stmt = $this->conn->prepare($sql);
        return $stmt->execute([
            $data['dato'],
            $data['usuario_modifica'],
            $data['id_granja'],
            $data['id_galpon'],
            $data['id_caracteristica']
        ]);
    }

    /**
     * Eliminar un galpón y sus detalles
     * ADAPTADO para regcencosgalpones: Solo elimina características, no el galpón maestro
     * 
     * @param string $id ID compuesto "tcencos-tcodint" (ej: "623-1")
     * @param string $idGranja ID de la granja (tcencos)
     * @return bool
     */
    public function delete($id, $idGranja) {
        // Extraer tcodint del ID compuesto
        $parts = explode('-', $id);
        if (count($parts) !== 2) {
            return false;
        }
        
        $tcencos = $parts[0];
        $tcodint = $parts[1];
        
        // Solo eliminar detalles (características) de pi_dim_detalles
        // NO eliminamos de regcencosgalpones porque es una tabla legacy de solo lectura
        $sql = "DELETE FROM pi_dim_detalles WHERE id_galpon = ? AND id_granja = ?";
        $stmt = $this->conn->prepare($sql);
        return $stmt->execute([$tcodint, $tcencos]);
    }

    /**
     * Obtener todas las características disponibles
     * 
     * @return array
     */
    public function getCaracteristicas() {
        try {
            $sql = "SELECT id, nombre, tipo_dato, listado_opciones FROM pi_dim_caracteristicas ORDER BY id";
            $stmt = $this->conn->query($sql);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error al obtener características: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Obtener todas las granjas desde tabla legacy regcencosgalpones
     * Consulta: SELECT tcencos, tnomcen FROM regcencosgalpones WHERE LEFT(tcencos,1)='6' GROUP BY tcencos, tnomcen
     * 
     * @return array Con estructura: [success => bool, data => array de granjas]
     */
    public function getGranjas() {
        try {
            // Mostrar TODAS las granjas sin filtrar, incluso sin nombre
            // Si tnomcen está vacío o NULL, usar "Granja XXX" como nombre
            $sql = "SELECT DISTINCT
                        tcencos as id, 
                        CASE
                            WHEN TRIM(COALESCE(tnomcen, '')) = '' THEN CONCAT('Granja ', tcencos)
                            ELSE tnomcen
                        END as nombre
                    FROM regcencosgalpones 
                    ORDER BY tcencos";
            
            $stmt = $this->conn->query($sql);
            $granjas = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            return [
                'success' => true,
                'data' => $granjas
            ];
        } catch (PDOException $e) {
            error_log("Error al obtener granjas: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Error al obtener granjas',
                'data' => []
            ];
        }
    }

    /**
     * Obtener galpones de una granja específica CON sus características desde pi_dim_detalles
     * 
     * @param string $tcencos ID de la granja (tcencos)
     * @return array Con estructura: [success => bool, data => array de galpones con características]
     */
    public function getGalponesByGranja($tcencos) {
        try {
            // Usar la misma lógica que findAll() pero filtrado por granja
            // Esto devuelve galpones con características pivotadas
            $sql = "SELECT 
                        CONCAT(m.tcencos, '-', m.tcodint) as id,
                        m.tcencos as granja,
                        m.tnomcen as nombre,
                        m.tcodint as galpon,
                        NULL as fecha_hora_crea,
                        NULL as fecha_hora_modifica,
                        NULL as usuario_crea,
                        NULL as usuario_modifica,
                        c.id as caracteristica_id,
                        c.nombre as caracteristica_nombre,
                        d.dato as caracteristica_valor
                    FROM regcencosgalpones m
                    LEFT JOIN pi_dim_detalles d ON m.tcencos = d.id_granja AND m.tcodint = d.id_galpon
                    LEFT JOIN pi_dim_caracteristicas c ON d.id_caracteristica = c.id
                    WHERE m.tcencos = ?
                    ORDER BY m.tcodint, c.id";
            
            $stmt = $this->conn->prepare($sql);
            $stmt->execute([$tcencos]);
            
            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $galpones = $this->pivotResults($results);
            
            return [
                'success' => true,
                'data' => $galpones
            ];
        } catch (PDOException $e) {
            error_log("Error al obtener galpones por granja: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Error al obtener galpones: ' . $e->getMessage(),
                'data' => []
            ];
        }
    }

    /**
     * MÉTODO CLAVE: Transforma filas EAV en objetos con propiedades dinámicas
     * 
     * Convierte:
     * [
     *   {id:1, caracteristica_nombre:'largo', caracteristica_valor:'50'},
     *   {id:1, caracteristica_nombre:'zona', caracteristica_valor:'norte'}
     * ]
     * 
     * En:
     * {
     *   id: 1,
     *   largo: '50',
     *   zona: 'norte'
     * }
     * 
     * @param array $rows Filas de la consulta SQL
     * @return array Array de objetos stdClass con propiedades dinámicas
     */
    private function pivotResults($rows) {
        $galpones = [];
        $currentGalpon = null;
        $currentId = null;
        
        foreach ($rows as $row) {
            // Si cambiamos de galpón, guardamos el anterior
            if ($currentId !== $row['id']) {
                if ($currentGalpon !== null) {
                    $galpones[] = $currentGalpon;
                }
                
                // Crear nuevo objeto galpón con propiedades base
                $currentGalpon = new stdClass();
                $currentGalpon->id = $row['id'];
                $currentGalpon->granja = $row['granja'];
                $currentGalpon->nombre = $row['nombre'];
                $currentGalpon->galpon = $row['galpon']; // Campo número de galpón
                $currentGalpon->fecha_hora_crea = $row['fecha_hora_crea'];
                $currentGalpon->fecha_hora_modifica = $row['fecha_hora_modifica'];
                $currentGalpon->usuario_crea = $row['usuario_crea'];
                $currentGalpon->usuario_modifica = $row['usuario_modifica'];
                
                $currentId = $row['id'];
            }
            
            // Agregar característica como propiedad dinámica usando el ID
            if ($row['caracteristica_id'] !== null) {
                $idPropiedad = $row['caracteristica_id'];
                $currentGalpon->$idPropiedad = $row['caracteristica_valor'];
            }
        }
        
        // Agregar el último galpón
        if ($currentGalpon !== null) {
            $galpones[] = $currentGalpon;
        }
        
        return $galpones;
    }
}


