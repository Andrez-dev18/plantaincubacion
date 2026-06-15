<?php
/**
 * DashboardModuloController
 *
 * Controlador para el orden del dashboard
 */

require_once __DIR__ . '/../services/DashboardModuloService.php';

class DashboardModuloController {
    private $service;

    public function __construct($dashboardModuloService) {
        $this->service = $dashboardModuloService;
    }

    /**
     * GET /dashboard-modulos?programa=...
     */
    public function listar() {
        try {
            header('Content-Type: application/json; charset=UTF-8');

            $programa = isset($_GET['programa']) && $_GET['programa'] !== ''
                ? $_GET['programa']
                : null;

            // Leer usuario de la sesión PHP (nuevo sistema: 'username'; legacy: 'usuario')
            $userCodigo = $_SESSION['username'] ?? $_SESSION['usuario'] ?? null;

            // Sin sesión activa → devolver vacío (el frontend redirigirá al login)
            if (empty($userCodigo)) {
                echo json_encode(['success' => true, 'data' => []]);
                return;
            }

            $data = $this->service->listar($programa, $userCodigo);

            echo json_encode([
                'success' => true,
                'data' => $data
            ]);
        } catch (Exception $e) {
            error_log("Error en DashboardModuloController::listar: " . $e->getMessage());
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'message' => 'Error al listar modulos del dashboard: ' . $e->getMessage()
            ]);
        }
    }

    /**
     * POST /dashboard-modulos/seed
     */
    public function seed() {
        try {
            $data = json_decode(file_get_contents('php://input'), true) ?: [];
            $programa = $data['programa'] ?? ($_GET['programa'] ?? 'Planta de Incubacion');

            $inserted = $this->service->seedIfEmpty($programa);

            echo json_encode([
                'success' => true,
                'data' => [
                    'inserted' => $inserted
                ]
            ]);
        } catch (Exception $e) {
            error_log("Error en DashboardModuloController::seed: " . $e->getMessage());
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'message' => 'Error al sembrar modulos del dashboard: ' . $e->getMessage()
            ]);
        }
    }

    /**
     * PATCH /dashboard-modulos/orden
     */
    public function mover() {
        try {
            $data = json_decode(file_get_contents('php://input'), true) ?: [];
            $programa = $data['programa'] ?? 'Planta de Incubacion';
            $codMod = $data['cod_mod'] ?? null;
            $direction = $data['direction'] ?? null;

            if (!$codMod || !in_array($direction, ['up', 'down'], true)) {
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'message' => 'Datos incompletos para ordenar'
                ]);
                return;
            }

            $result = $this->service->mover($programa, $codMod, $direction);

            echo json_encode($result);
        } catch (Exception $e) {
            error_log("Error en DashboardModuloController::mover: " . $e->getMessage());
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'message' => 'Error al ordenar modulos del dashboard: ' . $e->getMessage()
            ]);
        }
    }

    /**
     * POST /dashboard-modulos/sync
     */
    public function sync() {
        try {
            $data = json_decode(file_get_contents('php://input'), true) ?: [];
            $programa = $data['programa'] ?? 'Planta de Incubacion';
            $items = $data['items'] ?? [];

            if (!is_array($items) || empty($items)) {
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'message' => 'No hay items para sincronizar'
                ]);
                return;
            }

            $inserted = $this->service->syncFromList($programa, $items);

            echo json_encode([
                'success' => true,
                'data' => [
                    'inserted' => $inserted
                ]
            ]);
        } catch (Exception $e) {
            error_log("Error en DashboardModuloController::sync: " . $e->getMessage());
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'message' => 'Error al sincronizar modulos del dashboard: ' . $e->getMessage()
            ]);
        }
    }

    /**
     * POST /dashboard-modulos
     */
    public function crear() {
        try {
            $data = json_decode(file_get_contents('php://input'), true) ?: [];

            if (empty($data['cod_mod']) || empty($data['nom_mod']) || empty($data['tipo'])) {
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'message' => 'Campos obligatorios: cod_mod, nom_mod, tipo'
                ]);
                return;
            }

            $result = $this->service->crear($data);

            echo json_encode([
                'success' => true,
                'data' => $result,
                'message' => 'Módulo creado correctamente'
            ]);
        } catch (Exception $e) {
            error_log("Error en DashboardModuloController::crear: " . $e->getMessage());
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'message' => 'Error al crear módulo: ' . $e->getMessage()
            ]);
        }
    }

    /**
     * PUT /dashboard-modulos
     */
    public function actualizar() {
        try {
            $data = json_decode(file_get_contents('php://input'), true) ?: [];
            
            // Remover campo _method si existe (method override)
            unset($data['_method']);

            if (empty($data['cod_mod'])) {
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'message' => 'El campo cod_mod es obligatorio'
                ]);
                return;
            }

            $result = $this->service->actualizar($data);

            echo json_encode([
                'success' => true,
                'data' => $result,
                'message' => 'Módulo actualizado correctamente'
            ]);
        } catch (Exception $e) {
            error_log("Error en DashboardModuloController::actualizar: " . $e->getMessage());
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'message' => 'Error al actualizar módulo: ' . $e->getMessage()
            ]);
        }
    }

    /**
     * DELETE /dashboard-modulos
     */
    public function eliminar() {
        try {
            $data = json_decode(file_get_contents('php://input'), true) ?: [];
            
            // Remover campo _method si existe (method override)
            unset($data['_method']);

            if (empty($data['cod_mod'])) {
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'message' => 'El campo cod_mod es obligatorio'
                ]);
                return;
            }

            $result = $this->service->eliminar($data['cod_mod'], $data['programa'] ?? 'Planta de Incubacion');

            echo json_encode([
                'success' => true,
                'data' => $result,
                'message' => 'Módulo eliminado correctamente'
            ]);
        } catch (Exception $e) {
            error_log("Error en DashboardModuloController::eliminar: " . $e->getMessage());
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'message' => 'Error al eliminar módulo: ' . $e->getMessage()
            ]);
        }
    }
}

