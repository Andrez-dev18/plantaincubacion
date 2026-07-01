<?php
date_default_timezone_set('America/Lima');
require_once __DIR__ . '/../libraries/fpdf/fpdf.php';

class ReporteStockPdf extends FPDF
{
    public $filtros = [];

    public function Header()
    {
        // ── BARRA AZUL PRINCIPAL REFINADA (Ancho total 277mm) ──
        $this->SetFillColor(37, 99, 235);
        $this->Rect(10, 10, 277, 10, 'F');

        $this->SetTextColor(255, 255, 255);
        $this->SetFont('Arial', 'B', 9);

        //
        $formato = $this->filtros['formato'] ?? 'UNIDADES';
        $tituloReporte = 'STOCK DE PRODUCTOS EN UNIDADES'; // Por defecto

        if ($formato === 'VALOR') {
            $tituloReporte = 'STOCK DE PRODUCTOS VALORADOS';
        } elseif ($formato === 'PESO') {
            $tituloReporte = 'STOCK DE PRODUCTOS PESO';
        } elseif ($formato === 'RESUMEN') {
            $tituloReporte = 'STOCK DE PRODUCTOS RESUMEN';
        }

        // Fila de la Franja Superior con el título dinámico inyectado en el centro
        $this->SetXY(12, 10);
        $this->Cell(60, 10, utf8_decode('PLANTA INCUBACION'), 0, 0, 'L');
        $this->Cell(145, 10, utf8_decode($tituloReporte), 0, 0, 'C');
        $this->Cell(60, 10, date('d/m/Y H:i'), 0, 1, 'R');

        // Subtítulos de Filtros abajo de la barra
        $this->SetTextColor(100, 100, 100);
        $this->SetFont('Arial', '', 8);
        $this->SetX(10);

        $fechaRango = ($this->filtros['fechaInicio'] ?? '') . ' al ' . ($this->filtros['fechaFin'] ?? '');
        $quiebre = $this->filtros['quiebre'] ?? 'ALMACEN';

        // Distribuimos simétricamente el Rango a la izquierda y el Agrupado a la derecha
        $this->Cell(138, 6, utf8_decode('Rango de Fechas: ' . $fechaRango), 0, 0, 'L');
        $this->Cell(139, 6, utf8_decode('Agrupado por: ' . $quiebre), 0, 1, 'R');
        $this->Ln(2);

        // ── CABECERAS DINÁMICAS ESTILO FOXPRO ──
        $this->SetFillColor(37, 99, 235);
        $this->SetTextColor(255, 255, 255);
        $this->SetDrawColor(255, 255, 255);
        $this->SetFont('Arial', 'B', 7.5);

        if ($formato === 'VALOR' || $formato === 'PESO') {
            $tituloSec = $formato === 'VALOR' ? 'VALOR' : 'PESO';
            $this->SetFillColor(30, 58, 138); // Azul más oscuro para la super-cabecera

            // Fila 1 (Super Cabecera)
            $this->Cell(22, 5, 'CODIGO', 'TLR', 0, 'C', true);
            $this->Cell(75, 5, 'DESCRIPCION', 'TLR', 0, 'C', true);
            $this->Cell(72, 5, '<--- CANTIDAD --->', 1, 0, 'C', true);
            $this->Cell(88, 5, "<--- $tituloSec --->", 1, 0, 'C', true);
            $this->Cell(20, 5, 'PROM', 'TLR', 1, 'C', true);

            // Fila 2 (Sub Columnas)
            $this->SetFillColor(37, 99, 235); // Regresa al azul normal
            $this->Cell(22, 5, '', 'BLR', 0, 'C', true);
            $this->Cell(75, 5, '', 'BLR', 0, 'C', true);
            $this->Cell(18, 5, 'Inicio', 1, 0, 'R', true);
            $this->Cell(18, 5, 'Entrada', 1, 0, 'R', true);
            $this->Cell(18, 5, 'Salida', 1, 0, 'R', true);
            $this->Cell(18, 5, 'Stock', 1, 0, 'R', true);
            $this->Cell(22, 5, 'Inicio', 1, 0, 'R', true);
            $this->Cell(22, 5, 'Entrada', 1, 0, 'R', true);
            $this->Cell(22, 5, 'Salida', 1, 0, 'R', true);
            $this->Cell(22, 5, 'Stock', 1, 0, 'R', true);
            $this->Cell(20, 5, '', 'BLR', 1, 'C', true);
        } elseif ($formato === 'RESUMEN') {
            $cols = [
                ['CÓDIGO', 35],
                ['DESCRIPCIÓN', 110],
                ['STOCK UNIDADES', 44],
                ['STOCK VALORADO', 44],
                ['PRECIO PROM.', 44]
            ];
            foreach ($cols as $col) $this->Cell($col[1], 6, utf8_decode($col[0]), 1, 0, 'C', true);
            $this->Ln();
        } else {
            // UNIDADES
            $cols = [
                ['CÓDIGO', 25],
                ['DESCRIPCIÓN', 80],
                ['INICIO', 25],
                ['ENTRADA', 25],
                ['CONSUMO', 25],
                ['AJUSTE', 22],
                ['STOCK', 25],
                ['DIARIO', 25],
                ['ALCANCE', 25]
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
        // Llama a AddPage después de setear los filtros para que el Header lo detecte
        $this->AddPage('L', 'A4');
        $this->SetFont('Arial', '', 7.5);

        if (empty($data)) {
            $this->SetTextColor(100, 100, 100);
            $this->Cell(277, 10, 'No se encontraron registros para los filtros aplicados.', 1, 1, 'C');
            $this->Output('I', 'Reporte_Stock.pdf');
            return;
        }

        // Acumuladores
        $subIniU = 0;
        $subEntU = 0;
        $subSalU = 0;
        $subStoU = 0;
        $subIniExt = 0;
        $subEntExt = 0;
        $subSalExt = 0;
        $subStoExt = 0;
        $totIniU = 0;
        $totEntU = 0;
        $totSalU = 0;
        $totStoU = 0;
        $totIniExt = 0;
        $totEntExt = 0;
        $totSalExt = 0;
        $totStoExt = 0;

        $valorQuiebreActual = '';

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
                $subIniU = 0;
                $subEntU = 0;
                $subSalU = 0;
                $subStoU = 0;
                $subIniExt = 0;
                $subEntExt = 0;
                $subSalExt = 0;
                $subStoExt = 0;
            }

            if ($valorQuiebreActual !== $valorControl) {
                $this->SetFillColor(241, 245, 249); // Un gris slate más suave y moderno (Tailwind bg-slate-100)
                $this->SetTextColor(15, 23, 42);    // Texto oscuro pizarra
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

            $subIniU += (float)$item['inicio_u'];
            $subEntU += (float)$item['entrada_u'];
            $subSalU += (float)$item['salida_u'];
            $subStoU += (float)$item['stock_u'];
            $totIniU += (float)$item['inicio_u'];
            $totEntU += (float)$item['entrada_u'];
            $totSalU += (float)$item['salida_u'];
            $totStoU += (float)$item['stock_u'];
            $subIniExt += (float)$iniExt;
            $subEntExt += (float)$entExt;
            $subSalExt += (float)$salExt;
            $subStoExt += (float)$stoExt;
            $totIniExt += (float)$iniExt;
            $totEntExt += (float)$entExt;
            $totSalExt += (float)$salExt;
            $totStoExt += (float)$stoExt;

            // ── Renderizado de Fila ──
            $this->SetDrawColor(229, 231, 235);
            $this->SetTextColor(55, 65, 81);
            $this->SetFont('Arial', '', 7.5);

            $desc = ' ' . substr(utf8_decode($item['descripcion'] ?? ''), 0, 45);

            if ($formato === 'VALOR' || $formato === 'PESO') {
                $vIni = ($iniExt === '') ? '' : number_format((float)$iniExt, 2);
                $vEnt = ($entExt === '') ? '' : number_format((float)$entExt, 2);
                $vSal = ($salExt === '') ? '' : number_format((float)$salExt, 2);
                $vSto = ($stoExt === '') ? '' : number_format((float)$stoExt, 2);
                $prom = ($item['precio_promedio'] === '') ? '' : number_format((float)$item['precio_promedio'], 2);

                $this->Cell(22, 5.5, $item['codigo'], 1, 0, 'C');
                $this->Cell(75, 5.5, $desc, 1, 0, 'L');
                $this->Cell(18, 5.5, number_format((float)$item['inicio_u'], 2), 1, 0, 'R');
                $this->Cell(18, 5.5, number_format((float)$item['entrada_u'], 2), 1, 0, 'R');
                $this->Cell(18, 5.5, number_format((float)$item['salida_u'], 2), 1, 0, 'R');
                $this->SetFont('Arial', 'B', 7.5); // Stock en negrita
                $this->Cell(18, 5.5, number_format((float)$item['stock_u'], 2), 1, 0, 'R');
                $this->SetFont('Arial', '', 7.5);

                $this->Cell(22, 5.5, $vIni, 1, 0, 'R');
                $this->Cell(22, 5.5, $vEnt, 1, 0, 'R');
                $this->Cell(22, 5.5, $vSal, 1, 0, 'R');
                $this->SetFont('Arial', 'B', 7.5); // Stock valorado en negrita
                $this->Cell(22, 5.5, $vSto, 1, 0, 'R');
                $this->SetFont('Arial', '', 7.5);
                $this->Cell(20, 5.5, $prom, 1, 1, 'R');
            } elseif ($formato === 'RESUMEN') {
                $prom = ((float)$item['stock_u'] == 0 && (float)$item['precio_promedio'] == 0) ? '' : number_format((float)$item['precio_promedio'], 2);
                $this->Cell(35, 5.5, $item['codigo'], 1, 0, 'C');
                $this->Cell(110, 5.5, $desc, 1, 0, 'L');
                $this->Cell(44, 5.5, number_format((float)$item['stock_u'], 2), 1, 0, 'R');
                $this->Cell(44, 5.5, number_format((float)$item['stock_v'], 2), 1, 0, 'R');
                $this->Cell(44, 5.5, $prom, 1, 1, 'R');
            } else {
                $this->Cell(25, 5.5, $item['codigo'], 1, 0, 'C');
                $this->Cell(80, 5.5, $desc, 1, 0, 'L');
                $this->Cell(25, 5.5, number_format((float)$item['inicio_u'], 2), 1, 0, 'R');
                $this->Cell(25, 5.5, number_format((float)$item['entrada_u'], 2), 1, 0, 'R');
                $this->Cell(25, 5.5, number_format((float)$item['salida_u'], 2), 1, 0, 'R');
                $this->Cell(22, 5.5, '0.00', 1, 0, 'R'); // Ajuste
                $this->SetFont('Arial', 'B', 7.5);
                $this->Cell(25, 5.5, number_format((float)$item['stock_u'], 2), 1, 0, 'R');
                $this->SetFont('Arial', '', 7.5);
                $this->Cell(25, 5.5, '0.00', 1, 0, 'R'); // Diario
                $this->Cell(25, 5.5, '0.00', 1, 1, 'R'); // Alcance
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
                $iE = '';
                $eE = '';
                $sE = '';
                $stE = '';
            } else {
                $iE = number_format($iE, 2);
                $eE = number_format($eE, 2);
                $sE = number_format($sE, 2);
                $stE = number_format($stE, 2);
            }

            $this->Cell(97, 6, utf8_decode($titulo), 1, 0, 'R', true);
            $this->Cell(18, 6, number_format($iU, 2), 1, 0, 'R', true);
            $this->Cell(18, 6, number_format($eU, 2), 1, 0, 'R', true);
            $this->Cell(18, 6, number_format($sU, 2), 1, 0, 'R', true);
            $this->Cell(18, 6, number_format($stU, 2), 1, 0, 'R', true);
            $this->Cell(22, 6, $iE, 1, 0, 'R', true);
            $this->Cell(22, 6, $eE, 1, 0, 'R', true);
            $this->Cell(22, 6, $sE, 1, 0, 'R', true);
            $this->Cell(22, 6, $stE, 1, 0, 'R', true);
            $this->Cell(20, 6, '', 1, 1, 'R', true);
        } elseif ($formato === 'RESUMEN') {
            $this->Cell(145, 6, utf8_decode($titulo), 1, 0, 'R', true);
            $this->Cell(44, 6, number_format($stU, 2), 1, 0, 'R', true);
            $this->Cell(44, 6, number_format($stE, 2), 1, 0, 'R', true);
            $this->Cell(44, 6, '', 1, 1, 'R', true);
        } else {
            // UNIDADES
            $this->Cell(105, 6, utf8_decode($titulo), 1, 0, 'R', true);
            $this->Cell(25, 6, number_format($iU, 2), 1, 0, 'R', true);
            $this->Cell(25, 6, number_format($eU, 2), 1, 0, 'R', true);
            $this->Cell(25, 6, number_format($sU, 2), 1, 0, 'R', true);
            $this->Cell(22, 6, '', 1, 0, 'R', true);
            $this->Cell(25, 6, number_format($stU, 2), 1, 0, 'R', true);
            $this->Cell(25, 6, '', 1, 0, 'R', true);
            $this->Cell(25, 6, '', 1, 1, 'R', true);
        }
    }
}
