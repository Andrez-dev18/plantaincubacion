<?php
/**
 * PDFExporter
 * 
 * Clase para generar reportes PDF usando FPDF
 * Especializado en exportar tablas de galpones con características dinámicas
 */

require_once __DIR__ . '/fpdf/fpdf.php';

class PDFExporter extends FPDF {
    
    private $title = '';
    private $subtitle = '';
    private $proyeccion = '';
    private $anio = '';
    private $headerRightLines = [];
    private $headerColumns = [];
    private $columnWidths = [];
    private $totalWidth = 0;

    /**
     * Convierte texto UTF-8 a ISO-8859-1 para FPDF
     */
    public function encodeText($text) {
        return mb_convert_encoding($text, 'ISO-8859-1', 'UTF-8');
    }

    /**
     * Establece el patrón de línea punteada
     * 
     * @param float $black Longitud de la parte visible (en mm)
     * @param float $white Longitud del espacio (en mm)
     */
    public function SetDash($black = null, $white = null) {
        if ($black !== null) {
            $s = sprintf('[%.3F %.3F] 0 d', $black * $this->k, $white * $this->k);
        } else {
            $s = '[] 0 d';
        }
        $this->_out($s);
    }

    /**
     * Ajusta el texto para que no exceda el ancho de la celda
     */
    private function fitTextToWidth($text, $width) {
        $text = (string)$text;
        if ($this->GetStringWidth($text) <= $width) {
            return $text;
        }

        $ellipsis = '...';
        $maxWidth = $width - $this->GetStringWidth($ellipsis);
        if ($maxWidth <= 0) {
            return '';
        }

        $trimmed = $text;
        while ($this->GetStringWidth($trimmed) > $maxWidth && mb_strlen($trimmed) > 0) {
            $trimmed = mb_substr($trimmed, 0, -1);
        }

        return $trimmed . $ellipsis;
    }
    
    /**
     * Constructor
     */
    public function __construct($orientation = 'L', $unit = 'mm', $size = 'A4') {
        parent::__construct($orientation, $unit, $size);
        $this->SetAutoPageBreak(true, 15);
    }
    
    /**
     * Configurar encabezado del reporte
     */
    public function setReportTitle($title, $subtitle = '') {
        $this->title = $title;
        $this->subtitle = $subtitle;
    }
    
    /**
     * Configurar información de proyección
     */
    public function setProyeccionInfo($proyeccion, $anio = '') {
        $this->proyeccion = $proyeccion;
        $this->anio = $anio;
    }

    /**
     * Configurar lineas personalizadas para el bloque derecho del encabezado.
     */
    public function setHeaderRightLines(array $lines): void {
        $this->headerRightLines = array_values(array_filter(array_map(function ($line) {
            return trim((string)$line);
        }, $lines), function ($line) {
            return $line !== '';
        }));
    }
    
    /**
     * Configurar columnas de la tabla
     * 
     * @param array $columns Array de nombres de columnas
     * @param array $widths Array de anchos (opcional, se calculan automáticamente)
     */
    public function setTableColumns($columns, $widths = []) {
        $this->headerColumns = $columns;
        
        if (empty($widths)) {
            // Calcular anchos automáticamente distribuyendo el espacio disponible
            $availableWidth = $this->GetPageWidth() - 20; // Margen de 10mm a cada lado
            $columnCount = count($columns);
            
            // Formato transpuesto: Campo + Galpones
            if ($columns[0] === 'Campo') {
                $campoWidth = 50; // Ancho fijo para la columna "Campo"
                $remainingWidth = $availableWidth - $campoWidth;
                $galponCount = $columnCount - 1;
                $galponWidth = $galponCount > 0 ? $remainingWidth / $galponCount : 30;
                
                $this->columnWidths[] = $campoWidth;
                for ($i = 1; $i < $columnCount; $i++) {
                    $this->columnWidths[] = $galponWidth;
                }
            } else {
                // Formato original: columnas fijas más pequeñas
                $fixedColumns = ['#', 'Granja', 'Galpón'];
                $fixedWidth = 15;
                $fixedCount = 0;
                
                foreach ($columns as $col) {
                    if (in_array($col, $fixedColumns)) {
                        $fixedCount++;
                    }
                }
                
                $remainingWidth = $availableWidth - ($fixedCount * $fixedWidth);
                $dynamicWidth = $remainingWidth / ($columnCount - $fixedCount);
                
                foreach ($columns as $col) {
                    if (in_array($col, $fixedColumns)) {
                        $this->columnWidths[] = $fixedWidth;
                    } else {
                        $this->columnWidths[] = $dynamicWidth;
                    }
                }
            }
        } else {
            $this->columnWidths = $widths;
        }
        
        $this->totalWidth = array_sum($this->columnWidths);
    }
    
