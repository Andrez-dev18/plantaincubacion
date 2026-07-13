<?php
date_default_timezone_set('America/Lima');
require_once __DIR__ . '/../libraries/fpdf/fpdf.php';

class ReporteStockPdf extends FPDF
{
    public $filtros = [];

    public function Header()
    {
        // ── BARRA AZUL PRINCIPAL ──
        $this->SetFillColor(37, 99, 235);
        $this->Rect(10, 10, 277, 10, 'F');

        $this->SetTextColor(255, 255, 255);
        $this->SetFont('Arial', 'B', 9);

        $formato = $this->filtros['formato'] ?? 'UNIDADES';
        $tituloReporte = 'STOCK DE PRODUCTOS EN UNIDADES'; 

        if ($formato === 'VALOR') {
            $tituloReporte = 'STOCK DE PRODUCTOS VALORADOS';
        } elseif ($formato === 'PESO') {
            $tituloReporte = 'STOCK DE PRODUCTOS PESO';
        } elseif ($formato === 'RESUMEN') {
            $tituloReporte = 'STOCK DE PRODUCTOS RESUMEN';
        }

        $this->SetXY(12, 10);
        $this->Cell(60, 10, utf8_decode('PLANTA INCUBACION'), 0, 0, 'L');
        $this->Cell(145, 10, utf8_decode($tituloReporte), 0, 0, 'C');
        $this->Cell(60, 10, date('d/m/Y H:i'), 0, 1, 'R');

        $this->SetTextColor(100, 100, 100);
        $this->SetFont('Arial', '', 8);
        $this->SetX(10);

        $fechaRango = ($this->filtros['fechaInicio'] ?? '') . ' al ' . ($this->filtros['fechaFin'] ?? '');
        $quiebre = $this->filtros['quiebre'] ?? 'ALMACEN';

        $this->Cell(138, 6, utf8_decode('Rango de Fechas: ' . $fechaRango), 0, 0, 'L');
        $this->Cell(139, 6, utf8_decode('Agrupado por: ' . $quiebre), 0, 1, 'R');
        $this->Ln(2);

        // ── CABECERAS DINÁMICAS (LOTE INCLUIDO) ──
        $this->SetFillColor(37, 99, 235);
        $this->SetTextColor(255, 255, 255);
        $this->SetDrawColor(255, 255, 255);
        $this->SetFont('Arial', 'B', 7.5);

        if ($formato === 'VALOR' || $formato === 'PESO') {
            $tituloSec = $formato === 'VALOR' ? 'VALOR' : 'PESO';
            $this->SetFillColor(30, 58, 138); 

            // Fila 1 (Super Cabecera) - Total 277mm
            $this->Cell(18, 5, 'CODIGO', 'TLR', 0, 'C', true);
            $this->Cell(16, 5, 'LOTE', 'TLR', 0, 'C', true);
            $this->Cell(65, 5, 'DESCRIPCION', 'TLR', 0, 'C', true);
            $this->Cell(68, 5, '<--- CANTIDAD --->', 1, 0, 'C', true);
            $this->Cell(88, 5, "<--- $tituloSec --->", 1, 0, 'C', true);
            $this->Cell(22, 5, 'PROM', 'TLR', 1, 'C', true);

            // Fila 2 (Sub Columnas)
            $this->SetFillColor(37, 99, 235); 
            $this->Cell(18, 5, '', 'BLR', 0, 'C', true);
            $this->Cell(16, 5, '', 'BLR', 0, 'C', true);
            $this->Cell(65, 5, '', 'BLR', 0, 'C', true);
            // Cantidad (4x17 = 68)
            $this->Cell(17, 5, 'Inicio', 1, 0, 'R', true);
            $this->Cell(17, 5, 'Entrada', 1, 0, 'R', true);
            $this->Cell(17, 5, 'Salida', 1, 0, 'R', true);
            $this->Cell(17, 5, 'Stock', 1, 0, 'R', true);
            // Valor/Peso (4x22 = 88)
            $this->Cell(22, 5, 'Inicio', 1, 0, 'R', true);
            $this->Cell(22, 5, 'Entrada', 1, 0, 'R', true);
            $this->Cell(22, 5, 'Salida', 1, 0, 'R', true);
            $this->Cell(22, 5, 'Stock', 1, 0, 'R', true);
            
            $this->Cell(22, 5, '', 'BLR', 1, 'C', true);

        } elseif ($formato === 'RESUMEN') {
            // Total 277mm
            $cols = [
                ['CÓDIGO', 25],
                ['LOTE', 20],
                ['DESCRIPCIÓN', 100],
                ['STOCK UNIDADES', 44],
                ['STOCK VALORADO', 44],
                ['PRECIO PROM.', 44]
            ];
            foreach ($cols as $col) $this->Cell($col[1], 6, utf8_decode($col[0]), 1, 0, 'C', true);
            $this->Ln();
        } else {
            // UNIDADES - Total 277mm
            $cols = [
                ['CÓDIGO', 20],
                ['LOTE', 18],
                ['DESCRIPCIÓN', 60],
                ['INICIO', 26],
                ['ENTRADA', 26],
                ['CONSUMO', 26],
                ['AJUSTE', 23],
                ['STOCK', 26],
                ['DIARIO', 26],
                ['ALCANCE', 26]
            ];
            foreach ($cols as $col) $this->Cell($col[1], 6, utf8_decode($col[0]), 1, 0, 'C', true);
            $this->Ln();
        }
    }

