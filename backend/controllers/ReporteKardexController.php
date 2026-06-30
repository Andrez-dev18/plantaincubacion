<?php
require_once __DIR__ . '/../services/ReporteKardexService.php';

class ReporteKardexController
{
    private $service;

    public function __construct($db)
    {
        $this->service = new ReporteKardexService($db);
    }


    //FUNCIONES PARA FILTROS DE REPORTE KARDEX
    public function getAlmacenes()
    {
        header('Content-Type: application/json; charset=UTF-8');
        try {
            $almacenes = $this->service->listarAlmacenes();

            echo json_encode([
                "success" => true,
                "data" => $almacenes
            ], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode([
                "success" => false,
                "message" => "Error al obtener los almacenes: " . $e->getMessage()
            ], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
        }
        exit;
    }

    public function getLineas()
    {
        header('Content-Type: application/json; charset=UTF-8');
        try {
            $lineas = $this->service->listarLineas();

            echo json_encode([
                "success" => true,
                "data" => $lineas
            ], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode([
                "success" => false,
                "message" => "Error al obtener las líneas: " . $e->getMessage()
            ], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
        }
        exit;
    }

    public function getArticulos()
    {
        header('Content-Type: application/json; charset=UTF-8');
        try {
            $articulos = $this->service->listarArticulos();

            echo json_encode([
                "success" => true,
                "data" => $articulos
            ], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode([
                "success" => false,
                "message" => "Error al obtener los códigos de artículo: " . $e->getMessage()
            ], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
        }
        exit;
    }

    //FUNCION PARA REPORTE KARDEX 
    public function getKardex()
    {
        header('Content-Type: application/json; charset=UTF-8');
        try {
            // Como el JS usa Http.get(), los filtros viajan en la URL y caen en $_GET.
            // Usamos $_REQUEST que captura datos venga por donde venga (GET o POST).
            $filtros = $_REQUEST;

            // Respaldo por si en algún momento cambias el frontend a JSON crudo
            if (empty($filtros)) {
                $filtros = json_decode(file_get_contents("php://input"), true) ?? [];
            }

            // Validación básica
            if (empty($filtros['fechaInicio']) || empty($filtros['fechaFin'])) {
                throw new Exception("El rango de fechas es obligatorio.");
            }

            $kardexData = $this->service->generarKardex($filtros);

            echo json_encode([
                "success" => true,
                "data" => $kardexData
            ], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);

        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode([
                "success" => false,
                "message" => "Error al generar el Kardex: " . $e->getMessage()
            ], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
        }
        exit;
    }
 
    public function exportarPdf()
    {
        // 1. Recibimos todos los filtros del POST enviado desde JS
        $filtros = $_POST;

        // 2. Ejecutamos la consulta en el service usando los mismos filtros
        $resultados = $this->service->generarKardex($filtros);

        // 3. Incluimos e instanciamos la clase PDF que acabamos de crear
        require_once __DIR__ . '/../reports/ReporteKardexPDF.php'; // Asegúrate que la ruta sea correcta
        $pdf = new ReporteKardexPdf();

        // 4. Generamos y enviamos el PDF al navegador
        $pdf->exportarPDF($filtros, $resultados);
        exit;
    }
}
