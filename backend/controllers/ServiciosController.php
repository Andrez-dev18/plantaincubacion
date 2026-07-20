<?php
/**
 * ServiciosController — Controlador para la gestión de servicios
 * 
 * Endpoints Unificados:
 *   POST /api/servicios/listar       -> listar()
 *   POST /api/servicios/obtener      -> obtener()
 *   POST /api/servicios/guardar      -> guardar() (Crea/Edita)
 *   POST /api/servicios/eliminar     -> eliminar()
 */

require_once __DIR__ . '/../services/ServiciosService.php';
require_once __DIR__ . '/../config/database.php';

class ServiciosController {

    private $service;

    public function __construct($dependency) {
        // Mantenemos compatibilidad con el bootstrap por si inyecta el Repo o la BD
        $db = ($dependency instanceof ServiciosRepository) ? Database::getInstance()->getConnection() : $dependency;
        $this->service = new ServiciosService($db);
    }

    // ─── Helpers ──────────────────────────────────────────────────────────

    private function jsonResponse($data, $statusCode = 200) {
        http_response_code($statusCode);
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE);
        exit;
    }

    private function getRequestData() {
        $json = json_decode(file_get_contents('php://input'), true);
        return $json ? $json : $_POST;
    }

    // ─── Endpoints ────────────────────────────────────────────────────────

    /**
     * POST /api/servicios/listar
     * Lista todos los servicios con paginación
     */
    public function listar() {
        $data = $this->getRequestData();
        $q = $data['q'] ?? $_GET['q'] ?? '';
        $page = isset($data['page']) ? (int)$data['page'] : (isset($_GET['page']) ? (int)$_GET['page'] : 1);
        $pageSize = isset($data['pageSize']) ? (int)$data['pageSize'] : (isset($_GET['pageSize']) ? (int)$_GET['pageSize'] : 25);

        $resultado = $this->service->listarServicios($q, $page, $pageSize);
        $this->jsonResponse($resultado, $resultado['success'] ? 200 : 500);
    }

    /**
     * POST /api/servicios/obtener
     * Obtiene un servicio específico por su código
     */
    public function obtener() {
        $data = $this->getRequestData();
        $codi = $data['codi'] ?? null;

        if (!$codi) {
            $this->jsonResponse(['success' => false, 'message' => 'Código de servicio requerido'], 400);
        }

        $resultado = $this->service->obtenerPorId($codi);
        $this->jsonResponse($resultado, $resultado['success'] ? 200 : 404);
    }

    /**
     * POST /api/servicios/guardar
     * Inserta o actualiza un servicio
     */
    public function guardar() {
        $data = $this->getRequestData();
        $resultado = $this->service->guardarServicio($data);
        
        $this->jsonResponse($resultado, $resultado['success'] ? 200 : 400);
    }

    /**
     * POST /api/servicios/eliminar
     * Elimina un servicio
     */
    public function eliminar() {
        $data = $this->getRequestData();
        $codi = $data['codi'] ?? null;

        if (!$codi) {
            $this->jsonResponse(['success' => false, 'message' => 'Código requerido'], 400);
        }

        $resultado = $this->service->eliminarServicio($codi);
        $this->jsonResponse($resultado, $resultado['success'] ? 200 : 400);
    }
}
?>
