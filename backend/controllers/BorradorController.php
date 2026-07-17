<?php

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../services/BorradorService.php';

class BorradorController
{
    private $db;
    private $service;

    public function __construct($db = null)
    {
        // Si no se pasa la conexión, la obtenemos del Singleton
        $this->db = $db ? $db : Database::getInstance()->getConnection();
        $this->service = new BorradorService($this->db);
    }

    private function jsonResponse(array $data, int $statusCode = 200): void
    {
        http_response_code($statusCode);
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE);
        exit;
    }

    private function getRequestData(): array
    {
        $json = json_decode(file_get_contents('php://input'), true);
        return is_array($json) ? $json : $_POST;
    }

    /**
     * GET /api/borradores/obtener
     * Obtiene el borrador de un formulario específico.
     */
    public function obtener(): void
    {
        try {
            $requestData = $this->getRequestData();
            $params = array_merge($_GET, $requestData);

            // Intentar recuperar el usuario del request o la sesión activa
            if (empty($params['usuario'])) {
                $params['usuario'] = $_SESSION['username'] ?? $_SESSION['usuario'] ?? '';
            }

            // Programa por defecto es '1' (Planta Incubación)
            if (empty($params['id_programa'])) {
                $params['id_programa'] = '1';
            }

            $resultado = $this->service->obtenerBorrador($params);
            
            // Si tiene éxito devolvemos 200, si no se encontró 404
            $status = $resultado['success'] ? 200 : 404;
            $this->jsonResponse($resultado, $status);
        } catch (Exception $e) {
            $this->jsonResponse([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * POST /api/borradores/guardar
     * Guarda o actualiza el borrador del formulario.
     */
    public function guardar(): void
    {
        try {
            $params = $this->getRequestData();

            // Intentar recuperar el usuario del request o la sesión activa
            if (empty($params['usuario'])) {
                $params['usuario'] = $_SESSION['username'] ?? $_SESSION['usuario'] ?? '';
            }

            // Programa por defecto es '1' (Planta Incubación)
            if (empty($params['id_programa'])) {
                $params['id_programa'] = '1';
            }

            $resultado = $this->service->guardarBorrador($params);
            $status = $resultado['success'] ? 200 : 400;
            $this->jsonResponse($resultado, $status);
        } catch (Exception $e) {
            $this->jsonResponse([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * POST /api/borradores/eliminar
     * Elimina el borrador del formulario (típicamente tras un guardado exitoso).
     */
    public function eliminar(): void
    {
        try {
            $params = $this->getRequestData();

            // Intentar recuperar el usuario del request o la sesión activa
            if (empty($params['usuario'])) {
                $params['usuario'] = $_SESSION['username'] ?? $_SESSION['usuario'] ?? '';
            }

            // Programa por defecto es '1' (Planta Incubación)
            if (empty($params['id_programa'])) {
                $params['id_programa'] = '1';
            }

            $resultado = $this->service->eliminarBorrador($params);
            $status = $resultado['success'] ? 200 : 400;
            $this->jsonResponse($resultado, $status);
        } catch (Exception $e) {
            $this->jsonResponse([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        }
    }
}
?>