    public function exportarPDF($filtros, $data)
    {
        $this->filtros = $filtros;
        $formato = $filtros['formato'] ?? 'UNIDADES';
        $quiebre = $filtros['quiebre'] ?? 'ALMACEN';

        $tituloPestana = 'Reporte de Stock - ' . $formato;
        $this->SetTitle(utf8_decode($tituloPestana));
        $this->AddPage('L', 'A4');
        $this->SetFont('Arial', '', 7.5);

        if (empty($data)) {
            $this->SetTextColor(100, 100, 100);
            $this->Cell(277, 10, 'No se encontraron registros para los filtros aplicados.', 1, 1, 'C');
            $this->Output('I', 'Reporte_Stock.pdf');
            return;
        }

        // Acumuladores
        $subIniU = 0; $subEntU = 0; $subSalU = 0; $subStoU = 0;
        $subIniExt = 0; $subEntExt = 0; $subSalExt = 0; $subStoExt = 0;
        $totIniU = 0; $totEntU = 0; $totSalU = 0; $totStoU = 0;
        $totIniExt = 0; $totEntExt = 0; $totSalExt = 0; $totStoExt = 0;

        $valorQuiebreActual = '';
        $fmt = function($num) {
            $n = (float)$num;
            if (abs($n) < 0.000001) $n = 0;
            return ($n != 0) ? number_format($n, 2, '.', ',') : '';
        };

        $isNeg = function($val) {
            return (float)$val < -0.000001;
        };

        foreach ($data as $index => $item) {
            // ── Lógica de Agrupación ──
            $valorControl = '';
            $descripcionQuiebre = '';
            if ($quiebre === 'LINEA') {
                $valorControl = $item['linea_codigo'];
                $descripcionQuiebre = ($item['linea_codigo'] ?: '00') . ' - ' . ($item['linea_descri'] ?: 'SIN LÍNEA');
            } elseif ($quiebre === 'CUENTA') {
                $valorControl = $item['cuenta_codigo'];
                $descripcionQuiebre = ($item['cuenta_codigo'] ?: '000000') . ' - ' . ($item['cuenta_descri'] ?: 'SIN CUENTA');
            } else {
                $valorControl = $item['alma_codigo'];
                $descripcionQuiebre = ($item['alma_codigo'] ?: '000') . '   ' . ($item['alma_descri'] ?: 'ALMACÉN NO DEFINIDO');
            }

            if ($valorQuiebreActual !== '' && $valorQuiebreActual !== $valorControl) {
                $this->imprimirFilaTotal('TOTAL GRUPO :', $formato, $subIniU, $subEntU, $subSalU, $subStoU, $subIniExt, $subEntExt, $subSalExt, $subStoExt);
                $subIniU = 0; $subEntU = 0; $subSalU = 0; $subStoU = 0;
                $subIniExt = 0; $subEntExt = 0; $subSalExt = 0; $subStoExt = 0;
            }

            if ($valorQuiebreActual !== $valorControl) {
                $this->SetFillColor(241, 245, 249);
                $this->SetTextColor(15, 23, 42);   
                $this->SetDrawColor(241, 245, 249);
                $this->SetFont('Arial', 'B', 8);
                $this->Cell(277, 6, utf8_decode('   ' . $descripcionQuiebre), 1, 1, 'L', true);
                $valorQuiebreActual = $valorControl;
            }

            // ── Extracción y sumas ──
            $iniExt = $formato === 'PESO' ? $item['inicio_p'] : $item['inicio_v'];
            $entExt = $formato === 'PESO' ? $item['entrada_p'] : $item['entrada_v'];
            $salExt = $formato === 'PESO' ? $item['salida_p'] : $item['salida_v'];
            $stoExt = $formato === 'PESO' ? $item['stock_p'] : $item['stock_v'];
            $promedio = $item['precio_promedio'];
            
            $loteTexto = !empty($item['lote']) ? $item['lote'] : '00000000';

            $subIniU += (float)$item['inicio_u']; $subEntU += (float)$item['entrada_u'];
            $subSalU += (float)$item['salida_u']; $subStoU += (float)$item['stock_u'];
            $totIniU += (float)$item['inicio_u']; $totEntU += (float)$item['entrada_u'];
            $totSalU += (float)$item['salida_u']; $totStoU += (float)$item['stock_u'];
            
            $subIniExt += (float)$iniExt; $subEntExt += (float)$entExt;
            $subSalExt += (float)$salExt; $subStoExt += (float)$stoExt;
            $totIniExt += (float)$iniExt; $totEntExt += (float)$entExt;
            $totSalExt += (float)$salExt; $totStoExt += (float)$stoExt;

            // ── Verificación de Negativos ──
            $hasNegative = false;
            if ($formato === 'VALOR' || $formato === 'PESO') {
                $hasNegative = $isNeg($item['inicio_u']) || $isNeg($item['entrada_u']) || $isNeg($item['salida_u']) || $isNeg($item['stock_u']) || 
                               $isNeg($iniExt) || $isNeg($entExt) || $isNeg($salExt) || $isNeg($stoExt) || $isNeg($promedio);
            } elseif ($formato === 'RESUMEN') {
                $hasNegative = $isNeg($item['stock_u']) || $isNeg($stoExt) || $isNeg($promedio);
            } else {
                $hasNegative = $isNeg($item['inicio_u']) || $isNeg($item['entrada_u']) || $isNeg($item['salida_u']) || $isNeg($item['stock_u']);
            }

            $fill = $hasNegative;
            if ($hasNegative) {
                $this->SetFillColor(254, 240, 138); // Amarillo
            }

            // ── Renderizado de Fila ──
            $this->SetDrawColor(229, 231, 235);
            $this->SetTextColor(55, 65, 81);
            $this->SetFont('Arial', '', 7.5);

            // Función auxiliar para imprimir las celdas con colores dinámicos
            $printCell = function($w, $val, $defR, $defG, $defB, $defStyle = '', $align = 'R') use ($fill, $fmt, $isNeg) {
                if ($isNeg($val)) {
                    $this->SetTextColor(220, 38, 38); // Rojo Fuerte
                    $this->SetFont('Arial', 'B', 7.5);
                } else {
                    $this->SetTextColor($defR, $defG, $defB); // Color original
                    $this->SetFont('Arial', $defStyle, 7.5);
                }
                $strVal = ($val != 0 || $val === '0.00' || $val === '0') ? (is_numeric($val) ? number_format((float)$val, 2) : $val) : '';
                $this->Cell($w, 5.5, $strVal, 1, 0, $align, $fill);
            };

            $desc = ' ' . substr(utf8_decode($item['descripcion'] ?? ''), 0, 45);

            // Imprimir Columnas Base (Código, Desc, Lote)
            if ($hasNegative) {
                $this->SetTextColor(15, 23, 42); // Gris muy oscuro para el texto base si está amarillo
            } else {
                $this->SetTextColor(55, 65, 81);
            }
            $this->SetFont('Arial', '', 7.5);

            if ($formato === 'VALOR' || $formato === 'PESO') {
                $this->Cell(18, 5.5, $item['codigo'], 1, 0, 'C', $fill);
                $this->SetFont('Arial', 'B', 7.5);
                $this->Cell(16, 5.5, $loteTexto, 1, 0, 'C', $fill);
                $this->SetFont('Arial', '', 7.5);
                $this->Cell(65, 5.5, $desc, 1, 0, 'L', $fill);

                $printCell(17, $item['inicio_u'], 55, 65, 81);
                $printCell(17, $item['entrada_u'], 37, 99, 235);
                $printCell(17, $item['salida_u'], 220, 38, 38);
                $printCell(17, $item['stock_u'], 15, 23, 42, 'B');

                // Si es PESO y el artículo no tiene peso (0), no imprimimos nada
                $vIni = ($formato === 'PESO' && $iniExt === '') ? '' : $iniExt;
                $vEnt = ($formato === 'PESO' && $entExt === '') ? '' : $entExt;
                $vSal = ($formato === 'PESO' && $salExt === '') ? '' : $salExt;
                $vSto = ($formato === 'PESO' && $stoExt === '') ? '' : $stoExt;

                $printCell(22, $vIni, 55, 65, 81);
                $printCell(22, $vEnt, 37, 99, 235);
                $printCell(22, $vSal, 220, 38, 38);
                $printCell(22, $vSto, 15, 23, 42, 'B');

                // Promedio
                $vProm = ($formato === 'PESO' && $promedio === '') ? '' : $promedio;
                if ($isNeg($vProm)) {
                    $this->SetTextColor(220, 38, 38);
                    $this->SetFont('Arial', 'B', 7.5);
                } else {
                    $this->SetTextColor(107, 114, 128); // Gris claro original
                    $this->SetFont('Arial', '', 7.5);
                }
                $strProm = ($vProm != 0 && $vProm !== '') ? number_format((float)$vProm, 2) : '';
                $this->Cell(22, 5.5, $strProm, 1, 1, 'R', $fill);

            } elseif ($formato === 'RESUMEN') {
                $this->Cell(25, 5.5, $item['codigo'], 1, 0, 'C', $fill);
                $this->SetFont('Arial', 'B', 7.5);
                $this->Cell(20, 5.5, $loteTexto, 1, 0, 'C', $fill);
                $this->SetFont('Arial', '', 7.5);
                $this->Cell(100, 5.5, $desc, 1, 0, 'L', $fill);

                $printCell(44, $item['stock_u'], 15, 23, 42, 'B');
                $printCell(44, $item['stock_v'], 15, 23, 42, 'B');

                $prom = ((float)$item['stock_u'] == 0 && (float)$item['precio_promedio'] == 0) ? '' : $item['precio_promedio'];
                if ($isNeg($prom)) {
                    $this->SetTextColor(220, 38, 38);
                    $this->SetFont('Arial', 'B', 7.5);
                } else {
                    $this->SetTextColor(107, 114, 128);
                    $this->SetFont('Arial', '', 7.5);
                }
                $strProm = ($prom != 0 && $prom !== '') ? number_format((float)$prom, 2) : '';
                $this->Cell(44, 5.5, $strProm, 1, 1, 'R', $fill);

            } else {
                $this->Cell(20, 5.5, $item['codigo'], 1, 0, 'C', $fill);
                $this->SetFont('Arial', 'B', 7.5);
                $this->Cell(18, 5.5, $loteTexto, 1, 0, 'C', $fill);
                $this->SetFont('Arial', '', 7.5);
                $this->Cell(60, 5.5, $desc, 1, 0, 'L', $fill);

                $printCell(26, $item['inicio_u'], 55, 65, 81);
                $printCell(26, $item['entrada_u'], 37, 99, 235);
                $printCell(26, $item['salida_u'], 220, 38, 38);
                $this->Cell(23, 5.5, '0.00', 1, 0, 'R', $fill); // Ajuste
                $printCell(26, $item['stock_u'], 15, 23, 42, 'B');
                $this->Cell(26, 5.5, '0.00', 1, 0, 'R', $fill); // Diario
                $this->Cell(26, 5.5, '0.00', 1, 1, 'R', $fill); // Alcance
            }

            // Cierre de totales al último elemento
            if ($index == count($data) - 1) {
                $this->imprimirFilaTotal('TOTAL GRUPO :', $formato, $subIniU, $subEntU, $subSalU, $subStoU, $subIniExt, $subEntExt, $subSalExt, $subStoExt);
                $this->imprimirFilaTotal('TOTAL GENERAL :', $formato, $totIniU, $totEntU, $totSalU, $totStoU, $totIniExt, $totEntExt, $totSalExt, $totStoExt, true);
            }
        }

        if (ob_get_length()) ob_end_clean();
        $this->Output('I', 'Reporte_Stock_Almacenes.pdf');
    }

