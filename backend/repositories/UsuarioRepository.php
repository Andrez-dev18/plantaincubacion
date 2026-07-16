<?php

class UsuarioRepository {

    private $conn;

    public function __construct($db)
    {
        $this->conn = $db;
    }

    /**
     * Login original (mantener compatibilidad)
     * DEPRECADO: Usar loginConRol() para el nuevo sistema
     */
    public function login($usuario, $password) {
        $sql = "SELECT u.codigo, u.nombre, u.email, u.rol, u.rol_sanidad
                FROM usuario u
                JOIN conempre c ON c.epre = 'RS'
                WHERE u.codigo = ?
                AND u.password = LEFT(AES_ENCRYPT(?, c.enom), 8)";

        $stmt = $this->conn->prepare($sql);
        $stmt->bindParam(1, $usuario, PDO::PARAM_STR);
        $stmt->bindParam(2, $password, PDO::PARAM_STR);
        $stmt->execute();

        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Login con sistema de roles dinámicos multi-programa (usuarios_L)
     * Usa cifrado AES compatible con sistema legacy
     * Retorna datos del usuario (sin roles, se obtienen por separado)
     */
    public function loginConRol($username, $password) {
        $sql = "SELECT u.codigo as id_usuario, u.codigo as username, u.nombre as nombre_completo,
                   CASE WHEN u.estado = 'A' THEN 1 ELSE 0 END as estado
            FROM usuario u
            CROSS JOIN conempre c
            WHERE c.epre = 'RS'
            AND u.codigo = ?
            AND u.password = LEFT(AES_ENCRYPT(?, c.enom), 8)
            AND u.estado = 'A'";

        $stmt = $this->conn->prepare($sql);
        $stmt->bindParam(1, $username, PDO::PARAM_STR);
        $stmt->bindParam(2, $password, PDO::PARAM_STR);
        $stmt->execute();

        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Obtener todos los usuarios con sus roles
     */
    public function obtenerTodos() {
        $sql = "SELECT u.codigo as id_usuario,
                   u.codigo as username,
                   u.nombre as nombre_completo,
                   CASE WHEN u.estado = 'A' THEN 1 ELSE 0 END as activo,
                   u.crea,
                   u.modifica,
                   u.elimina,
                   u.anula
            FROM usuario u
            ORDER BY u.nombre";

        $stmt = $this->conn->prepare($sql);
        $stmt->execute();
        $usuarios = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Obtener roles para cada usuario
        foreach ($usuarios as &$usuario) {
            $usuario['roles'] = $this->obtenerRolesUsuario($usuario['id_usuario']);
        }
        
        return $usuarios;
    }

    /**
     * Obtener usuario por ID con sus roles
     */
    public function obtenerPorId($idUsuario) {
        $sql = "SELECT u.codigo as id_usuario,
                   u.codigo as username,
                   u.nombre as nombre_completo,
                   CASE WHEN u.estado = 'A' THEN 1 ELSE 0 END as activo,
                   u.crea,
                   u.modifica,
                   u.elimina,
                   u.anula
            FROM usuario u
            WHERE u.codigo = ?";

        $stmt = $this->conn->prepare($sql);
        $stmt->bindParam(1, $idUsuario, PDO::PARAM_STR);
        $stmt->execute();
        $usuario = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($usuario) {
            $usuario['roles'] = $this->obtenerRolesUsuario($idUsuario);
        }
        
        return $usuario;
    }

    /**
     * Crear un nuevo usuario
     * Usa cifrado AES para la contraseña
     * Los roles se asignan por separado con UsuarioRolRepository
     */
    public function crear($username, $password, $nombreCompleto): int {
        throw new Exception('Metodo crear sin rol no disponible');
    }

    /**
     * Crear un nuevo usuario con múltiples roles
     * @param array $idsRoles Array de IDs de roles
     */
    public function crearConRoles($username, $password, $nombreCompleto, $idsRoles = [], $crea = 0, $modifica = 0, $elimina = 0, $anula = 0) {
        try {
            $this->conn->beginTransaction();

            // PASO 1: Insertar usuario en tabla usuario
            $sql = "INSERT INTO usuario (codigo, nombre, password, epre, estado, reduser, crea, modifica, elimina, anula)
                    SELECT ?, ?, LEFT(AES_ENCRYPT(?, c.enom), 8), 'RS', 'A', ?, ?, ?, ?, ?
                    FROM conempre c WHERE c.epre = 'RS'";

            $stmt = $this->conn->prepare($sql);
            $stmt->bindParam(1, $username, PDO::PARAM_STR);
            $stmt->bindParam(2, $nombreCompleto, PDO::PARAM_STR);
            $stmt->bindParam(3, $password, PDO::PARAM_STR);
            $stmt->bindParam(4, $username, PDO::PARAM_STR);
            $stmt->bindParam(5, $crea, PDO::PARAM_INT);
            $stmt->bindParam(6, $modifica, PDO::PARAM_INT);
            $stmt->bindParam(7, $elimina, PDO::PARAM_INT);
            $stmt->bindParam(8, $anula, PDO::PARAM_INT);
            $stmt->execute();

            // PASO 2: Asignar múltiples roles
            if (!empty($idsRoles)) {
                foreach ($idsRoles as $idRol) {
                    $codRol = $this->obtenerCodRol($idRol);
                    if (!$codRol) {
                        throw new Exception("Rol con ID $idRol no encontrado");
                    }

                    $sqlRol = "INSERT INTO adm_usuario_rol (codigo, cod_rol) VALUES (?, ?)";
                    $stmtRol = $this->conn->prepare($sqlRol);
                    $stmtRol->bindParam(1, $username, PDO::PARAM_STR);
                    $stmtRol->bindParam(2, $codRol, PDO::PARAM_STR);
                    $stmtRol->execute();
                }
            }

            $this->conn->commit();
            return $username;
            
        } catch (Exception $e) {
            $this->conn->rollBack();
            error_log("Error en crearConRoles: " . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Compatibilidad con método antiguo para un solo rol
     */
    public function crearConRol($username, $password, $nombreCompleto, $idRol, $crea = 0, $modifica = 0, $elimina = 0, $anula = 0) {
        return $this->crearConRoles($username, $password, $nombreCompleto, [$idRol], $crea, $modifica, $elimina, $anula);
    }

    /**
     * Actualizar un usuario
     * Los roles se actualizan por separado con UsuarioRolRepository
     */
    public function actualizar($idUsuario, $username, $nombreCompleto) {
        throw new Exception('Metodo actualizar sin rol no disponible');
    }

    /**
     * Actualizar usuario con múltiples roles
     * @param array $idsRoles Array de IDs de roles
     */
    public function actualizarConRoles($idUsuario, $username, $nombreCompleto, $idsRoles = [], $crea = 0, $modifica = 0, $elimina = 0, $anula = 0) {
        $this->conn->beginTransaction();

        try {
            // PASO 1: Eliminar PRIMERO todos los roles del usuario ANTES de cambiar el username
            // Usamos $idUsuario (el código original) para asegurar que se borren correctamente
            $sqlDeleteRoles = "DELETE FROM adm_usuario_rol WHERE codigo = ?";
            $stmtDeleteRoles = $this->conn->prepare($sqlDeleteRoles);
            $stmtDeleteRoles->bindParam(1, $idUsuario, PDO::PARAM_STR);
            $stmtDeleteRoles->execute();

            // PASO 2: Actualizar datos del usuario en tabla usuario
            $sql = "UPDATE usuario
                    SET codigo = ?, nombre = ?, crea = ?, modifica = ?, elimina = ?, anula = ?
                    WHERE codigo = ?";

            $stmt = $this->conn->prepare($sql);
            $stmt->bindParam(1, $username, PDO::PARAM_STR);
            $stmt->bindParam(2, $nombreCompleto, PDO::PARAM_STR);
            $stmt->bindParam(3, $crea, PDO::PARAM_INT);
            $stmt->bindParam(4, $modifica, PDO::PARAM_INT);
            $stmt->bindParam(5, $elimina, PDO::PARAM_INT);
            $stmt->bindParam(6, $anula, PDO::PARAM_INT);
            $stmt->bindParam(7, $idUsuario, PDO::PARAM_STR);
            $stmt->execute();

            // PASO 3: Insertar los nuevos roles con el username (que puede ser nuevo o el mismo)
            if (!empty($idsRoles)) {
                foreach ($idsRoles as $idRol) {
                    $codRol = $this->obtenerCodRol($idRol);
                    if (!$codRol) {
                        $this->conn->rollBack();
                        return false;
                    }

                    $sqlRol = "INSERT INTO adm_usuario_rol (codigo, cod_rol) VALUES (?, ?)";
                    $stmtRol = $this->conn->prepare($sqlRol);
                    $stmtRol->bindParam(1, $username, PDO::PARAM_STR);
                    $stmtRol->bindParam(2, $codRol, PDO::PARAM_STR);
                    $stmtRol->execute();
                }
            }

            $this->conn->commit();
            return true;
            
        } catch (Exception $e) {
            $this->conn->rollBack();
            throw $e;
        }
    }
    
    /**
     * Compatibilidad con método antiguo para un solo rol
     */
    public function actualizarConRol($idUsuario, $username, $nombreCompleto, $idRol, $crea = 0, $modifica = 0, $elimina = 0, $anula = 0) {
        return $this->actualizarConRoles($idUsuario, $username, $nombreCompleto, [$idRol], $crea, $modifica, $elimina, $anula);
    }

    /**
     * Actualizar contraseña de usuario
     * Usa cifrado AES para la contraseña
     */
    public function actualizarPassword($idUsuario, $password) {
        $sql = "UPDATE usuario u
            CROSS JOIN conempre c
            SET u.password = LEFT(AES_ENCRYPT(?, c.enom), 8)
            WHERE c.epre = 'RS' AND u.codigo = ?";

        $stmt = $this->conn->prepare($sql);
        $stmt->bindParam(1, $password, PDO::PARAM_STR);
        $stmt->bindParam(2, $idUsuario, PDO::PARAM_STR);

        return $stmt->execute();
    }

    /**
     * Eliminar (desactivar) un usuario
     */
    public function eliminar($idUsuario) {
        $sql = "UPDATE usuario SET estado = 'I' WHERE codigo = ?";

        $stmt = $this->conn->prepare($sql);
        $stmt->bindParam(1, $idUsuario, PDO::PARAM_STR);

        return $stmt->execute();
    }

    /**
     * Verificar si existe un username
     */
    public function existeUsername($username, $idUsuarioExcluir = null) {
        if ($idUsuarioExcluir) {
            $sql = "SELECT COUNT(*) as total FROM usuario
                    WHERE codigo = ? AND codigo != ?";
            $stmt = $this->conn->prepare($sql);
            $stmt->bindParam(1, $username, PDO::PARAM_STR);
            $stmt->bindParam(2, $idUsuarioExcluir, PDO::PARAM_STR);
        } else {
            $sql = "SELECT COUNT(*) as total FROM usuario WHERE codigo = ?";
            $stmt = $this->conn->prepare($sql);
            $stmt->bindParam(1, $username, PDO::PARAM_STR);
        }

        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result['total'] > 0;
    }

    /**
     * Obtener todos los roles de un usuario
     */
    private function obtenerRolesUsuario($idUsuario) {
        $sql = "SELECT r.id as id_rol, r.nom_rol as nombre_rol, r.cod_rol
                FROM adm_usuario_rol ur
                INNER JOIN adm_rol r ON ur.cod_rol = r.cod_rol
                WHERE ur.codigo = ?
                ORDER BY r.nom_rol";
        
        $stmt = $this->conn->prepare($sql);
        $stmt->bindParam(1, $idUsuario, PDO::PARAM_STR);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function obtenerCodRol($idRol) {
        $sql = "SELECT cod_rol FROM adm_rol WHERE id = ?";
        $stmt = $this->conn->prepare($sql);
        $stmt->bindParam(1, $idRol, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchColumn();
    }

    // ─────────────────────────────────────────────────────────────────────
    // DATATABLES SERVER-SIDE (Optimizado con roles _pic)
    // ─────────────────────────────────────────────────────────────────────
    public function getUsuariosServerSide($start, $length, $searchValue, $orderColumn, $orderDir)
    {
        // Índices basados en la vista HTML (0: #, 1: Codigo, 2: Nombre, 3: Roles, 4: Estado, 5: Ultimo acceso)
        $columnasBd = ['u.codigo', 'u.codigo', 'u.nombre', 'nombres_roles', 'u.estado', 'u.ultimo_acceso'];
        $campoOrden = $columnasBd[$orderColumn] ?? 'u.codigo';

        $sqlBase = "FROM usuario u ";
        $params = [];

        // Filtro de búsqueda
        $whereSql = "";
        if (!empty($searchValue)) {
            $whereSql = " WHERE u.codigo LIKE :search1 OR u.nombre LIKE :search2 ";
            $params[':search1'] = "%$searchValue%";
            $params[':search2'] = "%$searchValue%";
        }

        // Conteo filtrado
        $stmtFiltro = $this->conn->prepare("SELECT COUNT(*) " . $sqlBase . $whereSql);
        $stmtFiltro->execute($params);
        $recordsFiltered = $stmtFiltro->fetchColumn();

        // Conteo total
        $stmtTotal = $this->conn->query("SELECT COUNT(*) FROM usuario");
        $recordsTotal = $stmtTotal->fetchColumn();

        // Consulta de datos con JOIN a tus tablas limpias (_pic)
        $sqlData = "SELECT 
                        u.codigo, 
                        u.nombre, 
                        CASE WHEN u.estado = 'A' THEN 1 ELSE 0 END AS activo, 
                        u.ultimo_acceso,
                        IFNULL(GROUP_CONCAT(r.nom_rol ORDER BY r.nom_rol SEPARATOR '||'), '') AS nombres_roles
                    " . $sqlBase . "
                    LEFT JOIN adm_usuario_rol_pic ur ON ur.codigo = u.codigo AND ur.epre = 'RS'
                    LEFT JOIN adm_rol_pic r ON r.cod_rol = ur.cod_rol AND r.id_programa = '1' AND r.activo = 1
                    " . $whereSql . "
                    GROUP BY u.codigo, u.nombre, u.estado, u.ultimo_acceso
                    ORDER BY $campoOrden $orderDir 
                    LIMIT :start, :length";

        $stmt = $this->conn->prepare($sqlData);

        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value, PDO::PARAM_STR);
        }
        $stmt->bindValue(':start', (int)$start, PDO::PARAM_INT);
        $stmt->bindValue(':length', (int)$length, PDO::PARAM_INT);
        $stmt->execute();

        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return [
            'recordsTotal'    => $recordsTotal,
            'recordsFiltered' => $recordsFiltered,
            'data'            => $data
        ];
    }

    // ─────────────────────────────────────────────────────────────────────
    // OBTENER INDIVIDUAL
    // ─────────────────────────────────────────────────────────────────────
    public function obtenerPorCodigo($codigo)
    {
        $sql = "SELECT codigo, nombre, ruc, CASE WHEN estado = 'A' THEN 1 ELSE 0 END as activo FROM usuario WHERE codigo = :codigo LIMIT 1";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([':codigo' => $codigo]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    // ─────────────────────────────────────────────────────────────────────
    // CREAR / EDITAR
    // ─────────────────────────────────────────────────────────────────────
    public function guardar($datos, $isEdit = false)
    {
        $ruc = !empty($datos['ruc']) ? $datos['ruc'] : null;

        if ($isEdit) {
            // EDITAR (Solo actualiza nombre y RUC, no toca contraseña)
            $sql = "UPDATE usuario SET nombre = :nombre, ruc = :ruc WHERE codigo = :codigo";
            $stmt = $this->conn->prepare($sql);
            $stmt->bindParam(':nombre', $datos['nombre']);
            $stmt->bindParam(':ruc', $ruc);
            $stmt->bindParam(':codigo', $datos['codigo']);
            return $stmt->execute();
        } else {
            // CREAR (Inserta con contraseña encriptada AES mediante conempre)
            $sql = "INSERT INTO usuario (codigo, nombre, ruc, password, epre, estado, reduser, fecha_registro) 
                    SELECT :codigo, :nombre, :ruc, LEFT(AES_ENCRYPT(:password, c.enom), 8), 'RS', 'A', :codigo, NOW() 
                    FROM conempre c WHERE c.epre = 'RS' LIMIT 1";

            $stmt = $this->conn->prepare($sql);
            $stmt->bindParam(':codigo', $datos['codigo']);
            $stmt->bindParam(':nombre', $datos['nombre']);
            $stmt->bindParam(':ruc', $ruc);
            $stmt->bindParam(':password', $datos['password']);
            return $stmt->execute();
        }
    }

    // ─────────────────────────────────────────────────────────────────────
    // CAMBIO DE ESTADOS Y CONTRASEÑA
    // ─────────────────────────────────────────────────────────────────────
    public function toggleEstado($codigo)
    {
        // Cambia 'A' a 'I', y cualquier otra cosa (o 'I') a 'A'
        $sql = "UPDATE usuario SET estado = IF(estado = 'A', 'I', 'A') WHERE codigo = :codigo";
        $stmt = $this->conn->prepare($sql);
        return $stmt->execute([':codigo' => $codigo]);
    }

    public function adminResetPassword($codigo, $newPassword)
    {
        $sql = "UPDATE usuario u
                JOIN conempre c ON c.epre = 'RS'
                SET u.password = LEFT(AES_ENCRYPT(:newPassword, c.enom), 8)
                WHERE u.codigo = :codigo";

        $stmt = $this->conn->prepare($sql);
        $stmt->bindParam(':newPassword', $newPassword, PDO::PARAM_STR);
        $stmt->bindParam(':codigo', $codigo, PDO::PARAM_STR);
        $stmt->execute();
        return $stmt->rowCount() > 0;
    }
}
