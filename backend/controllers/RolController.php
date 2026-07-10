<?php
/**
 * RolController — Controlador Único y Optimizado (Planta Incubación)
 * 
 * Endpoints Unificados:
 *   POST /api/rol/listar       -> listar()
 *   POST /api/rol/arbol        -> obtenerModulosArbol()
 *   POST /api/rol/obtener      -> obtener()
 *   POST /api/rol/guardar      -> guardar() (Crea/Edita y asigna permisos)
 *   POST /api/rol/toggle       -> toggleActivo()
 *   POST /api/rol/eliminar     -> eliminar()
 */

require_once __DIR__ . '/../services/RolService.php';
require_once __DIR__ . '/../config/database.php';

class RolController {

    private $service;

    public function __construct($dependency) {
        // Mantenemos compatibilidad con el bootstrap por si inyecta el Repo o la BD
        $db = ($dependency instanceof RolRepository) ? Database::getInstance()->getConnection() : $dependency;
        $this->service = new RolService($db);
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
     * POST /api/rol/listar
     * Lista todos los roles del Programa 1 (Planta Incubación)
     */
    public function listar() {
        $resultado = $this->service->listarRoles();
        $this->jsonResponse($resultado, $resultado['success'] ? 200 : 500);
    }

    /**
     * POST /api/rol/arbol
     * Obtiene el árbol completo de módulos para dibujar los Checkboxes
     */
    public function obtenerModulosArbol() {
        $resultado = $this->service->obtenerModulosArbol();
        $this->jsonResponse($resultado, $resultado['success'] ? 200 : 500);
    }

    /**
     * POST /api/rol/obtener
     * Obtiene un rol específico y su arreglo de módulos permitidos
     */
    public function obtener() {
        $data = $this->getRequestData();
        $id = $data['id'] ?? null;

        if (!$id) {
            $this->jsonResponse(['success' => false, 'message' => 'ID de rol requerido'], 400);
        }

        $resultado = $this->service->obtenerPorId($id);
        $this->jsonResponse($resultado, $resultado['success'] ? 200 : 404);
    }

    /**
     * POST /api/rol/guardar
     * Inserta o actualiza un rol Y guarda sus permisos asociados en una sola transacción
     */
    public function guardar() {
        $data = $this->getRequestData();
        $resultado = $this->service->guardarRol($data);
        
        $this->jsonResponse($resultado, $resultado['success'] ? 200 : 400);
    }

    /**
     * POST /api/rol/toggle
     * Activa o desactiva un rol (Protege al ADMIN)
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
     * POST /api/rol/eliminar
     * Elimina un rol validando que no tenga usuarios asignados
     */
    public function eliminar() {
        $data = $this->getRequestData();
        $id = $data['id'] ?? null;

        if (!$id) {
            $this->jsonResponse(['success' => false, 'message' => 'ID requerido'], 400);
        }

        $resultado = $this->service->eliminarRol($id);
        $this->jsonResponse($resultado, $resultado['success'] ? 200 : 400);
    }
}
?>