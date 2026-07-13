<?php
/**
 * ConfigApiController
 * 
 * Controlador para la gestión de configuración de APIs.
 */

require_once __DIR__ . '/../services/ConfigApiService.php';

class ConfigApiController {
    private $service;

    public function __construct($configApiService) {
        $this->service = $configApiService;
    }

    /**
     * Helpers para respuesta JSON y lectura de entrada
     */
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

    /**
     * POST /api/config-api/listar
     */
    public function listar() {
        $resultado = $this->service->listar();
        $this->jsonResponse($resultado, $resultado['success'] ? 200 : 500);
    }

    /**
     * POST /api/config-api/obtener
     */
    public function obtener() {
        $data = $this->getRequestData();
        $id = isset($data['id']) ? (int)$data['id'] : 0;

        if (!$id) {
            $this->jsonResponse(['success' => false, 'message' => 'ID de API requerido'], 400);
        }

        $resultado = $this->service->obtenerPorId($id);
        $this->jsonResponse($resultado, $resultado['success'] ? 200 : 404);
    }

    /**
     * POST /api/config-api/guardar
     */
    public function guardar() {
        $data = $this->getRequestData();
        $resultado = $this->service->guardar($data);
        $this->jsonResponse($resultado, $resultado['success'] ? 200 : 400);
    }

    /**
     * POST /api/config-api/actualizar-token
     */
    public function actualizarToken() {
        $data = $this->getRequestData();
        $id = isset($data['id']) ? (int)$data['id'] : 0;
        $token = $data['token'] ?? '';

        if (!$id || empty($token)) {
            $this->jsonResponse(['success' => false, 'message' => 'ID y token son requeridos'], 400);
        }

        $resultado = $this->service->actualizarToken($id, $token);
        $this->jsonResponse($resultado, $resultado['success'] ? 200 : 400);
    }

    /**
     * POST /api/config-api/eliminar
     */
    public function eliminar() {
        $data = $this->getRequestData();
        $id = isset($data['id']) ? (int)$data['id'] : 0;

        if (!$id) {
            $this->jsonResponse(['success' => false, 'message' => 'ID de API requerido'], 400);
        }

        $resultado = $this->service->eliminar($id);
        $this->jsonResponse($resultado, $resultado['success'] ? 200 : 400);
    }
}
