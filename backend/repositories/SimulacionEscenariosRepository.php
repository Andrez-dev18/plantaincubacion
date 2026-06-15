<?php
/**
 * SimulacionEscenariosRepository
 * 
 * Repositorio para gestión de escenarios de simulación de carga
 * Maneja las tablas: sim_escenario, sim_escenario_zona, sim_escenario_resultado
 */

class SimulacionEscenariosRepository {

    private $conn;

    public function __construct($db) {
        $this->conn = $db;
    }

    // ─────────────────────────────────────────────
    // LISTAR todos los escenarios de una proyección
    // ─────────────────────────────────────────────
    public function listarPorProyeccion(string $proyeccion): array {
        try {
            error_log("Repository: Iniciando listarPorProyeccion para: " . $proyeccion);
            
            $sql = "
                SELECT 
                    e.*,
                    r.total_galpones, r.area_total_m2, r.galp_std_2640,
                    r.pollos_semana, r.oferta_pollos_semana,
                    r.cargas_diario, r.tpo_ciclo_crianza,
                    r.tpo_crianza_ponderado, r.tpo_descanso_total,
                    r.tpo_descanso_efectivo, r.fecha_calculo
                FROM sim_escenario e
                LEFT JOIN sim_escenario_resultado r ON r.id_escenario = e.id
                WHERE e.proyeccion = :proyeccion
                  AND e.estado = 'A'
                ORDER BY FIELD(e.cod_escenario, 'ACTUAL', 'ESC1', 'ESC2')
            ";
            
            error_log("Repository: Ejecutando query SQL");
            $stmt = $this->conn->prepare($sql);
            $stmt->bindParam(':proyeccion', $proyeccion, PDO::PARAM_STR);
            $stmt->execute();
            
            error_log("Repository: Query ejecutado, obteniendo resultados");
            $rows = [];
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                error_log("Repository: Obteniendo zonas para escenario ID: " . $row['id']);
                $row['zonas'] = $this->listarZonas((int)$row['id']);
                $rows[] = $row;
            }
            
            error_log("Repository: Total escenarios encontrados: " . count($rows));
            return $rows;
        } catch (PDOException $e) {
            error_log("Repository: Error PDO en listarPorProyeccion: " . $e->getMessage());
            error_log("Repository: SQL State: " . $e->getCode());
            throw new Exception("Error al listar escenarios: " . $e->getMessage());
        }
    }

    // ─────────────────────────────────────────────
    // OBTENER un escenario por ID
    // ─────────────────────────────────────────────
    public function obtenerPorId(int $id): ?array {
        try {
            $sql = "SELECT * FROM sim_escenario WHERE id = :id AND estado = 'A'";
            $stmt = $this->conn->prepare($sql);
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);
            $stmt->execute();
            
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$row) return null;
            
            $row['zonas'] = $this->listarZonas($id);
            return $row;
        } catch (PDOException $e) {
            error_log("Error en obtenerPorId: " . $e->getMessage());
            throw new Exception("Error al obtener escenario: " . $e->getMessage());
        }
    }

    // ─────────────────────────────────────────────
    // LISTAR zonas de un escenario
    // ─────────────────────────────────────────────
    public function listarZonas(int $idEscenario): array {
        try {
            $sql = "SELECT * FROM sim_escenario_zona WHERE id_escenario = :id_escenario ORDER BY zona";
            $stmt = $this->conn->prepare($sql);
            $stmt->bindParam(':id_escenario', $idEscenario, PDO::PARAM_INT);
            $stmt->execute();
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error en listarZonas: " . $e->getMessage());
            throw new Exception("Error al listar zonas: " . $e->getMessage());
        }
    }

    // ─────────────────────────────────────────────
    // GUARDAR escenario + zonas (INSERT o UPDATE)
    // ─────────────────────────────────────────────
    public function guardar(array $data, string $usuario): array {
        try {
            $this->conn->beginTransaction();
            
            // UPSERT usando ON DUPLICATE KEY UPDATE
            // Si existe (uk_proy_esc), actualiza; si no existe, inserta
            $sql = "
                INSERT INTO sim_escenario
                    (proyeccion, cod_escenario, nom_escenario, notas,
                     n_galpones_2640, n_galpones_1800, pollos_por_galpon,
                     cargas_semanales, tpo_limpieza_dias, usuario_crea, estado)
                VALUES (:proyeccion, :cod_escenario, :nom_escenario, :notas,
                        :n_galpones_2640, :n_galpones_1800, :pollos_por_galpon,
                        :cargas_semanales, :tpo_limpieza_dias, :usuario_crea, 'A')
                ON DUPLICATE KEY UPDATE
                    nom_escenario = VALUES(nom_escenario),
                    notas = VALUES(notas),
                    n_galpones_2640 = VALUES(n_galpones_2640),
                    n_galpones_1800 = VALUES(n_galpones_1800),
                    pollos_por_galpon = VALUES(pollos_por_galpon),
                    cargas_semanales = VALUES(cargas_semanales),
                    tpo_limpieza_dias = VALUES(tpo_limpieza_dias),
                    usuario_modifica = :usuario_crea,
                    fecha_modifica = NOW()
            ";
            $stmt = $this->conn->prepare($sql);
            $stmt->bindParam(':proyeccion', $data['proyeccion']);
            $stmt->bindParam(':cod_escenario', $data['cod_escenario']);
            $stmt->bindParam(':nom_escenario', $data['nom_escenario']);
            $notasValue = $data['notas'] ?? null;
            $stmt->bindParam(':notas', $notasValue);
            $stmt->bindParam(':n_galpones_2640', $data['n_galpones_2640'], PDO::PARAM_INT);
            $stmt->bindParam(':n_galpones_1800', $data['n_galpones_1800'], PDO::PARAM_INT);
            $stmt->bindParam(':pollos_por_galpon', $data['pollos_por_galpon'], PDO::PARAM_INT);
            $stmt->bindParam(':cargas_semanales', $data['cargas_semanales']);
            $stmt->bindParam(':tpo_limpieza_dias', $data['tpo_limpieza_dias']);
            $stmt->bindParam(':usuario_crea', $usuario);
            $stmt->execute();
            
            // Obtener el ID después del UPSERT
            $sql2 = "SELECT id FROM sim_escenario WHERE proyeccion = :proyeccion AND cod_escenario = :cod_escenario";
            $stmt2 = $this->conn->prepare($sql2);
            $stmt2->bindParam(':proyeccion', $data['proyeccion']);
            $stmt2->bindParam(':cod_escenario', $data['cod_escenario']);
            $stmt2->execute();
            $row = $stmt2->fetch(PDO::FETCH_ASSOC);
            $id = (int)$row['id'];

            // Guardar zonas
            if (!empty($data['zonas'])) {
                $this->guardarZonas($id, $data['zonas']);
            }

            $this->conn->commit();
            return ['success' => true, 'id' => $id];

        } catch (Exception $e) {
            $this->conn->rollback();
            error_log("Error en guardar: " . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    // ─────────────────────────────────────────────
    // GUARDAR zonas (UPSERT)
    // ─────────────────────────────────────────────
    private function guardarZonas(int $idEscenario, array $zonas): void {
        $sql = "
            INSERT INTO sim_escenario_zona
                (id_escenario, zona, porcentaje_zona, tpo_crianza_dias)
            VALUES (:id_escenario, :zona, :porcentaje_zona, :tpo_crianza_dias)
            ON DUPLICATE KEY UPDATE
                porcentaje_zona  = VALUES(porcentaje_zona),
                tpo_crianza_dias = VALUES(tpo_crianza_dias)
        ";
        
        $stmt = $this->conn->prepare($sql);
        
        foreach ($zonas as $z) {
            $stmt->bindParam(':id_escenario', $idEscenario, PDO::PARAM_INT);
            $stmt->bindParam(':zona', $z['zona']);
            $stmt->bindParam(':porcentaje_zona', $z['porcentaje_zona']);
            $stmt->bindParam(':tpo_crianza_dias', $z['tpo_crianza_dias']);
            $stmt->execute();
        }
    }

    // ─────────────────────────────────────────────
    // GUARDAR resultado calculado (UPSERT)
    // ─────────────────────────────────────────────
    public function guardarResultado(int $idEscenario, array $r, string $usuario): void {
        try {
            $sql = "
                INSERT INTO sim_escenario_resultado
                    (id_escenario, total_galpones, area_total_m2,
                     galp_std_2640, pollos_semana, oferta_pollos_semana,
                     cargas_diario, tpo_ciclo_crianza, tpo_crianza_ponderado,
                     tpo_descanso_total, tpo_descanso_efectivo,
                     fecha_calculo, usuario_calculo)
                VALUES (:id_escenario, :total_galpones, :area_total_m2,
                        :galp_std_2640, :pollos_semana, :oferta_pollos_semana,
                        :cargas_diario, :tpo_ciclo_crianza, :tpo_crianza_ponderado,
                        :tpo_descanso_total, :tpo_descanso_efectivo,
                        NOW(), :usuario_calculo)
                ON DUPLICATE KEY UPDATE
                    total_galpones        = VALUES(total_galpones),
                    area_total_m2         = VALUES(area_total_m2),
                    galp_std_2640         = VALUES(galp_std_2640),
                    pollos_semana         = VALUES(pollos_semana),
                    oferta_pollos_semana  = VALUES(oferta_pollos_semana),
                    cargas_diario         = VALUES(cargas_diario),
                    tpo_ciclo_crianza     = VALUES(tpo_ciclo_crianza),
                    tpo_crianza_ponderado = VALUES(tpo_crianza_ponderado),
                    tpo_descanso_total    = VALUES(tpo_descanso_total),
                    tpo_descanso_efectivo = VALUES(tpo_descanso_efectivo),
                    fecha_calculo         = NOW(),
                    usuario_calculo       = VALUES(usuario_calculo)
            ";
            
            $stmt = $this->conn->prepare($sql);
            $stmt->bindParam(':id_escenario', $idEscenario, PDO::PARAM_INT);
            $stmt->bindParam(':total_galpones', $r['total_galpones'], PDO::PARAM_INT);
            $stmt->bindParam(':area_total_m2', $r['area_total_m2']);
            $stmt->bindParam(':galp_std_2640', $r['galp_std_2640'], PDO::PARAM_INT);
            $stmt->bindParam(':pollos_semana', $r['pollos_semana'], PDO::PARAM_INT);
            $stmt->bindParam(':oferta_pollos_semana', $r['oferta_pollos_semana'], PDO::PARAM_INT);
            $stmt->bindParam(':cargas_diario', $r['cargas_diario']);
            $stmt->bindParam(':tpo_ciclo_crianza', $r['tpo_ciclo_crianza']);
            $stmt->bindParam(':tpo_crianza_ponderado', $r['tpo_crianza_ponderado']);
            $stmt->bindParam(':tpo_descanso_total', $r['tpo_descanso_total'], PDO::PARAM_INT);
            $stmt->bindParam(':tpo_descanso_efectivo', $r['tpo_descanso_efectivo'], PDO::PARAM_INT);
            $stmt->bindParam(':usuario_calculo', $usuario);
            $stmt->execute();
        } catch (PDOException $e) {
            error_log("Error en guardarResultado: " . $e->getMessage());
            throw new Exception("Error al guardar resultado: " . $e->getMessage());
        }
    }

    // ─────────────────────────────────────────────
    // ELIMINAR (soft delete)
    // ─────────────────────────────────────────────
    public function eliminar(int $id, string $usuario): bool {
        try {
            $sql = "UPDATE sim_escenario SET estado='I', usuario_modifica=:usuario, fecha_modifica=NOW() WHERE id=:id";
            $stmt = $this->conn->prepare($sql);
            $stmt->bindParam(':usuario', $usuario);
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);
            return $stmt->execute();
        } catch (PDOException $e) {
            error_log("Error en eliminar: " . $e->getMessage());
            throw new Exception("Error al eliminar escenario: " . $e->getMessage());
        }
    }

    // ─────────────────────────────────────────────
    // VERIFICAR si existe un escenario
    // ─────────────────────────────────────────────
    public function existe(string $proyeccion, string $codEscenario): bool {
        try {
            $sql = "SELECT COUNT(*) as total FROM sim_escenario WHERE proyeccion = :proyeccion AND cod_escenario = :cod_escenario AND estado = 'A'";
            $stmt = $this->conn->prepare($sql);
            $stmt->bindParam(':proyeccion', $proyeccion);
            $stmt->bindParam(':cod_escenario', $codEscenario);
            $stmt->execute();
            
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return $result['total'] > 0;
        } catch (PDOException $e) {
            error_log("Error en existe: " . $e->getMessage());
            return false;
        }
    }
}