    /**
     * Obtener anchos de columna configurados
     */
    public function getColumnWidths() {
        return $this->columnWidths;
    }
    
    /**
     * Header - Se llama automáticamente en cada página
     */
    function Header() {
        $pageWidth = $this->GetPageWidth();

        // Layout compacto para formatos angostos (ticket 80mm).
        if ($pageWidth <= 100) {
            $x = 2;
            $y = 3;
            $h = 12;
            $wTotal = $pageWidth - 4;

            $this->SetDrawColor(70, 70, 70);
            $this->SetLineWidth(0.3);
            $this->SetDash(2.0, 1.2);
            $this->Rect($x, $y, $wTotal, $h, 'D');

            $this->SetXY($x + 1, $y + 1.2);
            $this->SetFont('Arial', 'B', 7.2);
            $this->SetTextColor(0, 0, 0);
            $this->Cell($wTotal - 2, 3.4, $this->encodeText($this->title), 0, 1, 'C');

            $lineY = $y + 5.3;
            $this->SetFont('Arial', 'B', 5.9);
            $customLines = !empty($this->headerRightLines)
                ? $this->headerRightLines
                : array_filter([
                    $this->proyeccion ? ('Proyeccion: ' . $this->proyeccion) : '',
                    $this->anio ? ('Año: ' . $this->anio) : '',
                ]);

            $maxLines = 2;
            $printed = 0;
            foreach ($customLines as $line) {
                if ($printed >= $maxLines) {
                    break;
                }
                $this->SetXY($x + 1, $lineY);
                $this->Cell($wTotal - 2, 2.9, $this->encodeText($line), 0, 1, 'C');
                $lineY += 2.9;
                $printed++;
            }

            $this->SetY($y + $h + 1.5);

            if (!empty($this->headerColumns) && !empty($this->columnWidths)) {
                $headerY = $this->GetY();
                $headerX = $x;
                $totalHeaderWidth = array_sum($this->columnWidths);
                $headerHeight = 5;

                $this->SetDrawColor(70, 70, 70);
                $this->SetLineWidth(0.3);
                $this->SetDash(2.0, 1.2);
                $this->Rect($headerX, $headerY, $totalHeaderWidth, $headerHeight, 'D');

                $this->SetXY($headerX, $headerY);
                $this->SetFont('Arial', 'B', 5.8);
                $this->SetFillColor(255, 255, 255);
                $this->SetTextColor(0, 0, 0);

                foreach ($this->headerColumns as $i => $header) {
                    $this->Cell($this->columnWidths[$i], $headerHeight, $this->encodeText($header), 0, 0, 'C', true);
                }
                $this->Ln();
            }

            $this->SetTextColor(0, 0, 0);
            $this->SetLineWidth(0.3);
            $this->SetDash(2.0, 1.2);
            $this->SetDrawColor(0, 0, 0);
            return;
        }

        // Marco principal del encabezado
        $x = 8;
        $y = 4;
        $h = 10;
        $wTotal = $pageWidth - 16;
        $leftW = 62;
        $rightW = 62;
        $centerW = $wTotal - $leftW - $rightW;

        // Configurar línea punteada más visible (2.5mm visible, 1.5mm espacio)
        $this->SetDrawColor(70, 70, 70);
        $this->SetLineWidth(0.3);
        $this->SetDash(2.5, 1.5);

        // Dibujar solo líneas perimetrales para evitar superposición
        // Línea superior completa
        $this->Line($x, $y, $x + $wTotal, $y);
        
        // Línea inferior completa
        $this->Line($x, $y + $h, $x + $wTotal, $y + $h);
        
        // Línea izquierda
        $this->Line($x, $y, $x, $y + $h);
        
        // Línea divisoria entre bloque izquierdo y central
        $this->Line($x + $leftW, $y, $x + $leftW, $y + $h);
        
        // Línea divisoria entre bloque central y derecho
        $this->Line($x + $leftW + $centerW, $y, $x + $leftW + $centerW, $y + $h);
        
        // Línea derecha (borde derecho del bloque derecho)
        $this->Line($x + $wTotal, $y, $x + $wTotal, $y + $h);

        // Rellenar bloque central con fondo azul
        $this->SetFillColor(173, 216, 230);
        $this->SetDash(); // Desactivar punteado temporalmente para el relleno
        $this->Rect($x + $leftW, $y, $centerW, $h, 'F');
        
        // Contenido bloque izquierdo con logo e info
        $logoPath = __DIR__ . '/../../frontend/assets/images/Favicon.png';
        if (file_exists($logoPath)) {
            $this->Image($logoPath, $x + 1.5, $y + 1.0, 8);
        }

        $this->SetXY($x + 11, $y + 1.5);
        $this->SetFont('Arial', 'B', 6.5);
        $this->SetTextColor(0, 0, 0);
        $this->Cell($leftW - 12, 3, 'GRANJA RINCONADA DEL SUR', 0, 1, 'L');
        $this->SetX($x + 11);
        $this->Cell($leftW - 12, 3, 'S.A.', 0, 1, 'L');

        // Contenido bloque central con título
        $this->SetXY($x + $leftW, $y + 1.5);
        $this->SetFont('Arial', 'B', 9.5);
        $this->SetTextColor(0, 0, 0);
        $this->Cell($centerW, 6, $this->encodeText($this->title), 0, 1, 'C');

        // Contenido bloque derecho personalizado
        if (!empty($this->headerRightLines)) {
            $this->SetXY($x + $leftW + $centerW + 2, $y + 1.8);
            $this->SetFont('Arial', 'B', 7.5);
            $this->SetTextColor(0, 0, 0);
            foreach ($this->headerRightLines as $line) {
                $this->Cell($rightW - 4, 3.5, $this->encodeText($line), 0, 1, 'L');
                $this->SetX($x + $leftW + $centerW + 2);
            }
        }
        // Contenido bloque derecho - Información de proyección y año (fallback)
        else if ($this->proyeccion || $this->anio) {
            $this->SetXY($x + $leftW + $centerW + 2, $y + 1.8);
            $this->SetFont('Arial', 'B', 7.5);
            $this->SetTextColor(0, 0, 0);
            if ($this->proyeccion) {
                $this->Cell($rightW - 4, 3.5, 'Proyeccion: ' . $this->proyeccion, 0, 1, 'L');
                $this->SetX($x + $leftW + $centerW + 2);
            }
            if ($this->anio) {
                $this->Cell($rightW - 4, 3.5, $this->encodeText('Año: ') . $this->anio, 0, 1, 'L');
            }
        }

        $this->SetY($y + $h + 2);
        
        // Dibujar encabezados de columna si están configurados
        if (!empty($this->headerColumns) && !empty($this->columnWidths)) {
            $headerY = $this->GetY();
            $headerX = 8;
            $totalHeaderWidth = array_sum($this->columnWidths);
            $headerHeight = 7;
            
            // Dibujar cuadro punteado alrededor de los encabezados
            $this->SetDrawColor(70, 70, 70);
            $this->SetLineWidth(0.3);
            $this->SetDash(2.5, 1.5);
            $this->Rect($headerX, $headerY, $totalHeaderWidth, $headerHeight, 'D');
            
            // Escribir encabezados
            $this->SetXY($headerX, $headerY);
            $this->SetFont('Arial', 'B', 8);
            $this->SetFillColor(255, 255, 255);
            $this->SetTextColor(0, 0, 0);
            
            
            foreach ($this->headerColumns as $i => $header) {
                $this->Cell($this->columnWidths[$i], $headerHeight, $this->encodeText($header), 0, 0, 'C', true);
            }
            $this->Ln();
        }
        
        // Restaurar para el contenido
        $this->SetTextColor(0, 0, 0);
        $this->SetLineWidth(0.3);
        $this->SetDash(2.5, 1.5);
        $this->SetDrawColor(0, 0, 0);
    }
    
