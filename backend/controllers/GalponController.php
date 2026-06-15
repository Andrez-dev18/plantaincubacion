<?php
/**
 * GalponController
 * 
 * Controlador para gestión de galpones
 * Maneja las peticiones HTTP y delega la lógica al servicio
 */

require_once __DIR__ . '/../services/GalponService.php';

class GalponController {
    
    private $service;

    public function __construct($galponService) {
        $this->service = $galponService;
    }

    /**
     * GET /galpon/listar
     * Listar galpones con filtros opcionales
     */
    public function listar() {
        try {
            // Obtener filtros de query params
            $filtros = [];
            
            if (isset($_GET['id_granja'])) {
                $filtros['id_granja'] = (int)$_GET['id_granja'];
            }
            
            if (isset($_GET['fecha_desde'])) {
                $filtros['fecha_desde'] = $_GET['fecha_desde'];
            }
            
            if (isset($_GET['fecha_hasta'])) {
                $filtros['fecha_hasta'] = $_GET['fecha_hasta'];
            }
            
            $galpones = $this->service->listar($filtros);
            
            echo json_encode([
                'success' => true,
                'data' => $galpones
            ]);
        } catch (Exception $e) {
            error_log("Error en GalponController::listar: " . $e->getMessage());
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'message' => 'Error al listar galpones: ' . $e->getMessage()
            ]);
        }
    }

    /**
     * GET /galpon/obtener/{id}?id_granja={id_granja}
     * Obtener un galpón específico
     */
    public function obtener() {
        // Extraer ID de la URL (ID compuesto "granja-galpon" como "623-1")
        $path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
        $segments = explode('/', $path);
        $id = end($segments);
        
        $idGranja = $_GET['id_granja'] ?? null;
        
        if (!$id || !$idGranja) {
            echo json_encode([
                'success' => false,
                'message' => 'Faltan parámetros requeridos: id y id_granja'
            ]);
            return;
        }
        
        // No convertir a int - mantener el ID compuesto "granja-galpon"
        $resultado = $this->service->obtenerPorId($id, $idGranja);
        echo json_encode($resultado);
    }

    /**
     * POST /galpon/crear o guardar características
     * Si viene con granja + galpon → Guarda características (UPSERT)
     * Si NO viene galpon → Crea nuevo galpón
     */
    public function crear($data) {
        // Si viene granja Y galpon (número), es guardado de características
        if (isset($data['granja']) && isset($data['galpon'])) {
            return $this->guardarCaracteristicas($data);
        }
        
        // Si NO, es creación de nuevo galpón
        if (isset($_SESSION['usuario'])) {
            $data['usuario_crea'] = $_SESSION['usuario'];
        }
        
        $resultado = $this->service->crear($data);
        
        http_response_code($resultado['success'] ? 201 : 400);
        echo json_encode($resultado);
    }

    /**
     * Guardar características de un galpón existente (UPSERT)
     */
    private function guardarCaracteristicas($data) {
        // Agregar usuario de sesión
        if (isset($_SESSION['usuario'])) {
            $data['usuario_crea'] = $_SESSION['usuario'];
            $data['usuario_modifica'] = $_SESSION['usuario'];
        }
        
        $resultado = $this->service->guardarCaracteristicas($data);
        
        http_response_code($resultado['success'] ? 200 : 400);
        echo json_encode($resultado);
    }

    /**
     * PUT /galpon/actualizar/{id}?id_granja={id_granja}
     * Actualizar un galpón existente
     */
    public function actualizar($data) {
        // Remover campo _method si existe (method override)
        unset($data['_method']);
        
        // Extraer ID compuesto de la URL (ej: "623-1")
        $path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
        $segments = explode('/', $path);
        $id = end($segments);
        
        $idGranja = $_GET['id_granja'] ?? null;
        
        if (!$id || !$idGranja) {
            echo json_encode([
                'success' => false,
                'message' => 'Faltan parámetros requeridos: id y id_granja'
            ]);
            return;
        }
        
        // Agregar usuario de sesión
        if (isset($_SESSION['usuario'])) {
            $data['usuario_modifica'] = $_SESSION['usuario'];
        }
        
        // No convertir a int - mantener ID compuesto
        $resultado = $this->service->actualizar($id, $idGranja, $data);
        echo json_encode($resultado);
    }

    /**
     * DELETE /galpon/eliminar/{id}?id_granja={id_granja}
     * Eliminar un galpón
     */
    public function eliminar() {
        // Extraer ID compuesto de la URL (ej: "623-1")
        $path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
        $segments = explode('/', $path);
        $id = end($segments);
        
        $idGranja = $_GET['id_granja'] ?? null;
        
        if (!$id || !$idGranja) {
            echo json_encode([
                'success' => false,
                'message' => 'Faltan parámetros requeridos: id y id_granja'
            ]);
            return;
        }
        
        // No convertir a int - mantener ID compuesto
        $resultado = $this->service->eliminar($id, $idGranja);
        echo json_encode($resultado);
    }

    /**
     * GET /galpon/caracteristicas
     * Obtener catálogo de características disponibles
     */
    public function caracteristicas() {
        $resultado = $this->service->obtenerCaracteristicas();
        echo json_encode($resultado);
    }

    /**
     * GET /galpon/granjas
     * Obtener lista de granjas disponibles
     */
    public function granjas() {
        $resultado = $this->service->obtenerGranjas();
        echo json_encode($resultado);
    }

    /**
     * GET /galpon/galpones-por-granja/{tcencos}
     * Obtener galpones de una granja específica
     */
    public function galponesPorGranja($tcencos) {
        $resultado = $this->service->obtenerGalponesPorGranja($tcencos);
        echo json_encode($resultado);
    }

    /**
     * GET /galpon/exportar-pdf
     * Exportar galpones a PDF con filtros opcionales
     */
    public function exportarPDF() {
        // Obtener filtros de query params
        $filtros = [];
        
        if (isset($_GET['id_granja'])) {
            $filtros['id_granja'] = (int)$_GET['id_granja'];
        }
        
        if (isset($_GET['fecha_desde'])) {
            $filtros['fecha_desde'] = $_GET['fecha_desde'];
        }
        
        if (isset($_GET['fecha_hasta'])) {
            $filtros['fecha_hasta'] = $_GET['fecha_hasta'];
        }
        
        // Llamar al servicio (este método descarga el PDF directamente)
        $this->service->exportarPDF($filtros);
    }

    /**
     * GET /galpon/exportar-excel
     * Exportar galpones a Excel con filtros opcionales
     */
    public function exportarExcel() {
        // Obtener filtros de query params
        $filtros = [];
        
        if (isset($_GET['id_granja'])) {
            $filtros['id_granja'] = (int)$_GET['id_granja'];
        }
        
        if (isset($_GET['fecha_desde'])) {
            $filtros['fecha_desde'] = $_GET['fecha_desde'];
        }
        
        if (isset($_GET['fecha_hasta'])) {
            $filtros['fecha_hasta'] = $_GET['fecha_hasta'];
        }
        
        // Limpiar cualquier output previo
        if (ob_get_level()) {
            ob_end_clean();
        }
        
        // Llamar al servicio (este método descarga el Excel directamente)
        $this->service->exportarExcel($filtros);
    }
}
