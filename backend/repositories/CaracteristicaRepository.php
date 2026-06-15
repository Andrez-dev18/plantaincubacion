<?php
/**
 * CaracteristicaRepository
 * 
 * Repositorio para gestión de características de galpones
 */

class CaracteristicaRepository {
    
    private $conn;

    public function __construct($db) {
        $this->conn = $db;
    }

    /**
     * Obtener todas las características
     * 
     * @return array Array de características
     */
    public function findAll() {
        $sql = "SELECT id, nombre, tipo_dato, listado_opciones, usuario_crea, fecha_hora_crea, usuario_modifica, fecha_hora_modifica
                FROM pi_dim_caracteristicas
                ORDER BY id ASC";
        
        $stmt = $this->conn->prepare($sql);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Obtener una característica por ID
     * 
     * @param int $id ID de la característica
     * @return array|null Datos de la característica o null
     */
    public function findById($id) {
        $sql = "SELECT id, nombre, tipo_dato, listado_opciones, usuario_crea, fecha_hora_crea, usuario_modifica, fecha_hora_modifica
                FROM pi_dim_caracteristicas
                WHERE id = ?";
        
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([$id]);
        
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /**
     * Crear una nueva característica
     * 
     * @param array $data Datos de la característica
     * @return int|false ID de la nueva característica o false
     */
    public function create($data) {
        $sql = "INSERT INTO pi_dim_caracteristicas (nombre, tipo_dato, listado_opciones, usuario_crea, fecha_hora_crea)
                VALUES (?, ?, ?, ?, NOW())";
        
        $stmt = $this->conn->prepare($sql);
        
        $nombre = $data['nombre'] ?? '';
        $tipoDato = $data['tipo_dato'] ?? 'Texto';
        $listadoOpciones = $data['listado_opciones'] ?? null;
        $usuarioCrea = $data['usuario_crea'] ?? 'sistema';
        
        if (!$stmt->execute([$nombre, $tipoDato, $listadoOpciones, $usuarioCrea])) {
            $errorInfo = $stmt->errorInfo();
            $errorMessage = $errorInfo[2] ?? 'Error desconocido al insertar característica';
            throw new Exception($errorMessage);
        }
        
        return $this->conn->lastInsertId();
    }

    /**
     * Actualizar una característica
     * 
     * @param int $id ID de la característica
     * @param array $data Datos a actualizar
     * @return bool Resultado de la operación
     */
    public function update($id, $data) {
        $sql = "UPDATE pi_dim_caracteristicas 
                SET nombre = ?,
                    tipo_dato = ?,
                    listado_opciones = ?,
                    usuario_modifica = ?,
                    fecha_hora_modifica = NOW()
                WHERE id = ?";
        
        $stmt = $this->conn->prepare($sql);
        
        $nombre = $data['nombre'] ?? '';
        $tipoDato = $data['tipo_dato'] ?? 'Texto';
        $listadoOpciones = $data['listado_opciones'] ?? null;
        $usuarioModifica = $data['usuario_modifica'] ?? 'sistema';
        
        return $stmt->execute([$nombre, $tipoDato, $listadoOpciones, $usuarioModifica, $id]);
    }

    /**
     * Eliminar una característica
     * 
     * @param int $id ID de la característica
     * @return bool Resultado de la operación
     */
    public function delete($id) {
        // Primero verificar si la característica está siendo usada
        $checkSql = "SELECT COUNT(*) as count FROM pi_dim_detalles WHERE id_caracteristica = ?";
        $checkStmt = $this->conn->prepare($checkSql);
        $checkStmt->execute([$id]);
        $result = $checkStmt->fetch(PDO::FETCH_ASSOC);
        
        if ($result['count'] > 0) {
            return false; // No se puede eliminar si está en uso
        }
        
        // Proceder a eliminar
        $sql = "DELETE FROM pi_dim_caracteristicas WHERE id = ?";
        $stmt = $this->conn->prepare($sql);
        
        return $stmt->execute([$id]);
    }

    /**
     * Verificar si una característica está siendo usada en galpones
     * 
     * @param int $id ID de la característica
     * @return array {en_uso: bool, count: int}
     */
    public function isInUse($id) {
        $sql = "SELECT COUNT(*) as count FROM pi_dim_detalles WHERE id_caracteristica = ?";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([$id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return [
            'en_uso' => $result['count'] > 0,
            'count' => (int)$result['count']
        ];
    }

    /**
     * Eliminar característica forzadamente (incluyendo todos sus datos asociados)
     * 
     * @param int $id ID de la característica
     * @return bool
     */
    public function forceDelete($id) {
        try {
            $this->conn->beginTransaction();
            
            // 1. Eliminar todos los datos asociados en pi_dim_detalles
            $deleteDetallesSql = "DELETE FROM pi_dim_detalles WHERE id_caracteristica = ?";
            $deleteDetallesStmt = $this->conn->prepare($deleteDetallesSql);
            $deleteDetallesStmt->execute([$id]);
            
            // 2. Eliminar la característica
            $deleteCaracSql = "DELETE FROM pi_dim_caracteristicas WHERE id = ?";
            $deleteCaracStmt = $this->conn->prepare($deleteCaracSql);
            $deleteCaracStmt->execute([$id]);
            
            $this->conn->commit();
            return true;
        } catch (PDOException $e) {
            $this->conn->rollBack();
            error_log("Error en forceDelete: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Verificar si una característica existe
     * 
     * @param int $id ID de la característica
     * @return bool
     */
    public function exists($id) {
        $sql = "SELECT COUNT(*) as count FROM pi_dim_caracteristicas WHERE id = ?";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([$id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $result['count'] > 0;
    }

    /**
     * Obtener tipos de datos disponibles
     * 
     * @return array
     */
    public function getTiposDatos() {
        return ['Texto', 'Número', 'Decimal'];
    }

    /**
     * Buscar características por nombre
     * 
     * @param string $searchTerm Término de búsqueda
     * @return array
     */
    public function search($searchTerm) {
        $sql = "SELECT id, nombre, tipo_dato, listado_opciones
                FROM pi_dim_caracteristicas
                WHERE LOWER(nombre) LIKE LOWER(?)
                ORDER BY nombre ASC";
        
        $stmt = $this->conn->prepare($sql);
        $stmt->execute(['%' . $searchTerm . '%']);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
