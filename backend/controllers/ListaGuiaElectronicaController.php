<?php
require_once __DIR__ . '/../services/ListaGuiaElectronicaService.php';

class ListaGuiaElectronicaController
{
    private $service;

    public function __construct($db)
    {
        $this->service = new ListaGuiaElectronicaService($db);
    }

    /**
     * Retorna el listado de guías de remisión electrónicas con filtros dinámicos
     */
    public function getListado()
    {
        header('Content-Type: application/json; charset=UTF-8');
        try {
            // Capturar parámetros $_GET
            $search = $_GET['search'] ?? $_GET['q'] ?? null;
            if (is_array($search)) {
                $search = $search['value'] ?? null;
            }
            $almacen = $_GET['almacen'] ?? $_GET['talm'] ?? null;
            $desde = $_GET['desde'] ?? $_GET['fecini'] ?? null;
            $hasta = $_GET['hasta'] ?? $_GET['fecfin'] ?? null;
            $serie = $_GET['serie'] ?? null;
            $numero = $_GET['numero'] ?? null;

            $draw = isset($_GET['draw']) ? (int)$_GET['draw'] : null;
            $start = isset($_GET['start']) ? (int)$_GET['start'] : null;
            $length = isset($_GET['length']) ? (int)$_GET['length'] : null;

            // Obtener listado paginado
            $result = $this->service->listarGuias($search, $almacen, $desde, $hasta, $serie, $numero, $start, $length);

            // Si es una petición de DataTable Server-Side (tiene parámetro draw)
            if ($draw !== null) {
                echo json_encode([
                    'draw' => $draw,
                    'recordsTotal' => $result['recordsTotal'],
                    'recordsFiltered' => $result['recordsFiltered'],
                    'data' => $result['rows']
                ], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
            } else {
                echo json_encode([
                    'success' => true,
                    'data' => [
                        'rows' => $result['rows'],
                        'recordsTotal' => $result['recordsTotal'],
                        'recordsFiltered' => $result['recordsFiltered']
                    ]
                ], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
            }
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'message' => 'Error al obtener el listado de guías: ' . $e->getMessage()
            ], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
        }
        exit;
    }

    /**
     * Retorna el detalle de ítems de una guía de remisión específica
     */
    public function getDetalle()
    {
        header('Content-Type: application/json; charset=UTF-8');
        try {
            $treg = $_GET['treg'] ?? null;

            if (empty($treg)) {
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'message' => 'El registro de la guía es obligatorio.'
                ], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
                exit;
            }

            $cabecera = $this->service->obtenerGuiaPorTreg($treg);
            $detalle = $this->service->obtenerDetalleGuia($treg);

            echo json_encode([
                'success' => true,
                'data' => [
                    'cabecera' => $cabecera,
                    'detalle' => $detalle
                ]
            ], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'message' => 'Error al obtener el detalle de la guía: ' . $e->getMessage()
            ], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
        }
        exit;
    }

