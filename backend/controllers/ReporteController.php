<?php

require_once __DIR__ . '/../services/ReporteService.php';
require_once __DIR__ . '/../libraries/ExcelGenerator.php';

class ReporteController {
    private $service;

    public function __construct($db) {
        $this->service = new ReporteService($db);
    }

    /**
     * GET /api/reporte/proyecciones
     * Lista todas las proyecciones disponibles
     */
    public function listarProyecciones() {
        try {
            $proyecciones = $this->service->listarProyecciones();
            
            $this->jsonResponse([
                'success' => true,
                'data' => $proyecciones
            ]);
        } catch (Exception $e) {
            $this->jsonResponse([
                'success' => false,
                'message' => 'Error al obtener proyecciones: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * GET /api/reporte/datos?proyeccion=XXXX
     * Obtiene datos de ccoscargapollo para tabla detalle
     */
    public function obtenerDatos() {
        try {
            $proyeccion = $_GET['proyeccion'] ?? null;
            
            if (!$proyeccion) {
                $this->jsonResponse([
                    'success' => false,
                    'message' => 'Parámetro proyeccion es requerido'
                ], 400);
                return;
            }

            if (!$this->service->validarProyeccion($proyeccion)) {
                $this->jsonResponse([
                    'success' => false,
                    'message' => 'Proyección no encontrada'
                ], 404);
                return;
            }

            // Obtener datos directos de ccoscargapollo en formato para tabla
            $datos = $this->service->obtenerDatosTablaDetalle($proyeccion);
            
            $this->jsonResponse([
                'success' => true,
                'data' => $datos
            ]);
        } catch (Exception $e) {
            $this->jsonResponse([
                'success' => false,
                'message' => 'Error al obtener datos: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * GET /api/reporte/resumen?proyeccion=XXXX
     * Obtiene resumen de oferta/demanda
     */
    public function obtenerResumen() {
        try {
            $proyeccion = $_GET['proyeccion'] ?? null;
            
            if (!$proyeccion) {
                $this->jsonResponse([
                    'success' => false,
                    'message' => 'Parámetro proyeccion es requerido'
                ], 400);
                return;
            }

            $resumen = $this->service->generarResumenOfertaDemanda($proyeccion);
            
            $this->jsonResponse([
                'success' => true,
                'data' => $resumen
            ]);
        } catch (Exception $e) {
            $this->jsonResponse([
                'success' => false,
                'message' => 'Error al obtener resumen: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * GET /api/reporte/calendario-paginated?proyeccion=XXXX&draw=1&start=0&length=25
     * Server-side processing para DataTables (tabla calendario)
     */
    public function obtenerCalendarioPaginado() {
        try {
            $proyeccion = $_GET['proyeccion'] ?? null;
            $draw = intval($_GET['draw'] ?? 1);
            $start = intval($_GET['start'] ?? 0);
            $length = intval($_GET['length'] ?? 25);
            $searchValue = $_GET['search']['value'] ?? '';
            $orderColumnIndex = intval($_GET['order'][0]['column'] ?? 0);
            $orderDir = $_GET['order'][0]['dir'] ?? 'asc';
            
            if (!$proyeccion) {
                $this->jsonResponse([
                    'success' => false,
                    'message' => 'Parámetro proyeccion es requerido'
                ], 400);
                return;
            }

            // Mapear índice de columna a nombre de campo
            $columns = ['semana', 'fecaqp', 'codigo', 'nombre', 'galpon', 'pollos'];
            $orderColumn = $columns[$orderColumnIndex] ?? 'fecaqp';

            $result = $this->service->obtenerCalendarioPaginado(
                $proyeccion, 
                $start, 
                $length, 
                $searchValue, 
                $orderColumn, 
                $orderDir
            );

            $this->jsonResponse([
                'draw' => $draw,
                'recordsTotal' => $result['total'],
                'recordsFiltered' => $result['filtered'],
                'data' => $result['data']
            ]);
        } catch (Exception $e) {
            $this->jsonResponse([
                'success' => false,
                'message' => 'Error al obtener calendario: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * GET /api/reporte/resumen-paginated?proyeccion=XXXX&draw=1&start=0&length=25
     * Server-side processing para DataTables (tabla resumen)
     */
    public function obtenerResumenPaginado() {
        try {
            $proyeccion = $_GET['proyeccion'] ?? null;
            $draw = intval($_GET['draw'] ?? 1);
            $start = intval($_GET['start'] ?? 0);
            $length = intval($_GET['length'] ?? 25);
            $searchValue = $_GET['search']['value'] ?? '';
            $orderColumnIndex = intval($_GET['order'][0]['column'] ?? 0);
            $orderDir = $_GET['order'][0]['dir'] ?? 'asc';
            
            if (!$proyeccion) {
                $this->jsonResponse([
                    'success' => false,
                    'message' => 'Parámetro proyeccion es requerido'
                ], 400);
                return;
            }

            // Mapear índice de columna a nombre de campo
            $columns = ['anuo', 'sem', 'oferta', 'demanda', 'diferencia'];
            $orderColumn = $columns[$orderColumnIndex] ?? 'anuo';

            $result = $this->service->obtenerResumenPaginado(
                $proyeccion, 
                $start, 
                $length, 
                $searchValue, 
                $orderColumn, 
                $orderDir
            );

            $this->jsonResponse([
                'draw' => $draw,
                'recordsTotal' => $result['total'],
                'recordsFiltered' => $result['filtered'],
                'data' => $result['data']
            ]);
        } catch (Exception $e) {
            $this->jsonResponse([
                'success' => false,
                'message' => 'Error al obtener resumen: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * GET /api/reporte/excel?proyeccion=XXXX
     * Genera y descarga archivo Excel
     */
    public function generarExcel() {
        try {
            $proyeccion = $_GET['proyeccion'] ?? null;
            
            if (!$proyeccion) {
                $this->jsonResponse([
                    'success' => false,
                    'message' => 'Parámetro proyeccion es requerido'
                ], 400);
                return;
            }

            if (!$this->service->validarProyeccion($proyeccion)) {
                $this->jsonResponse([
                    'success' => false,
                    'message' => 'Proyección no encontrada'
                ], 404);
                return;
            }

            // Generar datos
            $datos = $this->service->generarDatosPivotados($proyeccion);
            $estadisticas = $this->service->calcularEstadisticas($proyeccion);
            
            // Generar Excel (descarga directa)
            $excelGenerator = new ExcelGenerator();
            $excelGenerator->generarReporteCargaPollo($datos, $proyeccion, $estadisticas);
            
        } catch (Exception $e) {
            $this->jsonResponse([
                'success' => false,
                'message' => 'Error al generar Excel: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * GET /api/cargapollo/pdf?proyeccion=XXXX
     * Genera y descarga archivo PDF con datos agrupados por semana
     */
    public function generarPDF() {
        try {
            $proyeccion = $_GET['proyeccion'] ?? null;
            
            if (!$proyeccion) {
                $this->jsonResponse([
                    'success' => false,
                    'message' => 'Parámetro proyeccion es requerido'
                ], 400);
                return;
            }

            if (!$this->service->validarProyeccion($proyeccion)) {
                $this->jsonResponse([
                    'success' => false,
                    'message' => 'Proyección no encontrada'
                ], 404);
                return;
            }

            // Generar datos agrupados por semana
            $datosPorSemana = $this->service->obtenerDatosPorSemanaParaPDF($proyeccion);
            
            // Generar PDF
            require_once __DIR__ . '/../libraries/PDFExporter.php';
          
            $pdf = new PDFExporter('L', 'mm', 'A4');
            $pdf->setReportTitle('REPORTE DE PROYECCION DE CARGA DE POLLOS');
            $pdf->setProyeccionInfo($proyeccion, date('Y'));
            
            // Configurar columnas para que se repitan en cada página
            $widths = [14, 24, 18, 20, 18, 21, 63, 18, 24, 25, 25, 11];
            $headers = ['Semana', 'Pollo X Sem', 'Dia Salida', 'Solc.Lima SF', 'Dia Llegada', 'Llegada Granja', 'Granja', 'Galpon', 'N° Pollos', 'Edad Liquidacion', 'Fec Liquidacion', 'Fila'];
            $pdf->setTableColumns($headers, $widths);
            
            $pdf->AddPage();
            
            // Configurar estilo de líneas punteadas para los datos
            $pdf->SetLineWidth(0.1);  // Líneas muy delgadas
            $pdf->SetDash(1, 1);      // Patrón punteado: 1mm línea, 1mm espacio
            $pdf->SetDrawColor(0, 0, 0);  // Color negro para las líneas
            
            // Obtener anchos de columna
            $widths = $pdf->getColumnWidths();
            
            // Datos agrupados por semana
            $pdf->SetFont('Arial', '', 8);
            $currentSemana = null;
            $currentPeriodo = null;
            $totalPollosSemana = 0;
            $filasSemana = 0;
            
            foreach ($datosPorSemana as $row) {
                $periodoSemana = $row['anio'] . '-' . $row['semana'];

                // Si cambia la semana, mostrar subtotal anterior
                if ($currentPeriodo !== null && $currentPeriodo !== $periodoSemana) {
                    // Verificar espacio para el subtotal
                    $margenInferior = 15;
                    $alturaPagina = $pdf->GetPageHeight();
                    $posicionActual = $pdf->GetY();
                    $espacioRestante = $alturaPagina - $margenInferior - $posicionActual;
                    
                    if ($espacioRestante < 20) {
                        $pdf->AddPage();
                        // Restaurar configuración después de nueva página
                        $pdf->SetLineWidth(0.1);
                        $pdf->SetDash(1, 1);
                        $pdf->SetDrawColor(0, 0, 0);
                    }
                    
                    // Subtotal de la semana anterior en la columna N° Pollos
                    $pdf->SetFont('Arial', 'B', 9);
                    $pdf->SetFillColor(173, 216, 230);  // Color azul claro
                    
                    $pdf->SetX(8);
                    // Celdas vacías hasta la columna de N° Pollos
                    for ($i = 0; $i < 8; $i++) {
                        $pdf->Cell($widths[$i], 7, '', 1, 0, 'C', true);
                    }
                    // Total en la columna N° Pollos
                    $pdf->Cell($widths[8], 7, number_format($totalPollosSemana, 2, '.', ','), 1, 0, 'R', true);
                    // Celdas vacías restantes
                    for ($i = 9; $i < 11; $i++) {
                        $pdf->Cell($widths[$i], 7, '', 1, 0, 'C', true);
                    }
                    // Total de filas en la última columna
                    $pdf->Cell($widths[11], 7, $filasSemana, 1, 0, 'C', true);
                    $pdf->Ln();
                    
                    // Restaurar estilo de líneas punteadas después del subtotal
                    $pdf->SetLineWidth(0.1);
                    $pdf->SetDash(1, 1);
                    $pdf->SetDrawColor(0, 0, 0);
                    
                    $pdf->Ln(4);  // Espacio entre semanas
                    $totalPollosSemana = 0;
                    $filasSemana = 0;
                    $pdf->SetFont('Arial', '', 8);
                }
                
                // Encabezado de nueva semana
                if ($currentPeriodo !== $periodoSemana) {
                    // Verificar si hay suficiente espacio para la nueva semana
                    // Si quedan menos de 40mm hasta el margen inferior, crear nueva página
                    $margenInferior = 15;  // Margen inferior del PDF
                    $alturaPagina = $pdf->GetPageHeight();
                    $posicionActual = $pdf->GetY();
                    $espacioRestante = $alturaPagina - $margenInferior - $posicionActual;
                    
                    if ($espacioRestante < 40) {
                        $pdf->AddPage();
                    }
                    
                    // Siempre restaurar configuración de líneas punteadas antes de nueva semana
                    $pdf->SetLineWidth(0.1);
                    $pdf->SetDash(1, 1);
                    $pdf->SetDrawColor(0, 0, 0);  // Color negro para las líneas
                    
                    $currentSemana = $row['semana'];
                    $currentPeriodo = $periodoSemana;
                    $pdf->SetFont('Arial', 'B', 9);
                    $pdf->SetFillColor(255, 255, 255);
                    $pdf->SetX(8);
                    $pdf->Cell(0, 7, 'Semana: ' . $row['semana'] . ' (' . $row['anio'] . ')', 0, 1, 'L');
                    $pdf->Ln(1);
                    $pdf->SetFont('Arial', '', 8);
                }
                
                // Calcular días de la semana
                $diaSalida = $this->obtenerDiaSemana($row['diasalida']);
                $diaLlegadaGranja = $this->obtenerDiaSemana($row['diallegada']);
                $diaLiquidacion = $this->obtenerDiaSemana($row['fecliquidacion']);
                
                // Calcular edad de liquidación (días entre llegada y liquidación)
                $edadLiquidacion = '';
                if (!empty($row['diallegada']) && !empty($row['fecliquidacion'])) {
                    $fechaLlegada = new DateTime($row['diallegada']);
                    $fechaLiqui = new DateTime($row['fecliquidacion']);
                    $diff = $fechaLlegada->diff($fechaLiqui);
                    $edadLiquidacion = $diff->days;
                }
                
                // Verificar espacio antes de cada fila
                // Si quedan menos de 25mm (espacio para 2-3 filas), crear nueva página
                $margenInferior = 15;
                $alturaPagina = $pdf->GetPageHeight();
                $posicionActual = $pdf->GetY();
                $espacioRestante = $alturaPagina - $margenInferior - $posicionActual;
                
                if ($espacioRestante < 25) {
                    $pdf->AddPage();
                    // Restaurar configuración después de nueva página
                    $pdf->SetLineWidth(0.1);
                    $pdf->SetDash(1, 1);
                    $pdf->SetDrawColor(0, 0, 0);
                    $pdf->SetFont('Arial', '', 8);
                }
                
                // Fila de datos con más altura
                $pdf->SetFillColor(255, 255, 255);
                $pdf->SetX(8);
                $pdf->Cell($widths[0], 6, $row['semana'], 1, 0, 'C', true);
                $pdf->Cell($widths[1], 6, number_format($row['pollossem'], 0, ',', '.'), 1, 0, 'R', true);
                $pdf->Cell($widths[2], 6, $diaSalida, 1, 0, 'C', true);
                $pdf->Cell($widths[3], 6, $this->formatearFechaCorta($row['diasalida']), 1, 0, 'C', true);
                $pdf->Cell($widths[4], 6, $diaLlegadaGranja, 1, 0, 'C', true);
                $pdf->Cell($widths[5], 6, $this->formatearFechaCorta($row['diallegada']), 1, 0, 'C', true);
                $pdf->Cell($widths[6], 6, $pdf->encodeText('GJA. ' . $row['granja']), 1, 0, 'L', true);
                $pdf->Cell($widths[7], 6, $row['galpon'], 1, 0, 'C', true);
                $pdf->Cell($widths[8], 6, number_format($row['pollos'], 0, ',', '.'), 1, 0, 'R', true);
                $pdf->Cell($widths[9], 6, $edadLiquidacion, 1, 0, 'C', true);
                $pdf->Cell($widths[10], 6, $this->formatearFechaCorta($row['fecliquidacion']), 1, 0, 'C', true);
                
                // Incrementar y mostrar número de fila
                $filasSemana++;
                $pdf->Cell($widths[11], 6, $filasSemana, 1, 1, 'C', true);
                
                // Sumar los pollos individuales
                $totalPollosSemana += $row['pollos'];
            }
            
            // Último subtotal
            if ($currentSemana !== null) {
                // Verificar espacio para el subtotal
                $margenInferior = 15;
                $alturaPagina = $pdf->GetPageHeight();
                $posicionActual = $pdf->GetY();
                $espacioRestante = $alturaPagina - $margenInferior - $posicionActual;
                
                if ($espacioRestante < 20) {
                    $pdf->AddPage();
                    // Restaurar configuración después de nueva página
                    $pdf->SetLineWidth(0.1);
                    $pdf->SetDash(1, 1);
                    $pdf->SetDrawColor(0, 0, 0);
                }
                
                $pdf->SetFont('Arial', 'B', 9);
                $pdf->SetFillColor(173, 216, 230);  // Color azul claro
                
                $pdf->SetX(8);
                // Celdas vacías hasta la columna de N° Pollos
                for ($i = 0; $i < 8; $i++) {
                    $pdf->Cell($widths[$i], 7, '', 1, 0, 'C', true);
                }
                // Total en la columna N° Pollos
                $pdf->Cell($widths[8], 7, number_format($totalPollosSemana, 2, '.', ','), 1, 0, 'R', true);
                // Celdas vacías restantes
                for ($i = 9; $i < 11; $i++) {
                    $pdf->Cell($widths[$i], 7, '', 1, 0, 'C', true);
                }
                // Total de filas en la última columna
                $pdf->Cell($widths[11], 7, $filasSemana, 1, 0, 'C', true);
                $pdf->Ln();
                
                // Restaurar estilo de líneas punteadas después del último subtotal
                $pdf->SetLineWidth(0.1);
                $pdf->SetDash(1, 1);
                $pdf->SetDrawColor(0, 0, 0);
            }
            
            // Restaurar estilo de línea sólida
            $pdf->SetDash();  // Sin parámetros = línea sólida
            $pdf->SetLineWidth(0.2);  // Grosor normal
            
            // Determinar modo de salida según parámetro download
            $download = isset($_GET['download']) && $_GET['download'] == '1';
            $modo = $download ? 'D' : 'I'; // D=Descarga, I=Inline en navegador
            
            // Mostrar PDF en el navegador o forzar descarga
            $filename = 'Carga_Pollos_' . preg_replace('/[^a-zA-Z0-9]/', '_', $proyeccion) . '_' . date('Ymd_His') . '.pdf';
            $pdf->Output($modo, $filename);

            
        } catch (Exception $e) {
            $this->jsonResponse([
                'success' => false,
                'message' => 'Error al generar PDF: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * POST /api/reporte/excel-custom
     * Genera Excel con configuración personalizada (NO USADO - RESERVADO)
     */
    public function generarExcelPersonalizado() {
        try {
            $input = json_decode(file_get_contents('php://input'), true);
            
            $proyeccion = $input['proyeccion'] ?? null;
            
            if (!$proyeccion) {
                $this->jsonResponse([
                    'success' => false,
                    'message' => 'Parámetro proyeccion es requerido'
                ], 400);
                return;
            }

            // Por ahora usar el mismo método que generarExcel
            $datos = $this->service->generarDatosPivotados($proyeccion);
            $estadisticas = $this->service->calcularEstadisticas($proyeccion);
            
            $excelGenerator = new ExcelGenerator();
            $excelGenerator->generarReporteCargaPollo($datos, $proyeccion, $estadisticas);
            
        } catch (Exception $e) {
            $this->jsonResponse([
                'success' => false,
                'message' => 'Error al generar Excel: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Envía respuesta JSON
     */
    private function jsonResponse($data, $status = 200) {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        exit;
    }

    /**
     * Formatea fecha para mostrar en PDF
     */
    private function formatearFecha($fecha) {
        if (empty($fecha) || $fecha === '0000-00-00') {
            return '';
        }
        $timestamp = strtotime($fecha);
        return $timestamp ? date('d/m/Y', $timestamp) : '';
    }

    /**
     * Formatea fecha corta (dd-mmm-YY)
     */
    private function formatearFechaCorta($fecha) {
        if (empty($fecha) || $fecha === '0000-00-00') {
            return '';
        }
        $meses = ['', 'Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun', 'Jul', 'Ago', 'Sep', 'Oct', 'Nov', 'Dic'];
        $timestamp = strtotime($fecha);
        if (!$timestamp) return '';
        $dia = date('d', $timestamp);
        $mes = $meses[(int)date('n', $timestamp)];
        $anio = date('-y', $timestamp);
        return $dia . '-' . $mes . $anio;
    }

    /**
     * Obtiene día de la semana en español
     */
    private function obtenerDiaSemana($fecha) {
        if (empty($fecha) || $fecha === '0000-00-00') {
            return '';
        }
        $dias = ['domingo', 'lunes', 'martes', 'miercoles', 'jueves', 'viernes', 'sabado'];
        $timestamp = strtotime($fecha);
        if (!$timestamp) return '';
        $numeroDia = (int)date('w', $timestamp);
        return $dias[$numeroDia];
    }

    /**
     * GET /api/reporte/fechaproy?proyeccion=XXXX
     * Obtiene datos de fechaproy (calendario de fechas y cargas)
     */
    public function obtenerFechaProy() {
        try {
            $proyeccion = $_GET['proyeccion'] ?? null;
            
            if (!$proyeccion) {
                $this->jsonResponse([
                    'success' => false,
                    'message' => 'Parámetro proyeccion es requerido'
                ], 400);
                return;
            }

            $datos = $this->service->obtenerFechaProy($proyeccion);
            
            $this->jsonResponse([
                'success' => true,
                'data' => $datos
            ]);
        } catch (Exception $e) {
            $this->jsonResponse([
                'success' => false,
                'message' => 'Error al obtener fechaproy: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * PUT /api/reporte/fechaproy/cargas
     * Actualiza cargas de una fecha específica
     */
    public function actualizarCargas() {
        try {
            $input = json_decode(file_get_contents('php://input'), true);
            
            $proyeccion = $input['proyeccion'] ?? null;
            $fecha = $input['fecha'] ?? null;
            $cargas = $input['cargas'] ?? null;
            
            if (!$proyeccion || !$fecha || $cargas === null) {
                $this->jsonResponse([
                    'success' => false,
                    'message' => 'Parámetros proyeccion, fecha y cargas son requeridos'
                ], 400);
                return;
            }

            $resultado = $this->service->actualizarCargas($proyeccion, $fecha, $cargas);
            
            $this->jsonResponse([
                'success' => true,
                'message' => 'Cargas actualizado correctamente',
                'data' => $resultado
            ]);
        } catch (Exception $e) {
            $this->jsonResponse([
                'success' => false,
                'message' => 'Error al actualizar cargas: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * POST /api/reporte/recalcular
     * Recalcula el calendario completo basado en secuencia y cargas
     */
    public function recalcular() {
        try {
            $input = json_decode(file_get_contents('php://input'), true);
            
            $proyeccion = $input['proyeccion'] ?? null;
            $fechaDesde = $input['fechaDesde'] ?? null;
            $fechaHasta = $input['fechaHasta'] ?? null;
            
            if (!$proyeccion) {
                $this->jsonResponse([
                    'success' => false,
                    'message' => 'Parámetro proyeccion es requerido'
                ], 400);
                return;
            }

            $resultado = $this->service->recalcularCalendario($proyeccion, $fechaDesde, $fechaHasta);
            
            $this->jsonResponse([
                'success' => true,
                'message' => 'Calendario recalculado correctamente',
                'data' => $resultado
            ]);
        } catch (Exception $e) {
            $this->jsonResponse([
                'success' => false,
                'message' => 'Error al recalcular: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * POST /api/cargapollo/importar-calendario
     * Importa calendario completo (elimina el anterior y lo reemplaza)
     */
    public function importarCalendario() {
        try {
            $input = json_decode(file_get_contents('php://input'), true);
            
            $proyeccion = $input['proyeccion'] ?? null;
            $registros = $input['registros'] ?? [];
            
            if (!$proyeccion || empty($registros)) {
                $this->jsonResponse([
                    'success' => false,
                    'message' => 'Parámetros proyeccion y registros son requeridos'
                ], 400);
                return;
            }

            $resultado = $this->service->importarCalendario($proyeccion, $registros);
            
            $this->jsonResponse([
                'success' => true,
                'message' => 'Calendario importado correctamente',
                'data' => $resultado
            ]);
        } catch (Exception $e) {
            $this->jsonResponse([
                'success' => false,
                'message' => 'Error al importar calendario: ' . $e->getMessage()
            ], 500);
        }
    }
}
