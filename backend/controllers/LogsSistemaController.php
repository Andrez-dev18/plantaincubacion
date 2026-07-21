<?php
/**
 * LogsSistemaController — Controlador para la gestión de logs de auditoría del sistema
 */

require_once __DIR__ . '/../services/LogsSistemaService.php';
require_once __DIR__ . '/../config/database.php';

class LogsSistemaController {

    private $service;

    public function __construct($dependency) {
        $db = ($dependency instanceof LogsSistemaRepository) ? Database::getInstance()->getConnection() : $dependency;
        $this->service = new LogsSistemaService($db);
    }

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
     * POST /api/logs/listar
     * Lista los logs con paginación, filtros y búsquedas para Datatable
     */
    public function listar() {
        $params = $this->getRequestData();
        $resultado = $this->service->listarLogs($params);
        $this->jsonResponse($resultado, $resultado['success'] ? 200 : 500);
    }

    /**
     * POST /api/logs/guardar
     * Registra un nuevo log del sistema
     */
    public function guardar() {
        $data = $this->getRequestData();
        $resultado = $this->service->registrarAccion($data);
        $this->jsonResponse($resultado, $resultado['success'] ? 200 : 400);
    }

    /**
     * POST /api/logs/filtros
     * Obtiene los valores únicos de filtros para los desplegables de búsqueda
     */
    public function filtros() {
        $resultado = $this->service->obtenerFiltrosDisponibles();
        $this->jsonResponse($resultado, $resultado['success'] ? 200 : 500);
    }
}