    private function imprimirFilaTotal($titulo, $formato, $iU, $eU, $sU, $stU, $iE, $eE, $sE, $stE, $esGeneral = false)
    {
        $this->SetDrawColor(229, 231, 235);
        $this->SetFont('Arial', 'B', $esGeneral ? 8 : 7.5);

        if ($esGeneral) {
            $this->SetFillColor(30, 64, 175); // Azul oscuro
            $this->SetTextColor(255, 255, 255);
        } else {
            $this->SetFillColor(248, 250, 252); // Gris tenue
            $this->SetTextColor(15, 23, 42);
        }

        if ($formato === 'VALOR' || $formato === 'PESO') {
            if ($formato === 'PESO' && $iE == 0 && $eE == 0 && $sE == 0 && $stE == 0) {
                $iE = ''; $eE = ''; $sE = ''; $stE = '';
            } else {
                $iE = number_format($iE, 2); $eE = number_format($eE, 2);
                $sE = number_format($sE, 2); $stE = number_format($stE, 2);
            }

            $this->Cell(99, 6, utf8_decode($titulo), 1, 0, 'R', true); // 18 + 65 + 16
            $this->Cell(17, 6, number_format($iU, 2), 1, 0, 'R', true);
            $this->Cell(17, 6, number_format($eU, 2), 1, 0, 'R', true);
            $this->Cell(17, 6, number_format($sU, 2), 1, 0, 'R', true);
            $this->Cell(17, 6, number_format($stU, 2), 1, 0, 'R', true);
            $this->Cell(22, 6, $iE, 1, 0, 'R', true);
            $this->Cell(22, 6, $eE, 1, 0, 'R', true);
            $this->Cell(22, 6, $sE, 1, 0, 'R', true);
            $this->Cell(22, 6, $stE, 1, 0, 'R', true);
            $this->Cell(22, 6, '', 1, 1, 'R', true);
            
        } elseif ($formato === 'RESUMEN') {
            $this->Cell(145, 6, utf8_decode($titulo), 1, 0, 'R', true); // 25 + 100 + 20
            $this->Cell(44, 6, number_format($stU, 2), 1, 0, 'R', true);
            $this->Cell(44, 6, number_format($stE, 2), 1, 0, 'R', true);
            $this->Cell(44, 6, '', 1, 1, 'R', true);
            
        } else {
            // UNIDADES
            $this->Cell(98, 6, utf8_decode($titulo), 1, 0, 'R', true); // 20 + 60 + 18
            $this->Cell(26, 6, number_format($iU, 2), 1, 0, 'R', true);
            $this->Cell(26, 6, number_format($eU, 2), 1, 0, 'R', true);
            $this->Cell(26, 6, number_format($sU, 2), 1, 0, 'R', true);
            $this->Cell(23, 6, '', 1, 0, 'R', true);
            $this->Cell(26, 6, number_format($stU, 2), 1, 0, 'R', true);
            $this->Cell(26, 6, '', 1, 0, 'R', true);
            $this->Cell(26, 6, '', 1, 1, 'R', true);
        }
    }
}