<?php
date_default_timezone_set('America/Lima');

class AsignacionRepository
{
    private $conn;

    public function __construct($db)
    {
        $this->conn = $db;
    }

    // 1. DataTables: Trae usuarios locales + los nombres de los roles que tienen (Programa 2)
    public function getUsuariosRolesDataTable($start, $length, $searchValue, $epre = 'RS')
    {
        $sqlBase = "FROM usuario u WHERE u.estado = 'A'";
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
        $stmtTotal = $this->conn->query("SELECT COUNT(codigo) FROM usuario WHERE estado = 'A'");
        $recordsTotal = $stmtTotal->fetchColumn();

        $sqlData = "SELECT u.codigo, u.nombre,
                        (SELECT GROUP_CONCAT(r.nom_rol SEPARATOR '||') 
                         FROM adm_usuario_rol ur 
                         INNER JOIN adm_rol r ON ur.cod_rol = r.cod_rol 
                         WHERE ur.codigo = u.codigo AND (r.id_programa = 2 OR r.id_programa IS NULL OR r.id_programa = '')) as roles_asignados
                    " . $sqlBase . " 
                    ORDER BY CASE WHEN roles_asignados IS NOT NULL THEN 0 ELSE 1 END ASC, 
                             u.codigo ASC 
                    LIMIT :start, :length";

        $stmt = $this->conn->prepare($sqlData);

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

    // 2. Traer todos los roles activos del Programa 2 para pintar las "tarjetas" en el modal
    public function obtenerRolesDisponibles($idPrograma = '2')
    {
        $stmt = $this->conn->prepare("SELECT cod_rol, nom_rol, descripcion FROM adm_rol WHERE activo = 1 AND (id_programa = :idProg OR id_programa IS NULL OR id_programa = '') ORDER BY nom_rol");
        $stmt->execute([':idProg' => $idPrograma]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // 3. Traer solo los CÓDIGOS de rol de un usuario específico para marcar los checkboxes (Programa 2)
    public function obtenerRolesDeUsuario($codigo, $epre = 'RS')
    {
        $sql = "SELECT ur.cod_rol 
                FROM adm_usuario_rol ur
                INNER JOIN adm_rol r ON ur.cod_rol = r.cod_rol
                WHERE ur.codigo = :codigo AND (r.id_programa = 2 OR r.id_programa IS NULL OR r.id_programa = '')";

        $stmt = $this->conn->prepare($sql);
        $stmt->execute([':codigo' => $codigo]);
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    // 4. Transacción: Borrar roles viejos y guardar los nuevos para el usuario
    public function guardarRoles($codigo, $epre, $roles, $reduser)
    {
        try {
            $this->conn->beginTransaction();

            $sqlDel = "DELETE ur FROM adm_usuario_rol ur 
                       INNER JOIN adm_rol r ON ur.cod_rol = r.cod_rol 
                       WHERE ur.codigo = :codigo AND (r.id_programa = 2 OR r.id_programa IS NULL OR r.id_programa = '')";
            $stmtDel = $this->conn->prepare($sqlDel);
            $stmtDel->execute([':codigo' => $codigo]);

            // Si envió roles, los insertamos en bloque (adm_usuario_rol solo tiene codigo y cod_rol)
            if (!empty($roles) && is_array($roles)) {
                $placeholders = [];
                $valores = [];

                foreach ($roles as $rol) {
                    $placeholders[] = "(?, ?)";
                    $valores[] = $codigo;
                    $valores[] = $rol;
                }

                $queryIns = "INSERT INTO adm_usuario_rol (codigo, cod_rol) VALUES " . implode(', ', $placeholders);
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
               FROM adm_usuario_rol ur
               JOIN adm_rol r ON r.cod_rol = ur.cod_rol 
                             AND (r.id_programa = '2' OR r.id_programa IS NULL OR r.id_programa = '')
                             AND r.activo = 1
              WHERE ur.codigo = :codigo 
              ORDER BY r.id ASC"
        );
        $stmt->execute([':codigo' => $codigo]);
        return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
    }

    public function obtenerRolesActivos(): array {
        $stmt = $this->conn->query(
            "SELECT id, cod_rol, nom_rol
               FROM adm_rol
              WHERE activo = 1 AND (id_programa = '2' OR id_programa IS NULL OR id_programa = '')
              ORDER BY nom_rol ASC"
        );
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function guardarRolesUsuario(string $codigo, array $idsRol, $reduser = 'SYSTEM'): void {
        $this->conn->beginTransaction();
        try {
            $stmtDelete = $this->conn->prepare(
                "DELETE ur FROM adm_usuario_rol ur
                 JOIN adm_rol r ON r.cod_rol = ur.cod_rol AND (r.id_programa = '2' OR r.id_programa IS NULL OR r.id_programa = '')
                 WHERE ur.codigo = :codigo"
            );
            $stmtDelete->execute([':codigo' => $codigo]);

            if (!empty($idsRol)) {
                $placeholders = implode(',', array_fill(0, count($idsRol), '?'));
                $rolStmt = $this->conn->prepare(
                    "SELECT id, cod_rol FROM adm_rol
                      WHERE id IN ({$placeholders}) AND (id_programa = '2' OR id_programa IS NULL OR id_programa = '') AND activo = 1"
                );
                $rolStmt->execute(array_values($idsRol));
                $rolMap = $rolStmt->fetchAll(PDO::FETCH_KEY_PAIR);

                $ins = $this->conn->prepare(
                    "INSERT IGNORE INTO adm_usuario_rol (codigo, cod_rol)
                     VALUES (:codigo, :cod_rol)"
                );
                
                foreach ($idsRol as $idRol) {
                    if (!isset($rolMap[$idRol])) {
                        continue;
                    }
                    $ins->execute([
                        ':codigo'  => $codigo, 
                        ':cod_rol' => $rolMap[$idRol]
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
