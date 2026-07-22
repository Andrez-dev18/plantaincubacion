<?php

/**
 * UsuarioController — Controlador Único y Optimizado
 * Reemplaza a: UsuarioSistemaController, UsuarioRolController, UsuarioAdminController
 */

require_once __DIR__ . '/../services/UsuarioService.php';

class UsuarioController
{

    private $service;

    public function __construct($db)
    {
        $this->service = new UsuarioService($db);
    }

    // ─── Helpers ──────────────────────────────────────────────────────────

    private function jsonResponse($data, $statusCode = 200)
    {
        http_response_code($statusCode);
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE);
        exit;
    }

    private function getRequestData()
    {
        // Soporta tanto FormData ($_POST) como JSON crudo (php://input)
        $json = json_decode(file_get_contents('php://input'), true);
        return $json ? $json : $_POST;
    }

    // ─── Endpoints de la Tabla y CRUD ─────────────────────────────────────

    /**
     * POST /usuario/listar
     * Consumido directamente por Datatables Server-Side
     */
    public function listarServerSide()
    {
        $postData = $this->getRequestData();
        $resultado = $this->service->obtenerUsuariosServerSide($postData);

        // Datatables espera la respuesta cruda, no envuelta en success/data
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode($resultado);
        exit;
    }

    /**
     * POST /usuario/obtener
     * Obtiene un usuario y sus roles asignados para llenar el Modal de edición
     */
    public function obtener()
    {
        $data = $this->getRequestData();
        $codigo = trim($data['codigo'] ?? '');

        if (empty($codigo)) {
            $this->jsonResponse(['success' => false, 'message' => 'Código requerido'], 400);
        }

        $resultado = $this->service->obtenerUsuarioConRoles($codigo);
        $this->jsonResponse($resultado, $resultado['success'] ? 200 : 404);
    }

    /**
     * POST /usuario/guardar
     * Crea o edita un usuario y le asigna sus roles
     */
    public function guardar()
    {
        $data = $this->getRequestData();
        $resultado = $this->service->guardarUsuario($data);

        $this->jsonResponse($resultado, $resultado['success'] ? 200 : 400);
    }

    /**
     * POST /usuario/toggle
     * Activa o desactiva un usuario
     */
    public function toggleEstado()
    {
        $data = $this->getRequestData();
        $codigo = trim($data['codigo'] ?? '');

        if (empty($codigo)) {
            $this->jsonResponse(['success' => false, 'message' => 'Código requerido'], 400);
        }

        $resultado = $this->service->cambiarEstado($codigo);
        $this->jsonResponse($resultado, $resultado['success'] ? 200 : 400);
    }

    /**
     * POST /usuario/reset-password
     * Cambia la contraseña desde el panel de administrador
     */
    public function resetPassword()
    {
        $data = $this->getRequestData();
        $codigo = trim($data['codigo'] ?? '');
        $password = $data['password'] ?? '';

        if (empty($codigo) || empty($password)) {
            $this->jsonResponse(['success' => false, 'message' => 'Código y nueva contraseña son requeridos'], 400);
        }

        $resultado = $this->service->resetearPassword($codigo, $password);
        $this->jsonResponse($resultado, $resultado['success'] ? 200 : 400);
    }

    // ─── Endpoints de Catálogos ───────────────────────────────────────────

    /**
     * GET /usuario/roles
     * Obtiene los roles activos de Planta Incubación para pintar los checkboxes
     */
    public function obtenerRoles()
    {
        $resultado = $this->service->obtenerCatalogoRoles();
        $this->jsonResponse($resultado, $resultado['success'] ? 200 : 500);
    }

    public function loginConRol($data = null)
    {
        if ($data === null) {
            $data = $this->getRequestData();
        }

        $username = trim($data['username'] ?? '');
        $password = $data['password'] ?? '';

        // Ahora sí usamos el servicio con toda su lógica de negocio
        $resultado = $this->service->autenticarConRol($username, $password);

        if ($resultado['success']) {
            session_name('SESS_INCUBA');
            session_start();
            $_SESSION['id_usuario']      = $resultado['data']['id_usuario'];
            $_SESSION['username']        = $resultado['data']['username'];
            $_SESSION['nombre_completo'] = $resultado['data']['nombre_completo'];
            $_SESSION['roles']           = $resultado['data']['roles'];
            $_SESSION['crea']            = $resultado['data']['crea'];
            $_SESSION['modifica']        = $resultado['data']['modifica'];
            $_SESSION['elimina']          = $resultado['data']['elimina'];

            $this->jsonResponse(['success' => true, 'data' => $_SESSION], 200);
        } else {
            // El 400 evita que el frontend dispare el error de "Sesión expirada" 
            $this->jsonResponse(['success' => false, 'message' => $resultado['message']], 400);
        }
    }

    public function obtenerMenuLateral() {
        session_name('SESS_INCUBA');
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $idUsuario = $_SESSION['id_usuario'] ?? $_SESSION['usuario'] ?? null;

        if (!$idUsuario) {
            $this->jsonResponse(['success' => false, 'message' => 'Sesión no iniciada'], 401);
        }

        try {
            // Instanciamos el repositorio de navegación que ya procesa la jerarquía limpia
            require_once __DIR__ . '/../repositories/NavegacionRepository.php';
            $db = Database::getInstance()->getConnection();
            $navegacionRepo = new NavegacionRepository($db);

            // Obtenemos los módulos usando el ID del programa '1' fijo para PIC
            $modulos = $navegacionRepo->obtenerMenuJerarquico($idUsuario, '1');

            $this->jsonResponse([
                'success' => true,
                'data' => $modulos
            ]);
        } catch (Exception $e) {
            error_log("Error en obtenerMenuLateral: " . $e->getMessage());
            $this->jsonResponse(['success' => false, 'message' => 'Error al procesar el menú'], 500);
        }
    }

    /**
     * Valida si existe una sesión activa y retorna los datos del usuario
     */
    public function validarSesionConRol()
    {
        session_name('SESS_INCUBA');
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $idUsuario = $_SESSION['id_usuario'] ?? $_SESSION['username'] ?? null;

        if ($idUsuario) {
            $this->jsonResponse([
                'success' => true,
                'data' => $_SESSION
            ]);
        } else {
            $this->jsonResponse(['success' => false, 'message' => 'Sesión expirada o inactiva'], 401);
        }
    }

    /**
     * Cierra la sesión activa y destruye las variables correspondientes
     */
    public function logoutConRol()
    {
        session_name('SESS_INCUBA');
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $idUsuario = $_SESSION['id_usuario'] ?? $_SESSION['username'] ?? null;
        $nombreCompleto = $_SESSION['nombre_completo'] ?? $_SESSION['nombre'] ?? null;

        if ($idUsuario) {
            $this->service->registrarLogout($idUsuario, $nombreCompleto);
        }

        $_SESSION = [];
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $params["path"], $params["domain"],
                $params["secure"], $params["httponly"]
            );
        }
        session_destroy();
        $this->jsonResponse(['success' => true, 'message' => 'Sesión cerrada correctamente']);
    }

    /**
     * Alias de obtenerMenuLateral para compatibilidad con rutas de auth
     */
    public function obtenerMenu()
    {
        $this->obtenerMenuLateral();
    }
}
