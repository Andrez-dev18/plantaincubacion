<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);

date_default_timezone_set('America/Lima');
// Zona horaria explícita para mostrar hora local en cabecera PDF
$tzLima = new DateTimeZone('America/Lima');


ob_start();
include_once '../../../conexion_grs_joya/conexion.php';
$conn = conectar_joya();
ob_end_clean();

if (!$conn) {
    die('Error de conexión a la base de datos');
}

// Obtener parámetros del POST o GET
$cencos = isset($_POST['cencos']) ? $_POST['cencos'] : (isset($_GET['cencos']) ? $_GET['cencos'] : 'todos');
$galpones = isset($_POST['galpones']) ? $_POST['galpones'] : (isset($_GET['galpones']) ? $_GET['galpones'] : 'todos');
$galpones_pares = isset($_POST['galpones_pares']) ? $_POST['galpones_pares'] : (isset($_GET['galpones_pares']) ? $_GET['galpones_pares'] : '');
$fecha_inicio = isset($_POST['fecha_inicio']) ? $_POST['fecha_inicio'] : (isset($_GET['fecha_inicio']) ? $_GET['fecha_inicio'] : '');
$fecha_fin = isset($_POST['fecha_fin']) ? $_POST['fecha_fin'] : (isset($_GET['fecha_fin']) ? $_GET['fecha_fin'] : '');
$formato = isset($_POST['formato']) ? $_POST['formato'] : (isset($_GET['formato']) ? $_GET['formato'] : 'pdf');

$dataJson = file_get_contents('php://input');
if (!empty($dataJson)) {
    $input = json_decode($dataJson, true);
    if ($input) {
        $cencos = $input['cencos'] ?? $cencos;
        $galpones = $input['galpones'] ?? $galpones;
        $galpones_pares = $input['galpones_pares'] ?? $galpones_pares;
        $fecha_inicio = $input['fecha_inicio'] ?? $fecha_inicio;
        $fecha_fin = $input['fecha_fin'] ?? $fecha_fin;
        $formato = $input['formato'] ?? $formato;
    }
}


if (empty($fecha_inicio) || empty($fecha_fin)) {
    die('Debe especificar fecha de inicio y fecha de fin');
}


if (is_string($cencos) && $cencos !== 'todos') {
    $cencos = explode(',', $cencos);
}
if (is_string($galpones) && $galpones !== 'todos') {
    $galpones = explode(',', $galpones);
}

// PARES tgranja|tgalpon (para filtrar combinación exacta)
$galponesParesArray = [];
if (is_string($galpones_pares) && trim($galpones_pares) !== '') {
    $rawPairs = array_filter(array_map('trim', explode(',', $galpones_pares)));
    foreach ($rawPairs as $pair) {
        // Formato esperado: 123456|12
        $parts = explode('|', $pair, 2);
        if (count($parts) === 2) {
            $granja = trim($parts[0]);
            $galpon = trim($parts[1]);
            if ($granja !== '' && $galpon !== '') {
                $galponesParesArray[] = [$granja, $galpon];
            }
        }
    }
}


$sql = "SELECT 
    tcencos, tgranja, tgalpon, tedad, tsistema, tnivel, tparametro, 
    tporcentajetotal, tobservacion, tdate, tfectra, tnumreg, tcampania
FROM t_regnecropsia 
WHERE tdate >= ? AND tdate <= ?
    AND tgalpon IS NOT NULL 
    AND tgalpon != '' 
    AND tgalpon != '0'";

$params = [];
$types = "ss";
$params[] = $fecha_inicio;
$params[] = $fecha_fin;

// Filtrar por CENCOS
if ($cencos !== 'todos' && is_array($cencos) && !empty($cencos)) {
    $placeholders = implode(',', array_fill(0, count($cencos), '?'));
    $sql .= " AND tgranja IN ($placeholders)";
    $types .= str_repeat('s', count($cencos));
    foreach ($cencos as $cenco) {
        $params[] = $cenco;
    }
}

// Filtrar por galpones
// Prioridad: si llegan pares (tgranja|tgalpon), filtrar por combinación exacta
if (!empty($galponesParesArray)) {
    $orParts = [];
    foreach ($galponesParesArray as $_) {
        $orParts[] = "(tgranja = ? AND tgalpon = ?)";
    }
    $sql .= " AND (" . implode(" OR ", $orParts) . ")";
    $types .= str_repeat('ss', count($galponesParesArray));
    foreach ($galponesParesArray as $pair) {
        $params[] = $pair[0]; // tgranja (6 dígitos)
        $params[] = $pair[1]; // tgalpon
    }
} elseif ($galpones !== 'todos' && is_array($galpones) && !empty($galpones)) {
    $placeholders = implode(',', array_fill(0, count($galpones), '?'));
    $sql .= " AND tgalpon IN ($placeholders)";
    $types .= str_repeat('s', count($galpones));
    foreach ($galpones as $galpon) {
        $params[] = $galpon;
    }
}

$sql .= " ORDER BY 
    CASE 
        WHEN LOWER(tsistema) LIKE '%inmunol%' THEN 1
        WHEN LOWER(tsistema) LIKE '%digestiv%' THEN 2
        WHEN LOWER(tsistema) LIKE '%respirat%' THEN 3
        WHEN LOWER(tsistema) LIKE '%evaluaci%' AND LOWER(tsistema) LIKE '%físic%' THEN 4
        ELSE 5
    END,
    tsistema, tnivel, tparametro, tcencos, tgalpon";

$stmt = $conn->prepare($sql);

if (!$stmt) {
    die('Error preparando consulta: ' . $conn->error);
}

$bindParams = array_merge(array($types), $params);
$refs = array();
foreach ($bindParams as $key => $value) {
    $refs[$key] = &$bindParams[$key];
}
call_user_func_array(array($stmt, 'bind_param'), $refs);

$stmt->execute();
$result = $stmt->get_result();

$registros = [];
while ($row = $result->fetch_assoc()) {
    // Filtrar galpones sin número o con 0
    $galpon = trim($row['tgalpon'] ?? '');
    
    if (empty($galpon) || $galpon === '0') {
        continue;
    }
    
    // Asegurar que tgalpon y tedad estén presentes
    if (!isset($row['tgalpon']) || !isset($row['tedad'])) {
        // Si no están en el registro, usar los valores tal cual vienen de la BD
        $row['tgalpon'] = $galpon;
        $row['tedad'] = $row['tedad'] ?? '0';
    }
    
    $registros[] = $row;
}

$stmt->close();

$sqlTodosNiveles = "
    SELECT DISTINCT tsistema, tnivel, tparametro 
    FROM t_regnecropsia 
    ORDER BY 
        CASE 
            WHEN LOWER(tsistema) LIKE '%inmunol%' THEN 1
            WHEN LOWER(tsistema) LIKE '%digestiv%' THEN 2
            WHEN LOWER(tsistema) LIKE '%respirat%' THEN 3
            WHEN LOWER(tsistema) LIKE '%evaluaci%' AND LOWER(tsistema) LIKE '%físic%' THEN 4
            ELSE 5
        END,
        tsistema,
        tnivel,
        tparametro
";
$resultTodosNiveles = $conn->query($sqlTodosNiveles);
$todosNiveles = [];
while ($row = $resultTodosNiveles->fetch_assoc()) {
    $sistema = $row['tsistema'] ?? '';
    $nivel = $row['tnivel'] ?? '';
    $parametro = $row['tparametro'] ?? '';
    
    // Filtrar parámetros vacíos o que sean "0"
    if (empty(trim($parametro)) || trim($parametro) === '0') {
        continue;
    }
    
    if (!isset($todosNiveles[$sistema])) {
        $todosNiveles[$sistema] = [];
    }
    if (!isset($todosNiveles[$sistema][$nivel])) {
        $todosNiveles[$sistema][$nivel] = [];
    }
    if (!in_array($parametro, $todosNiveles[$sistema][$nivel])) {
        $todosNiveles[$sistema][$nivel][] = $parametro;
    }
}

$conn->close();

if (empty($registros)) {
    // Si se solicita verificar (desde el frontend), devolver JSON
    if (isset($_GET['check']) && $_GET['check'] == '1') {
        header('Content-Type: application/json');
        echo json_encode(['tiene_resultados' => false, 'mensaje' => 'No se encontraron registros para los filtros especificados']);
        exit;
    }
    // Si no hay check, mostrar mensaje normal
    die('No se encontraron registros para los filtros especificados');
}

// Si se solicita verificar (desde el frontend) y SÍ hay registros, devolver JSON y salir
if (isset($_GET['check']) && $_GET['check'] == '1') {
    header('Content-Type: application/json');
    echo json_encode(['tiene_resultados' => true]);
    exit;
}