    /**
     * Footer - Se llama automáticamente en cada página
     */
    function Footer() {
        if ($this->GetPageWidth() <= 100) {
            $this->SetY(-7);
            $this->SetFont('Arial', 'I', 5.5);
            $this->SetTextColor(120, 120, 120);
            $this->Cell(0, 3.5, 'Pag ' . $this->PageNo(), 0, 0, 'C');
            return;
        }

        $this->SetY(-15);
        $this->SetFont('Arial', 'I', 8);
        $this->SetTextColor(156, 163, 175);
        
        // Línea separadora
        $this->SetDrawColor(229, 231, 235);
        $this->Line(10, $this->GetY() - 5, $this->GetPageWidth() - 10, $this->GetY() - 5);
        $this->SetDrawColor(0, 0, 0);  // Restaurar color negro para las líneas
        
        // Número de página
        $this->Cell(0, 10, 'Pagina ' . $this->PageNo() . ' de {nb}', 0, 0, 'C');
        
        // Fecha de generación
        $this->SetX(10);
        $this->Cell(80, 10, 'Generado: ' . date('d/m/Y H:i'), 0, 0, 'L');
    }
    
    /**
     * Dibujar encabezado de tabla
     */
    public function drawTableHeader() {
        $this->SetFont('Arial', 'B', 8);
        $this->SetFillColor(37, 99, 235); // Azul oscuro
        $this->SetTextColor(255, 255, 255); // Blanco
        $this->SetDrawColor(37, 99, 235);
        
        $x = 10;
        foreach ($this->headerColumns as $i => $column) {
            $this->SetX($x);
            $label = $this->encodeText($column);
            $label = $this->fitTextToWidth($label, $this->columnWidths[$i] - 1);
            $this->Cell($this->columnWidths[$i], 7, $label, 1, 0, 'C', true);
            $x += $this->columnWidths[$i];
        }
        $this->Ln();
        
        // Resetear colores para contenido
        $this->SetTextColor(0, 0, 0);
        $this->SetFont('Arial', '', 7);
    }
    
