<?php
date_default_timezone_set('America/Lima');

class AsignacionRepository
{
    private $conn;

    public function __construct($db)
    {
        $this->conn = $db;
    }

    // 1. DataTables: Trae usuarios locales (usuarios_L) + los nombres de los roles que tienen en Planta Incubación (Programa 1)
    public function getUsuariosRolesDataTable($start, $length, $searchValue, $epre = 'RS')
    {
        $sqlBase = "FROM usuarios_L u WHERE u.activo = 1";
        $params = [];

        if (!empty($searchValue)) {
            $sqlBase .= " AND (u.codigo LIKE :search1 OR u.nombre LIKE :search2)";
            $params[':search1'] = "%$searchValue%";
            $params[':search2'] = "%$searchValue%";
        }

        // Conteo filtrado
        $stmtFiltro = $this->conn->prepare("SELECT COUNT(u.codigo) " . $sqlBase);
        $stmtFiltro->execute($params);
        $recordsFiltered = $stmtFiltro->fetchColumn();

        // Conteo total
        $stmtTotal = $this->conn->query("SELECT COUNT(codigo) FROM usuarios_L WHERE activo = 1");
        $recordsTotal = $stmtTotal->fetchColumn();

        $sqlData = "SELECT u.codigo, u.nombre,
                        (SELECT GROUP_CONCAT(r.nom_rol SEPARATOR '||') 
                         FROM adm_usuario_rol_pic ur 
                         INNER JOIN adm_rol_pic r ON ur.cod_rol = r.cod_rol 
                         WHERE ur.codigo = u.codigo AND ur.epre = :epre AND r.id_programa = 1) as roles_asignados
                    " . $sqlBase . " 
                    ORDER BY CASE WHEN roles_asignados IS NOT NULL THEN 0 ELSE 1 END ASC, 
                             u.codigo ASC 
                    LIMIT :start, :length";

        $stmt = $this->conn->prepare($sqlData);

        $stmt->bindValue(':epre', $epre, PDO::PARAM_STR);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value, PDO::PARAM_STR);
        }
        $stmt->bindValue(':start', (int)$start, PDO::PARAM_INT);
        $stmt->bindValue(':length', (int)$length, PDO::PARAM_INT);
        $stmt->execute();

        return [
            'recordsTotal' => $recordsTotal,
            'recordsFiltered' => $recordsFiltered,
            'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)
        ];
    }

    // 2. Traer todos los roles activos del Programa 1 (Planta Incubación) para pintar las "tarjetas" en el modal
    public function obtenerRolesDisponibles($idPrograma = '1')
    {
        $stmt = $this->conn->prepare("SELECT cod_rol, nom_rol, descripcion FROM adm_rol_pic WHERE activo = 1 AND id_programa = :idProg ORDER BY nom_rol");
        $stmt->execute([':idProg' => $idPrograma]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // 3. Traer solo los CÓDIGOS de rol de un usuario específico para marcar los checkboxes en Planta (Programa 1)
    public function obtenerRolesDeUsuario($codigo, $epre = 'RS')
    {
        $sql = "SELECT ur.cod_rol 
                FROM adm_usuario_rol_pic ur
                INNER JOIN adm_rol_pic r ON ur.cod_rol = r.cod_rol
                WHERE ur.codigo = :codigo AND ur.epre = :epre AND r.id_programa = 1";

        $stmt = $this->conn->prepare($sql);
        $stmt->execute([':codigo' => $codigo, ':epre' => $epre]);
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    // 4. Transacción: Borrar roles viejos de Planta y guardar los nuevos para el usuario
    public function guardarRoles($codigo, $epre, $roles, $reduser)
    {
        try {
            $this->conn->beginTransaction();

            $sqlDel = "DELETE ur FROM adm_usuario_rol_pic ur 
                       INNER JOIN adm_rol_pic r ON ur.cod_rol = r.cod_rol 
                       WHERE ur.codigo = :codigo AND ur.epre = :epre AND r.id_programa = 1";
            $stmtDel = $this->conn->prepare($sqlDel);
            $stmtDel->execute([':codigo' => $codigo, ':epre' => $epre]);

            // Si envió roles, los insertamos en bloque
            if (!empty($roles) && is_array($roles)) {
                $placeholders = [];
                $valores = [];

                foreach ($roles as $rol) {
                    $placeholders[] = "(?, ?, ?, ?)";
                    $valores[] = $codigo;
                    $valores[] = $epre;
                    $valores[] = $rol;
                    $valores[] = $reduser;
                }

                $queryIns = "INSERT INTO adm_usuario_rol_pic (codigo, epre, cod_rol, reduser) VALUES " . implode(', ', $placeholders);
                $stmtIns = $this->conn->prepare($queryIns);
                $stmtIns->execute($valores);
            }

            $this->conn->commit();
            return true;
        } catch (Exception $e) {
            $this->conn->rollBack();
            throw $e;
        }
    }

    // ─── Métodos de Compatibilidad para Login y Servicios Internos ─────────

    public function obtenerRolesCodigo(string $codigo): array {
        $stmt = $this->conn->prepare(
            "SELECT r.id
               FROM adm_usuario_rol_pic ur
               JOIN adm_rol_pic r ON r.cod_rol = ur.cod_rol 
                                 AND r.id_programa = '1' 
                                 AND r.activo = 1
              WHERE ur.codigo = :codigo 
                AND ur.epre = 'RS'
              ORDER BY r.id ASC"
        );
        $stmt->execute([':codigo' => $codigo]);
        return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
    }

    public function obtenerRolesActivos(): array {
        $stmt = $this->conn->query(
            "SELECT id, cod_rol, nom_rol
               FROM adm_rol_pic
              WHERE activo = 1 AND id_programa = '1'
              ORDER BY nom_rol ASC"
        );
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function guardarRolesUsuario(string $codigo, array $idsRol, $reduser = 'SYSTEM'): void {
        $this->conn->beginTransaction();
        try {
            $stmtDelete = $this->conn->prepare(
                "DELETE ur FROM adm_usuario_rol_pic ur
                 JOIN adm_rol_pic r ON r.cod_rol = ur.cod_rol AND r.id_programa = '1'
                 WHERE ur.codigo = :codigo AND ur.epre = 'RS'"
            );
            $stmtDelete->execute([':codigo' => $codigo]);

            if (!empty($idsRol)) {
                $placeholders = implode(',', array_fill(0, count($idsRol), '?'));
                $rolStmt = $this->conn->prepare(
                    "SELECT id, cod_rol FROM adm_rol_pic
                      WHERE id IN ({$placeholders}) AND id_programa = '1' AND activo = 1"
                );
                $rolStmt->execute(array_values($idsRol));
                $rolMap = $rolStmt->fetchAll(PDO::FETCH_KEY_PAIR);

                $ins = $this->conn->prepare(
                    "INSERT IGNORE INTO adm_usuario_rol_pic (codigo, epre, cod_rol, fecha_asignacion, reduser)
                     VALUES (:codigo, 'RS', :cod_rol, NOW(), :reduser)"
                );
                
                foreach ($idsRol as $idRol) {
                    if (!isset($rolMap[$idRol])) {
                        continue;
                    }
                    $ins->execute([
                        ':codigo'  => $codigo, 
                        ':cod_rol' => $rolMap[$idRol],
                        ':reduser' => $reduser
                    ]);
                }
            }

            $this->conn->commit();
        } catch (Exception $e) {
            $this->conn->rollBack();
            throw $e;
        }
    }
}
?>
