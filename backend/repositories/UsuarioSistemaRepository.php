<?php

class UsuarioRepository
{
    private $conn;

    public function __construct($db)
    {
        $this->conn = $db;
    }

    // ─────────────────────────────────────────────────────────────────────
    // DATATABLES SERVER-SIDE (Optimizado con roles _pic)
    // ─────────────────────────────────────────────────────────────────────
    public function getUsuariosServerSide($start, $length, $searchValue, $orderColumn, $orderDir)
    {
        // Índices basados en la vista HTML (0: #, 1: Codigo, 2: Nombre, 3: Roles, 4: Estado, 5: Ultimo acceso)
        $columnasBd = ['u.codigo', 'u.codigo', 'u.nombre', 'nombres_roles', 'u.activo', 'u.ultimo_acceso'];
        $campoOrden = $columnasBd[$orderColumn] ?? 'u.codigo';

        $sqlBase = "FROM usuarios_L u ";
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
        $stmtTotal = $this->conn->query("SELECT COUNT(*) FROM usuarios_L");
        $recordsTotal = $stmtTotal->fetchColumn();

        // Consulta de datos con JOIN a tus tablas limpias (_pic)
        $sqlData = "SELECT 
                        u.codigo, 
                        u.nombre, 
                        u.activo, 
                        u.ultimo_acceso,
                        IFNULL(GROUP_CONCAT(r.nom_rol ORDER BY r.nom_rol SEPARATOR '||'), '') AS nombres_roles
                    " . $sqlBase . "
                    LEFT JOIN adm_usuario_rol_pic ur ON ur.codigo = u.codigo AND ur.epre = 'RS'
                    LEFT JOIN adm_rol_pic r ON r.cod_rol = ur.cod_rol AND r.id_programa = '1' AND r.activo = 1
                    " . $whereSql . "
                    GROUP BY u.codigo, u.nombre, u.activo, u.ultimo_acceso
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
        $sql = "SELECT codigo, nombre, activo FROM usuarios_L WHERE codigo = :codigo LIMIT 1";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([':codigo' => $codigo]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    // ─────────────────────────────────────────────────────────────────────
    // CREAR / EDITAR
    // ─────────────────────────────────────────────────────────────────────
    public function guardar($datos, $isEdit = false)
    {
        if ($isEdit) {
            // EDITAR (Solo actualiza nombre, no toca contraseña)
            $sql = "UPDATE usuarios_L SET nombre = :nombre WHERE codigo = :codigo";
            $stmt = $this->conn->prepare($sql);
            $stmt->bindParam(':nombre', $datos['nombre']);
            $stmt->bindParam(':codigo', $datos['codigo']);
            return $stmt->execute();
        } else {
            // CREAR (Inserta con contraseña encriptada AES mediante conempre)
            $sql = "INSERT INTO usuarios_L (codigo, nombre, password, epre, activo, fecha_registro) 
                    SELECT :codigo, :nombre, LEFT(AES_ENCRYPT(:password, c.enom), 8), 'RS', 1, NOW() 
                    FROM conempre c WHERE c.epre = 'RS' LIMIT 1";

            $stmt = $this->conn->prepare($sql);
            $stmt->bindParam(':codigo', $datos['codigo']);
            $stmt->bindParam(':nombre', $datos['nombre']);
            $stmt->bindParam(':password', $datos['password']);
            return $stmt->execute();
        }
    }

    // ─────────────────────────────────────────────────────────────────────
    // CAMBIO DE ESTADOS Y CONTRASEÑA
    // ─────────────────────────────────────────────────────────────────────
    public function toggleEstado($codigo)
    {
        // Cambia 1 a 0, y 0 a 1
        $sql = "UPDATE usuarios_L SET activo = IF(activo = 1, 0, 1) WHERE codigo = :codigo";
        $stmt = $this->conn->prepare($sql);
        return $stmt->execute([':codigo' => $codigo]);
    }

    public function adminResetPassword($codigo, $newPassword)
    {
        $sql = "UPDATE usuarios_L u
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
?>