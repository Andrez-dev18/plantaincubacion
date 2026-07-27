<?php
/**
 * ArticulosController — Controlador para la gestión de artículos
 * 
 * Endpoints Unificados:
 *   POST /api/articulos/listar       -> listar()
 *   POST /api/articulos/obtener      -> obtener()
 *   POST /api/articulos/guardar      -> guardar() (Crea/Edita)
 *   POST /api/articulos/eliminar     -> eliminar()
 */

require_once __DIR__ . '/../services/ArticulosService.php';
require_once __DIR__ . '/../config/database.php';

class ArticulosController {

    private $service;

    public function __construct($dependency) {
        // Mantenemos compatibilidad con el bootstrap por si inyecta el Repo o la BD
        $db = ($dependency instanceof ArticulosRepository) ? Database::getInstance()->getConnection() : $dependency;
        $this->service = new ArticulosService($db);
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
     * POST /api/articulos/listar
     * Lista todos los artículos con paginación
     */
    public function listar() {
        $data = $this->getRequestData();
        $q = $data['q'] ?? $_GET['q'] ?? '';
        $codigo = $data['codigo'] ?? $_GET['codigo'] ?? '';
        $soloIncompletos = filter_var($data['solo_incompletos'] ?? $_GET['solo_incompletos'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $page = isset($data['page']) ? (int)$data['page'] : (isset($_GET['page']) ? (int)$_GET['page'] : 1);
        $pageSize = isset($data['pageSize']) ? (int)$data['pageSize'] : (isset($_GET['pageSize']) ? (int)$_GET['pageSize'] : 25);

        $resultado = $this->service->listarArticulos($q, $page, $pageSize, $codigo, $soloIncompletos);
        $this->jsonResponse($resultado, $resultado['success'] ? 200 : 500);
    }

    /**
     * POST /api/articulos/obtener
     * Obtiene un artículo específico por su código
     */
    public function obtener() {
        $data = $this->getRequestData();
        $codigo = $data['codigo'] ?? null;

        if (!$codigo) {
            $this->jsonResponse(['success' => false, 'message' => 'Código de artículo requerido'], 400);
        }

        $resultado = $this->service->obtenerPorId($codigo);
        $this->jsonResponse($resultado, $resultado['success'] ? 200 : 404);
    }

    /**
     * POST /api/articulos/guardar
     * Inserta o actualiza un artículo
     */
    public function guardar() {
        $data = $this->getRequestData();
        $resultado = $this->service->guardarArticulo($data);
        
        $this->jsonResponse($resultado, $resultado['success'] ? 200 : 400);
    }

    /**
     * POST /api/articulos/eliminar
     * Elimina un artículo
     */
    public function eliminar() {
        $data = $this->getRequestData();
        $codigo = $data['codigo'] ?? null;

        if (!$codigo) {
            $this->jsonResponse(['success' => false, 'message' => 'Código requerido'], 400);
        }

        $resultado = $this->service->eliminarArticulo($codigo);
        $this->jsonResponse($resultado, $resultado['success'] ? 200 : 400);
    }
}
?>
