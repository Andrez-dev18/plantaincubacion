<?php
/**
 * RolRepository — Acceso a datos para Módulo Roles y Permisos
 *
 * Tablas:
 *   adm_rol              — catálogo de roles
 *   adm_rol_progr_modulo — módulos permitidos por rol y programa
 *   amd_dashboard_modulos — menús disponibles (árbol de checkboxes)
 *   amd_programas         — programas del sistema
 *
 * @package Backend
 * @subpackage Repositories
 */
class RolRepository {

    /** @var PDO */
    private $conn;

    public function __construct($db) {
        $this->conn = $db;
        $this->migrarColumnas();
    }

    /**
     * Auto-migración: agrega columnas faltantes en adm_rol.
     */
    private function migrarColumnas(): void {
        try {
            $ddl = [
                'activo'       => "ALTER TABLE adm_rol ADD COLUMN activo TINYINT(1) NOT NULL DEFAULT 1 AFTER nom_rol",
                'descripcion'  => "ALTER TABLE adm_rol ADD COLUMN descripcion VARCHAR(255) NULL AFTER activo",
                'id_programa'  => "ALTER TABLE adm_rol ADD COLUMN id_programa TINYINT(4) NULL AFTER descripcion",
                'fecha_update' => "ALTER TABLE adm_rol ADD COLUMN fecha_update DATETIME NULL AFTER id_programa",
            ];
            foreach ($ddl as $col => $sql) {
                $check = $this->conn->query("SHOW COLUMNS FROM adm_rol LIKE '{$col}'");
                if ($check->rowCount() === 0) {
                    $this->conn->exec($sql);
                }
            }
        } catch (Exception $e) {
            error_log("RolRepository::migrarColumnas: " . $e->getMessage());
        }
    }

    // ─────────────────────────────────────────────────────────────────────
    // CATÁLOGO DE ROLES
    // ─────────────────────────────────────────────────────────────────────