    /**
     * Dibujar fila de datos
     * 
     * @param array $rowData Array de valores para cada columna
     * @param bool $alternate Alternar color de fondo
     */
    public function drawTableRow($rowData, $alternate = false) {
        // Color de fondo alternado
        if ($alternate) {
            $this->SetFillColor(243, 244, 246); // Gris claro
        } else {
            $this->SetFillColor(255, 255, 255); // Blanco
        }
        
        $this->SetDrawColor(229, 231, 235); // Borde gris claro
        $this->SetFont('Arial', '', 7);
        
        $x = 10;
        $maxHeight = 5;
        
        foreach ($rowData as $i => $value) {
            $this->SetX($x);
            
            // Primera columna (Campo) con fuente un poco destacada
            if ($i === 0 && $this->headerColumns[0] === 'Campo') {
                $this->SetFont('Arial', 'B', 7);
            } else {
                $this->SetFont('Arial', '', 7);
            }
            
            // Convertir encoding para caracteres especiales
            $value = $this->encodeText($value);
            
            // Ajustar texto al ancho de la celda
            $value = $this->fitTextToWidth($value, $this->columnWidths[$i] - 2);
            
            // Alinear: primera columna a la izquierda, números a la derecha, resto centrado
            if ($i === 0 && $this->headerColumns[0] === 'Campo') {
                $align = 'L';
            } else if (is_numeric($value) && $value !== '-') {
                $align = 'R';
            } else {
                $align = 'C';
            }
            
            $this->Cell($this->columnWidths[$i], $maxHeight, $value, 1, 0, $align, true);
            $x += $this->columnWidths[$i];
        }
        $this->Ln();
    }
    
