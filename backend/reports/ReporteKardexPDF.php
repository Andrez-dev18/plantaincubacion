<?php
date_default_timezone_set('America/Lima');
require_once __DIR__ . '/../libraries/fpdf/fpdf.php';

class ReporteKardexPdf extends FPDF
{
    public $filtros = [];

    public function Header()
    {
        $this->SetFillColor(37, 99, 235);
        $this->Rect(10, 10, 277, 10, 'F');

        $this->SetTextColor(255, 255, 255);
        $this->SetFont('Arial', 'B', 9);

        $fIni = str_replace('-', '/', $this->filtros['fechaInicio'] ?? '');
        $fFin = str_replace('-', '/', $this->filtros['fechaFin'] ?? '');
        $tituloReporte = "KARDEX DE PRODUCCION DESDE $fIni HASTA $fFin";

        $this->SetXY(12, 10);
        $this->Cell(60, 10, utf8_decode('GRANJA RINCONADA DEL SUR S.A.'), 0, 0, 'L');
        $this->Cell(145, 10, utf8_decode($tituloReporte), 0, 0, 'C');
        $this->Cell(60, 10, date('d/m/Y H:i'), 0, 1, 'R');

        $this->SetTextColor(100, 100, 100);
        $this->SetFont('Arial', '', 8);
        $this->SetX(10);
        $almacen = $this->filtros['almacenNombre'] ?? $this->filtros['zona'] ?? '';
        $this->Cell(138, 6, utf8_decode('Rango de Fechas: ' . ($this->filtros['fechaInicio'] ?? '') . ' al ' . ($this->filtros['fechaFin'] ?? '')), 0, 0, 'L');
        $this->Cell(139, 6, utf8_decode('Almacén: ' . $almacen), 0, 1, 'R');
        $this->Ln(2);

        // ── CABECERA DE COLUMNAS ──
        $this->SetFillColor(30, 58, 138);
        $this->SetTextColor(255, 255, 255);
        $this->SetDrawColor(255, 255, 255);
        $this->SetFont('Arial', 'B', 6.5);

        // Fila 1 super cabecera
        $this->Cell(14, 5, 'FECHA',      'TLR', 0, 'C', true);
        $this->Cell(20, 5, 'NRO.DOC',    'TLR', 0, 'C', true);
        $this->Cell(12, 5, 'CODTRA',     'TLR', 0, 'C', true);
        $this->Cell(50, 5, 'DESCRIPCION','TLR', 0, 'C', true);
        $this->Cell(42, 5, 'UNIDADES',   1,     0, 'C', true);
        $this->Cell(52, 5, 'PESO',       1,     0, 'C', true);
        $this->Cell(59, 5, 'IMPORTE',    1,     0, 'C', true);
        $this->Cell(14, 5, 'COS.UNIT',  'TLR', 1, 'C', true);

        // Fila 2 sub columnas
        $this->SetFillColor(37, 99, 235);
        $this->Cell(14, 4, '', 'BLR', 0, 'C', true);
        $this->Cell(20, 4, '', 'BLR', 0, 'C', true);
        $this->Cell(12, 4, '', 'BLR', 0, 'C', true);
        $this->Cell(50, 4, '', 'BLR', 0, 'C', true);
        // Unidades
        $this->Cell(14, 4, 'ENTRADA', 1, 0, 'R', true);
        $this->Cell(14, 4, 'SALIDA',  1, 0, 'R', true);
        $this->Cell(14, 4, 'STOCK',   1, 0, 'R', true);
        // Peso
        $this->Cell(17, 4, 'ENTRADA', 1, 0, 'R', true);
        $this->Cell(17, 4, 'SALIDA',  1, 0, 'R', true);
        $this->Cell(18, 4, 'STOCK',   1, 0, 'R', true);
        // Importe
        $this->Cell(19, 4, 'ENTRADA', 1, 0, 'R', true);
        $this->Cell(19, 4, 'SALIDA',  1, 0, 'R', true);
        $this->Cell(21, 4, 'STOCK',   1, 0, 'R', true);
        $this->Cell(14, 4, '', 'BLR', 1, 'C', true);
    }