// Agrupar datos por BLOQUE (tdate, tfectra, tnumreg, tgranja, tcampania, tedad, tgalpon)
// Cada bloque representa una columna (% y Obs) en el reporte
$bloques = [];
$cencosUnicos = [];
foreach ($registros as $reg) {
    // Clave del bloque: todos los campos que lo definen
    $keyBloque = $reg['tdate'] . '_' . $reg['tfectra'] . '_' . $reg['tnumreg'] . '_' . 
                 $reg['tgranja'] . '_' . ($reg['tcampania'] ?? '') . '_' . 
                 $reg['tedad'] . '_' . $reg['tgalpon'];
    
    if (!isset($bloques[$keyBloque])) {
        $bloques[$keyBloque] = [
            'tcencos' => $reg['tcencos'],
            'tgranja' => $reg['tgranja'],
            'tgalpon' => trim($reg['tgalpon']),
            'tedad' => $reg['tedad'],
            'tdate' => $reg['tdate'],
            'tfectra' => $reg['tfectra'],
            'tnumreg' => $reg['tnumreg'],
            'tcampania' => $reg['tcampania'] ?? '',
            'registros' => []
        ];
    }
    $bloques[$keyBloque]['registros'][] = $reg;
    
    // Agrupar cencos únicos
    if (!isset($cencosUnicos[$reg['tcencos']])) {
        $cencosUnicos[$reg['tcencos']] = $reg['tgranja'];
    }
}

// Determinar si es comparativo entre cencos (más de un cenco)
$esComparativoCencos = count($cencosUnicos) > 1;

// Agrupar bloques directamente por CENCO
$galponesUnicos = []; // Para la leyenda: galpones únicos con su edad por CENCO
$galponesPorCenco = []; // Estructura: [cenco => ['granja' => ..., 'bloques' => [keyBloque1, keyBloque2, ...]]]
foreach ($bloques as $keyBloque => $bloque) {
    $galponKey = $bloque['tgalpon'];
    $cenco = $bloque['tcencos'];
    
    // Filtrar galpones sin número o con 0
    if (empty($galponKey) || $galponKey == '0') {
        continue;
    }
    
    $edadGalpon = $bloque['tedad'] ?? '0';
    
    // Agrupar galpones únicos para la leyenda (por CENCO)
    $keyLeyenda = $cenco . '_' . $galponKey;
    if (!isset($galponesUnicos[$keyLeyenda])) {
        $galponesUnicos[$keyLeyenda] = [
            'tcencos' => $cenco,
            'tgranja' => $bloque['tgranja'],
            'tgalpon' => $galponKey,
            'tedad' => $edadGalpon
        ];
    } else {
        // Si la edad actual es 0 o vacía y la nueva no lo es, actualizar
        if (($galponesUnicos[$keyLeyenda]['tedad'] == '0' || empty($galponesUnicos[$keyLeyenda]['tedad'])) 
            && $edadGalpon != '0' && !empty($edadGalpon)) {
            $galponesUnicos[$keyLeyenda]['tedad'] = $edadGalpon;
        }
    }
    
    // Agrupar bloques directamente por CENCO
    if (!isset($galponesPorCenco[$cenco])) {
        $galponesPorCenco[$cenco] = [
            'granja' => $bloque['tgranja'],
            'bloques' => [] // Lista de bloques (claves) para este CENCO
        ];
    }
    
    // Agregar bloque si no existe
    if (!in_array($keyBloque, $galponesPorCenco[$cenco]['bloques'])) {
        $galponesPorCenco[$cenco]['bloques'][] = $keyBloque;
    }
}
// Ordenar galpones
ksort($galponesUnicos);

// Generar reporte según formato
if ($formato === 'excel') {
    _generarExcel($bloques, $fecha_inicio, $fecha_fin, $cencosUnicos, $esComparativoCencos, $todosNiveles, $galponesUnicos, $galponesPorCenco);
} else {
    _generarPDF($bloques, $fecha_inicio, $fecha_fin, $cencosUnicos, $esComparativoCencos, $todosNiveles, $galponesUnicos, $galponesPorCenco);
}

function _generarPDF($bloques, $fecha_inicio, $fecha_fin, $cencosUnicos, $esComparativoCencos, $todosNiveles, $galponesUnicos, $galponesPorCenco) {
    require_once '../../vendor/autoload.php';
    
    $numColumnas = 3 + (2 * count($galponesUnicos));
    $anchoMM = max(297, 60 + ($numColumnas * 28));

    
    try {
        // Evitar que cualquier Notice/Warning (o algún echo accidental) se mezcle con la salida binaria del PDF.
        // Esto es especialmente importante en servidores con display_errors=On, donde un Notice rompe el PDF.
        $oldDisplayErrors = ini_get('display_errors');
        $oldErrorReporting = error_reporting();
        ini_set('display_errors', '0');
        // Suprimir warnings/notices durante la generación del PDF (mPDF 8.0 puede emitir Notices en PHP 8.x)
        error_reporting($oldErrorReporting & ~E_NOTICE & ~E_WARNING);

        // Buffer para poder limpiar cualquier salida previa antes de enviar el PDF
        if (ob_get_level() === 0) {
            ob_start();
        }

        // Asegurar tempDir existente y escribible
        $tempDir = __DIR__ . '/../../pdf_tmp';
        if (!is_dir($tempDir)) {
            @mkdir($tempDir, 0775, true);
        }
        if (!is_dir($tempDir) || !is_writable($tempDir)) {
            $tempDir = sys_get_temp_dir();
        }

        $mpdf = new \Mpdf\Mpdf([
            'mode' => 'utf-8',
            'format' => [210,  $anchoMM], 
            'orientation' => 'L',
            'margin_left' => 8,
            'margin_right' => 8,
            'margin_top' => 15,
            'margin_bottom' => 10,
            'margin_header' => 5,
            'margin_footer' => 8,
            'tempDir' => $tempDir,
        ]);

        // Header/Footer se definen dentro del HTML con htmlpageheader/htmlpagefooter
        // (más confiable cuando hay tablas grandes y @page en el HTML)


        $html = _generarHTMLReporte(
            $bloques, 
            $fecha_inicio, 
            $fecha_fin, 
            $cencosUnicos, 
            $esComparativoCencos, 
            $todosNiveles,
            $galponesUnicos,
            $galponesPorCenco 
        );
        
        $mpdf->WriteHTML($html);

        // Limpiar cualquier salida (por ejemplo Notices) antes de imprimir el PDF
        if (ob_get_level() > 0) {
            ob_clean();
        }
        $mpdf->Output('reporte_comparativo_' . date('Ymd_His') . '.pdf', 'I');
        exit;
        
    } catch (Exception $e) {
        // Si había buffer activo, limpiar para evitar "Data has already been sent..."
        if (ob_get_level() > 0) {
            @ob_end_clean();
        }
        // Restaurar configuración de errores si alcanzamos el catch
        if (isset($oldDisplayErrors)) {
            @ini_set('display_errors', $oldDisplayErrors);
        }
        if (isset($oldErrorReporting)) {
            @error_reporting($oldErrorReporting);
        }
        die('Error generando PDF: ' . $e->getMessage());
    } finally {
        // Restaurar configuración de errores (cuando no hacemos exit antes)
        if (isset($oldDisplayErrors)) {
            @ini_set('display_errors', $oldDisplayErrors);
        }
        if (isset($oldErrorReporting)) {
            @error_reporting($oldErrorReporting);
        }
    }
}

