<?php
/**
 * SimulacionEscenariosController
 * 
 * Controlador para gestión de escenarios de simulación de carga
 * Maneja peticiones HTTP del módulo
 */

require_once __DIR__ . '/../services/SimulacionEscenariosService.php';

class SimulacionEscenariosController {
    
    private $service;

    public function __construct($db) {
        $this->service = new SimulacionEscenariosService($db);
    }

    // ─────────────────────────────────────────────
    // GET /api/simulacion-escenarios/listar?proyeccion=xxx
    // ─────────────────────────────────────────────
    public function listar() {
        try {
            $proyeccion = $_GET['proyeccion'] ?? '';
            
            if (empty($proyeccion)) {
                $this->jsonResponse([
                    'success' => false,
                    'message' => 'Parámetro proyeccion es requerido'
                ], 400);
                return;
            }

            $result = $this->service->listarEscenarios($proyeccion);
            
            $this->jsonResponse($result, $result['success'] ? 200 : 500);

        } catch (Exception $e) {
            error_log("Error en listar controller: " . $e->getMessage());
            error_log("Stack trace: " . $e->getTraceAsString());
            $this->jsonResponse([
                'success' => false,
                'message' => 'Error al listar escenarios: ' . $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ], 500);
        }
    }

    // ─────────────────────────────────────────────
    // POST /api/simulacion-escenarios/calcular
    // Body JSON: { id_escenario: int }
    // ─────────────────────────────────────────────
    public function calcular() {
        try {
            $body = $this->getBody();
            $id   = (int)($body['id_escenario'] ?? 0);

            if (!$id) {
                $this->jsonResponse([
                    'success' => false,
                    'message' => 'id_escenario es requerido'
                ], 400);
                return;
            }

            $usuario = $this->getUsuario();
            $result  = $this->service->calcularYGuardar($id, $usuario);

            $this->jsonResponse($result, $result['success'] ? 200 : 404);

        } catch (Exception $e) {
            $this->jsonResponse([
                'success' => false,
                'message' => 'Error al calcular escenario: ' . $e->getMessage()
            ], 500);
        }
    }

    // ─────────────────────────────────────────────
    // POST /api/simulacion-escenarios/guardar
    // Body JSON: escenario + zonas []
    // ─────────────────────────────────────────────
    public function guardar() {
        try {
            $body = $this->getBody();
            $usuario = $this->getUsuario();

            if (empty($body['proyeccion']) || empty($body['cod_escenario'])) {
                $this->jsonResponse([
                    'success' => false,
                    'message' => 'proyeccion y cod_escenario son requeridos'
                ], 400);
                return;
            }

            $result = $this->service->guardarEscenario($body, $usuario);

            $this->jsonResponse($result, $result['success'] ? 200 : 500);

        } catch (Exception $e) {
            $this->jsonResponse([
                'success' => false,
                'message' => 'Error al guardar escenario: ' . $e->getMessage()
            ], 500);
        }
    }

    // ─────────────────────────────────────────────
    // DELETE /api/simulacion-escenarios/eliminar/{id}
    // ─────────────────────────────────────────────
    public function eliminar() {
        try {
            $uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
            $segmentos = explode('/', trim($uri, '/'));
            $id = (int)end($segmentos);

            if (!$id) {
                $this->jsonResponse([
                    'success' => false,
                    'message' => 'ID de escenario inválido'
                ], 400);
                return;
            }

            $usuario = $this->getUsuario();
            $result  = $this->service->eliminarEscenario($id, $usuario);

            $this->jsonResponse($result, $result['success'] ? 200 : 500);

        } catch (Exception $e) {
            $this->jsonResponse([
                'success' => false,
                'message' => 'Error al eliminar escenario: ' . $e->getMessage()
            ], 500);
        }
    }

    // ─────────────────────────────────────────────
    // GET /api/simulacion-escenarios/obtener/{id}
    // ─────────────────────────────────────────────
    public function obtener() {
        try {
            $uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
            $segmentos = explode('/', trim($uri, '/'));
            $id = (int)end($segmentos);

            if (!$id) {
                $this->jsonResponse([
                    'success' => false,
                    'message' => 'ID de escenario inválido'
                ], 400);
                return;
            }

            $result = $this->service->obtenerEscenario($id);

            $this->jsonResponse($result, $result['success'] ? 200 : 404);

        } catch (Exception $e) {
            $this->jsonResponse([
                'success' => false,
                'message' => 'Error al obtener escenario: ' . $e->getMessage()
            ], 500);
        }
    }