    /**
     * Listar todos los roles con conteo de módulos asignados.
     */
    public function obtenerTodos(): array {
        $sql = "SELECT r.id,
                       r.cod_rol,
                       r.nom_rol,
                       r.descripcion,
                       COALESCE(r.activo, 1) AS activo,
                       r.fecha_update,
                       COUNT(rpm.cod_mod) AS total_modulos
                FROM adm_rol r
                LEFT JOIN adm_rol_progr_modulo rpm ON rpm.id_rol = r.id
                GROUP BY r.id
                ORDER BY r.nom_rol ASC";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Obtener un rol por ID.
     */
    public function obtenerPorId(int $id): ?array {
        $stmt = $this->conn->prepare("SELECT * FROM adm_rol WHERE id = :id LIMIT 1");
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /**
     * Crear un nuevo rol.
     * @throws Exception si cod_rol ya existe
     */
    public function crear(array $data): int {
        $codRol = strtoupper(trim($data['cod_rol']));
        $nomRol = trim($data['nom_rol']);
        $desc   = trim($data['descripcion'] ?? '');

        $chk = $this->conn->prepare("SELECT COUNT(*) FROM adm_rol WHERE cod_rol = :c");
        $chk->execute([':c' => $codRol]);
        if ((int)$chk->fetchColumn() > 0) {
            throw new Exception("Ya existe un rol con el código «{$codRol}».");
        }

        $stmt = $this->conn->prepare(
            "INSERT INTO adm_rol (cod_rol, nom_rol, descripcion, activo, fecha_update)
             VALUES (:cod, :nom, :desc, 1, NOW())"
        );
        $stmt->execute([':cod' => $codRol, ':nom' => $nomRol, ':desc' => $desc ?: null]);
        return (int)$this->conn->lastInsertId();
    }

    /**
     * Actualizar nombre y descripción de un rol.
     */
    public function actualizar(int $id, string $nomRol, string $descripcion): bool {
        $stmt = $this->conn->prepare(
            "UPDATE adm_rol
                SET nom_rol     = :nom,
                    descripcion = :desc,
                    fecha_update = NOW()
              WHERE id = :id"
        );
        return $stmt->execute([
            ':nom'  => trim($nomRol),
            ':desc' => trim($descripcion) ?: null,
            ':id'   => $id,
        ]);
    }

    /**
     * Activar / desactivar un rol.
     */
    public function toggleActivo(int $id, int $activo): bool {
        $stmt = $this->conn->prepare(
            "UPDATE adm_rol
                SET activo       = :a,
                    fecha_update = NOW()
              WHERE id = :id"
        );
        return $stmt->execute([':a' => $activo ? 1 : 0, ':id' => $id]);
    }

    /**
     * Eliminar permanentemente un rol y todas sus asignaciones en cascada.
     * @throws Exception si el rol no existe
     */
    public function eliminar(int $id): void {
        $chk = $this->conn->prepare("SELECT COUNT(*) FROM adm_rol WHERE id = :id");
        $chk->execute([':id' => $id]);
        if ((int)$chk->fetchColumn() === 0) {
            throw new Exception("El rol con ID {$id} no existe.");
        }

        $this->conn->beginTransaction();
        try {
            $this->conn->prepare(
                "DELETE FROM adm_usuario_rol
                  WHERE cod_rol = (SELECT cod_rol FROM adm_rol WHERE id = :id)"
            )->execute([':id' => $id]);

            $this->conn->prepare(
                "DELETE FROM adm_rol_progr_modulo WHERE id_rol = :id"
            )->execute([':id' => $id]);

            $this->conn->prepare(
                "DELETE FROM adm_rol WHERE id = :id"
            )->execute([':id' => $id]);

            $this->conn->commit();
        } catch (Exception $e) {
            $this->conn->rollBack();
            throw $e;
        }
    }

    // ─────────────────────────────────────────────────────────────────────
    // MÓDULOS POR ROL
    // ─────────────────────────────────────────────────────────────────────

    /**
     * Obtener los cod_mod asignados a un rol (opcionalmente filtrado por programa).
     *
     * @return string[]
     */
    public function obtenerModulosDeRol(int $idRol, string $idPrograma = ''): array {
        if ($idPrograma !== '') {
            $stmt = $this->conn->prepare(
                "SELECT cod_mod FROM adm_rol_progr_modulo
                  WHERE id_rol = :id AND id_programa = :prog"
            );
            $stmt->execute([':id' => $idRol, ':prog' => $idPrograma]);
        } else {
            $stmt = $this->conn->prepare(
                "SELECT cod_mod FROM adm_rol_progr_modulo WHERE id_rol = :id"
            );
            $stmt->execute([':id' => $idRol]);
        }
        return array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'cod_mod');
    }

    /**
     * Guardar (reemplazar) los módulos de un rol para un programa dado.
     */
    public function guardarModulos(int $idRol, string $idPrograma, array $codMods): void {
        $this->conn->beginTransaction();
        try {
            $this->conn->prepare(
                "DELETE FROM adm_rol_progr_modulo WHERE id_rol = :id AND id_programa = :prog"
            )->execute([':id' => $idRol, ':prog' => $idPrograma]);

            if (!empty($codMods)) {
                $ins = $this->conn->prepare(
                    "INSERT IGNORE INTO adm_rol_progr_modulo
                        (id_rol, id_programa, cod_mod, fecha_creacion, fecha_actualizacion)
                     VALUES (:id, :prog, :mod, NOW(), NOW())"
                );
                foreach ($codMods as $cod) {
                    $ins->execute([':id' => $idRol, ':prog' => $idPrograma, ':mod' => trim($cod)]);
                }
            }
            $this->conn->commit();
        } catch (Exception $e) {
            $this->conn->rollBack();
            throw $e;
        }
    }

    // ─────────────────────────────────────────────────────────────────────
    // MENÚS DISPONIBLES (árbol de checkboxes)
    // ─────────────────────────────────────────────────────────────────────

    /**
     * Retorna los módulos de un programa para construir el árbol de checkboxes.
     */
    public function obtenerMenusDisponibles(string $idPrograma): array {
        $stmt = $this->conn->prepare(
            "SELECT cod_mod, tipo, parent_cod, nom_mod, label_short,
                    icono, url, nivel0, nivel1, nivel2, nivel3, orden
               FROM amd_dashboard_modulos
              WHERE id_programa = :prog
              ORDER BY orden, nivel0, nivel1, nivel2"
        );
        $stmt->execute([':prog' => $idPrograma]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // ─────────────────────────────────────────────────────────────────────
    // PROGRAMAS
    // ─────────────────────────────────────────────────────────────────────

    /**
     * Retorna todos los programas disponibles en amd_programas.
     */
    public function obtenerProgramas(): array {
        $stmt = $this->conn->query(
            "SELECT id_programa, nombre FROM amd_programas ORDER BY nombre ASC"
        );
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

