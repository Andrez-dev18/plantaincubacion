<?php
/**
 * CaracteristicaController
 * 
 * Controlador para gestión de características
 */

require_once __DIR__ . '/../services/CaracteristicaService.php';

class CaracteristicaController {
    
    private $service;

    public function __construct($caracteristicaService) {
        $this->service = $caracteristicaService;
    }

    /**
     * GET /caracteristicas - Listar todas las características
     */
    public function listar() {
        $resultado = $this->service->listar();
        echo json_encode($resultado);
    }

    /**
     * GET /caracteristicas/{id} - Obtener una característica específica
     */
    public function obtener() {
        // Extraer ID de la URL
        $path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
        $segments = explode('/', $path);
        $id = end($segments);
        
        if (!$id || !is_numeric($id)) {
            echo json_encode([
                'success' => false,
                'message' => 'ID inválido'
            ]);
            return;
        }
        
        $resultado = $this->service->obtenerPorId((int)$id);
        echo json_encode($resultado);
    }

    /**
     * POST /caracteristicas - Crear nueva característica
     */
    public function crear() {
        $input = file_get_contents('php://input');
        error_log('📝 [CaracteristicaController] Raw input: ' . $input);
        
        $data = json_decode($input, true);
        error_log('📝 [CaracteristicaController] Decoded data: ' . json_encode($data));
        
        if (!$data) {
            error_log('❌ [CaracteristicaController] Datos inválidos: ' . json_last_error_msg());
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'Datos inválidos: ' . json_last_error_msg()
            ]);
            return;
        }
        
        if (isset($_SESSION['usuario'])) {
            $data['usuario_crea'] = $_SESSION['usuario'];
        }
        
        $resultado = $this->service->crear($data);
        
        http_response_code($resultado['success'] ? 201 : 400);
        echo json_encode($resultado);
    }

    /**
     * PUT /caracteristicas/{id} - Actualizar característica
     */
    public function actualizar() {
        // Extraer ID de la URL
        $path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
        $segments = explode('/', $path);
        $id = end($segments);
        
        if (!$id || !is_numeric($id)) {
            echo json_encode([
                'success' => false,
                'message' => 'ID inválido'
            ]);
            return;
        }
        
        $data = json_decode(file_get_contents('php://input'), true);
        
        // Remover campo _method si existe (method override)
        unset($data['_method']);
        
        if (!$data) {
            echo json_encode([
                'success' => false,
                'message' => 'Datos inválidos'
            ]);
            return;
        }
        
        if (isset($_SESSION['usuario'])) {
            $data['usuario_modifica'] = $_SESSION['usuario'];
        }
        
        $resultado = $this->service->actualizar((int)$id, $data);
        echo json_encode($resultado);
    }

    /**
     * DELETE /caracteristicas/{id} - Eliminar característica
     * Query params: ?forzar=true para eliminar con datos asociados
     */
    public function eliminar() {
        // Extraer ID de la URL
        $path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
        $segments = explode('/', $path);
        $id = end($segments);
        
        if (!$id || !is_numeric($id)) {
            echo json_encode([
                'success' => false,
                'message' => 'ID inválido'
            ]);
            return;
        }
        
        // Verificar si se solicita eliminación forzada
        $forzar = isset($_GET['forzar']) && $_GET['forzar'] === 'true';
        
        $resultado = $this->service->eliminar((int)$id, $forzar);
        echo json_encode($resultado);
    }

    /**
     * GET /caracteristicas/{id}/verificar-uso - Verificar si característica está en uso
     */
    public function verificarUso() {
        $path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
        $segments = explode('/', $path);
        $id = $segments[count($segments) - 2]; // Penúltimo segmento
        
        if (!$id || !is_numeric($id)) {
            echo json_encode([
                'success' => false,
                'message' => 'ID inválido'
            ]);
            return;
        }
        
        $resultado = $this->service->verificarUso((int)$id);
        echo json_encode($resultado);
    }

    /**
     * GET /caracteristicas/buscar/{termino} - Buscar características
     */
    public function buscar($searchTerm) {
        $resultado = $this->service->buscar($searchTerm);
        echo json_encode($resultado);
    }

    /**
     * GET /caracteristicas/tipos-datos - Obtener tipos de datos disponibles
     */
    public function tiposDatos() {
        $resultado = $this->service->obtenerTiposDatos();
        echo json_encode($resultado);
    }
}
