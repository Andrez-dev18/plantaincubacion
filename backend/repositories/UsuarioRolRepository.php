<?php
/**
 * UsuarioRolRepository — Acceso a datos para Asignación de Roles a Usuarios
 * Adaptado para Planta Incubación (Tablas _pic)
 */
class UsuarioRolRepository {

    private $conn;

    public function __construct($db) {
        $this->conn = $db;
    }

    /**
     * Retorna los IDs de roles actualmente asignados a un código de usuario
     * FILTRADO POR: epre 'RS' y programa '1'
     */
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

    /**
     * Reemplaza TODOS los roles de un usuario para este programa en específico.
     */
    public function guardarRolesUsuario(string $codigo, array $idsRol, $reduser = 'SYSTEM'): void {
        $this->conn->beginTransaction();
        try {
            // 1. Borrar SOLAMENTE los roles actuales de este usuario para el programa 1
            $stmtDelete = $this->conn->prepare(
                "DELETE ur FROM adm_usuario_rol_pic ur
                 JOIN adm_rol_pic r ON r.cod_rol = ur.cod_rol AND r.id_programa = '1'
                 WHERE ur.codigo = :codigo AND ur.epre = 'RS'"
            );
            $stmtDelete->execute([':codigo' => $codigo]);

            // 2. Insertar los nuevos roles marcados
            if (!empty($idsRol)) {
                $placeholders = implode(',', array_fill(0, count($idsRol), '?'));
                
                // Mapear los IDs enviados a sus cod_rol reales
                $rolStmt = $this->conn->prepare(
                    "SELECT id, cod_rol FROM adm_rol_pic
                      WHERE id IN ({$placeholders}) AND id_programa = '1' AND activo = 1"
                );
                $rolStmt->execute(array_values($idsRol));
                $rolMap = $rolStmt->fetchAll(PDO::FETCH_KEY_PAIR); // Retorna [id => cod_rol]

                $ins = $this->conn->prepare(
                    "INSERT IGNORE INTO adm_usuario_rol_pic (codigo, epre, cod_rol, fecha_asignacion, reduser)
                     VALUES (:codigo, 'RS', :cod_rol, NOW(), :reduser)"
                );
                
                foreach ($idsRol as $idRol) {
                    if (!isset($rolMap[$idRol])) {
                        continue; // Si el rol no existe o no es de este programa, se ignora
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

    // Alias para mantener compatibilidad con tu frontend/services
    public function obtenerRolesPorUsuario($idUsuario): array {
        return $this->obtenerRolesCodigo((string)$idUsuario);
    }

    public function asignarRolesLote($idUsuario, array $idsRol, $reduser = 'SYSTEM'): void {
        $this->guardarRolesUsuario((string)$idUsuario, $idsRol, $reduser);
    }

    /**
     * Retorna todos los roles activos de Planta Incubación para dibujar los checkboxes
     */
    public function obtenerRolesActivos(): array {
        $stmt = $this->conn->query(
            "SELECT id, cod_rol, nom_rol
               FROM adm_rol_pic
              WHERE activo = 1 AND id_programa = '1'
              ORDER BY nom_rol ASC"
        );
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
?>