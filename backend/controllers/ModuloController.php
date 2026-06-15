<?php
/**
 * ModuloController
 *
 * Controlador para listar modulos por programa
 */

require_once __DIR__ . '/../services/ModuloService.php';

class ModuloController {
    private $service;

    public function __construct($moduloService) {
        $this->service = $moduloService;
    }

    /**
     * GET /modulos?programa=produccion
     */
    public function listar() {
        try {
            $programa = $_GET['programa'] ?? 'produccion';

            $modulos = $this->service->listar($programa);

            echo json_encode([
                'success' => true,
                'data' => $modulos
            ]);
        } catch (Exception $e) {
            error_log("Error en ModuloController::listar: " . $e->getMessage());
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'message' => 'Error al listar modulos: ' . $e->getMessage()
            ]);
        }
    }

    /**
     * GET /modulos/programas
     */
    public function listarProgramas() {
        try {
            $programas = $this->service->listarProgramas();

            echo json_encode([
                'success' => true,
                'data' => $programas
            ]);
        } catch (Exception $e) {
            error_log("Error en ModuloController::listarProgramas: " . $e->getMessage());
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'message' => 'Error al listar programas: ' . $e->getMessage()
            ]);
        }
    }
}
