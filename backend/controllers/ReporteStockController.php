<?php
require_once __DIR__ . '/../services/ReporteStockService.php';
class ReporteStockController
{
    private $service;

    public function __construct($db)
    {
        $this->service = new ReporteStockService($db);
    }


    //FUNCIONES PARA FILTROS DE REPORTE STOCK
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

    //FUNCION PARA REPORTE STOCK    
    public function procesarReporte()
    {
        header('Content-Type: application/json; charset=UTF-8');

        try {
            // Capturar todos los filtros de FoxPro adaptados a la Web
            $filtros = [
                'fechaInicio'    => $_GET['fechaInicio'] ?? date('Y-m-d'),
                'fechaFin'       => $_GET['fechaFin'] ?? date('Y-m-d'),
                'zona'           => $_GET['zona'] ?? '', // Vacío = TODAS
                'lineasValores'  => $_GET['lineasValores'] ?? '',
                'codigosValores' => $_GET['codigosValores'] ?? '',
                'formato'        => $_GET['formato'] ?? 'UNIDADES', // UNIDADES, VALOR, RESUMEN, PESO
                'quiebre'        => $_GET['quiebre'] ?? 'ALMACEN'   // ALMACEN, CUENTA, LINEA
            ];

            // Delegar el proceso matemático al servicio
            $resultados = $this->service->procesarReporteGrid($filtros);

            echo json_encode([
                "success" => true,
                "data"    => $resultados,
                "filtros" => $filtros // Retornamos los filtros por si el frontend los necesita para pintar cabeceras
            ], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode([
                "success" => false,
                "message" => "Error al generar el reporte de stock: " . $e->getMessage()
            ], JSON_UNESCAPED_UNICODE);
        }
        exit;
    }

    public function exportarPdf()
    {
        // 1. Recibimos todos los filtros del POST enviado desde JS
        $filtros = $_POST;

        // 2. Ejecutamos la consulta en el service usando los mismos filtros
        $resultados = $this->service->procesarReporteGrid($filtros);

        // 3. Incluimos e instanciamos la clase PDF que acabamos de crear
        require_once __DIR__ . '/../reports/ReporteStockPdf.php'; // Asegúrate que la ruta sea correcta
        $pdf = new ReporteStockPdf();

        // 4. Generamos y enviamos el PDF al navegador
        $pdf->exportarPDF($filtros, $resultados);
        exit;
    }
}
