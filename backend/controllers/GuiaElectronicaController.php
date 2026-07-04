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
}
