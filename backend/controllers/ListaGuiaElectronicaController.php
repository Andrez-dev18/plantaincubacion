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
            $almacen = $_GET['almacen'] ?? $_GET['talm'] ?? null;
            $desde = $_GET['desde'] ?? $_GET['fecini'] ?? null;
            $hasta = $_GET['hasta'] ?? $_GET['fecfin'] ?? null;
            $serie = $_GET['serie'] ?? null;
            $numero = $_GET['numero'] ?? null;

            // Obtener listado
            $rows = $this->service->listarGuias($search, $almacen, $desde, $hasta, $serie, $numero);

            // Estructura de retorno compatible con el controlador frontend
            $data = [
                'rows' => $rows
            ];

            echo json_encode([
                'success' => true,
                'data' => $data
            ], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
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
