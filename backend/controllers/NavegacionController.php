<?php

require_once __DIR__ . '/../services/NavegacionService.php';
require_once __DIR__ . '/../config/database.php';

class NavegacionController {
    private $service;

    public function __construct() {
        $db = Database::getInstance()->getConnection();
        $this->service = new NavegacionService($db);
    }

    /**
     * GET /admin/roles/{id}/navegacion
     * Obtener módulos visibles para un rol
     */
    public function obtenerModulosPorRol($idRol) {
        try {
            $modulos = $this->service->obtenerModulosPorRol($idRol);
            
            header('Content-Type: application/json');
            echo json_encode([
                'success' => true,
                'data' => $modulos
            ]);
        } catch (Exception $e) {
            http_response_code(500);
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false,
                'message' => $e->getMessage()
            ]);
        }
    }

    /**
     * GET /admin/usuarios/{id}/navegacion
     * Obtener módulos visibles para un usuario
     */
    public function obtenerModulosPorUsuario($idUsuario) {
        try {
            $modulos = $this->service->obtenerModulosPorUsuario($idUsuario);
            
            header('Content-Type: application/json');
            echo json_encode([
                'success' => true,
                'data' => $modulos
            ]);
        } catch (Exception $e) {
            http_response_code(500);
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false,
                'message' => $e->getMessage()
            ]);
        }
    }

    /**
     * GET /admin/usuarios/{id}/menu
     * GET /admin/usuarios/{id}/menu?programa=Planta%20de%20Incubacion
     * Obtener menú jerárquico para un usuario
     */
    public function obtenerMenuJerarquico($idUsuario) {
        try {
            $programa = isset($_GET['programa']) ? $_GET['programa'] : null;
            $menu = $this->service->obtenerMenuJerarquico($idUsuario, $programa);
            
            header('Content-Type: application/json');
            echo json_encode([
                'success' => true,
                'data' => $menu
            ]);
        } catch (Exception $e) {
            http_response_code(500);
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false,
                'message' => $e->getMessage()
            ]);
        }
    }

    /**
     * GET /admin/usuarios/{id}/acceso/{codMod}
     * Verificar si usuario tiene acceso a módulo
     */
    public function verificarAcceso($idUsuario, $codMod) {
        try {
            $tieneAcceso = $this->service->usuarioTieneAcceso($idUsuario, $codMod);
            
            header('Content-Type: application/json');
            echo json_encode([
                'success' => true,
                'tiene_acceso' => $tieneAcceso
            ]);
        } catch (Exception $e) {
            http_response_code(500);
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false,
                'message' => $e->getMessage()
            ]);
        }
    }

    /**
     * POST /admin/roles/{id}/navegacion
     * Asignar un módulo a un rol
     * Body: { "cod_mod": "MOD001" }
     */
    public function asignarModulo($idRol) {
        try {
            $data = json_decode(file_get_contents('php://input'), true);
            
            if (!isset($data['cod_mod'])) {
                http_response_code(400);
                header('Content-Type: application/json');
                echo json_encode([
                    'success' => false,
                    'message' => 'cod_mod es requerido'
                ]);
                return;
            }

            $resultado = $this->service->asignarModulo($idRol, $data['cod_mod']);
            
            $statusCode = $resultado['success'] ? 200 : 400;
            http_response_code($statusCode);
            header('Content-Type: application/json');
            echo json_encode($resultado);
        } catch (Exception $e) {
            http_response_code(500);
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false,
                'message' => $e->getMessage()
            ]);
        }
    }

    /**
     * DELETE /admin/roles/{id}/navegacion/{codMod}
     * Remover módulo de un rol
     */
    public function removerModulo($idRol, $codMod) {
        try {
            $resultado = $this->service->removerModulo($idRol, $codMod);
            
            $statusCode = $resultado['success'] ? 200 : 400;
            http_response_code($statusCode);
            header('Content-Type: application/json');
            echo json_encode($resultado);
        } catch (Exception $e) {
            http_response_code(500);
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false,
                'message' => $e->getMessage()
            ]);
        }
    }

    /**
     * PUT /admin/roles/{id}/navegacion
     * Asignar múltiples módulos a un rol (reemplaza existentes)
     * Body: { "modulos": ["MOD001", "MOD002"] }
     */
    public function asignarModulosLote($idRol) {
        try {
            $data = json_decode(file_get_contents('php://input'), true);
            
            if (!isset($data['modulos']) || !is_array($data['modulos'])) {
                http_response_code(400);
                header('Content-Type: application/json');
                echo json_encode([
                    'success' => false,
                    'message' => 'modulos debe ser un array'
                ]);
                return;
            }

            $resultado = $this->service->asignarModulosLote($idRol, $data['modulos']);
            
            $statusCode = $resultado['success'] ? 200 : 400;
            http_response_code($statusCode);
            header('Content-Type: application/json');
            echo json_encode($resultado);
        } catch (Exception $e) {
            http_response_code(500);
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false,
                'message' => $e->getMessage()
            ]);
        }
    }

    /**
     * GET /admin/modulos/programa/{programa}
     * Obtener módulos disponibles de un programa
     */
    public function obtenerModulosDisponibles($programa) {
        try {
            $modulos = $this->service->obtenerModulosDisponiblesPorPrograma($programa);
            
            header('Content-Type: application/json');
            echo json_encode([
                'success' => true,
                'data' => $modulos
            ]);
        } catch (Exception $e) {
            http_response_code(500);
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false,
                'message' => $e->getMessage()
            ]);
        }
    }

    /**
     * GET /admin/roles/{id}/navegacion/asignacion?programa=X
     * Obtener módulos para interfaz de asignación
     */
    public function obtenerParaAsignacion($idRol) {
        try {
            $programa = isset($_GET['programa']) ? $_GET['programa'] : null;
            
            if (!$programa) {
                http_response_code(400);
                header('Content-Type: application/json');
                echo json_encode([
                    'success' => false,
                    'message' => 'Parámetro programa es requerido'
                ]);
                return;
            }

            $datos = $this->service->obtenerModulosParaAsignacion($idRol, $programa);
            
            header('Content-Type: application/json');
            echo json_encode([
                'success' => true,
                'data' => $datos
            ]);
        } catch (Exception $e) {
            http_response_code(500);
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false,
                'message' => $e->getMessage()
            ]);
        }
    }
}