    public function exportarPDF($filtros, $data)
    {
        $this->filtros = $filtros;
        $this->SetTitle(utf8_decode('Reporte de Kardex'));
        $this->AddPage('L', 'A4');
        $this->SetFont('Arial', '', 7);

        if (empty($data)) {
            $this->SetTextColor(100, 100, 100);
            $this->Cell(263, 10, 'No se encontraron registros.', 1, 1, 'C');
            $this->Output('I', 'Reporte_Kardex.pdf');
            return;
        }

        $fmt = function($num) {
            $n = (float)$num;
            if (abs($n) < 0.000001) $n = 0;
            return ($n != 0) ? number_format($n, 2, '.', ',') : '';
        };

        $almacenFiltro = utf8_decode($this->filtros['almacenNombre'] ?? $this->filtros['zona'] ?? '');
        $codigoActual  = '';

        foreach ($data as $producto) {
            $codigo      = $producto['codigo'];
            $descripcion = $producto['descripcion'];
            $lote        = (!empty($producto['lote']) && $producto['lote'] !== '00000000') ? $producto['lote'] : '';

            // ── CABECERA DE PRODUCTO ──
            if ($codigoActual !== $codigo) {
                $this->SetFillColor(241, 245, 249);
                $this->SetDrawColor(229, 231, 235);
                $this->SetTextColor(100, 100, 100);
                $this->SetFont('Arial', '', 6);
                $this->Cell(263, 3.5, utf8_decode('   ALMACÉN: ' . $almacenFiltro), 'TLR', 1, 'L', true);
                $this->SetTextColor(15, 23, 42);
                $this->SetFont('Arial', 'B', 7.5);
                $this->Cell(263, 5.5, utf8_decode('   ' . $codigo . ' - ' . $descripcion), 'BLR', 1, 'L', true);
                $codigoActual = $codigo;
            }

            // ── SUB-CABECERA DE LOTE ──
            if ($lote !== '') {
                $this->SetFillColor(248, 250, 252);
                $this->SetDrawColor(229, 231, 235);
                $this->SetTextColor(30, 41, 59);
                $this->SetFont('Arial', 'B', 7);
                $this->Cell(263, 4.5, '   ' . $lote, 'LR', 1, 'L', true);
            }

            // ── SALDO INICIAL ──
            $this->SetFillColor(255, 255, 255);
            $this->SetDrawColor(209, 213, 219);
            $this->SetTextColor(55, 65, 81);
            $this->SetFont('Arial', 'B', 7);

            $this->Cell(96, 5, 'SALDO:', 1, 0, 'R');
            $this->Cell(14, 5, '', 1, 0, 'R');
            $this->Cell(14, 5, '', 1, 0, 'R');
            $this->Cell(14, 5, number_format((float)$producto['saldo_inicial']['cant'], 2, '.', ','), 1, 0, 'R');
            $this->Cell(17, 5, '', 1, 0, 'R');
            $this->Cell(17, 5, '', 1, 0, 'R');
            $this->Cell(18, 5, number_format((float)$producto['saldo_inicial']['peso'], 2, '.', ','), 1, 0, 'R');
            $this->Cell(19, 5, '', 1, 0, 'R');
            $this->Cell(19, 5, '', 1, 0, 'R');
            $this->Cell(21, 5, number_format((float)$producto['saldo_inicial']['val'], 2, '.', ','), 1, 0, 'R');
            $this->SetFont('Arial', '', 7);
            $this->Cell(14, 5, number_format((float)$producto['saldo_inicial']['pu'], 2, '.', ','), 1, 1, 'R');

            // ── TOTALIZADORES ──
            $sumEntU = 0; $sumSalU = 0;
            $sumEntP = 0; $sumSalP = 0;
            $sumEntI = 0; $sumSalI = 0;

            // ── LÓGICA DE DIBUJO DE MOVIMIENTOS ──
            $isNeg = function($val) {
                return (float)$val < -0.000001;
            };

            foreach ($producto['detalle'] as $mov) {
                $fPts     = explode('-', $mov['fecha']);
                $fechaFmt = (count($fPts) == 3) ? $fPts[2] . '/' . $fPts[1] : $mov['fecha'];
                $descriMov = (!empty($mov['nomref']) && trim($mov['nomref']) !== '') ? $mov['nomref'] : $mov['descri'];
                $desc = substr(utf8_decode($descriMov), 0, 38);

                $sumEntU += $mov['ent_cant']; $sumSalU += $mov['sal_cant'];
                $sumEntP += $mov['ent_peso']; $sumSalP += $mov['sal_peso'];
                $sumEntI += $mov['ent_val'];  $sumSalI += $mov['sal_val'];

                // 1. Verificamos si hay algún negativo en la fila
                $hasNegative = (
                    $isNeg($mov['ent_cant']) || $isNeg($mov['sal_cant']) || $isNeg($mov['sto_cant']) ||
                    $isNeg($mov['ent_peso']) || $isNeg($mov['sal_peso']) || $isNeg($mov['sto_peso']) ||
                    $isNeg($mov['ent_val'])  || $isNeg($mov['sal_val'])  || $isNeg($mov['sto_val']) || $isNeg($mov['pu'])
                );

                $fill = $hasNegative;
                if ($hasNegative) {
                    $this->SetFillColor(254, 240, 138); // Amarillo
                }

                $this->SetTextColor(55, 65, 81);
                $this->SetFont('Arial', '', 6.5);
                $this->Cell(14, 4.5, $fechaFmt,       1, 0, 'C', $fill);
                $this->Cell(20, 4.5, $mov['tnumfac'],  1, 0, 'C', $fill);
                $this->Cell(12, 4.5, $mov['codtra'],   1, 0, 'C', $fill);
                $this->Cell(50, 4.5, $desc,            1, 0, 'L', $fill);

                // Función auxiliar para imprimir números evaluando colores individualmente
                $printCell = function($w, $val, $defR, $defG, $defB, $defStyle = '') use ($fill, $fmt, $isNeg) {
                    if ($isNeg($val)) {
                        $this->SetTextColor(220, 38, 38); // Rojo Fuerte
                        $this->SetFont('Arial', 'B', 6.5);
                    } else {
                        $this->SetTextColor($defR, $defG, $defB); // Color Original
                        $this->SetFont('Arial', $defStyle, 6.5);
                    }
                    $this->Cell($w, 4.5, ($val != 0 ? $fmt($val) : ''), 1, 0, 'R', $fill);
                };

                // Unidades
                $printCell(14, $mov['ent_cant'], 37, 99, 235);
                $printCell(14, $mov['sal_cant'], 220, 38, 38);
                $printCell(14, $mov['sto_cant'], 15, 23, 42, 'B');

                // Peso
                $printCell(17, $mov['ent_peso'], 37, 99, 235);
                $printCell(17, $mov['sal_peso'], 220, 38, 38);
                $printCell(18, $mov['sto_peso'], 15, 23, 42, 'B');

                // Importe
                $printCell(19, $mov['ent_val'], 37, 99, 235);
                $printCell(19, $mov['sal_val'], 220, 38, 38);
                $printCell(21, $mov['sto_val'], 15, 23, 42, 'B');

                // COS.UNIT
                if ($isNeg($mov['pu'])) {
                    $this->SetTextColor(220, 38, 38);
                    $this->SetFont('Arial', 'B', 6.5);
                } else {
                    $this->SetTextColor(100, 100, 100);
                    $this->SetFont('Arial', '', 6.5);
                }
                $this->Cell(14, 4.5, ($mov['pu'] != 0 ? $fmt($mov['pu']) : ''), 1, 1, 'R', $fill);

                $this->SetTextColor(55, 65, 81);
            }

            // ── FILA DE TOTALES ──
            if (!empty($producto['detalle'])) {
                $this->SetFillColor(255, 255, 255);
                $this->SetFont('Arial', 'B', 6.5);
                $this->SetTextColor(15, 23, 42);
                $this->Cell(96, 5.5, '', 1, 0);
                
                $this->SetTextColor(37, 99, 235);
                $this->Cell(14, 5.5, $fmt($sumEntU), 1, 0, 'R');
                $this->SetTextColor(220, 38, 38);
                $this->Cell(14, 5.5, $fmt($sumSalU), 1, 0, 'R');
                $this->SetTextColor(15, 23, 42);
                $this->Cell(14, 5.5, '', 1, 0, 'R');
                
                $this->SetTextColor(37, 99, 235);
                $this->Cell(17, 5.5, $fmt($sumEntP), 1, 0, 'R');
                $this->SetTextColor(220, 38, 38);
                $this->Cell(17, 5.5, $fmt($sumSalP), 1, 0, 'R');
                $this->SetTextColor(15, 23, 42);
                $this->Cell(18, 5.5, '', 1, 0, 'R');
                
                $this->SetTextColor(37, 99, 235);
                $this->Cell(19, 5.5, $fmt($sumEntI), 1, 0, 'R');
                $this->SetTextColor(220, 38, 38);
                $this->Cell(19, 5.5, $fmt($sumSalI), 1, 0, 'R');
                
                $this->SetTextColor(15, 23, 42);
                $this->Cell(35, 5.5, '', 1, 1, 'R');
            }

            $this->Ln(2);
        }

        if (ob_get_length()) ob_end_clean();
        $this->Output('I', 'Reporte_Kardex.pdf');
    }
}