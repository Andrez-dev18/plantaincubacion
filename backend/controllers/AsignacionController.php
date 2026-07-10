<?php

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../services/AsignacionService.php';

class AsignacionController
{
    private $db;
    private $service;

    public function __construct()
    {
        $this->db = Database::getInstance()->getConnection();

        // Pasamos la conexión al Service
        $this->service = new AsignacionService($this->db);
    }

    private function getRequestData()
    {
        $json = json_decode(file_get_contents('php://input'), true);
        return $json ? $json : $_POST;
    }

    public function datatable()
    {
        header('Content-Type: application/json');
        try {
            $params = $this->getRequestData();
            $data = $this->service->obtenerDatatable($params);
            
            echo json_encode([
                "draw" => isset($params['draw']) ? intval($params['draw']) : 1,
                "recordsTotal" => $data['recordsTotal'],
                "recordsFiltered" => $data['recordsFiltered'],
                "data" => $data['data']
            ]);
        } catch (Exception $e) {
            echo json_encode(['error' => $e->getMessage()]);
        }
    }

    // Esta ruta alimentará el Modal (trae las tarjetas de roles + los roles marcados del user)
    public function obtenerDatosParaRoles()
    {
        header('Content-Type: application/json');
        try {
            $params = $this->getRequestData();
            $codigo = $params['codigo'] ?? $_GET['codigo'] ?? null;
            if (!$codigo) {
                throw new Exception("El Documento de Identidad (codigo) no fue proporcionado.");
            }

            $data = $this->service->obtenerDatosRoles($codigo);
            echo json_encode(['success' => true, 'data' => $data]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    public function guardar()
    {
        header('Content-Type: application/json');
        try {
            $datos = $this->getRequestData();
            $this->service->guardarRolesUsuario($datos);
            echo json_encode(['success' => true, 'message' => "Roles asignados correctamente al usuario."]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
    }
}
?>
