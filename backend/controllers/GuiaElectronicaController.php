<?php
require_once __DIR__ . '/../services/GuiaElectronicaService.php';

class GuiaElectronicaController
{
    private $service;

    public function __construct($db)
    {
        $this->service = new GuiaElectronicaService($db);
    }

    /**
     * Retorna todas las zonas/almacenes en formato JSON
     */
    public function getZonas()
    {
        header('Content-Type: application/json; charset=UTF-8');
        try {
            $zonas = $this->service->listarAlmacenes();

            echo json_encode([
                "success" => true,
                "data" => $zonas
            ], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode([
                "success" => false,
                "message" => "Error al obtener las zonas: " . $e->getMessage()
            ], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
        }
        exit;
    }

    /**
     * Retorna todos los tipos de transporte en formato JSON
     */
    public function getTiposTransporte()
    {
        header('Content-Type: application/json; charset=UTF-8');
        try {
            $tipos = $this->service->listarTiposTransporte();

            echo json_encode([
                "success" => true,
                "data" => $tipos
            ], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode([
                "success" => false,
                "message" => "Error al obtener los tipos de transporte: " . $e->getMessage()
            ], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
        }
        exit;
    }

    /**
     * Retorna el listado de transportistas filtrado o completo en formato JSON
     */
    public function getTransportistas()
    {
        header('Content-Type: application/json; charset=UTF-8');
        try {
            $search = $_GET['q'] ?? null;
            $transportistas = $this->service->listarTransportistas($search);

            echo json_encode([
                "success" => true,
                "data" => $transportistas
            ], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode([
                "success" => false,
                "message" => "Error al obtener los transportistas: " . $e->getMessage()
            ], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
        }
        exit;
    }

    public function getConductores() 
    {
        header('Content-Type: application/json; charset=UTF-8');
        try {
            $search = $_GET['q'] ?? null;
            $conductores = $this->service->listarConductores($search);

            echo json_encode([
                "success" => true,
                "data" => $conductores
            ], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
            exit;
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode([
                "success" => false,
                "message" => "Error al obtener los conductores: " . $e->getMessage()
            ], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
            exit;
        }
    }

    /**
     * Retorna el listado de camiones filtrado o completo en formato JSON
     */
    public function getCamiones()
    {
        header('Content-Type: application/json; charset=UTF-8');
        try {
            $search = $_GET['q'] ?? null;
            $camiones = $this->service->listarCamiones($search);

            echo json_encode([
                "success" => true,
                "data" => $camiones
            ], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode([
                "success" => false,
                "message" => "Error al obtener los camiones: " . $e->getMessage()
            ], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
        }
        exit;
    }

    /**
     * Retorna el listado de clientes filtrado o completo en formato JSON
     */
    public function getClientes()
    {
        header('Content-Type: application/json; charset=UTF-8');
        try {
            $search = $_GET['q'] ?? null;
            $clientes = $this->service->listarClientes($search);

            echo json_encode([
                "success" => true,
                "data" => $clientes
            ], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode([
                "success" => false,
                "message" => "Error al obtener los clientes: " . $e->getMessage()
            ], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
        }
        exit;
    }
}
