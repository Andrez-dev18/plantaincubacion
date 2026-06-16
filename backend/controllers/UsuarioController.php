<?php
/**
 * UsuarioController - Sistema de Autenticación Legacy
 * 
 * @deprecated Este controlador está obsoleto desde 2026-02-10
 * @see /iam/controllers/IAMAuthController.php para el nuevo sistema
 * @see /iam/IAM_API_REFERENCE.md para documentación de la API IAM
 * 
 * NOTA DE MIGRACIÓN:
 * Este controlador mantiene la funcionalidad antigua de autenticación.
 * Los nuevos desarrollos deben usar el módulo IAM en /iam/ (raíz del proyecto)
 * 
 * Endpoints migrados:
 * - POST /usuario/login → POST /iam/auth/login
 * - GET /usuario/validarSesion → GET /iam/auth/validate
 * - GET /usuario/logout → POST /iam/auth/logout
 * 
 * El nuevo sistema IAM incluye:
 * - Validación de permisos multinivel
 * - Soporte multi-programa
 * - Auditoría completa
 * - Menús dinámicos
 * - Gestión de roles y accesos granulares
 */

require_once __DIR__ . '/../services/UsuarioService.php';
require_once __DIR__ . '/../config/database.php';

class UsuarioController
{
    private $service;

    public function __construct()
    {
        $db = Database::getInstance()->getConnection();
        $this->service = new UsuarioService($db);
    }

    /**
     * Login original (mantener compatibilidad)
     * DEPRECADO: Usar loginConRol() para el nuevo sistema
     */
    public function login($data)
    {
        $usuario = $data['usuario'] ?? '';
        $password = $data['password'] ?? '';
        $ubicacion = $data['ubicacion_gps'] ?? null;

        $resultado = $this->service->autenticar($usuario, $password, $ubicacion);

        if ($resultado['success']) {
            // Reabrir sesión para escritura (security.php la cerró después de leer)
            session_start();
            $_SESSION['usuario'] = $resultado['data']['codigo'];
            $_SESSION['nombre'] = $resultado['data']['nombre'];

            echo json_encode([
                'success' => true,
                'data' => $resultado['data']
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'message' => 'Usuario o contraseña incorrectos'
            ]);
        }
    }

