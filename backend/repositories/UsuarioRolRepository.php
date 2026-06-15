<?php
/**
 * UsuarioRolRepository — Acceso a datos para Asignación de Roles a Usuarios
 *
 * Tablas:
 *   adm_usuario_rol — relación usuario↔rol  (codigo, cod_rol)
 *   adm_rol         — catálogo de roles
 *   usuarios_L      — usuarios de login IAM
 *
 * @package Backend
 * @subpackage Repositories
 */
class UsuarioRolRepository {

    /** @var PDO */
    private $conn;

    public function __construct($db) {
        $this->conn = $db;
    }

    // ─────────────────────────────────────────────────────────────────────
    // CONSULTAS PRINCIPALES
    // ─────────────────────────────────────────────────────────────────────

    /**
     * Lista todos los usuarios con los roles asignados (GROUP_CONCAT).
     */
    public function obtenerUsuariosConRoles(): array {
        $sql = "SELECT
                    u.codigo,
                    u.nombre,
                    COALESCE(u.activo, 1)                                AS activo,
                    IFNULL(GROUP_CONCAT(
                        r.id ORDER BY r.id SEPARATOR ','
                    ), '')                                                AS ids_roles,
                    IFNULL(GROUP_CONCAT(
                        r.cod_rol ORDER BY r.id SEPARATOR ','
                    ), '')                                                AS cods_roles,
                    IFNULL(GROUP_CONCAT(
                        r.nom_rol ORDER BY r.id SEPARATOR '||'
                    ), '')                                                AS nombres_roles
                FROM usuarios_L u
                LEFT JOIN adm_usuario_rol ur ON ur.codigo = u.codigo
                LEFT JOIN adm_rol r           ON r.cod_rol  = ur.cod_rol
                                               AND COALESCE(r.activo, 1) = 1
                GROUP BY u.codigo, u.nombre, u.activo
                ORDER BY u.nombre ASC";

        $stmt = $this->conn->prepare($sql);
        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($rows as &$row) {
            $row['activo']        = (int)$row['activo'];
            $row['ids_roles']     = $row['ids_roles']     !== ''
                ? array_map('intval', explode(',', $row['ids_roles']))
                : [];
            $row['cods_roles']    = $row['cods_roles']    !== ''
                ? explode(',', $row['cods_roles'])
                : [];
            $row['nombres_roles'] = $row['nombres_roles'] !== ''
                ? explode('||', $row['nombres_roles'])
                : [];
        }
        unset($row);

        return $rows;
    }

    /**
     * Alias de obtenerRolesCodigo() — mantiene compatibilidad con UsuarioService.
     * $idUsuario es en realidad el campo `codigo` de usuarios_L.
     *
     * @return int[]
     */
    public function obtenerRolesPorUsuario($idUsuario): array {
        return $this->obtenerRolesCodigo((string)$idUsuario);
    }

    /**
     * Alias de guardarRolesUsuario() — mantiene compatibilidad con UsuarioService.
     * Reemplaza los roles del usuario por el lote indicado.
     */
    public function asignarRolesLote($idUsuario, array $idsRol): void {
        $this->guardarRolesUsuario((string)$idUsuario, $idsRol);
    }

    /**
     * Retorna los IDs de roles actualmente asignados a un código de usuario.
     *
     * @return int[]
     */
    public function obtenerRolesCodigo(string $codigo): array {
        $stmt = $this->conn->prepare(
            "SELECT r.id
               FROM adm_usuario_rol ur
               JOIN adm_rol r ON r.cod_rol = ur.cod_rol
                              AND COALESCE(r.activo, 1) = 1
              WHERE ur.codigo = :codigo
              ORDER BY r.id ASC"
        );
        $stmt->execute([':codigo' => $codigo]);
        return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
    }

    /**
     * Reemplaza TODOS los roles de un usuario en una transacción atómica.
     * Si $idsRol está vacío, elimina todas las asignaciones del usuario.
     */
    public function guardarRolesUsuario(string $codigo, array $idsRol): void {
        $this->conn->beginTransaction();
        try {
            $this->conn->prepare(
                "DELETE FROM adm_usuario_rol WHERE codigo = :codigo"
            )->execute([':codigo' => $codigo]);

            if (!empty($idsRol)) {
                $placeholders = implode(',', array_fill(0, count($idsRol), '?'));
                $rolStmt = $this->conn->prepare(
                    "SELECT id, cod_rol FROM adm_rol
                      WHERE id IN ({$placeholders})
                        AND COALESCE(activo, 1) = 1"
                );
                $rolStmt->execute(array_values($idsRol));
                $rolMap = $rolStmt->fetchAll(PDO::FETCH_KEY_PAIR); // [id => cod_rol]

                $ins = $this->conn->prepare(
                    "INSERT IGNORE INTO adm_usuario_rol (codigo, cod_rol)
                     VALUES (:codigo, :cod_rol)"
                );
                foreach ($idsRol as $idRol) {
                    if (!isset($rolMap[$idRol])) {
                        continue;
                    }
                    $ins->execute([':codigo' => $codigo, ':cod_rol' => $rolMap[$idRol]]);
                }
            }

            $this->conn->commit();
        } catch (Exception $e) {
            $this->conn->rollBack();
            throw $e;
        }
    }

    // ─────────────────────────────────────────────────────────────────────
    // CATÁLOGO DE ROLES (para poblar checkboxes del modal)
    // ─────────────────────────────────────────────────────────────────────

    /**
     * Retorna todos los roles activos ordenados por nombre.
     */
    public function obtenerRolesActivos(): array {
        $stmt = $this->conn->query(
            "SELECT id, cod_rol, nom_rol
               FROM adm_rol
              WHERE COALESCE(activo, 1) = 1
              ORDER BY nom_rol ASC"
        );
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