    /**
     * Generar reporte completo de galpones
     * 
     * @param array $galpones Array de objetos stdClass con datos de galpones
     * @param array $caracteristicas Array de características disponibles
     */
    public function generarReporteGalpones($galpones, $caracteristicas, $filtros = []) {
        // Configurar título
        $subtitle = 'Reporte de Galpones y Características';
        if (!empty($filtros)) {
            $filtrosTexto = [];
            if (isset($filtros['granja'])) {
                $filtrosTexto[] = "Granja: {$filtros['granja']}";
            }
            if (isset($filtros['fechaDesde'])) {
                $filtrosTexto[] = "Desde: {$filtros['fechaDesde']}";
            }
            if (isset($filtros['fechaHasta'])) {
                $filtrosTexto[] = "Hasta: {$filtros['fechaHasta']}";
            }
            if (!empty($filtrosTexto)) {
                $subtitle .= ' | ' . implode(' - ', $filtrosTexto);
            }
        }
        
        $this->setReportTitle('Sistema de Gestion GRS', $subtitle);
        
        // Configurar para formato transpuesto (características como filas, galpones como columnas)
        $maxGalponesPerPage = 5; // Máximo de galpones por página en vertical
        $totalGalpones = count($galpones);
        $pages = ceil($totalGalpones / $maxGalponesPerPage);
        
        for ($page = 0; $page < $pages; $page++) {
            $inicio = $page * $maxGalponesPerPage;
            $fin = min($inicio + $maxGalponesPerPage, $totalGalpones);
            $galponesPage = array_slice($galpones, $inicio, $fin - $inicio);
            
            // Preparar columnas: Campo + Galpones
            $columns = ['Campo'];
            foreach ($galponesPage as $idx => $galpon) {
                $granja = $galpon->granja ?? '-';
                $numGalpon = $galpon->galpon ?? '-';
                $columns[] = "G$granja-$numGalpon";
            }
            
            $this->setTableColumns($columns);
            $this->AddPage();
            $this->AliasNbPages();
            
            // Dibujar encabezado
            $this->drawTableHeader();
            
            // Filas de información básica
            $camposBasicos = [
                ['campo' => 'Granja', 'key' => 'granja'],
                ['campo' => 'Galpón', 'key' => 'galpon'],
                ['campo' => 'Nombre', 'key' => 'nombre']
            ];
            
            $rowIndex = 0;
            foreach ($camposBasicos as $info) {
                $rowData = [$info['campo']];
                foreach ($galponesPage as $galpon) {
                    $valor = $galpon->{$info['key']} ?? '-';
                    $rowData[] = $valor;
                }
                $this->drawTableRow($rowData, $rowIndex % 2 == 0);
                $rowIndex++;
            }
            
            // Filas de características
            foreach ($caracteristicas as $carac) {
                $nombre = is_array($carac) ? ($carac['nombre'] ?? '') : ($carac->nombre ?? '');
                $id = is_array($carac) ? ($carac['id'] ?? null) : ($carac->id ?? null);
                
                if ($nombre === '' || $id === null) continue;
                
                $rowData = [$nombre];
                foreach ($galponesPage as $galpon) {
                    $valor = $galpon->{$id} ?? '-';
                    $rowData[] = $valor;
                }
                
                // Verificar si necesita nueva página
                if ($this->GetY() > 250) {
                    $this->AddPage();
                    $this->drawTableHeader();
                    $rowIndex = 0;
                }
                
                $this->drawTableRow($rowData, $rowIndex % 2 == 0);
                $rowIndex++;
            }
            
            // Agregar nota si hay más páginas
            if ($page < $pages - 1) {
                $this->Ln(3);
                $this->SetFont('Arial', 'I', 8);
                $this->SetTextColor(107, 114, 128);
                $this->Cell(0, 5, 'Continua en la siguiente pagina...', 0, 1, 'R');
            }
        }
        
        // Agregar resumen al final
        $this->Ln(5);
        $this->SetFont('Arial', 'B', 10);
        $this->SetTextColor(37, 99, 235);
        $this->Cell(0, 8, 'Total de galpones: ' . $totalGalpones, 0, 1, 'L');
    }
    
    /**
     * Generar y descargar PDF
     */
    public function descargar($filename = 'reporte.pdf') {
        $this->Output('D', $filename);
    }
    
    /**
     * Mostrar PDF en el navegador
     */
    public function mostrar($filename = 'reporte.pdf') {
        $this->Output('I', $filename);
    }
}