    // ─────────────────────────────────────────────
    // GET /api/simulacion-escenarios/exportar-pdf?proyeccion=xxx
    // ─────────────────────────────────────────────
    public function exportarPDF() {
        try {
            $proyeccion = $_GET['proyeccion'] ?? '';
            
            if (empty($proyeccion)) {
                $this->jsonResponse([
                    'success' => false,
                    'message' => 'Parámetro proyeccion es requerido'
                ], 400);
                return;
            }

            $result = $this->service->listarEscenarios($proyeccion);
            
            if (!$result['success']) {
                $this->jsonResponse($result, 500);
                return;
            }

            $escenarios = $result['data'] ?? [];
            
            if (empty($escenarios)) {
                $this->jsonResponse([
                    'success' => false,
                    'message' => 'No hay escenarios guardados para esta proyección. Por favor, cargue y guarde los datos primero.'
                ], 404);
                return;
            }
            
            $numEsc = count($escenarios);
            
            // Generar PDF
            require_once __DIR__ . '/../libraries/PDFExporter.php';
            
            $pdf = new PDFExporter();
            $pdf->SetTitle('Simulación de Escenarios - ' . $proyeccion);
            $pdf->AliasNbPages();
            $pdf->AddPage('L'); // Landscape
            
            // Título
            $pdf->SetFont('Arial', 'B', 14);
            $pdf->SetFillColor(30, 87, 153);
            $pdf->SetTextColor(255, 255, 255);
            $pdf->Cell(0, 10, 'SIMULACION DE ESCENARIOS DE CARGA - ' . $proyeccion, 0, 1, 'C', true);
            
            $pdf->SetFont('Arial', '', 8);
            $pdf->SetFillColor(232, 244, 248);
            $pdf->SetTextColor(85, 85, 85);
            $pdf->Cell(0, 6, 'Fecha: ' . date('d/m/Y H:i:s'), 0, 1, 'C', true);
            $pdf->Ln(3);
            
            // Calcular anchos de columna
            $colParametro = 70;
            $colEscenario = ($pdf->GetPageWidth() - $colParametro - 20) / $numEsc;
            
            // Headers con nombres de escenarios
            $pdf->SetFont('Arial', 'B', 9);
            $pdf->SetFillColor(30, 87, 153);
            $pdf->SetTextColor(255, 255, 255);
            $pdf->Cell($colParametro, 8, 'PARAMETRO', 1, 0, 'C', true);
            foreach ($escenarios as $esc) {
                $pdf->Cell($colEscenario, 8, $esc['nom_escenario'] ?? $esc['cod_escenario'], 1, 0, 'C', true);
            }
            $pdf->Ln();
            
            // SECCIÓN: DATOS DE ENTRADA
            $pdf->SetFont('Arial', 'B', 8);
            $pdf->SetFillColor(92, 184, 92);
            $pdf->SetTextColor(255, 255, 255);
            $pdf->Cell($colParametro + ($colEscenario * $numEsc), 6, 'DATOS DE ENTRADA', 1, 1, 'C', true);
            
            $pdf->SetFont('Arial', '', 8);
            $pdf->SetTextColor(0, 0, 0);
            
            $datosEntrada = [
                ['Galpones 2640 m2', 'n_galpones_2640', 0],
                ['Galpones 1800 m2', 'n_galpones_1800', 0],
                ['Pollos por Galpon', 'pollos_por_galpon', 0],
                ['Cargas Semanales', 'cargas_semanales', 1],
                ['Tiempo Limpieza (dias)', 'tpo_limpieza_dias', 1]
            ];
            
            $fill = false;
            foreach ($datosEntrada as $row) {
                $pdf->SetFillColor(245, 245, 245);
                $pdf->SetFont('Arial', 'B', 8);
                $pdf->Cell($colParametro, 5, $row[0], 1, 0, 'L', $fill);
                $pdf->SetFont('Arial', '', 8);
                foreach ($escenarios as $esc) {
                    $valor = $esc[$row[1]] ?? 0;
                    $valorFormat = $row[2] > 0 ? number_format($valor, $row[2], '.', ',') : number_format($valor, 0, '.', ',');
                    $pdf->Cell($colEscenario, 5, $valorFormat, 1, 0, 'R', false);
                }
                $pdf->Ln();
                $fill = !$fill;
            }
            
            // SECCIÓN: RESULTADOS
            $pdf->Ln(2);
            $pdf->SetFont('Arial', 'B', 8);
            $pdf->SetFillColor(92, 184, 92);
            $pdf->SetTextColor(255, 255, 255);
            $pdf->Cell($colParametro + ($colEscenario * $numEsc), 6, 'RESULTADOS CALCULADOS', 1, 1, 'C', true);
            
            $pdf->SetFont('Arial', '', 8);
            $pdf->SetTextColor(0, 0, 0);
            
            $resultados = [
                ['Total Galpones', 'total_galpones', 0],
                ['Area Total (m2)', 'area_total_m2', 0],
                ['Galpones Std 2640', 'galp_std_2640', 2],
                ['Pollos/Semana', 'pollos_semana', 0],
                ['Oferta Pollos/Semana', 'oferta_pollos_semana', 0],
                ['Cargas Diario', 'cargas_diario', 2],
                ['Tpo Ciclo Crianza (dias)', 'tpo_ciclo_crianza', 2],
                ['Tpo Crianza Ponderado (dias)', 'tpo_crianza_ponderado', 2],
                ['Tpo Descanso Total (dias)', 'tpo_descanso_total', 2],
                ['Tpo Descanso Efectivo (dias)', 'tpo_descanso_efectivo', 2]
            ];
            
            $fill = false;
            foreach ($resultados as $row) {
                $pdf->SetFillColor(245, 245, 245);
                $pdf->SetFont('Arial', 'B', 8);
                $pdf->Cell($colParametro, 5, $row[0], 1, 0, 'L', $fill);
                $pdf->SetFont('Arial', '', 8);
                foreach ($escenarios as $esc) {
                    $valor = $esc[$row[1]] ?? 0;
                    $valorFormat = number_format($valor, $row[2], '.', ',');
                    $pdf->Cell($colEscenario, 5, $valorFormat, 1, 0, 'R', false);
                }
                $pdf->Ln();
                $fill = !$fill;
            }
            
            // NOTAS (si existen)
            $hayNotas = false;
            foreach ($escenarios as $esc) {
                if (!empty($esc['notas'])) {
                    $hayNotas = true;
                    break;
                }
            }
            
            if ($hayNotas) {
                $pdf->Ln(2);
                $pdf->SetFont('Arial', 'B', 8);
                $pdf->SetFillColor(92, 184, 92);
                $pdf->SetTextColor(255, 255, 255);
                $pdf->Cell($colParametro + ($colEscenario * $numEsc), 6, 'NOTAS Y OBSERVACIONES', 1, 1, 'C', true);
                
                $pdf->SetFont('Arial', '', 7);
                $pdf->SetTextColor(0, 0, 0);
                $pdf->SetFillColor(245, 245, 245);
                $pdf->Cell($colParametro, 5, 'Notas', 1, 0, 'L', true);
                foreach ($escenarios as $esc) {
                    $pdf->Cell($colEscenario, 5, substr($esc['notas'] ?? '', 0, 40), 1, 0, 'L', false);
                }
                $pdf->Ln();
            }
            
            // Output
            $filename = 'Simulacion_Escenarios_' . preg_replace('/[^a-zA-Z0-9_-]/', '_', $proyeccion) . '_' . date('Ymd_His') . '.pdf';
            $pdf->Output('D', $filename);
            
        } catch (Exception $e) {
            error_log("Error en exportarPDF: " . $e->getMessage());
            $this->jsonResponse([
                'success' => false,
                'message' => 'Error al exportar PDF: ' . $e->getMessage()
            ], 500);
        }
    }