function _generarExcel($bloques, $fecha_inicio, $fecha_fin, $cencosUnicos, $esComparativoCencos, $todosNiveles, $galponesUnicos, $galponesPorCenco) {
    try {
    require_once '../../vendor/autoload.php';
    
        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    
        // === Logo ===
    $logoPath = __DIR__ . '/logo.png';
    $logoObj = null;
    if (file_exists($logoPath)) {
        $drawing = new \PhpOffice\PhpSpreadsheet\Worksheet\Drawing();
        $drawing->setPath($logoPath);
        $drawing->setHeight(20);
        $drawing->setCoordinates('A1');
        $logoObj = $drawing;
    }
    
 
    $headerStyle = [
        'font' => ['bold' => true, 'color' => ['rgb' => '0C4A6E']], // Color del texto igual al PDF
        'fill' => [
            'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
            'startColor' => ['rgb' => 'F0F9FF'] 
        ],
        'alignment' => ['horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER, 'vertical' => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER],
        'borders' => [
            'allBorders' => [
                'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
                'color' => ['rgb' => 'CBD5E1'] 
            ]
        ]
    ];
    
    $cellStyle = [
        'alignment' => ['horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_LEFT, 'vertical' => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_TOP],
        'borders' => [
            'allBorders' => [
                'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
                'color' => ['rgb' => 'CBD5E1'] 
            ]
        ]
    ];
    
    $row = 1;
    
    // Calcular número de columnas totales
    $numCols = 2; // Nivel, Parámetro (o Sistema si se usa)
    foreach ($galponesPorCenco as $info) {
        $numBloques = isset($info['bloques']) && is_array($info['bloques']) ? count($info['bloques']) : 0;
        $numCols += $numBloques * 2; // % y Obs por cada bloque del CENCO
    }
    // Si hay más de un CENCO, no contamos Sistema como columna separada
    if (count($galponesPorCenco) <= 1) {
        $numCols += 1; // Sistema
    }
    $lastCol = $sheet->getCellByColumnAndRow($numCols, 1)->getColumn();
    
    // === Header con Logo (igual al PDF) ===
    // Calcular proporciones: 20% logo, 60% título, 20% vacía
    $logoCols = max(1, floor($numCols * 0.2));
    $titleCols = max(1, floor($numCols * 0.6));
    $emptyCols = $numCols - $logoCols - $titleCols;
    
    $logoEndCol = $sheet->getCellByColumnAndRow($logoCols, $row)->getColumn();
    $titleStartCol = $sheet->getCellByColumnAndRow($logoCols + 1, $row)->getColumn();
    $titleEndCol = $sheet->getCellByColumnAndRow($logoCols + $titleCols, $row)->getColumn();
    $emptyStartCol = $sheet->getCellByColumnAndRow($logoCols + $titleCols + 1, $row)->getColumn();
    
    // Celda 1: Logo y nombre de empresa (20% del ancho)
    if ($logoObj) {
        $logoObj->setCoordinates('A' . $row);
        $logoObj->setOffsetX(5);
        $logoObj->setOffsetY(5);
        $logoObj->setWorksheet($sheet);
        $sheet->getRowDimension($row)->setRowHeight(30);
    }
    $sheet->setCellValue('A' . $row, 'GRANJA RINCONADA DEL SUR S.A.');
    $sheet->mergeCells('A' . $row . ':' . $logoEndCol . $row);
    $sheet->getStyle('A' . $row)->applyFromArray([
        'font' => ['size' => 8, 'color' => ['rgb' => '334155']],
        'alignment' => ['horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_LEFT, 'vertical' => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER],
        'borders' => [
            'allBorders' => [
                'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
                'color' => ['rgb' => 'CBD5E1']
            ]
        ],
        'fill' => [
            'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
            'startColor' => ['rgb' => 'FFFFFF']
        ]
    ]);
    
    // Celda 2: Título del reporte (60% del ancho)
    $sheet->setCellValue($titleStartCol . $row, 'REPORTE COMPARATIVO DE NECROPSIA');
    $sheet->mergeCells($titleStartCol . $row . ':' . $titleEndCol . $row);
    $sheet->getStyle($titleStartCol . $row)->applyFromArray([
        'font' => ['bold' => true, 'size' => 14, 'color' => ['rgb' => '000000']],
        'alignment' => ['horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER, 'vertical' => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER],
        'borders' => [
            'allBorders' => [
                'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
                'color' => ['rgb' => 'CBD5E1']
            ]
        ],
        'fill' => [
            'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
            'startColor' => ['rgb' => 'E6F2FF']
        ]
    ]);
    
    // Celda 3: Vacía (20% del ancho, igual al PDF)
    $sheet->setCellValue($emptyStartCol . $row, '');
    $sheet->mergeCells($emptyStartCol . $row . ':' . $lastCol . $row);
    $sheet->getStyle($emptyStartCol . $row)->applyFromArray([
        'borders' => [
            'allBorders' => [
                'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
                'color' => ['rgb' => 'CBD5E1']
            ]
        ],
        'fill' => [
            'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
            'startColor' => ['rgb' => 'FFFFFF']
        ]
    ]);
    $row++;
    
    // === Período ===
    $fecha_inicio_formatted = date('d/m/Y', strtotime($fecha_inicio));
    $fecha_fin_formatted = date('d/m/Y', strtotime($fecha_fin));
    $sheet->setCellValue('A' . $row, 'Período: ' . $fecha_inicio_formatted . ' - ' . $fecha_fin_formatted);
        $sheet->mergeCells('A' . $row . ':' . $lastCol . $row);
        $sheet->getStyle('A' . $row)->applyFromArray([
        'font' => ['bold' => true, 'size' => 10, 'color' => ['rgb' => '1E3A8A']],
        'alignment' => ['horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER],
            'fill' => [
            'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
            'startColor' => ['rgb' => 'FFFFFF']
            ]
        ]);
        $row++;
        
    $coloresBaseCenco = [
        '#3b82f6', '#10b981', '#f59e0b', '#ef4444', '#8b5cf6',
        '#06b6d4', '#ec4899', '#14b8a6', '#f97316', '#84cc16',
        '#6366f1', '#22c55e', '#fbbf24', '#f87171', '#a78bfa',
        '#34d399', '#fb923c', '#60a5fa', '#c084fc', '#f472b6',
        '#38bdf8', '#4ade80', '#fbbf24', '#fb7185', '#a5b4fc'
    ];
    
    $coloresPorCenco = [];
    $idxColor = 0;
   
    $hslToHex = function($h, $s, $l) {
        $h /= 360;
        $s /= 100;
        $l /= 100;
        $r = $l;
        $g = $l;
        $b = $l;
        $v = ($l <= 0.5) ? ($l * (1.0 + $s)) : ($l + $s - $l * $s);
        if ($v > 0) {
            $m = $l + $l - $v;
            $sv = ($v - $m) / $v;
            $h *= 6.0;
            $sextant = floor($h);
            $fract = $h - $sextant;
            $vsf = $v * $sv * $fract;
            $mid1 = $m + $vsf;
            $mid2 = $v - $vsf;
            switch ($sextant) {
                case 0: $r = $v; $g = $mid1; $b = $m; break;
                case 1: $r = $mid2; $g = $v; $b = $m; break;
                case 2: $r = $m; $g = $v; $b = $mid1; break;
                case 3: $r = $m; $g = $mid2; $b = $v; break;
                case 4: $r = $mid1; $g = $m; $b = $v; break;
                case 5: $r = $v; $g = $m; $b = $mid2; break;
            }
        }
        $r = round($r * 255);
        $g = round($g * 255);
        $b = round($b * 255);
        return sprintf("%02x%02x%02x", $r, $g, $b);
    };
    

    // Asignar colores a cada CENCO - mejorado para evitar repeticiones
    $coloresUsados = [];
    foreach (array_keys($galponesPorCenco) as $cenco) {
        if ($idxColor < count($coloresBaseCenco)) {
            $colorHex = strtoupper(ltrim($coloresBaseCenco[$idxColor], '#'));
            $coloresPorCenco[$cenco] = $colorHex;
            $coloresUsados[] = $colorHex;
    } else {
            // Generar color dinámicamente usando HSL con golden angle para distribución uniforme
            $hue = (($idxColor - count($coloresBaseCenco)) * 137.508) % 360;
            $saturation = 50 + (($idxColor % 5) * 4); // Entre 50-66%
            $lightness = 45 + (($idxColor % 4) * 3); // Entre 45-54%
            $colorHex = strtoupper(ltrim($hslToHex($hue, $saturation, $lightness), '#'));
            
            // Verificar que no sea muy similar a colores ya usados
            $intentos = 0;
            while ($intentos < 10 && in_array($colorHex, $coloresUsados)) {
                $hue = ($hue + 30) % 360;
                $saturation = 50 + (($idxColor + $intentos) % 5 * 4);
                $lightness = 45 + (($idxColor + $intentos) % 4 * 3);
                $colorHex = strtoupper(ltrim($hslToHex($hue, $saturation, $lightness), '#'));
                $intentos++;
            }
            
            $coloresPorCenco[$cenco] = $colorHex;
            $coloresUsados[] = $colorHex;
        }
        $idxColor++;
    }
    
    
    if (!empty($galponesPorCenco)) {
        $row++;
        
       
        $sheet->setCellValue('A' . $row, 'LEYENDA');
        $sheet->mergeCells('A' . $row . ':C' . $row);
        $sheet->getStyle('A' . $row)->applyFromArray([
            'font' => ['bold' => true, 'size' => 11, 'color' => ['rgb' => '1E40AF']],
            'fill' => [
                'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                'startColor' => ['rgb' => 'F8FAFC']
            ],
            'borders' => [
                'allBorders' => [
                    'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
                    'color' => ['rgb' => 'CBD5E1']
                ]
            ]
        ]);
        $row++;
        
        // Cabeceras de la tabla de leyenda
        $sheet->setCellValue('A' . $row, 'Color');
        $sheet->setCellValue('B' . $row, 'CENCO');
        $sheet->setCellValue('C' . $row, 'Galpones');
        // Aumentar ancho de columna CENCO
        $sheet->getColumnDimension('B')->setWidth(25);
        $sheet->getStyle('A' . $row . ':C' . $row)->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => '1E40AF']],
            'fill' => [
                'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                'startColor' => ['rgb' => 'E6F2FF']
            ],
            'borders' => [
                'allBorders' => [
                    'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
                    'color' => ['rgb' => 'CBD5E1']
                ]
            ],
            'alignment' => ['horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_LEFT, 'vertical' => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER]
        ]);
        $row++;
        
        // Filas de la leyenda
        foreach ($galponesPorCenco as $cenco => $info) {
            $colorHex = $coloresPorCenco[$cenco];
            
            // Construir lista de galpones (sin edades, agrupados por CENCO)
            $galponesList = [];
            foreach ($galponesUnicos as $keyLeyenda => $det) {
                if ($det['tcencos'] == $cenco) {
                    $galpon = $det['tgalpon'];
                    $texto = 'Galpón ' . $galpon;
                    $galponesList[] = $texto;
                }
            }
            $galponesStr = implode(', ', $galponesList);
            
            // Color (celda con fondo de color)
            $sheet->setCellValue('A' . $row, '');
            $sheet->getStyle('A' . $row)->applyFromArray([
                'fill' => [
                    'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                    'startColor' => ['rgb' => $colorHex]
                ],
                'borders' => [
                    'allBorders' => [
                        'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
                        'color' => ['rgb' => 'CBD5E1']
                    ]
                ]
            ]);
            
            // CENCO
            $sheet->setCellValue('B' . $row, 'CENCO ' . $cenco);
            $sheet->getStyle('B' . $row)->applyFromArray([
                'font' => ['bold' => true, 'color' => ['rgb' => '1E293B']],
                'borders' => [
                    'allBorders' => [
                        'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
                        'color' => ['rgb' => 'CBD5E1']
                    ]
                ],
                'alignment' => ['horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_LEFT, 'vertical' => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER]
            ]);
            
            // Galpones
            $sheet->setCellValue('C' . $row, $galponesStr);
            $sheet->getStyle('C' . $row)->applyFromArray([
                'font' => ['color' => ['rgb' => '475569']],
                'borders' => [
                    'allBorders' => [
                        'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
                        'color' => ['rgb' => 'CBD5E1']
                    ]
                ],
                'alignment' => ['horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_LEFT, 'vertical' => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER],
                'wrapText' => true
            ]);
            
            // Aplicar fondo alternado
            if ($row % 2 == 0) {
                $sheet->getStyle('B' . $row . ':C' . $row)->applyFromArray([
                    'fill' => [
                        'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                        'startColor' => ['rgb' => 'F8FAFC']
                    ]
                ]);
            }
            
            $row++;
        }
        
        // Ajustar ancho de columnas de la leyenda
        $sheet->getColumnDimension('A')->setWidth(12);
        $sheet->getColumnDimension('B')->setWidth(25); // Aumentado para CENCO
        $sheet->getColumnDimension('C')->setWidth(50);
        
        $row++; // Espacio después de la leyenda
    }
    
    // === Función para ordenar niveles y parámetros según orden específico ===
    function ordenarNivelesYParametros($sistema, $niveles) {
        $sLower = mb_strtolower(trim($sistema), 'UTF-8');
        $nivelesOrdenados = [];
        
        // Definir orden de niveles y parámetros por sistema
        $ordenNiveles = [];
        
        if (strpos($sLower, 'inmunol') !== false) {
            // Sistema Inmunológico
            $ordenNiveles = [
                'Índice Bursal' => ['Normal', 'Atrofia', 'Severa Atrofia'],
                'Mucosa de la bursa' => ['Normal', 'Petequias', 'Hemorragia'],
                'Timos' => ['Normal', 'Atrofiados', 'Aspecto Normal', 'Congestionados']
            ];
        } elseif (strpos($sLower, 'digestiv') !== false) {
            // Sistema Digestivo
            $ordenNiveles = [
                'Hígados' => ['Normal', 'Esteatosico', 'Tamaño Normal', 'Hipertrofiado'],
                'Vesícula biliar' => ['Color normal', 'Color claro', 'Tamaño normal', 'Atrofiado', 'Hipertrofiado'],
                'Erosión de molleja' => ['Normal', 'Grado 1', 'Grado 2', 'Grado 3', 'Grado 4'],
                'Retracción del páncreas' => ['Normal', 'Retraido'],
                'Absorcion del saco vitelino' => ['Si', 'No'],
                'Enteritis' => ['Normal', 'Leve', 'Moderado', 'Severo'],
                'Contenido cecal' => ['Normal', 'Gas', 'Espuma'],
                'Alimento sin digerir' => ['Si', 'No'],
                'Heces anaranjadas' => ['Si', 'No'],
                'Lesión oral' => ['Si', 'No'],
                'Tonicidad Intestinal' => ['Buena', 'Regular', 'Mala']
            ];
        } elseif (strpos($sLower, 'respirat') !== false) {
            // Sistema Respiratorio
            $ordenNiveles = [
                'Tráquea' => ['Normal', 'Leve', 'Moderada', 'Severa'],
                'Pulmón' => ['Normal', 'Neumónico'],
                'Sacos aéreos' => ['Normal', 'Turbio', 'Con material caseoso']
            ];
        } elseif (strpos($sLower, 'evaluaci') !== false && strpos($sLower, 'físic') !== false) {
            // Evaluación física
            $ordenNiveles = [
                'Pododermatitis' => ['Grado 0', 'Grado 1', 'Grado 2', 'Grado 3', 'Grado 4'],
                'Color de tarsos' => ['3.5', '4', '4.5', '5', '5.5', '6']
            ];
        }
        
        // Si hay orden definido, aplicarlo
        if (!empty($ordenNiveles)) {
            foreach ($ordenNiveles as $nivelNombre => $parametrosOrden) {
                if (isset($niveles[$nivelNombre])) {
                    $parametros = $niveles[$nivelNombre];
                    // Ordenar parámetros según el orden definido
                    $parametrosOrdenados = [];
                    $parametrosRestantes = [];
                    
                    // Primero agregar los parámetros en el orden especificado
                    foreach ($parametrosOrden as $paramOrden) {
                        foreach ($parametros as $param) {
                            if (mb_strtolower(trim($param)) === mb_strtolower(trim($paramOrden))) {
                                $parametrosOrdenados[] = $param;
                                break;
                            }
                        }
                    }
                    
                    // Agregar parámetros que no están en el orden definido
                    foreach ($parametros as $param) {
                        if (!in_array($param, $parametrosOrdenados)) {
                            $parametrosRestantes[] = $param;
                        }
                    }
                    
                    $nivelesOrdenados[$nivelNombre] = array_merge($parametrosOrdenados, $parametrosRestantes);
                }
            }
            
            // Agregar niveles que no están en el orden definido
            foreach ($niveles as $nivelNombre => $parametros) {
                if (!isset($nivelesOrdenados[$nivelNombre])) {
                    $nivelesOrdenados[$nivelNombre] = $parametros;
                }
            }
        } else {
            // Si no hay orden definido, mantener el orden original
            $nivelesOrdenados = $niveles;
        }
        
        return $nivelesOrdenados;
    }
    
    // === Mapa de datos usando bloques como clave ===
    // Estructura: $mapaCompleto[sistema][nivel][parametro][keyBloque] = [porc, obs, ...]
    $mapaCompleto = [];
    foreach ($bloques as $keyBloque => $bloque) {
        foreach ($bloque['registros'] as $reg) {
            $s = $reg['tsistema'] ?? '';
            $n = $reg['tnivel'] ?? '';
            $p = $reg['tparametro'] ?? '';
            
            if ($s !== '' && $n !== '' && $p !== '') {
                // Usar la clave del bloque como identificador
                $mapaCompleto[$s][$n][$p][$keyBloque] = [
                    'porc' => $reg['tporcentajetotal'] ?? '0',
                    'obs' => $reg['tobservacion'] ?? '',
                    'cenco' => $bloque['tcencos']
                ];
            }
        }
    }
    
    // === Ordenar sistemas (igual al PDF) ===
    $sistemasFinales = [];
    $otros = [];
    foreach ($todosNiveles as $sistema => $niveles) {
        $sLower = mb_strtolower(trim($sistema), 'UTF-8');
        $pos = false;
        if (strpos($sLower, 'inmunol') !== false) $pos = 0;
        elseif (strpos($sLower, 'digestiv') !== false) $pos = 1;
        elseif (strpos($sLower, 'respirat') !== false) $pos = 2;
        elseif (strpos($sLower, 'evaluaci') !== false && strpos($sLower, 'físic') !== false) $pos = 3;
        
        // Ordenar niveles y parámetros dentro del sistema
        $nivelesOrdenados = ordenarNivelesYParametros($sistema, $niveles);
        
        if ($pos !== false) {
            $sistemasFinales[$pos] = [$sistema => $nivelesOrdenados];
        } else {
            $otros[$sistema] = $nivelesOrdenados;
        }
    }
    ksort($sistemasFinales);
    $final = [];
    foreach ($sistemasFinales as $item) $final = array_merge($final, $item);
    $final = array_merge($final, $otros);
    
    // === Función auxiliar: convertir hex a rgb ===
    $hexToRgb = function($hex) {
        $hex = ltrim($hex, '#');
        if (strlen($hex) == 3) {
            $hex = str_repeat(substr($hex,0,1),2) . str_repeat(substr($hex,1,1),2) . str_repeat(substr($hex,2,1),2);
        }
        return [
            'r' => hexdec(substr($hex,0,2)),
            'g' => hexdec(substr($hex,2,2)),
            'b' => hexdec(substr($hex,4,2))
        ];
    };
    
    // === Por cada sistema (igual al PDF) ===
    foreach ($final as $sistema => $niveles) {
        // Título del sistema
        $sheet->setCellValue('A' . $row, strtoupper($sistema));
        $sheet->mergeCells('A' . $row . ':' . $lastCol . $row);
        $sheet->getStyle('A' . $row)->applyFromArray([
            'font' => ['bold' => true, 'size' => 11, 'color' => ['rgb' => '1E40AF']],
            'fill' => [
                'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                'startColor' => ['rgb' => 'DBEAFE']
            ],
            'alignment' => ['horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER, 'vertical' => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER],
            'borders' => [
                'allBorders' => [
                    'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
                    'color' => ['rgb' => '93C5FD']
                ]
            ]
        ]);
    $row++;
    
        // === Fila 1: Agrupación por CENCO (igual al PDF) ===
        $headerRow1 = $row;
    $col = 1;
        
        if (count($galponesPorCenco) > 1) {
            // Nivel (rowspan 3)
            $sheet->setCellValueByColumnAndRow($col++, $row, 'Nivel');
            // Parámetro (rowspan 3)
            $sheet->setCellValueByColumnAndRow($col++, $row, 'Parámetro');
            
            // Fila 1: CENCOS (sin fechas)
            foreach ($galponesPorCenco as $cenco => $info) {
                $colorHex = isset($coloresPorCenco[$cenco]) ? $coloresPorCenco[$cenco] : '3B82F6';
                $colorHex = strtoupper(ltrim($colorHex, '#'));
                // Calcular columnas considerando bloques del CENCO
                $numBloques = isset($info['bloques']) && is_array($info['bloques']) ? count($info['bloques']) : 0;
                $numColsCenco = $numBloques * 2; // % y Obs por cada bloque
                $startCol = $col;
                $endCol = $col + $numColsCenco - 1;
                
                // Construir texto del CENCO (sin fechas)
                $cencoText = 'CENCO ' . $cenco . "\n" . $info['granja'];
                
                $sheet->setCellValueByColumnAndRow($col, $row, $cencoText);
                $startCoord = $sheet->getCellByColumnAndRow($startCol, $row)->getCoordinate();
                $endCoord = $sheet->getCellByColumnAndRow($endCol, $row)->getCoordinate();
                $sheet->mergeCells($startCoord . ':' . $endCoord);
                // IMPORTANTE: aplicar estilo a TODO el rango combinado para que se vea el color
                $sheet->getStyle($startCoord . ':' . $endCoord)->applyFromArray([
                    'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF'], 'size' => 8.5],
                    'fill' => [
                        'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                        'startColor' => ['rgb' => $colorHex]
                    ],
                    'alignment' => ['horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER, 'vertical' => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER, 'wrapText' => true],
                    'borders' => [
                        'allBorders' => [
                            'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
                            'color' => ['rgb' => 'CBD5E1']
                        ]
                    ]
                ]);
                $col = $endCol + 1;
            }
        } else {
            // Si solo hay un CENCO, mostrar Nivel y Parámetro normalmente
            $sheet->setCellValueByColumnAndRow($col++, $row, 'Nivel');
            $sheet->setCellValueByColumnAndRow($col++, $row, 'Parámetro');
        }
    $row++;
    
        // === Fila 2: Fechas por bloque (celdas que abarcan cada par % y Obs) ===
    $col = 1;
        if (count($galponesPorCenco) > 1) {
            $col = 3; // Después de Nivel y Parámetro
        } else {
            $col = 3;
        }
        
        foreach ($galponesPorCenco as $cenco => $info) {
            $colorHex = isset($coloresPorCenco[$cenco]) ? $coloresPorCenco[$cenco] : '#3B82F6';
            // Iterar sobre los bloques del CENCO directamente
            if (isset($info['bloques']) && is_array($info['bloques'])) {
                foreach ($info['bloques'] as $keyBloque) {
                    if (isset($bloques[$keyBloque])) {
                        $bloque = $bloques[$keyBloque];
                        $tdateFormatted = date('d/m/Y', strtotime($bloque['tdate']));
                        $tfectraFormatted = date('d/m/Y', strtotime($bloque['tfectra']));
                        $edad = $bloque['tedad'] ?? '0';
                        $fechasText = "Reg: $tdateFormatted | Nec: $tfectraFormatted | Edad: $edad días";
                        
                        $startColFechas = $col;
                        $endColFechas = $col + 1; // Abarca % y Obs (2 columnas)
                        
                        $sheet->setCellValueByColumnAndRow($col, $row, $fechasText);
                        $sheet->mergeCells($sheet->getCellByColumnAndRow($startColFechas, $row)->getCoordinate() . ':' . $sheet->getCellByColumnAndRow($endColFechas, $row)->getCoordinate());
                        $sheet->getStyle($sheet->getCellByColumnAndRow($startColFechas, $row)->getCoordinate())->applyFromArray([
                            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF'], 'size' => 7.5],
                            'fill' => [
                                'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                                'startColor' => ['rgb' => $colorHex]
                            ],
                            'alignment' => ['horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER, 'vertical' => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER, 'wrapText' => true],
        'borders' => [
            'allBorders' => [
                                    'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
                                    'color' => ['rgb' => 'CBD5E1']
                                ]
                            ]
                        ]);
                        $col = $endColFechas + 1;
                    }
                }
            }
        }
    $row++;
    
        // === Fila 3: Encabezados individuales (% y Obs) ===
        $col = 1;
        
        // Si hay más de un CENCO, empezar después de Nivel (col 1) y Parámetro (col 2)
        if (count($galponesPorCenco) > 1) {
            $col = 3; // Después de Nivel y Parámetro
        } else {
            // Si solo hay un CENCO, ya pusimos Nivel y Parámetro en la fila anterior
            $col = 3;
        }
        
        foreach ($galponesPorCenco as $cenco => $info) {
            $colorHex = isset($coloresPorCenco[$cenco]) ? $coloresPorCenco[$cenco] : '#3b82f6';
            $rgb = $hexToRgb('#' . $colorHex);
            // Calcular color con transparencia (similar a rgba en PDF)
            $bgColorR = min(255, $rgb['r'] + round((255 - $rgb['r']) * 0.88));
            $bgColorG = min(255, $rgb['g'] + round((255 - $rgb['g']) * 0.88));
            $bgColorB = min(255, $rgb['b'] + round((255 - $rgb['b']) * 0.88));
            $bgColor = sprintf('%02X%02X%02X', $bgColorR, $bgColorG, $bgColorB);
            
            // Iterar sobre los bloques del CENCO directamente
            if (isset($info['bloques']) && is_array($info['bloques'])) {
                foreach ($info['bloques'] as $keyBloque) {
                    if (isset($bloques[$keyBloque])) {
                        $bloque = $bloques[$keyBloque];
                        $galpon = $bloque['tgalpon'];
                        
                        // % Galpón (sin fechas, solo número de galpón)
                        $headerText = '% Galpón ' . $galpon;
                        $sheet->setCellValueByColumnAndRow($col++, $row, $headerText);
                    $sheet->getStyle($sheet->getCellByColumnAndRow($col - 1, $row)->getCoordinate())->applyFromArray([
                        'font' => ['size' => 7.5, 'color' => ['rgb' => '0C4A6E']],
                        'fill' => [
                            'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                            'startColor' => ['rgb' => $bgColor]
                        ],
                        'alignment' => ['horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER, 'vertical' => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER, 'wrapText' => true],
                        'borders' => [
                            'allBorders' => [
                                'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
                                'color' => ['rgb' => 'CBD5E1']
                            ]
                        ]
                    ]);
                    
                    $obsHeaderText = 'Obs Galpón ' . $galpon;
                    $sheet->setCellValueByColumnAndRow($col++, $row, $obsHeaderText);
                    $sheet->getStyle($sheet->getCellByColumnAndRow($col - 1, $row)->getCoordinate())->applyFromArray([
                        'font' => ['size' => 7.5, 'color' => ['rgb' => '0C4A6E']],
                        'fill' => [
                            'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                            'startColor' => ['rgb' => $bgColor]
                        ],
                        'alignment' => ['horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER, 'vertical' => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER, 'wrapText' => true],
                        'borders' => [
                            'allBorders' => [
                                'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
                                'color' => ['rgb' => 'CBD5E1']
                            ]
                        ]
                    ]);
                    }
                }
            }
        }
        
        $row++;
        
        // === Escribir datos (igual al PDF) ===
        foreach ($niveles as $nivel => $parametros) {
            $totalFilas = count($parametros);
            $nivelStartRow = $row;
            
            foreach ($parametros as $i => $parametro) {
                $col = 1;
                
                // Nivel (con rowspan) - solo en la primera fila del nivel
                if ($i === 0) {
                $sheet->setCellValueByColumnAndRow($col++, $row, $nivel);
                } else {
                    $col++; // Saltar columna de Nivel
                }
                
                // Parámetro
                $sheet->setCellValueByColumnAndRow($col++, $row, $parametro);
                
                // Datos por CENCO (iterar sobre bloques directamente)
                foreach ($galponesPorCenco as $cenco => $info) {
                    // Iterar sobre los bloques del CENCO directamente
                    if (isset($info['bloques']) && is_array($info['bloques'])) {
                        foreach ($info['bloques'] as $keyBloque) {
                            // Verificar que el dato pertenezca a este CENCO
                            $porc = '0';
                            $obs = '';
                            if (isset($mapaCompleto[$sistema][$nivel][$parametro][$keyBloque])) {
                                $datosRegistro = $mapaCompleto[$sistema][$nivel][$parametro][$keyBloque];
                                // Solo usar si el CENCO coincide
                                if (isset($datosRegistro['cenco']) && $datosRegistro['cenco'] == $cenco) {
                                    $porc = $datosRegistro['porc'] ?? '0';
                                    $obs = $datosRegistro['obs'] ?? '';
                                }
                            }
                            
                            // % Galpón (par de % y Obs para este bloque del CENCO)
                            $sheet->setCellValueByColumnAndRow($col++, $row, $porc);
                            
                            // Obs Galpón (solo en la primera fila del nivel, con rowspan)
                            if ($i === 0) {
                                $primerParametro = !empty($parametros) ? $parametros[0] : '';
                                $obsPrimero = '';
                                if (isset($mapaCompleto[$sistema][$nivel][$primerParametro][$keyBloque])) {
                                    $datosPrimero = $mapaCompleto[$sistema][$nivel][$primerParametro][$keyBloque];
                                    if (isset($datosPrimero['cenco']) && $datosPrimero['cenco'] == $cenco) {
                                        $obsPrimero = $datosPrimero['obs'] ?? '';
                                    }
                                }
                                $sheet->setCellValueByColumnAndRow($col++, $row, $obsPrimero);
                            } else {
                                $col++; // Saltar columna de Obs
                            }
                        }
                    }
                }
                
                // Aplicar estilos básicos a la fila
                $rowRange = $sheet->getCellByColumnAndRow(1, $row)->getCoordinate() . ':' . $sheet->getCellByColumnAndRow($numCols, $row)->getCoordinate();
                $sheet->getStyle($rowRange)->applyFromArray([
                    'borders' => [
                        'allBorders' => [
                            'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
                            'color' => ['rgb' => 'CBD5E1']
                        ]
                    ]
                ]);
                
                $row++;
            }
            
            // Aplicar rowspan a Nivel después de escribir todas las filas del nivel (siempre, como en el PDF)
            $sheet->mergeCells($sheet->getCellByColumnAndRow(1, $nivelStartRow)->getCoordinate() . ':' . $sheet->getCellByColumnAndRow(1, $nivelStartRow + $totalFilas - 1)->getCoordinate());
            $sheet->getStyle($sheet->getCellByColumnAndRow(1, $nivelStartRow)->getCoordinate())->applyFromArray([
                'alignment' => ['horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER, 'vertical' => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER],
                'borders' => [
                    'allBorders' => [
                        'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
                        'color' => ['rgb' => 'CBD5E1']
                    ]
                ]
            ]);
       
            $obsCol = 4;
            foreach ($galponesPorCenco as $cenco => $info) {
                // Iterar sobre los bloques del CENCO directamente
                if (isset($info['bloques']) && is_array($info['bloques'])) {
                    foreach ($info['bloques'] as $keyBloque) {
                        $sheet->mergeCells($sheet->getCellByColumnAndRow($obsCol, $nivelStartRow)->getCoordinate() . ':' . $sheet->getCellByColumnAndRow($obsCol, $nivelStartRow + $totalFilas - 1)->getCoordinate());
                        $sheet->getStyle($sheet->getCellByColumnAndRow($obsCol, $nivelStartRow)->getCoordinate())->applyFromArray([
                            'alignment' => ['horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_LEFT, 'vertical' => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_TOP, 'wrapText' => true],
                            'borders' => [
                                'allBorders' => [
                                    'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
                                    'color' => ['rgb' => 'CBD5E1']
                                ]
                            ]
                        ]);
                        $obsCol += 2; 
                    }
                }
            }
        }
        
        $row++; 
    }
    
    
    $sheet->getColumnDimension('A')->setWidth(20);
    $sheet->getColumnDimension('B')->setWidth(20);
    $sheet->getColumnDimension('C')->setWidth(25);
    for ($i = 0; $i < count($galponesUnicos) * 2; $i++) {
        $colLetter = $sheet->getCellByColumnAndRow(4 + $i, 1)->getColumn();
        $sheet->getColumnDimension($colLetter)->setWidth(15);
    }
    
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment;filename="reporte_comparativo_' . date('Ymd_His') . '.xlsx"');
    header('Cache-Control: max-age=0');
    
        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
    $writer->save('php://output');
    exit;
    } catch (Exception $e) {
        die('Error generando Excel: ' . $e->getMessage());
    }
}

function _generarHTMLReporte($bloques, $fecha_inicio, $fecha_fin, $cencosUnicos, $esComparativoCencos, $todosNiveles, $galponesUnicos, $galponesPorCenco = []) {

    $logoPath = __DIR__ . '/logo.png';
    $logo = '';
    if (file_exists($logoPath)) {
        $logoData = file_get_contents($logoPath);
        $logoBase64 = 'data:image/png;base64,' . base64_encode($logoData);
        $logo = '<img src="' . htmlspecialchars($logoBase64) . '" style="height: 20px; vertical-align: top;">';
    }

    $fechaReporte = (new DateTime('now', new DateTimeZone('America/Lima')))->format('d/m/Y H:i');

    $html = '<html><head><meta charset="UTF-8"><style>
        @page {
            margin-top: 20mm;
            margin-bottom: 14mm; /* espacio para pie sin empujar demasiado el contenido */
            margin-left: 8mm;
            margin-right: 8mm;
            header: html_myheader;
            footer: html_myfooter;
        }
        body {
            font-family: "Segoe UI", Arial, sans-serif;
            font-size: 8.5pt;
            color: #1e293b;
            margin: 0;
            padding: 0;
        }
        .header-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 10px;
        }
        .header-title-cell {
            width: 75%;
            background-color: #dbeafe;
            text-align: center; 
            font-weight: bold;
            font-size: 12pt;
            padding: 8px;
            border: 1px solid #93c5fd;
            color: #1e40af;
        }
        .header-logo-cell {
            width: 25%;
            text-align: center;
            vertical-align: middle;
            padding: 6px;
            border: 1px solid #93c5fd;
        }
        .header-logo-cell img {
            width: 28px;
            height: auto;
        }
        .header-logo-cell .logo-text {
            font-size: 6.5pt;
            color: #334155;
            line-height: 1.2;
            margin-top: 3px;
        }
        .periodo {
            font-weight: bold;
            margin: 8px 0;
            font-size: 10pt;
            text-align: center;
            color: #1e3a8a;
        }
        .leyenda-box {
            background-color: #f8fafc;
            border: 1px solid #cbd5e1;
            border-radius: 6px;
            padding: 10px;
            margin: 10px 0;
            font-size: 8.5pt;
        }
        .leyenda-title {
            font-weight: bold;
            margin-bottom: 8px;
            color: #1e40af;
            font-size: 9.5pt;
        }
        .leyenda-table {
            width: 100%; 
            border-collapse: collapse; 
            background-color: white;
            font-size: 8.5pt;
        }
        .leyenda-table th {
            background-color: #e6f2ff;
            color: #1e40af;
            font-weight: bold;
            padding: 6px 8px;
            text-align: left; 
            border: 1px solid #cbd5e1;
        }
        .leyenda-table td {
            padding: 6px 8px;
            border: 1px solid #cbd5e1;
            vertical-align: middle;
        }
        .leyenda-table tr:nth-child(even) {
            background-color: #f8fafc;
        }
        .leyenda-color-cell {
            width: 30px;
            text-align: center;
            padding: 4px !important;
            background-color: #fff;
        }
        .leyenda-color-box {
            width: 16px;
            height: 16px;
            border: 1px solid #000;
            display: block;
            margin: 0 auto;
        }
        .leyenda-cenco {
            font-weight: 600;
            color: #1e293b;
        }
        .leyenda-galpones {
            color: #475569;
        }
        table.data-table {
            width: 100%;
            border-collapse: collapse;
            margin: 0 0 14px 0;
            font-size: 8.5pt;
            page-break-inside: auto;
        }
        .data-table th,
        .data-table td {
            padding: 5px;
            border: 1px solid #cbd5e1;
            vertical-align: top;
            text-align: center;
        }
        .data-table th {
            background-color: #f0f9ff;
            color: #0c4a6e;
            font-weight: bold;
            font-size: 8.5pt;
        }
        .sistema-header {
            background-color: #dbeafe;
            color: #1e40af;
            font-weight: bold;
            font-size: 11pt;
            text-align: center;
            padding: 6px;
            border: 1px solid #93c5fd;
            margin: 12px 0 6px 0;
            page-break-after: avoid;
        }
        .cenco-group-header {
            font-weight: bold;
            color: white;
            text-align: center;
            padding: 4px;
            font-size: 8.5pt;
        }
        .galpon-col-porc, .galpon-col-obs {
            font-size: 8pt;
        }
    </style></head><body>
    <htmlpageheader name="myheader">
        <div style="text-align:right; font-size:9pt; color:#475569;">' . htmlspecialchars($fechaReporte) . '</div>
    </htmlpageheader>
    <htmlpagefooter name="myfooter">
        <div style="text-align:center; font-size:9pt; color:#475569;">Página {PAGENO} de {nbpg}</div>
    </htmlpagefooter>';
    
    // === Cabecera conjunta ===
    $html .= '<table width="100%" style="border-collapse: collapse; border: 1px solid #cbd5e1; margin-top: 10px; margin-bottom: 0;">';
    $html .= '<tr>';
    if (!empty($logo)) {
        $html .= '<td style="width: 20%; text-align: left; padding: 5px; background-color: #fff; font-size: 8pt; white-space: nowrap; border: 1px solid #cbd5e1;">';
        $html .= $logo . ' GRANJA RINCONADA DEL SUR S.A.';
        $html .= '</td>';
    } else {
        $html .= '<td style="width: 20%; text-align: left; padding: 5px; background-color: #fff; font-size: 8pt; white-space: nowrap; border: 1px solid #cbd5e1;">';
        $html .= 'GRANJA RINCONADA DEL SUR S.A.';
        $html .= '</td>';
    }
    $html .= '<td style="width: 60%; text-align: center; padding: 5px; background-color: #e6f2ff; color: #000; font-weight: bold; font-size: 14px; border: 1px solid #cbd5e1;">';
    $html .= 'REPORTE COMPARATIVO DE NECROPSIA';
    $html .= '</td>';
    $html .= '<td style="width: 20%; background-color: #fff; border: 1px solid #cbd5e1;"></td>';
    $html .= '</tr>';
    $html .= '</table>';
    
    // === Período ===
    $fecha_inicio_formatted = date('d/m/Y', strtotime($fecha_inicio));
    $fecha_fin_formatted = date('d/m/Y', strtotime($fecha_fin));
    $html .= '<div class="periodo">Período: ' . htmlspecialchars($fecha_inicio_formatted) . ' - ' . htmlspecialchars($fecha_fin_formatted) . '</div>';

    // === Colores por CENCO  ===

    $coloresBaseCenco = [
        '#3b82f6', '#10b981', '#f59e0b', '#ef4444', '#8b5cf6',
        '#06b6d4', '#ec4899', '#14b8a6', '#f97316', '#84cc16',
        '#6366f1', '#22c55e', '#fbbf24', '#f87171', '#a78bfa',
        '#34d399', '#fb923c', '#60a5fa', '#c084fc', '#f472b6',
        '#38bdf8', '#4ade80', '#fbbf24', '#fb7185', '#a5b4fc'
    ];
    
    $coloresPorCenco = [];
    $idxColor = 0;
    $numCencos = count($galponesPorCenco);
    
    // Función auxiliar para generar color hex desde HSL
    $hslToHex = function($h, $s, $l) {
        $h /= 360;
        $s /= 100;
        $l /= 100;
        $r = $l;
        $g = $l;
        $b = $l;
        $v = ($l <= 0.5) ? ($l * (1.0 + $s)) : ($l + $s - $l * $s);
        if ($v > 0) {
            $m = $l + $l - $v;
            $sv = ($v - $m) / $v;
            $h *= 6.0;
            $sextant = floor($h);
            $fract = $h - $sextant;
            $vsf = $v * $sv * $fract;
            $mid1 = $m + $vsf;
            $mid2 = $v - $vsf;
            switch ($sextant) {
                case 0: $r = $v; $g = $mid1; $b = $m; break;
                case 1: $r = $mid2; $g = $v; $b = $m; break;
                case 2: $r = $m; $g = $v; $b = $mid1; break;
                case 3: $r = $m; $g = $mid2; $b = $v; break;
                case 4: $r = $mid1; $g = $m; $b = $v; break;
                case 5: $r = $v; $g = $m; $b = $mid2; break;
            }
        }
        $r = round($r * 255);
        $g = round($g * 255);
        $b = round($b * 255);
        return sprintf("#%02x%02x%02x", $r, $g, $b);
    };
    

    $coloresUsados = [];
    foreach (array_keys($galponesPorCenco) as $cenco) {
        if ($idxColor < count($coloresBaseCenco)) {
            $coloresPorCenco[$cenco] = $coloresBaseCenco[$idxColor];
            $coloresUsados[] = strtoupper(ltrim($coloresBaseCenco[$idxColor], '#'));
        } else {
         
            $hue = (($idxColor - count($coloresBaseCenco)) * 137.508) % 360;
            $saturation = 50 + (($idxColor % 5) * 4); // Entre 50-66%
            $lightness = 45 + (($idxColor % 4) * 3); // Entre 45-54%
            $colorHex = $hslToHex($hue, $saturation, $lightness);
            

            $intentos = 0;
            $colorHexUpper = strtoupper(ltrim($colorHex, '#'));
            while ($intentos < 10 && in_array($colorHexUpper, $coloresUsados)) {
                $hue = ($hue + 30) % 360;
                $saturation = 50 + (($idxColor + $intentos) % 5 * 4);
                $lightness = 45 + (($idxColor + $intentos) % 4 * 3);
                $colorHex = $hslToHex($hue, $saturation, $lightness);
                $colorHexUpper = strtoupper(ltrim($colorHex, '#'));
                $intentos++;
            }
            
            $coloresPorCenco[$cenco] = $colorHex;
            $coloresUsados[] = $colorHexUpper;
        }
        $idxColor++;
    }

    // === Leyenda en tabla: agrupa CENCOS con sus galpones y edades ===
    if (!empty($galponesPorCenco)) {
        $html .= '<div class="leyenda-box">';
        $html .= '<div class="leyenda-title">LEYENDA</div>';
        $html .= '<table class="leyenda-table">';
        $html .= '<thead><tr>';
        $html .= '<th style="width: 30px;">Color</th>';
        $html .= '<th style="width: 200px;">CENCO</th>';
        $html .= '<th>Galpones</th>';
        $html .= '</tr></thead><tbody>';
        
        foreach ($galponesPorCenco as $cenco => $info) {
            $color = isset($coloresPorCenco[$cenco]) ? $coloresPorCenco[$cenco] : '#3b82f6';
            
            // Construir lista de galpones (sin edades, agrupados por CENCO)
            $galponesList = [];
            foreach ($galponesUnicos as $keyLeyenda => $det) {
                if ($det['tcencos'] == $cenco) {
                    $galpon = $det['tgalpon'];
                    $texto = 'Galpón ' . htmlspecialchars($galpon);
                    $galponesList[] = $texto;
                }
            }
            $galponesStr = implode(', ', $galponesList);
            
            $html .= '<tr>';
            $html .= '<td class="leyenda-color-cell" style="background-color: ' . $color . ';">';
            $html .= '<span class="leyenda-color-box" style="background-color: ' . $color . '; border-color: #000;"></span>';
            $html .= '</td>';
            $html .= '<td class="leyenda-cenco">CENCO ' . htmlspecialchars($cenco) . '</td>';
            $html .= '<td class="leyenda-galpones">' . $galponesStr . '</td>';
            $html .= '</tr>';
        }
        
        $html .= '</tbody></table></div>';
    }

    // === Mapa de datos usando bloques como clave ===
    // Estructura: $mapaCompleto[sistema][nivel][parametro][keyBloque] = [porc, obs, ...]
    $mapaCompleto = [];
    foreach ($bloques as $keyBloque => $bloque) {
        foreach ($bloque['registros'] as $reg) {
            $s = $reg['tsistema'] ?? '';
            $n = $reg['tnivel'] ?? '';
            $p = $reg['tparametro'] ?? '';
            
            if ($s !== '' && $n !== '' && $p !== '') {
                // Usar la clave del bloque como identificador
                $mapaCompleto[$s][$n][$p][$keyBloque] = [
                    'porc' => $reg['tporcentajetotal'] ?? '0',
                    'obs' => $reg['tobservacion'] ?? '',
                    'cenco' => $bloque['tcencos']
                ];
            }
        }
    }

    // === Función para ordenar niveles y parámetros según orden específico ===
    function ordenarNivelesYParametros($sistema, $niveles) {
        $sLower = mb_strtolower(trim($sistema), 'UTF-8');
        $nivelesOrdenados = [];
        
        // Definir orden de niveles y parámetros por sistema
        $ordenNiveles = [];
        
        if (strpos($sLower, 'inmunol') !== false) {
            // Sistema Inmunológico
            $ordenNiveles = [
                'Índice Bursal' => ['Normal', 'Atrofia', 'Severa Atrofia'],
                'Mucosa de la bursa' => ['Normal', 'Petequias', 'Hemorragia'],
                'Timos' => ['Normal', 'Atrofiados', 'Aspecto Normal', 'Congestionados']
            ];
        } elseif (strpos($sLower, 'digestiv') !== false) {
            // Sistema Digestivo
            $ordenNiveles = [
                'Hígados' => ['Normal', 'Esteatosico', 'Tamaño Normal', 'Hipertrofiado'],
                'Vesícula biliar' => ['Color normal', 'Color claro', 'Tamaño normal', 'Atrofiado', 'Hipertrofiado'],
                'Erosión de molleja' => ['Normal', 'Grado 1', 'Grado 2', 'Grado 3', 'Grado 4'],
                'Retracción del páncreas' => ['Normal', 'Retraido'],
                'Absorcion del saco vitelino' => ['Si', 'No'],
                'Enteritis' => ['Normal', 'Leve', 'Moderado', 'Severo'],
                'Contenido cecal' => ['Normal', 'Gas', 'Espuma'],
                'Alimento sin digerir' => ['Si', 'No'],
                'Heces anaranjadas' => ['Si', 'No'],
                'Lesión oral' => ['Si', 'No'],
                'Tonicidad Intestinal' => ['Buena', 'Regular', 'Mala']
            ];
        } elseif (strpos($sLower, 'respirat') !== false) {
            // Sistema Respiratorio
            $ordenNiveles = [
                'Tráquea' => ['Normal', 'Leve', 'Moderada', 'Severa'],
                'Pulmón' => ['Normal', 'Neumónico'],
                'Sacos aéreos' => ['Normal', 'Turbio', 'Con material caseoso']
            ];
        } elseif (strpos($sLower, 'evaluaci') !== false && strpos($sLower, 'físic') !== false) {
            // Evaluación física
            $ordenNiveles = [
                'Pododermatitis' => ['Grado 0', 'Grado 1', 'Grado 2', 'Grado 3', 'Grado 4'],
                'Color de tarsos' => ['3.5', '4', '4.5', '5', '5.5', '6']
            ];
        }
        
        // Si hay orden definido, aplicarlo
        if (!empty($ordenNiveles)) {
            foreach ($ordenNiveles as $nivelNombre => $parametrosOrden) {
                if (isset($niveles[$nivelNombre])) {
                    $parametros = $niveles[$nivelNombre];
                    // Ordenar parámetros según el orden definido
                    $parametrosOrdenados = [];
                    $parametrosRestantes = [];
                    
                    // Primero agregar los parámetros en el orden especificado
                    foreach ($parametrosOrden as $paramOrden) {
                        foreach ($parametros as $param) {
                            if (mb_strtolower(trim($param)) === mb_strtolower(trim($paramOrden))) {
                                $parametrosOrdenados[] = $param;
                                break;
                            }
                        }
                    }
                    
                    // Agregar parámetros que no están en el orden definido
                    foreach ($parametros as $param) {
                        if (!in_array($param, $parametrosOrdenados)) {
                            $parametrosRestantes[] = $param;
                        }
                    }
                    
                    $nivelesOrdenados[$nivelNombre] = array_merge($parametrosOrdenados, $parametrosRestantes);
                }
            }
            
            // Agregar niveles que no están en el orden definido
            foreach ($niveles as $nivelNombre => $parametros) {
                if (!isset($nivelesOrdenados[$nivelNombre])) {
                    $nivelesOrdenados[$nivelNombre] = $parametros;
                }
            }
        } else {
            // Si no hay orden definido, mantener el orden original
            $nivelesOrdenados = $niveles;
        }
        
        return $nivelesOrdenados;
    }

    // === Ordenar sistemas ===
    $sistemasFinales = [];
    $otros = [];
    foreach ($todosNiveles as $sistema => $niveles) {
        $sLower = mb_strtolower(trim($sistema), 'UTF-8');
        $pos = false;
        if (strpos($sLower, 'inmunol') !== false) $pos = 0;
        elseif (strpos($sLower, 'digestiv') !== false) $pos = 1;
        elseif (strpos($sLower, 'respirat') !== false) $pos = 2;
        elseif (strpos($sLower, 'evaluaci') !== false && strpos($sLower, 'físic') !== false) $pos = 3;
        
        // Ordenar niveles y parámetros dentro del sistema
        $nivelesOrdenados = ordenarNivelesYParametros($sistema, $niveles);
        
        if ($pos !== false) {
            $sistemasFinales[$pos] = [$sistema => $nivelesOrdenados];
        } else {
            $otros[$sistema] = $nivelesOrdenados;
        }
    }
    ksort($sistemasFinales);
    $final = [];
    foreach ($sistemasFinales as $item) $final = array_merge($final, $item);
    $final = array_merge($final, $otros);

    // === Función auxiliar: convertir hex a rgba ===
    function hexToRgb($hex) {
        $hex = ltrim($hex, '#');
        if (strlen($hex) == 3) {
            $hex = str_repeat(substr($hex,0,1),2) . str_repeat(substr($hex,1,1),2) . str_repeat(substr($hex,2,1),2);
        }
        return [
            'r' => hexdec(substr($hex,0,2)),
            'g' => hexdec(substr($hex,2,2)),
            'b' => hexdec(substr($hex,4,2))
        ];
    }

    // === Por cada sistema ===
    foreach ($final as $sistema => $niveles) {
        $html .= '<div style="page-break-after: avoid;">';
        $html .= '<div class="sistema-header">' . htmlspecialchars(strtoupper($sistema)) . '</div>';

        $html .= '<table class="data-table">';
        $html .= '<thead>';

        // === Fila 1: Agrupación por CENCO (sin fechas) ===
        if (count($galponesPorCenco) > 1) {
    $html .= '<tr>';
    $html .= '<th rowspan="3">Nivel</th>';
    $html .= '<th rowspan="3">Parámetro</th>';
            foreach ($galponesPorCenco as $cenco => $info) {
                $color = isset($coloresPorCenco[$cenco]) ? $coloresPorCenco[$cenco] : '#3b82f6';
                // Calcular columnas considerando bloques del CENCO
                $numBloques = isset($info['bloques']) && is_array($info['bloques']) ? count($info['bloques']) : 0;
                $numCols = $numBloques * 2; // % y Obs por cada bloque
                
                // Construir texto del CENCO (sin fechas)
                $cencoText = 'CENCO ' . htmlspecialchars($cenco) . '<br><small>' . htmlspecialchars($info['granja']) . '</small>';
                
                $html .= '<th class="cenco-group-header" colspan="' . $numCols . '" style="background-color:' . $color . ';">';
                $html .= $cencoText;
                $html .= '</th>';
    }
    $html .= '</tr>';
        }
    
        // === Fila 2: Fechas por bloque (celdas que abarcan cada par % y Obs) ===
        if (count($galponesPorCenco) > 1) {
    $html .= '<tr>';
            foreach ($galponesPorCenco as $cenco => $info) {
                $color = isset($coloresPorCenco[$cenco]) ? $coloresPorCenco[$cenco] : '#3b82f6';
                // Iterar sobre los bloques del CENCO directamente
                if (isset($info['bloques']) && is_array($info['bloques'])) {
                    foreach ($info['bloques'] as $keyBloque) {
                        if (isset($bloques[$keyBloque])) {
                            $bloque = $bloques[$keyBloque];
                            $tdateFormatted = date('d/m/Y', strtotime($bloque['tdate']));
                            $tfectraFormatted = date('d/m/Y', strtotime($bloque['tfectra']));
                            $edad = $bloque['tedad'] ?? '0';
                            $fechasText = "Reg: $tdateFormatted | Nec: $tfectraFormatted | Edad: $edad días";
                            
                            $html .= '<th class="cenco-group-header" colspan="2" style="background-color:' . $color . ';">';
                            $html .= htmlspecialchars($fechasText);
                            $html .= '</th>';
                        }
                    }
                }
    }
    $html .= '</tr>';
        }
    
        // === Fila 3: Encabezados individuales (% y Obs)===
    $html .= '<tr>';
        if (count($galponesPorCenco) <= 1) {
            $html .= '<th>Nivel</th><th>Parámetro</th>';
        }
        foreach ($galponesPorCenco as $cenco => $info) {
            $color = isset($coloresPorCenco[$cenco]) ? $coloresPorCenco[$cenco] : '#3b82f6';
            $rgb = hexToRgb($color);
            $bgColor = 'rgba(' . $rgb['r'] . ',' . $rgb['g'] . ',' . $rgb['b'] . ', 0.12)';
            // Iterar sobre los bloques del CENCO directamente
            if (isset($info['bloques']) && is_array($info['bloques'])) {
                foreach ($info['bloques'] as $keyBloque) {
                    if (isset($bloques[$keyBloque])) {
                        $bloque = $bloques[$keyBloque];
                        $galpon = $bloque['tgalpon'];
                        
                        // % Galpón (sin fechas, solo número de galpón)
                        $headerText = '% Galpón ' . htmlspecialchars($galpon);
                        $html .= '<th class="galpon-col-porc" style="background-color:' . $bgColor . ';">' . $headerText . '</th>';
                        // Obs Galpón (sin fechas, solo número de galpón)
                        $obsHeaderText = 'Obs Galpón ' . htmlspecialchars($galpon);
                        $html .= '<th class="galpon-col-obs" style="background-color:' . $bgColor . ';">' . $obsHeaderText . '</th>';
                    }
                }
            }
        }
        $html .= '</tr>';
        $html .= '</thead><tbody>';
        
        foreach ($niveles as $nivel => $parametros) {
            $totalFilas = count($parametros);
            
            foreach ($parametros as $i => $parametro) {
                $html .= '<tr>';
            
                // Celda de Nivel (con rowspan)
                if ($i === 0) {
                    $html .= '<td rowspan="' . $totalFilas . '">' . htmlspecialchars($nivel) . '</td>';
                }
            
                // Celda de Parámetro
                $html .= '<td>' . htmlspecialchars($parametro) . '</td>';
                
                // Datos por CENCO (iterar sobre bloques directamente)
                foreach ($galponesPorCenco as $cenco => $info) {
                    // Iterar sobre los bloques del CENCO directamente
                    if (isset($info['bloques']) && is_array($info['bloques'])) {
                        foreach ($info['bloques'] as $keyBloque) {
                            // Verificar que el dato pertenezca a este CENCO
                            $porc = '0';
                            $obs = '';
                            if (isset($mapaCompleto[$sistema][$nivel][$parametro][$keyBloque])) {
                                $datosRegistro = $mapaCompleto[$sistema][$nivel][$parametro][$keyBloque];
                                // Solo usar si el CENCO coincide
                                if (isset($datosRegistro['cenco']) && $datosRegistro['cenco'] == $cenco) {
                                    $porc = $datosRegistro['porc'] ?? '0';
                                    $obs = $datosRegistro['obs'] ?? '';
                                }
                            }
                            
                            $primerParametro = !empty($parametros) ? $parametros[0] : '';
                            $obsPrimero = '';
                            if (isset($mapaCompleto[$sistema][$nivel][$primerParametro][$keyBloque])) {
                                $datosPrimero = $mapaCompleto[$sistema][$nivel][$primerParametro][$keyBloque];
                                if (isset($datosPrimero['cenco']) && $datosPrimero['cenco'] == $cenco) {
                                    $obsPrimero = $datosPrimero['obs'] ?? '';
                                }
                            }
            
                            if ($i === 0) {
                                $html .= '<td>' . htmlspecialchars($porc) . '</td>';
                                $html .= '<td rowspan="' . $totalFilas . '">' . nl2br(htmlspecialchars($obsPrimero)) . '</td>';
                            } else {
                                $html .= '<td>' . htmlspecialchars($porc) . '</td>';
                            }
                        }
                    }
                }
                
                $html .= '</tr>';
            }
        }
        $html .= '</tbody></table>';
        $html .= '</div>';
    }
    
    $html .= '</body></html>';
    return $html;
}

