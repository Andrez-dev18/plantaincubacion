<?php
/**
 * RolRepository — Adaptado exclusivamente para Planta Incubación
 * Tablas: adm_rol, adm_rol_progr_modulo, amd_dashboard_modulos
 */
date_default_timezone_set('America/Lima');

class RolRepository
{
    private $conn;
    // Forzamos el ID de programa para Planta Incubación a 2
    private $idPrograma = '2';

    public function __construct($db)
    {
        $this->conn = $db;
    }

    // ─────────────────────────────────────────────────────────────────────
    // LISTADOS Y OBTENCIÓN INDIVIDUAL
    // ─────────────────────────────────────────────────────────────────────
    public function listarRoles()
    {
        $query = "SELECT r.id, r.cod_rol, r.nom_rol, r.descripcion, r.activo,
                          (SELECT COUNT(*) FROM adm_rol_progr_modulo rpm WHERE rpm.id_rol = r.id AND rpm.id_programa = :id_programa) as total_modulos
                  FROM adm_rol r 
                  WHERE (r.id_programa = :id_programa OR r.id_programa IS NULL OR r.id_programa = '') 
                  ORDER BY r.id DESC";
                  
        $stmt = $this->conn->prepare($query);
        $stmt->execute([':id_programa' => $this->idPrograma]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function obtenerPorId($id)
    {
        $stmt = $this->conn->prepare("SELECT * FROM adm_rol WHERE id = :id AND (id_programa = :id_programa OR id_programa IS NULL OR id_programa = '')");
        $stmt->execute([':id' => $id, ':id_programa' => $this->idPrograma]);
        $rol = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($rol) {
            // Obtenemos el arreglo simple de IDs de módulos marcados
            $stmtPermisos = $this->conn->prepare("SELECT cod_mod FROM adm_rol_progr_modulo WHERE id_rol = :id AND id_programa = :id_programa");
            $stmtPermisos->execute([':id' => $id, ':id_programa' => $this->idPrograma]);
            $rol['permisos'] = $stmtPermisos->fetchAll(PDO::FETCH_COLUMN);
        }

        return $rol;
    }

    // Retorna la estructura jerárquica para dibujar los Checkboxes de Permisos
    public function obtenerModulosPrograma()
    {
        $query = "SELECT cod_mod, nom_mod, tipo, parent_cod, orden, icono 
                  FROM amd_dashboard_modulos 
                  WHERE id_programa = :id_programa 
                  ORDER BY parent_cod, orden";
        $stmt = $this->conn->prepare($query);
        $stmt->execute([':id_programa' => $this->idPrograma]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // ─────────────────────────────────────────────────────────────────────
    // CREAR Y EDITAR (Con Transacción Atómica)
    // ─────────────────────────────────────────────────────────────────────
    public function guardar($datos, $modulosPermitidos, $isEdit = false)
    {
        try {
            $this->conn->beginTransaction();
            $idRol = null;

            if ($isEdit) {
                // ACTUALIZAR ROL
                $query = "UPDATE adm_rol SET cod_rol = :cod_rol, nom_rol = :nom_rol, descripcion = :descripcion 
                          WHERE id = :id AND (id_programa = :id_programa OR id_programa IS NULL OR id_programa = '')";
                $stmt = $this->conn->prepare($query);
                $stmt->execute([
                    ':cod_rol' => strtoupper(trim($datos['cod_rol'])),
                    ':nom_rol' => trim($datos['nom_rol']),
                    ':descripcion' => $datos['descripcion'] ?? null,
                    ':id' => $datos['id'],
                    ':id_programa' => $this->idPrograma
                ]);
                $idRol = $datos['id'];
            } else {
                // CREAR ROL (La tabla adm_rol no tiene fecha_creacion, solo fecha_update)
                $query = "INSERT INTO adm_rol (cod_rol, nom_rol, descripcion, activo, id_programa) 
                          VALUES (:cod_rol, :nom_rol, :descripcion, 1, :id_programa)";
                $stmt = $this->conn->prepare($query);
                $stmt->execute([
                    ':cod_rol' => strtoupper(trim($datos['cod_rol'])),
                    ':nom_rol' => trim($datos['nom_rol']),
                    ':descripcion' => $datos['descripcion'] ?? null,
                    ':id_programa' => $this->idPrograma
                ]);
                $idRol = $this->conn->lastInsertId();
            }

            // REEMPLAZAR PERMISOS DE MÓDULOS
            // 1. Borramos los permisos actuales para este rol y programa
            $stmtDel = $this->conn->prepare("DELETE FROM adm_rol_progr_modulo WHERE id_rol = :id_rol AND id_programa = :id_programa");
            $stmtDel->execute([':id_rol' => $idRol, ':id_programa' => $this->idPrograma]);

            // 2. Insertamos los nuevos marcados
            if (!empty($modulosPermitidos) && is_array($modulosPermitidos)) {
                $placeholders = [];
                $valores = [];

                foreach ($modulosPermitidos as $codMod) {
                    $placeholders[] = "(?, ?, ?)";
                    $valores[] = $idRol;
                    $valores[] = $this->idPrograma;
                    $valores[] = $codMod;
                }

                $queryIns = "INSERT INTO adm_rol_progr_modulo (id_rol, id_programa, cod_mod) VALUES " . implode(', ', $placeholders);
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

    // ─────────────────────────────────────────────────────────────────────
    // VALIDACIONES Y CAMBIO DE ESTADO
    // ─────────────────────────────────────────────────────────────────────
    public function eliminar($id)
    {
        // Verificar que ningún usuario esté utilizando este rol
        // adm_usuario_rol no tiene columna epre
        $sqlCheck = "SELECT COUNT(*) 
                     FROM adm_usuario_rol ur 
                      JOIN adm_rol r ON r.cod_rol = ur.cod_rol
                      WHERE r.id = :id AND (r.id_programa = :id_programa OR r.id_programa IS NULL OR r.id_programa = '')";

        $stmtCheck = $this->conn->prepare($sqlCheck);
        $stmtCheck->execute([
            ':id' => $id,
            ':id_programa' => $this->idPrograma
        ]);

        if ($stmtCheck->fetchColumn() > 0) {
            throw new Exception("No puedes eliminar este rol porque hay usuarios asignados a él en Planta Incubación. Remueva los accesos primero.");
        }

        $this->conn->beginTransaction();
        try {
            // Eliminar asignaciones de módulos
            $stmtDelPerm = $this->conn->prepare("DELETE FROM adm_rol_progr_modulo WHERE id_rol = :id AND id_programa = :id_programa");
            $stmtDelPerm->execute([':id' => $id, ':id_programa' => $this->idPrograma]);

            // Eliminar el catálogo del rol
            $stmtDelRol = $this->conn->prepare("DELETE FROM adm_rol WHERE id = :id AND (id_programa = :id_programa OR id_programa IS NULL OR id_programa = '')");
            $stmtDelRol->execute([
                ':id' => $id,
                ':id_programa' => $this->idPrograma
            ]);

            $this->conn->commit();
            return true;
        } catch (Exception $e) {
            $this->conn->rollBack();
            throw $e;
        }
    }

    public function cambiarEstado($id)
    {
        // Intercambia el estado entre 1 y 0
        $stmt = $this->conn->prepare("UPDATE adm_rol SET activo = IF(activo = 1, 0, 1) WHERE id = :id AND (id_programa = :id_programa OR id_programa IS NULL OR id_programa = '')");
        return $stmt->execute([
            ':id' => $id,
            ':id_programa' => $this->idPrograma
        ]);
    }

    public function existeCodRol($codRol, $idExcluir = null)
    {
        $query = "SELECT id FROM adm_rol WHERE cod_rol = :cod_rol AND (id_programa = :id_programa OR id_programa IS NULL OR id_programa = '')";
        $params = [':cod_rol' => $codRol, ':id_programa' => $this->idPrograma];

        if ($idExcluir) {
            $query .= " AND id != :id_excluir";
            $params[':id_excluir'] = $idExcluir;
        }

        $stmt = $this->conn->prepare($query);
        $stmt->execute($params);
        return $stmt->fetchColumn() !== false;
    }
}
?>