    /**
     * Elimina una guía de remisión electrónica
     */
    public function eliminarGuia()
    {
        header('Content-Type: application/json; charset=UTF-8');
        try {
            $treg = $_GET['treg'] ?? null;

            if (empty($treg)) {
                $input = json_decode(file_get_contents('php://input'), true);
                $treg = $input['treg'] ?? null;
            }

            if (empty($treg)) {
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'message' => 'El registro de la guía (treg) es obligatorio.'
                ], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
                exit;
            }

            $resultado = $this->service->eliminarGuia($treg);

            echo json_encode([
                'success' => $resultado,
                'message' => 'Guía de remisión eliminada correctamente.'
            ], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'message' => 'Error al eliminar la guía: ' . $e->getMessage()
            ], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
        }
        exit;
    }

    /**
     * Genera y descarga el reporte PDF de la guía de remisión electrónica
     */
    public function descargarPDF()
    {
        try {
            $treg = $_GET['treg'] ?? null;

            if (empty($treg)) {
                http_response_code(400);
                header('Content-Type: application/json; charset=UTF-8');
                echo json_encode([
                    'success' => false,
                    'message' => 'El registro de la guía (treg) es obligatorio.'
                ], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
                exit;
            }

            // 1. Obtener los datos de la cabecera
            $cabecera = $this->service->obtenerGuiaPorTreg($treg);
            if (!$cabecera) {
                http_response_code(404);
                header('Content-Type: application/json; charset=UTF-8');
                echo json_encode([
                    'success' => false,
                    'message' => 'No se encontró la cabecera de la guía de remisión.'
                ], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
                exit;
            }

            // 2. Obtener el detalle
            $detalle = $this->service->obtenerDetalleGuia($treg);

            // Formatear fechas para el reporte FPDF
            $cabecera['fecha_emision'] = $this->_formatFechaReporte($cabecera['fecha_emision']);
            $cabecera['fecha_traslado'] = $this->_formatFechaReporte($cabecera['fecha_traslado']);

            // 3. Requerir e instanciar la clase del reporte
            require_once __DIR__ . '/../reports/ReporteGuiaElectronicaPDF.php';

            $pdf = new PDFGuia('P', 'mm', 'A4');
            $pdf->AddPage();

            // --- NUEVO CÓDIGO CON PAGINACIÓN ---
            $limite_filas = 21; // El límite exacto para no salirnos de la caja de 115mm
            $paginas_detalle = array_chunk($detalle, $limite_filas);

            foreach ($paginas_detalle as $indice => $grupo_items) {

                // Si ya pasamos la primera página, agregamos una hoja nueva
                if ($indice > 0) {
                    $pdf->AddPage();
                }

                // 1. Volvemos a dibujar TODA la estructura superior para esta nueva hoja
                $pdf->HeaderCustom($cabecera);
                $pdf->GenerarCabecerasGenerales($cabecera);
                $pdf->TablaDetalleCabecera();

                // 2. Imprimimos SOLO los items que caben en esta página (máximo 21)
                foreach ($grupo_items as $item) {
                    $pdf->RowDetalle($item);
                }

                // 3. Imprimimos el footer (Observaciones, textos legales y QR) para cerrar la hoja
                $pdf->GenerarFooter($cabecera);
            }

            // Generar salida y descargar
            $pdf->Output('I', "Guia_{$cabecera['serie']}_{$cabecera['numero']}.pdf");
            exit;
        } catch (Exception $e) {
            http_response_code(500);
            header('Content-Type: application/json; charset=UTF-8');
            echo json_encode([
                'success' => false,
                'message' => 'Error al generar el PDF: ' . $e->getMessage()
            ], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
            exit;
        }
    }

    /**
     * Descarga y sirve un PDF externo (evitando la restricción X-Frame-Options: sameorigin)
     */
    public function proxyPDFExterno()
    {
        try {
            $url = $_GET['url'] ?? null;
            if (empty($url) || !filter_var($url, FILTER_VALIDATE_URL)) {
                http_response_code(400);
                header('Content-Type: application/json; charset=UTF-8');
                echo json_encode([
                    'success' => false,
                    'message' => 'La URL del PDF externo es requerida e inválida.'
                ], JSON_UNESCAPED_UNICODE);
                exit;
            }

            // Permite nubefact.com, sunat.gob.pe o cualquier dominio que maneje los CPEs
            if (stripos($url, 'nubefact.com') === false && stripos($url, 'sunat.gob.pe') === false) {
                http_response_code(403);
                header('Content-Type: application/json; charset=UTF-8');
                echo json_encode([
                    'success' => false,
                    'message' => 'No está permitido acceder a este dominio externo.'
                ], JSON_UNESCAPED_UNICODE);
                exit;
            }

            // Descargar el PDF usando stream context y fallback de cURL
            $opts = [
                'http' => [
                    'method' => "GET",
                    'header' => "User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36\r\n"
                ]
            ];
            $context = stream_context_create($opts);
            $pdfContent = @file_get_contents($url, false, $context);

            if ($pdfContent === false) {
                if (function_exists('curl_init')) {
                    $ch = curl_init();
                    curl_setopt($ch, CURLOPT_URL, $url);
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
                    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)');
                    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                    $pdfContent = curl_exec($ch);
                    curl_close($ch);
                }
            }

            if ($pdfContent === false || empty($pdfContent)) {
                throw new Exception("No se pudo descargar el archivo PDF externo.");
            }

            // Responder con el PDF
            header('Content-Type: application/pdf');
            header('Content-Disposition: inline; filename="guia_remision_sunat.pdf"');
            echo $pdfContent;
            exit;
        } catch (Exception $e) {
            http_response_code(500);
            header('Content-Type: application/json; charset=UTF-8');
            echo json_encode([
                'success' => false,
                'message' => 'Error al proxyar el PDF: ' . $e->getMessage()
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }
    }

    /**
     * Utilidad para formatear fechas en el PDF
     */
    private function _formatFechaReporte($fecha)
    {
        $val = trim((string)$fecha);
        if ($val === '') return '-';

        $datePart = explode(' ', $val)[0];
        $parts = explode('-', $datePart);
        if (count($parts) === 3) {
            return "{$parts[2]}/{$parts[1]}/{$parts[0]}";
        }
        return $val;
    }
}