    // ─────────────────────────────────────────────
    // GET /api/simulacion-escenarios/exportar-excel?proyeccion=xxx
    // ─────────────────────────────────────────────
    public function exportarExcel() {
        try {
            $proyeccion = $_GET['proyeccion'] ?? '';
            
            if (empty($proyeccion)) {
                $this->jsonResponse([
                    'success' => false,
                    'message' => 'Parámetro proyeccion es requerido'
                ], 400);
                return;
            }

            $result = $this->service->listarEscenarios($proyeccion);
            
            if (!$result['success']) {
                // Error al listar, mostrar mensaje
                header('Content-Type: text/html; charset=UTF-8');
                echo "<html><body style='font-family: Arial; padding: 20px;'>";
                echo "<h2 style='color: red;'>Error al cargar escenarios</h2>";
                echo "<p><strong>Proyección solicitada:</strong> " . htmlspecialchars($proyeccion) . "</p>";
                echo "<p><strong>Mensaje:</strong> " . htmlspecialchars($result['message'] ?? 'Error desconocido') . "</p>";
                echo "</body></html>";
                exit;
            }

            $escenarios = $result['data'] ?? [];
            
            // Si no hay escenarios, mostrar mensaje informativo
            if (empty($escenarios)) {
                header('Content-Type: text/html; charset=UTF-8');
                echo "<html><body style='font-family: Arial; padding: 20px;'>";
                echo "<h2 style='color: orange;'>No hay escenarios para exportar</h2>";
                echo "<p><strong>Proyección:</strong> " . htmlspecialchars($proyeccion) . "</p>";
                echo "<p>Por favor, primero cargue y guarde los escenarios en la interfaz web.</p>";
                echo "<p><strong>Pasos:</strong></p>";
                echo "<ol>";
                echo "<li>Seleccione la proyección en el sistema</li>";
                echo "<li>Haga clic en 'Cargar' para ver los escenarios</li>";
                echo "<li>Haga clic en 'Guardar Todo' para guardar los datos</li>";
                echo "<li>Intente exportar nuevamente</li>";
                echo "</ol>";
                echo "</body></html>";
                exit;
            }
            
            // Headers para Excel
            $filename = 'Simulacion_Escenarios_' . preg_replace('/[^a-zA-Z0-9_-]/', '_', $proyeccion) . '_' . date('Ymd_His') . '.xls';
            header('Content-Type: application/vnd.ms-excel; charset=UTF-8');
            header('Content-Disposition: attachment; filename="' . $filename . '"');
            header('Cache-Control: max-age=0');
            header('Pragma: public');
            
            // BOM UTF-8
            echo "\xEF\xBB\xBF";
            
            ?>
<html xmlns:x="urn:schemas-microsoft-com:office:excel">
<head>
    <meta http-equiv="content-type" content="application/vnd.ms-excel; charset=UTF-8">
    <xml>
        <x:ExcelWorkbook>
            <x:ExcelWorksheets>
                <x:ExcelWorksheet>
                    <x:Name>Escenarios</x:Name>
                    <x:WorksheetOptions><x:Selected/></x:WorksheetOptions>
                </x:ExcelWorksheet>
            </x:ExcelWorksheets>
        </x:ExcelWorkbook>
    </xml>
    <style>
        table { 
            border-collapse: collapse; 
            width: 100%; 
            font-family: 'Calibri', 'Segoe UI', Arial, sans-serif;
            font-size: 11px;
        }
        th { 
            background: #1e3a8a;
            color: white; 
            font-weight: bold; 
            padding: 12px 8px;
            border: 2px solid #1e3a8a;
            text-align: center;
            font-size: 13px;
        }
        td { 
            padding: 8px 10px;
            border: 1px solid #ccc;
            vertical-align: middle;
        }
        .header { 
            background: #1e3a8a;
            color: white; 
            font-weight: bold; 
            text-align: center; 
            font-size: 20px;
            padding: 15px;
            letter-spacing: 1px;
        }
        .subheader {
            background: white;
            text-align: center;
            padding: 5px;
            font-size: 11px;
            color: #333;
            border: 1px solid #1e3a8a;
        }
        .label { 
            font-weight: 600;
            background-color: #f9f9f9;
            text-align: left;
            padding-left: 15px;
        }
        .number { 
            text-align: center;
            font-weight: 500;
        }
        .number-red {
            text-align: center;
            font-weight: bold;
            color: #dc2626;
        }
        .unidad {
            text-align: left;
            font-size: 10px;
            color: #555;
            background-color: #fafafa;
            font-style: italic;
        }
        
        /* Secciones con colores */
        .seccion-galpones {
            background-color: #fef3c7 !important; /* Amarillo claro */
        }
        .seccion-total {
            background-color: #ffe4b5 !important; /* Beige */
            font-weight: bold;
        }
        .seccion-area {
            background-color: #ffedd5 !important; /* Naranja muy claro */
        }
        .seccion-pollos {
            background-color: #fecaca !important; /* Rosa claro */
        }
        .seccion-tiempos {
            background-color: #fef9c3 !important; /* Amarillo muy claro */
        }
        
        tr:hover td {
            opacity: 0.9;
        }
    </style>
</head>
<body>
    <table>
        <tr>
            <td colspan="<?php echo count($escenarios) + 2; ?>" class="header">
                SIMULACIÓN DE ESCENARIOS DE CARGA<?php if ($proyeccion) echo ' - ' . htmlspecialchars($proyeccion); ?>
            </td>
        </tr>
        <tr>
            <td colspan="<?php echo count($escenarios) + 2; ?>" class="subheader">
                Fecha: <?php echo date('d/m/Y H:i:s'); ?>
            </td>
        </tr>
        
        <!-- Headers con nombres de escenarios -->
        <tr>
            <th style="width: 35%;">PARÁMETRO</th>
            <?php foreach ($escenarios as $esc): ?>
                <th style="width: <?php echo floor(50 / count($escenarios)); ?>%;"><?php echo htmlspecialchars($esc['nom_escenario'] ?? $esc['cod_escenario']); ?></th>
            <?php endforeach; ?>
            <th style="width: 15%;">UNIDAD</th>
        </tr>
        
        <!-- GALPONES - DATOS DE ENTRADA -->
        <tr>
            <td style="font-weight: 600; background-color: #fef3c7; text-align: left; padding: 8px 10px 8px 15px; border: 1px solid #ccc;">N° Galpones 2,640 m²</td>
            <?php foreach ($escenarios as $esc): ?>
                <td style="text-align: center; font-weight: bold; color: #dc2626; background-color: #fef3c7; padding: 8px 10px; border: 1px solid #ccc;"><?php echo $esc['n_galpones_2640'] ?? 0; ?></td>
            <?php endforeach; ?>
            <td style="text-align: left; font-size: 10px; color: #555; background-color: #fafafa; font-style: italic; padding: 8px 10px; border: 1px solid #ccc;">Galpones</td>
        </tr>
        <tr>
            <td style="font-weight: 600; background-color: #fef3c7; text-align: left; padding: 8px 10px 8px 15px; border: 1px solid #ccc;">N° Galpones 1,800 m²</td>
            <?php foreach ($escenarios as $esc): ?>
                <td style="text-align: center; font-weight: bold; color: #dc2626; background-color: #fef3c7; padding: 8px 10px; border: 1px solid #ccc;"><?php echo $esc['n_galpones_1800'] ?? 0; ?></td>
            <?php endforeach; ?>
            <td style="text-align: left; font-size: 10px; color: #555; background-color: #fafafa; font-style: italic; padding: 8px 10px; border: 1px solid #ccc;">Galpones</td>
        </tr>
        <tr>
            <td style="font-weight: 700; background-color: #ffe4b5; text-align: left; padding: 8px 10px 8px 15px; border: 1px solid #ccc;"><strong>TOTAL</strong></td>
            <?php foreach ($escenarios as $esc): ?>
                <td style="text-align: center; font-weight: 700; background-color: #ffe4b5; padding: 8px 10px; border: 1px solid #ccc;"><strong><?php echo $esc['total_galpones'] ?? 0; ?></strong></td>
            <?php endforeach; ?>
            <td style="text-align: left; font-size: 10px; color: #555; background-color: #fafafa; font-style: italic; font-weight: 700; padding: 8px 10px; border: 1px solid #ccc;"><strong>Galpones</strong></td>
        </tr>
        
        <!-- ÁREA Y ESTANDARIZACIÓN -->
        <tr>
            <td style="font-weight: 600; background-color: #ffedd5; text-align: left; padding: 8px 10px 8px 15px; border: 1px solid #ccc;">Área total</td>
            <?php foreach ($escenarios as $esc): ?>
                <td style="text-align: center; font-weight: 500; background-color: #ffedd5; padding: 8px 10px; border: 1px solid #ccc;"><?php echo number_format($esc['area_total_m2'] ?? 0, 0, '.', ','); ?></td>
            <?php endforeach; ?>
            <td style="text-align: left; font-size: 10px; color: #555; background-color: #fafafa; font-style: italic; padding: 8px 10px; border: 1px solid #ccc;">m²</td>
        </tr>
        <tr>
            <td style="font-weight: 600; background-color: #ffedd5; text-align: left; padding: 8px 10px 8px 15px; border: 1px solid #ccc;">N° Total Galpones estandarizado a 2,640 m²</td>
            <?php foreach ($escenarios as $esc): ?>
                <td style="text-align: center; font-weight: 500; background-color: #ffedd5; padding: 8px 10px; border: 1px solid #ccc;"><?php echo number_format($esc['galp_std_2640'] ?? 0, 0); ?></td>
            <?php endforeach; ?>
            <td style="text-align: left; font-size: 10px; color: #555; background-color: #fafafa; font-style: italic; padding: 8px 10px; border: 1px solid #ccc;">Galpones</td>
        </tr>
        
        <!-- POLLOS Y CARGAS -->
        <tr>
            <td style="font-weight: 600; background-color: #fecaca; text-align: left; padding: 8px 10px 8px 15px; border: 1px solid #ccc;">N° pollos criados por galpon</td>
            <?php foreach ($escenarios as $esc): ?>
                <td style="text-align: center; font-weight: bold; color: #dc2626; background-color: #fecaca; padding: 8px 10px; border: 1px solid #ccc;"><?php echo number_format($esc['pollos_por_galpon'] ?? 0, 0, '.', ','); ?></td>
            <?php endforeach; ?>
            <td style="text-align: left; font-size: 10px; color: #555; background-color: #fafafa; font-style: italic; padding: 8px 10px; border: 1px solid #ccc;">Pollos / Galpón</td>
        </tr>
        <tr>
            <td style="font-weight: 600; background-color: #fecaca; text-align: left; padding: 8px 10px 8px 15px; border: 1px solid #ccc;">N° de cargas semanales</td>
            <?php foreach ($escenarios as $esc): ?>
                <td style="text-align: center; font-weight: bold; color: #dc2626; background-color: #fecaca; padding: 8px 10px; border: 1px solid #ccc;"><?php echo number_format($esc['cargas_semanales'] ?? 0, 1, '.', ','); ?></td>
            <?php endforeach; ?>
            <td style="text-align: left; font-size: 10px; color: #555; background-color: #fafafa; font-style: italic; padding: 8px 10px; border: 1px solid #ccc;">Galpones / Semana</td>
        </tr>
        <tr>
            <td style="font-weight: 600; background-color: #fecaca; text-align: left; padding: 8px 10px 8px 15px; border: 1px solid #ccc;">N° pollos criados por semana</td>
            <?php foreach ($escenarios as $esc): ?>
                <td style="text-align: center; font-weight: 500; background-color: #fecaca; padding: 8px 10px; border: 1px solid #ccc;"><?php echo number_format($esc['pollos_semana'] ?? 0, 0, '.', ','); ?></td>
            <?php endforeach; ?>
            <td style="text-align: left; font-size: 10px; color: #555; background-color: #fafafa; font-style: italic; padding: 8px 10px; border: 1px solid #ccc;">Pollos / Semana</td>
        </tr>
        <tr>
            <td style="font-weight: 600; background-color: #fecaca; text-align: left; padding: 8px 10px 8px 15px; border: 1px solid #ccc;">N° pollos oferta por semana</td>
            <?php foreach ($escenarios as $esc): ?>
                <td style="text-align: center; font-weight: 500; background-color: #fecaca; padding: 8px 10px; border: 1px solid #ccc;"><?php echo number_format($esc['oferta_pollos_semana'] ?? 0, 0, '.', ','); ?></td>
            <?php endforeach; ?>
            <td style="text-align: left; font-size: 10px; color: #555; background-color: #fafafa; font-style: italic; padding: 8px 10px; border: 1px solid #ccc;">Pollos / Semana</td>
        </tr>
        <tr>
            <td style="font-weight: 600; background-color: #fecaca; text-align: left; padding: 8px 10px 8px 15px; border: 1px solid #ccc;">N° de cargas diario</td>
            <?php foreach ($escenarios as $esc): ?>
                <td style="text-align: center; font-weight: 500; background-color: #fecaca; padding: 8px 10px; border: 1px solid #ccc;"><?php echo number_format($esc['cargas_diario'] ?? 0, 1, '.', ','); ?></td>
            <?php endforeach; ?>
            <td style="text-align: left; font-size: 10px; color: #555; background-color: #fafafa; font-style: italic; padding: 8px 10px; border: 1px solid #ccc;">Galpones / Día</td>
        </tr>
        
        <!-- TIEMPOS -->
        <tr>
            <td style="font-weight: 600; background-color: #fef9c3; text-align: left; padding: 8px 10px 8px 15px; border: 1px solid #ccc;">Tiempo de ciclo de crianza</td>
            <?php foreach ($escenarios as $esc): ?>
                <td style="text-align: center; font-weight: 500; background-color: #fef9c3; padding: 8px 10px; border: 1px solid #ccc;"><?php echo number_format($esc['tpo_ciclo_crianza'] ?? 0, 1, '.', ','); ?></td>
            <?php endforeach; ?>
            <td style="text-align: left; font-size: 10px; color: #555; background-color: #fafafa; font-style: italic; padding: 8px 10px; border: 1px solid #ccc;">Días</td>
        </tr>
        <tr>
            <td style="font-weight: 600; background-color: #fef9c3; text-align: left; padding: 8px 10px 8px 15px; border: 1px solid #ccc;">Tiempo de crianza</td>
            <?php foreach ($escenarios as $esc): ?>
                <td style="text-align: center; font-weight: 500; background-color: #fef9c3; padding: 8px 10px; border: 1px solid #ccc;"><?php echo number_format($esc['tpo_crianza_ponderado'] ?? 0, 1, '.', ','); ?></td>
            <?php endforeach; ?>
            <td style="text-align: left; font-size: 10px; color: #555; background-color: #fafafa; font-style: italic; padding: 8px 10px; border: 1px solid #ccc;">Días</td>
        </tr>
        <?php 
        // Mostrar zonas si existen (solo del primer escenario como referencia)
        if (!empty($escenarios[0]['zonas'])):
            foreach ($escenarios[0]['zonas'] as $zona):
        ?>
        <tr>
            <td style="font-weight: 600; background-color: #fef9c3; text-align: left; padding: 8px 10px 8px 40px; border: 1px solid #ccc;">└─ <?php echo htmlspecialchars($zona['zona'] ?? ''); ?> <?php echo number_format($zona['porcentaje_zona'] ?? 0, 1); ?>%</td>
            <?php foreach ($escenarios as $esc): 
                $zonaData = null;
                foreach (($esc['zonas'] ?? []) as $z) {
                    if ($z['zona'] === $zona['zona']) {
                        $zonaData = $z;
                        break;
                    }
                }
            ?>
                <td style="text-align: center; font-weight: bold; color: #dc2626; background-color: #fef9c3; padding: 8px 10px; border: 1px solid #ccc;"><?php echo number_format($zonaData['tpo_crianza_dias'] ?? 0, 1, '.', ','); ?></td>
            <?php endforeach; ?>
            <td style="text-align: left; font-size: 10px; color: #555; background-color: #fafafa; font-style: italic; padding: 8px 10px; border: 1px solid #ccc;">Días</td>
        </tr>
        <?php 
            endforeach;
        endif;
        ?>
        <tr>
            <td style="font-weight: 600; background-color: #fef9c3; text-align: left; padding: 8px 10px 8px 15px; border: 1px solid #ccc;">Tiempo de descanso total</td>
            <?php foreach ($escenarios as $esc): ?>
                <td style="text-align: center; font-weight: 500; background-color: #fef9c3; padding: 8px 10px; border: 1px solid #ccc;"><?php echo number_format($esc['tpo_descanso_total'] ?? 0, 0); ?></td>
            <?php endforeach; ?>
            <td style="text-align: left; font-size: 10px; color: #555; background-color: #fafafa; font-style: italic; padding: 8px 10px; border: 1px solid #ccc;">Días</td>
        </tr>
        <tr>
            <td style="font-weight: 600; background-color: #fef9c3; text-align: left; padding: 8px 10px 8px 15px; border: 1px solid #ccc;">Tiempo de Limpieza y Desinfección</td>
            <?php foreach ($escenarios as $esc): ?>
                <td style="text-align: center; font-weight: bold; color: #dc2626; background-color: #fef9c3; padding: 8px 10px; border: 1px solid #ccc;"><?php echo number_format($esc['tpo_limpieza_dias'] ?? 0, 1, '.', ','); ?></td>
            <?php endforeach; ?>
            <td style="text-align: left; font-size: 10px; color: #555; background-color: #fafafa; font-style: italic; padding: 8px 10px; border: 1px solid #ccc;">Días</td>
        </tr>
        <tr>
            <td style="font-weight: 600; background-color: #fef9c3; text-align: left; padding: 8px 10px 8px 15px; border: 1px solid #ccc;">Tiempo de descanso efectivo</td>
            <?php foreach ($escenarios as $esc): ?>
                <td style="text-align: center; font-weight: 500; background-color: #fef9c3; padding: 8px 10px; border: 1px solid #ccc;"><?php echo number_format($esc['tpo_descanso_efectivo'] ?? 0, 0); ?></td>
            <?php endforeach; ?>
            <td style="text-align: left; font-size: 10px; color: #555; background-color: #fafafa; font-style: italic; padding: 8px 10px; border: 1px solid #ccc;">Días</td>
        </tr>
        
        <!-- SECCIÓN: NOTAS -->
        <?php 
        $hayNotas = false;
        foreach ($escenarios as $esc) {
            if (!empty($esc['notas'])) {
                $hayNotas = true;
                break;
            }
        }
        if ($hayNotas): 
        ?>
        <tr>
            <td colspan="<?php echo count($escenarios) + 2; ?>" style="background-color: #e5e7eb; padding: 10px; font-weight: bold; text-align: center; border: 2px solid #9ca3af;">
                NOTAS Y OBSERVACIONES
            </td>
        </tr>
        <tr>
            <td style="font-weight: 600; background-color: #f3f4f6; text-align: left; padding: 8px 10px 8px 15px; border: 1px solid #ccc;">Notas</td>
            <?php foreach ($escenarios as $esc): ?>
                <td style="text-align: left; font-size: 10px; padding: 8px; background-color: #ffffff; border: 1px solid #ccc;"><?php echo htmlspecialchars($esc['notas'] ?? ''); ?></td>
            <?php endforeach; ?>
            <td style="text-align: left; font-size: 10px; color: #555; background-color: #fafafa; font-style: italic; padding: 8px 10px; border: 1px solid #ccc;">-</td>
        </tr>
        <?php endif; ?>
    </table>
</body>
</html>
            <?php
            exit;
            
        } catch (Exception $e) {
            error_log("Error en exportarExcel: " . $e->getMessage());
            $this->jsonResponse([
                'success' => false,
                'message' => 'Error al exportar Excel: ' . $e->getMessage()
            ], 500);
        }
    }

    // ─────────────────────────────────────────────
    // Helpers
    // ─────────────────────────────────────────────
    
    private function getBody(): array {
        $body = file_get_contents('php://input');
        return json_decode($body, true) ?? [];
    }

    private function getUsuario(): string {
        // Intenta obtener el usuario de la sesión
        if (isset($_SESSION['usuario'])) {
            return $_SESSION['usuario'];
        }
        if (isset($_SESSION['username'])) {
            return $_SESSION['username'];
        }
        return 'SYSTEM';
    }

    private function jsonResponse(array $data, int $status = 200): void {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE);
    }
}
