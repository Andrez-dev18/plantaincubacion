<?php
/**
 * UsuarioSistemaRepository — Acceso a datos para Módulo Usuarios del Sistema
 *
 * Tablas:
 *   usuarios_L       — usuarios de login IAM (codigo, nombre, password AES, activo)
 *   conempre         — clave AES (epre = 'RS')
 *   adm_usuario_rol  — relación usuario↔rol
 *   adm_rol          — catálogo de roles
 *
 * @package Backend
 * @subpackage Repositories
 */
class UsuarioSistemaRepository {

    /** @var PDO */
    private $conn;

    public function __construct($db) {
        $this->conn = $db;
    }

    // ─────────────────────────────────────────────────────────────────────
    // LISTADO ADMIN
    // ─────────────────────────────────────────────────────────────────────

    /**
     * Lista todos los usuarios con sus roles (GROUP_CONCAT).
     */
    public function obtenerTodosAdmin(): array {
        $sql = "SELECT
                    u.codigo,
                    u.nombre,
                    u.activo,
                    u.fecha_registro,
                    u.ultimo_acceso,
                    IFNULL(GROUP_CONCAT(r.id       ORDER BY r.nom_rol SEPARATOR ','),  '') AS ids_roles,
                    IFNULL(GROUP_CONCAT(r.nom_rol  ORDER BY r.nom_rol SEPARATOR '||'), '') AS nombres_roles
                FROM usuarios_L u
                LEFT JOIN adm_usuario_rol ur ON ur.codigo  = u.codigo
                LEFT JOIN adm_rol r           ON r.cod_rol  = ur.cod_rol
                                             AND COALESCE(r.activo, 1) = 1
                GROUP BY u.codigo, u.nombre, u.activo, u.fecha_registro, u.ultimo_acceso
                ORDER BY u.nombre ASC";

        $stmt = $this->conn->prepare($sql);
        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($rows as &$row) {
            $row['activo']        = (int)$row['activo'];
            $row['ids_roles']     = $row['ids_roles']     !== ''
                ? array_map('intval', explode(',', $row['ids_roles']))
                : [];
            $row['nombres_roles'] = $row['nombres_roles'] !== ''
                ? explode('||', $row['nombres_roles'])
                : [];
        }
        unset($row);

        return $rows;
    }

    // ─────────────────────────────────────────────────────────────────────
    // BÚSQUEDA INDIVIDUAL
    // ─────────────────────────────────────────────────────────────────────

    public function obtenerPorCodigo(string $codigo): ?array {
        $stmt = $this->conn->prepare(
            "SELECT codigo, nombre,
                    COALESCE(activo, 1) AS activo
             FROM usuarios_L WHERE codigo = :codigo LIMIT 1"
        );
        $stmt->execute([':codigo' => $codigo]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function existeCodigo(string $codigo): bool {
        $stmt = $this->conn->prepare("SELECT COUNT(*) FROM usuarios_L WHERE codigo = :codigo");
        $stmt->execute([':codigo' => $codigo]);
        return (int)$stmt->fetchColumn() > 0;
    }

    // ─────────────────────────────────────────────────────────────────────
    // CRUD
    // ─────────────────────────────────────────────────────────────────────

    /**
     * Crear usuario con contraseña cifrada AES (compatible con sistema legado).
     * @throws Exception si el código ya existe
     */
    public function crear(array $data): string {
        $codigo   = trim($data['codigo']);
        $nombre   = trim($data['nombre']);
        $password = $data['password'];

        if ($this->existeCodigo($codigo)) {
            throw new Exception("El usuario «{$codigo}» ya está registrado.");
        }

        $sql = "INSERT INTO usuarios_L (codigo, nombre, password, epre, activo, fecha_registro)
                SELECT ?, ?, LEFT(AES_ENCRYPT(?, c.enom), 8), 'RS', 1, NOW()
                FROM conempre c WHERE c.epre = 'RS' LIMIT 1";

        $stmt = $this->conn->prepare($sql);
        $stmt->execute([$codigo, $nombre, $password]);
        return $codigo;
    }

    /**
     * Actualizar nombre del usuario.
     */
    public function actualizar(string $codigo, string $nombre): bool {
        $stmt = $this->conn->prepare(
            "UPDATE usuarios_L SET nombre = :nombre WHERE codigo = :codigo"
        );
        return $stmt->execute([':nombre' => trim($nombre), ':codigo' => $codigo]);
    }

    /**
     * Activar (1) o desactivar (0) un usuario (estado A / I).
     */
    public function toggleActivo(string $codigo, int $activo): bool {
        $stmt = $this->conn->prepare(
            "UPDATE usuarios_L SET activo = :activo WHERE codigo = :codigo"
        );
        return $stmt->execute([':activo' => $activo ? 1 : 0, ':codigo' => $codigo]);
    }

    /**
     * Cambiar contraseña con cifrado AES (mismo mecanismo que el login).
     */
    public function cambiarPassword(string $codigo, string $password): bool {
        $sql = "UPDATE usuarios_L
                SET password = (
                    SELECT LEFT(AES_ENCRYPT(?, c.enom), 8)
                    FROM conempre c WHERE c.epre = 'RS' LIMIT 1
                )
                WHERE codigo = ?";
        $stmt = $this->conn->prepare($sql);
        return $stmt->execute([$password, $codigo]);
    }
}
