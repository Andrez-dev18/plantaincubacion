<?php
/**
 * EmpresaController — Controlador para la gestión de empresas (proveedores de facturación)
 * 
 * Endpoints Unificados:
 *   POST /api/empresa/listar       -> listar()
 *   POST /api/empresa/obtener      -> obtener()
 *   POST /api/empresa/guardar      -> guardar() (Crea/Edita)
 *   POST /api/empresa/toggle       -> toggleActivo()
 *   POST /api/empresa/eliminar     -> eliminar()
 */

require_once __DIR__ . '/../services/EmpresaService.php';
require_once __DIR__ . '/../config/database.php';

class EmpresaController {

    private $service;

    public function __construct($dependency) {
        // Mantenemos compatibilidad con el bootstrap por si inyecta el Repo o la BD
        $db = ($dependency instanceof EmpresaRepository) ? Database::getInstance()->getConnection() : $dependency;
        $this->service = new EmpresaService($db);
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
     * POST /api/empresa/listar
     * Lista todas las empresas (con búsqueda opcional)
     */
    public function listar() {
        $data = $this->getRequestData();
        $q = $data['q'] ?? $_GET['q'] ?? null;
        $resultado = $this->service->listarEmpresas($q);
        $this->jsonResponse($resultado, $resultado['success'] ? 200 : 500);
    }

    /**
     * POST /api/empresa/obtener
     * Obtiene una empresa específica por su ID
     */
    public function obtener() {
        $data = $this->getRequestData();
        $id = $data['id'] ?? null;

        if (!$id) {
            $this->jsonResponse(['success' => false, 'message' => 'ID de empresa requerido'], 400);
        }

        $resultado = $this->service->obtenerPorId($id);
        $this->jsonResponse($resultado, $resultado['success'] ? 200 : 404);
    }

    /**
     * POST /api/empresa/guardar
     * Inserta o actualiza una empresa
     */
    public function guardar() {
        $data = $this->getRequestData();
        $resultado = $this->service->guardarEmpresa($data);
        
        $this->jsonResponse($resultado, $resultado['success'] ? 200 : 400);
    }

    /**
     * POST /api/empresa/toggle
     * Activa o desactiva una empresa
     */
    public function toggleActivo() {
        $data = $this->getRequestData();
        $id = $data['id'] ?? null;

        if (!$id) {
            $this->jsonResponse(['success' => false, 'message' => 'ID requerido'], 400);
        }

        $resultado = $this->service->cambiarEstado($id);
        $this->jsonResponse($resultado, $resultado['success'] ? 200 : 400);
    }

    /**
     * POST /api/empresa/eliminar
     * Elimina una empresa
     */
    public function eliminar() {
        $data = $this->getRequestData();
        $id = $data['id'] ?? null;

        if (!$id) {
            $this->jsonResponse(['success' => false, 'message' => 'ID requerido'], 400);
        }

        $resultado = $this->service->eliminarEmpresa($id);
        $this->jsonResponse($resultado, $resultado['success'] ? 200 : 400);
    }

    /**
     * POST /api/empresa/listarCCTE
     * Lista clientes/proveedores de la tabla ccte
     */
    public function listarCCTE() {
        $data = $this->getRequestData();
        $q = $data['q'] ?? $_GET['q'] ?? '';
        $page = isset($data['page']) ? (int)$data['page'] : (isset($_GET['page']) ? (int)$_GET['page'] : 1);
        $pageSize = isset($data['pageSize']) ? (int)$data['pageSize'] : (isset($_GET['pageSize']) ? (int)$_GET['pageSize'] : 20);

        $resultado = $this->service->listarCCTE($q, $page, $pageSize);
        $this->jsonResponse($resultado, $resultado['success'] ? 200 : 500);
    }
}
?>
