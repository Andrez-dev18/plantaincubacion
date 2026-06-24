<?php

require_once __DIR__ . '/../services/ReporteTransaccionService.php';

class ReporteTransaccionController
{
    private $service;

    public function __construct($db)
    {
        $this->service = new ReporteTransaccionService($db);
    }

    //FILTROS
    public function getTransacciones()
    {
        header('Content-Type: application/json');
        try {
            $transacciones = $this->service->listarTransacciones();

            echo json_encode([
                "success" => true,
                "data" => $transacciones
            ]);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode([
                "success" => false,
                "message" => "Error al obtener las transacciones: " . $e->getMessage()
            ]);
        }
        exit;
    }

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

    public function getCencos()
    {
        header('Content-Type: application/json; charset=UTF-8');
        try {
            $cencos = $this->service->listarCencos();

            echo json_encode([
                "success" => true,
                "data" => $cencos
            ], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode([
                "success" => false,
                "message" => "Error al obtener los centros de costo: " . $e->getMessage()
            ], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
        }
        exit;
    }

    public function getCuentasCorrientes()
    {
        header('Content-Type: application/json; charset=UTF-8');
        try {
            $cuentas = $this->service->listarCuentasCorrientes();

            echo json_encode([
                "success" => true,
                "data" => $cuentas
            ], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode([
                "success" => false,
                "message" => "Error al obtener las cuentas corrientes: " . $e->getMessage()
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

    //END FILTROS

    //REPORTE
    public function procesarReporte()
    {
        header('Content-Type: application/json; charset=UTF-8');
        try {
            // Recolectar filtros enviados desde JS
            $filtros = [
                'fechaInicio' => $_GET['fechaInicio'] ?? '',
                'fechaFin'    => $_GET['fechaFin'] ?? '',
                'transaccion' => $_GET['transaccion'] ?? '',
                'zona'        => $_GET['zona'] ?? '',

                'cencos'          => $_GET['cencos'] ?? '',
                'cuentaCorriente' => $_GET['cuentaCorriente'] ?? '',
                'lineasValores'   => $_GET['lineasValores'] ?? '',
                'codigosValores'  => $_GET['codigosValores'] ?? '',
                'agruparPor'      => $_GET['agruparPor'] ?? 'FECHA'
            ];

            $resultados = $this->service->generarReporteGrid($filtros);

            echo json_encode([
                "success" => true,
                "data" => $resultados
            ], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode([
                "success" => false,
                "message" => "Error al generar el reporte: " . $e->getMessage()
            ], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
        }
        exit;
    }

    public function exportarPdf()
    {
        // No necesitamos setear headers JSON aquí porque FPDF se encarga de enviar los headers de PDF
        try {
            // Importamos la librería FPDF (Ajusta la ruta a tu archivo si es necesario)
            require_once __DIR__ . '/../reports/ReporteTransaccionesPdf.php';

            // Recolectamos los filtros que llegan por POST desde el formulario oculto de JS
            $filtros = [
                'fechaInicio'     => $_POST['fechaInicio'] ?? '',
                'fechaFin'        => $_POST['fechaFin'] ?? '',
                'transaccion'     => $_POST['transaccion'] ?? '',
                'zona'            => $_POST['zona'] ?? '',
                'cencos'          => $_POST['cencos'] ?? '',
                'cuentaCorriente' => $_POST['cuentaCorriente'] ?? '',
                'lineasValores'   => $_POST['lineasValores'] ?? '',
                'codigosValores'  => $_POST['codigosValores'] ?? '',
                'agruparPor'      => $_POST['agruparPor'] ?? 'FECHA',
                'transaccionNombre' => $_POST['transaccionNombre'] ?? '',
            ];

            // Obtenemos la data de forma segura usando la capa de servicio interna
            $resultados = $this->service->generarReporteGrid($filtros);

            // Instanciamos y generamos el PDF
            $pdf = new ReporteTransaccionesPdf();
            $pdf->exportarPDF($filtros, $resultados);

        } catch (Exception $e) {
            // Si algo falla aquí, sí devolvemos un error en formato JSON
            header('Content-Type: application/json; charset=UTF-8');
            http_response_code(500);
            echo json_encode([
                "success" => false,
                "message" => "Error al generar el PDF del reporte: " . $e->getMessage()
            ], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
        }
        exit;
    }
}
