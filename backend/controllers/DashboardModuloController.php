<?php
/**
 * DashboardModuloController
 *
 * Controlador para gestionar el menú del dashboard
 * Adaptado a la lógica del sistema mejorado con programa ID 1
 */

class DashboardModuloController {
    private $service;

    public function __construct($dashboardModuloService) {
        $this->service = $dashboardModuloService;
    }

    /**
     * Obtener el menú jerárquico del usuario autenticado
     * GET /menu/obtener
     */
    public function obtener() {
        header('Content-Type: application/json; charset=UTF-8');

        try {
            $usuarioActual = $_SESSION['username'] ?? $_SESSION['usuario'] ?? null;
            $epre = $_SESSION['epre'] ?? 'RS';

            if (!$usuarioActual) {
                http_response_code(401);
                echo json_encode(['success' => false, 'message' => 'Usuario no autenticado']);
                return;
            }

            $menuArbol = $this->service->obtenerMenuJerarquico($usuarioActual, $epre);

            if (!empty($menuArbol)) {
                echo json_encode(['success' => true, 'data' => $menuArbol]);
            } else {
                echo json_encode(['success' => true, 'data' => [], 'message' => 'El usuario no tiene roles asignados']);
            }
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    /**
     * Listar todos los módulos
     * GET /menu/listar
     */
    public function listar() {
        header('Content-Type: application/json; charset=UTF-8');
        try {
            $data = $this->service->listarTodos();
            echo json_encode(['success' => true, 'data' => $data]);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    /**
     * Listar grupos de módulos
     * GET /menu/grupos
     */
    public function listarGrupos() {
        header('Content-Type: application/json; charset=UTF-8');
        try {
            $data = $this->service->listarGrupos();
            echo json_encode(['success' => true, 'data' => $data]);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    /**
     * Obtener un módulo por ID
     * GET /menu/obtenerPorId?id=X
     */
    public function obtenerModulosId() {
        header('Content-Type: application/json; charset=UTF-8');
        try {
            $id = $_GET['id'] ?? null;
            if (!$id) {
                throw new Exception("ID no proporcionado.");
            }

            $data = $this->service->obtenerPorId($id);
            echo json_encode(['success' => true, 'data' => $data]);
        } catch (Exception $e) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    /**
     * Guardar (crear o actualizar) un módulo
     * POST /menu/guardar
     */
    public function guardar() {
        header('Content-Type: application/json; charset=UTF-8');
        try {
            // Recibimos los datos (Puede ser $_POST estándar o JSON payload)
            $datos = $_POST;
            if (empty($datos)) {
                $datos = json_decode(file_get_contents("php://input"), true) ?: [];
            }

            $this->service->guardarModulo($datos);

            $mensaje = empty($datos['id']) ? "Módulo creado correctamente." : "Módulo actualizado correctamente.";
            echo json_encode(['success' => true, 'message' => $mensaje]);
        } catch (Exception $e) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    /**
     * Eliminar un módulo por ID
     * POST /menu/eliminar
     */
    public function eliminar() {
        header('Content-Type: application/json; charset=UTF-8');
        try {
            $datos = json_decode(file_get_contents("php://input"), true);
            $id = $datos['id'] ?? $_POST['id'] ?? null;

            if (!$id) {
                throw new Exception("ID no proporcionado.");
            }

            $this->service->eliminarModulo($id);
            echo json_encode(['success' => true, 'message' => "Módulo eliminado correctamente."]);
        } catch (Exception $e) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
    }
}
