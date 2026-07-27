<?php
/**
 * ContribuyentesController — Controlador para la gestión de contribuyentes
 * 
 * Endpoints Unificados:
 *   POST /api/contribuyentes/listar       -> listar()
 *   POST /api/contribuyentes/obtener      -> obtener()
 *   POST /api/contribuyentes/guardar      -> guardar() (Crea/Edita)
 *   POST /api/contribuyentes/eliminar     -> eliminar()
 */

require_once __DIR__ . '/../services/ContribuyentesService.php';
require_once __DIR__ . '/../config/database.php';

class ContribuyentesController {

    private $service;

    public function __construct($dependency) {
        // Mantenemos compatibilidad con el bootstrap por si inyecta el Repo o la BD
        $db = ($dependency instanceof ContribuyentesRepository) ? Database::getInstance()->getConnection() : $dependency;
        $this->service = new ContribuyentesService($db);
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
     * POST /api/contribuyentes/listar
     * Lista todos los contribuyentes con paginación
     */
    public function listar() {
        $data = $this->getRequestData();
        $q = $data['q'] ?? $_GET['q'] ?? '';
        $nombre = $data['nombre'] ?? $_GET['nombre'] ?? '';
        $ruc = $data['ruc'] ?? $_GET['ruc'] ?? '';
        $page = isset($data['page']) ? (int)$data['page'] : (isset($_GET['page']) ? (int)$_GET['page'] : 1);
        $pageSize = isset($data['pageSize']) ? (int)$data['pageSize'] : (isset($_GET['pageSize']) ? (int)$_GET['pageSize'] : 25);

        $resultado = $this->service->listarContribuyentes($q, $page, $pageSize, $nombre, $ruc);
        $this->jsonResponse($resultado, $resultado['success'] ? 200 : 500);
    }

    /**
     * POST /api/contribuyentes/obtener
     * Obtiene un contribuyente específico por su código
     */
    public function obtener() {
        $data = $this->getRequestData();
        $codigo = $data['codigo'] ?? null;

        if (!$codigo) {
            $this->jsonResponse(['success' => false, 'message' => 'Código de contribuyente requerido'], 400);
        }

        $resultado = $this->service->obtenerPorId($codigo);
        $this->jsonResponse($resultado, $resultado['success'] ? 200 : 404);
    }

    /**
     * POST /api/contribuyentes/guardar
     * Inserta o actualiza un contribuyente
     */
    public function guardar() {
        $data = $this->getRequestData();
        $resultado = $this->service->guardarContribuyente($data);
        
        $this->jsonResponse($resultado, $resultado['success'] ? 200 : 400);
    }

    /**
     * POST /api/contribuyentes/eliminar
     * Elimina un contribuyente
     */
    public function eliminar() {
        $data = $this->getRequestData();
        $codigo = $data['codigo'] ?? null;

        if (!$codigo) {
            $this->jsonResponse(['success' => false, 'message' => 'Código requerido'], 400);
        }

        $resultado = $this->service->eliminarContribuyente($codigo);
        $this->jsonResponse($resultado, $resultado['success'] ? 200 : 400);
    }

    /**
     * POST /api/contribuyentes/toggle
     * Activa o desactiva un contribuyente
     */
    public function toggleActivo() {
        $data = $this->getRequestData();
        $codigo = $data['codigo'] ?? null;

        if (!$codigo) {
            $this->jsonResponse(['success' => false, 'message' => 'Código requerido'], 400);
        }

        $resultado = $this->service->cambiarEstado($codigo);
        $this->jsonResponse($resultado, $resultado['success'] ? 200 : 400);
    }
}
?>