    /**
     * Login con sistema multi-programa
     * POST /login
     * Body: { "username": "admin", "password": "***" }
     */
    public function loginConRol($data = null)
    {
        if ($data === null) {
            $data = json_decode(file_get_contents('php://input'), true);
        }

        $username = $data['username'] ?? '';
        $password = $data['password'] ?? '';

        $resultado = $this->service->autenticarConRol($username, $password);

        if ($resultado['success']) {
            // Reabrir sesión para escritura (security.php la cerró después de leer)
            session_name('SESS_INCUBA'); 
            session_start();
            $_SESSION['id_usuario'] = $resultado['data']['id_usuario'];
            $_SESSION['username'] = $resultado['data']['username'];
            $_SESSION['nombre_completo'] = $resultado['data']['nombre_completo'];
            $_SESSION['roles'] = $resultado['data']['roles']; // Array de roles
            $_SESSION['modulos'] = $resultado['data']['modulos']; // Array de módulos

            header('Content-Type: application/json');
            echo json_encode([
                'success' => true,
                'data' => $resultado['data']
            ]);
        } else {
            http_response_code(400);  // 400 = credenciales inválidas (no 401, para no disparar "Sesión expirada")
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false,
                'message' => $resultado['message']
            ]);
        }
    }

    /**
     * Validar sesión activa con sistema multi-rol
     * GET /auth/validar
     */
    public function validarSesionConRol()
    {
        header('Content-Type: application/json');
        
        // Verificar si hay sesión activa
        if (!isset($_SESSION['id_usuario']) || !isset($_SESSION['username'])) {
            http_response_code(401);
            echo json_encode([
                'success' => false,
                'message' => 'Sesión no válida o expirada'
            ]);
            return;
        }

        // Retornar datos de la sesión
        echo json_encode([
            'success' => true,
            'data' => [
                'id_usuario' => $_SESSION['id_usuario'],
                'username' => $_SESSION['username'],
                'nombre_completo' => $_SESSION['nombre_completo'] ?? '',
                'roles' => $_SESSION['roles'] ?? [],
                'modulos' => $_SESSION['modulos'] ?? []
            ]
        ]);
    }

    /**
     * Logout - Cerrar sesión
     * POST /auth/logout
     */
    public function logoutConRol()
    {
        // Reabrir sesión para destruirla (security.php la cerró después de leer)
        session_start();
        session_destroy();
        $_SESSION = [];
        
        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'message' => 'Sesión cerrada correctamente'
        ]);
    }

    /**
     * GET /admin/usuarios
     * Obtener todos los usuarios
     */
    public function listar()
    {
        try {
            $resultado = $this->service->obtenerTodos();
            
            header('Content-Type: application/json');
            echo json_encode($resultado);
        } catch (Exception $e) {
            http_response_code(500);
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false,
                'message' => $e->getMessage()
            ]);
        }
    }

    /**
     * GET /admin/usuarios/{id}
     * Obtener usuario por ID
     */
    public function obtenerPorId($id)
    {
        try {
            $resultado = $this->service->obtenerPorId($id);
            
            header('Content-Type: application/json');
            echo json_encode($resultado);
        } catch (Exception $e) {
            http_response_code(500);
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false,
                'message' => $e->getMessage()
            ]);
        }
    }

    /**
     * POST /admin/usuarios
     * Crear un nuevo usuario
     * Body: { "username": "...", "password": "...", "nombre_completo": "...", "roles": [1, 2] }
     */
    public function crear()
    {
        try {
            $data = json_decode(file_get_contents('php://input'), true);
            
            $username = $data['username'] ?? '';
            $password = $data['password'] ?? '';
            $nombreCompleto = $data['nombre_completo'] ?? '';
            $roles = $data['roles'] ?? [];

            $resultado = $this->service->crear($username, $password, $nombreCompleto, $roles);
            
            $statusCode = $resultado['success'] ? 201 : 400;
            http_response_code($statusCode);
            header('Content-Type: application/json');
            echo json_encode($resultado);
        } catch (Exception $e) {
            http_response_code(500);
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false,
                'message' => $e->getMessage()
            ]);
        }
    }

    /**
     * PUT /admin/usuarios/{id}
     * Actualizar usuario
     * Body: { "username": "...", "nombre_completo": "...", "roles": [1, 2] }
     */
    public function actualizar($id)
    {
        try {
            $data = json_decode(file_get_contents('php://input'), true);
            
            $username = $data['username'] ?? '';
            $nombreCompleto = $data['nombre_completo'] ?? '';
            $roles = isset($data['roles']) ? $data['roles'] : null;

            $resultado = $this->service->actualizar($id, $username, $nombreCompleto, $roles);
            
            $statusCode = $resultado['success'] ? 200 : 400;
            http_response_code($statusCode);
            header('Content-Type: application/json');
            echo json_encode($resultado);
        } catch (Exception $e) {
            http_response_code(500);
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false,
                'message' => $e->getMessage()
            ]);
        }
    }

    /**
     * PUT /admin/usuarios/{id}/password
     * Cambiar contraseña
     * Body: { "password": "..." }
     */
    public function cambiarPassword($id)
    {
        try {
            $data = json_decode(file_get_contents('php://input'), true);
            
            $password = $data['password'] ?? '';

            if (empty($password)) {
                http_response_code(400);
                header('Content-Type: application/json');
                echo json_encode([
                    'success' => false,
                    'message' => 'La contraseña es requerida'
                ]);
                return;
            }

            $resultado = $this->service->cambiarPassword($id, $password);
            
            $statusCode = $resultado['success'] ? 200 : 400;
            http_response_code($statusCode);
            header('Content-Type: application/json');
            echo json_encode($resultado);
        } catch (Exception $e) {
            http_response_code(500);
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false,
                'message' => $e->getMessage()
            ]);
        }
    }

    /**
     * DELETE /admin/usuarios/{id}
     * Eliminar (desactivar) usuario
     */
    public function eliminar($id)
    {
        try {
            $resultado = $this->service->eliminar($id);
            
            $statusCode = $resultado['success'] ? 200 : 400;
            http_response_code($statusCode);
            header('Content-Type: application/json');
            echo json_encode($resultado);
        } catch (Exception $e) {
            http_response_code(500);
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false,
                'message' => $e->getMessage()
            ]);
        }
    }

    /**
     * GET /admin/usuarios/{id}/menu
     * Obtener menú del usuario
     */
    public function obtenerMenu($id)
    {
        try {
            $programa = isset($_GET['programa']) ? $_GET['programa'] : null;
            $resultado = $this->service->obtenerMenu($id, $programa);
            
            header('Content-Type: application/json');
            echo json_encode($resultado);
        } catch (Exception $e) {
            http_response_code(500);
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false,
                'message' => $e->getMessage()
            ]);
        }
    }
}
