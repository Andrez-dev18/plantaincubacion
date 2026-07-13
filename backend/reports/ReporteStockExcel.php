<?php
date_default_timezone_set('America/Lima');

// Importar la librería PHPExcel desde la ubicación correcta
require_once __DIR__ . '/../libraries/Classes/PHPExcel.php';

class ReporteStockExcel
{
    public function exportarExcel($filtros, $data)
    {
        $fechaRango = ($filtros['fechaInicio'] ?? '') . ' al ' . ($filtros['fechaFin'] ?? '');
        $quiebre = $filtros['quiebre'] ?? 'ALMACEN';
        $formato = $filtros['formato'] ?? 'UNIDADES';

        $tituloReporte = 'STOCK DE PRODUCTOS EN UNIDADES'; 
        if ($formato === 'VALOR') {
            $tituloReporte = 'STOCK DE PRODUCTOS VALORADOS';
        } elseif ($formato === 'PESO') {
            $tituloReporte = 'STOCK DE PRODUCTOS PESO';
        } elseif ($formato === 'RESUMEN') {
            $tituloReporte = 'STOCK DE PRODUCTOS RESUMEN';
        }

        $filename = 'Reporte_Stock_' . date('Ymd_His') . '.xlsx';

        // Instanciar PHPExcel
        $objPHPExcel = new PHPExcel();
        $objPHPExcel->setActiveSheetIndex(0);
        $sheet = $objPHPExcel->getActiveSheet();
        $sheet->setTitle('Stock');

        // Configurar orientación horizontal para impresión (Landscape)
        $sheet->getPageSetup()->setOrientation(PHPExcel_Worksheet_PageSetup::ORIENTATION_LANDSCAPE);

        // Estilos
        $styleHeader = array(
            'font' => array(
                'bold' => true,
                'color' => array('rgb' => 'FFFFFF'),
                'size' => 10,
                'name' => 'Arial'
            ),
            'fill' => array(
                'type' => PHPExcel_Style_Fill::FILL_SOLID,
                'color' => array('rgb' => '1E3A8A')
            ),
            'alignment' => array(
                'horizontal' => PHPExcel_Style_Alignment::HORIZONTAL_CENTER,
                'vertical' => PHPExcel_Style_Alignment::VERTICAL_CENTER,
            ),
            'borders' => array(
                'allborders' => array(
                    'style' => PHPExcel_Style_Border::BORDER_THIN,
                    'color' => array('rgb' => 'CBD5E1')
                )
            )
        );

        $styleSubtotal = array(
            'font' => array(
                'bold' => true,
                'size' => 9.5,
                'name' => 'Arial'
            ),
            'fill' => array(
                'type' => PHPExcel_Style_Fill::FILL_SOLID,
                'color' => array('rgb' => 'F8FAFC')
            ),
            'borders' => array(
                'allborders' => array(
                    'style' => PHPExcel_Style_Border::BORDER_THIN,
                    'color' => array('rgb' => 'E2E8F0')
                )
            )
        );

        $styleTotal = array(
            'font' => array(
                'bold' => true,
                'color' => array('rgb' => 'FFFFFF'),
                'size' => 10,
                'name' => 'Arial'
            ),
            'fill' => array(
                'type' => PHPExcel_Style_Fill::FILL_SOLID,
                'color' => array('rgb' => '1E3A8A')
            ),
            'borders' => array(
                'allborders' => array(
                    'style' => PHPExcel_Style_Border::BORDER_THIN,
                    'color' => array('rgb' => '1E3A8A')
                )
            )
        );

        $styleDataLeft = array(
            'alignment' => array('horizontal' => PHPExcel_Style_Alignment::HORIZONTAL_LEFT),
            'borders' => array(
                'allborders' => array(
                    'style' => PHPExcel_Style_Border::BORDER_THIN,
                    'color' => array('rgb' => 'E2E8F0')
                )
            )
        );

        $styleDataCenter = array(
            'alignment' => array('horizontal' => PHPExcel_Style_Alignment::HORIZONTAL_CENTER),
            'borders' => array(
                'allborders' => array(
                    'style' => PHPExcel_Style_Border::BORDER_THIN,
                    'color' => array('rgb' => 'E2E8F0')
                )
            )
        );

        $styleDataRight = array(
            'alignment' => array('horizontal' => PHPExcel_Style_Alignment::HORIZONTAL_RIGHT),
            'borders' => array(
                'allborders' => array(
                    'style' => PHPExcel_Style_Border::BORDER_THIN,
                    'color' => array('rgb' => 'E2E8F0')
                )
            )
        );

        // Encabezados informativos
        $sheet->setCellValue('A1', 'PLANTA INCUBACION');
        $sheet->getStyle('A1')->getFont()->setSize(14)->setBold(true)->setColor(new PHPExcel_Style_Color('1E3A8A'));

        $sheet->setCellValue('A2', $tituloReporte);
        $sheet->getStyle('A2')->getFont()->setSize(12)->setBold(true)->setColor(new PHPExcel_Style_Color('3B82F6'));

        $sheet->setCellValue('A3', 'Fecha Emisión:');
        $sheet->getStyle('A3')->getFont()->setBold(true)->setColor(new PHPExcel_Style_Color('475569'));
        $sheet->setCellValue('C3', date('d/m/Y H:i'));

        $sheet->setCellValue('A4', 'Rango Fechas:');
        $sheet->getStyle('A4')->getFont()->setBold(true)->setColor(new PHPExcel_Style_Color('475569'));
        $sheet->setCellValue('C4', $fechaRango);

        $sheet->setCellValue('A5', 'Agrupado por:');
        $sheet->getStyle('A5')->getFont()->setBold(true)->setColor(new PHPExcel_Style_Color('475569'));
        $sheet->setCellValue('C5', $quiebre);

        // Fila 7: Cabeceras de tabla
        $headerRow = 8;
        $maxCol = 'J';

        if ($formato === 'VALOR' || $formato === 'PESO') {
            $maxCol = 'L';
            $tituloSec = $formato === 'VALOR' ? 'VALOR' : 'PESO';

            // Cabecera superior combinada
            $sheet->setCellValue('A8', 'CODIGO');
            $sheet->mergeCells('A8:A9');
            $sheet->setCellValue('B8', 'LOTE');
            $sheet->mergeCells('B8:B9');
            $sheet->setCellValue('C8', 'DESCRIPCION');
            $sheet->mergeCells('C8:C9');

            $sheet->setCellValue('D8', '<--- CANTIDAD --->');
            $sheet->mergeCells('D8:G8');

            $sheet->setCellValue('H8', "<--- {$tituloSec} --->");
            $sheet->mergeCells('H8:K8');

            $sheet->setCellValue('L8', 'PROM');
            $sheet->mergeCells('L8:L9');

            // Fila de subcolumnas
            $subHeaders = [
                'D' => 'Inicio',
                'E' => 'Entrada',
                'F' => 'Salida',
                'G' => 'Stock',
                'H' => 'Inicio',
                'I' => 'Entrada',
                'J' => 'Salida',
                'K' => 'Stock'
            ];
            foreach ($subHeaders as $col => $title) {
                $sheet->setCellValue($col . '9', $title);
            }

            // Aplicar estilo de cabecera a ambas filas
            $sheet->getStyle('A8:L9')->applyFromArray($styleHeader);
            $headerRow = 9;

        } elseif ($formato === 'RESUMEN') {
            $maxCol = 'F';
            $headers = [
                'A' => 'CÓDIGO',
                'B' => 'LOTE',
                'C' => 'DESCRIPCIÓN',
                'D' => 'STOCK UNIDADES',
                'E' => 'STOCK VALORADO',
                'F' => 'PRECIO PROM.'
            ];
            foreach ($headers as $col => $title) {
                $sheet->setCellValue($col . '8', $title);
            }
            $sheet->getStyle('A8:F8')->applyFromArray($styleHeader);
        } else {
            // UNIDADES
            $maxCol = 'J';
            $headers = [
                'A' => 'CÓDIGO',
                'B' => 'LOTE',
                'C' => 'DESCRIPCIÓN',
                'D' => 'INICIO',
                'E' => 'ENTRADA',
                'F' => 'CONSUMO',
                'G' => 'AJUSTE',
                'H' => 'STOCK',
                'I' => 'DIARIO',
                'J' => 'ALCANCE'
            ];
            foreach ($headers as $col => $title) {
                $sheet->setCellValue($col . '8', $title);
            }
            $sheet->getStyle('A8:J8')->applyFromArray($styleHeader);
        }

        $currentRow = $headerRow + 1;

        // Acumuladores
        $subIniU = 0; $subEntU = 0; $subSalU = 0; $subStoU = 0;
        $subIniExt = 0; $subEntExt = 0; $subSalExt = 0; $subStoExt = 0;
        $totIniU = 0; $totEntU = 0; $totSalU = 0; $totStoU = 0;
        $totIniExt = 0; $totEntExt = 0; $totSalExt = 0; $totStoExt = 0;

        $valorQuiebreActual = '';

        // Helper para imprimir totales de grupo/general
        $inyectarFilaTotal = function ($sheet, $rowNum, $titulo, $formato, $iU, $eU, $sU, $stU, $iE, $eE, $sE, $stE, $esGeneral, $styleSubtotal, $styleTotal) {
            $sheet->setCellValue('A' . $rowNum, $titulo);
            $sheet->mergeCells("A{$rowNum}:C{$rowNum}");

            $style = $esGeneral ? $styleTotal : $styleSubtotal;

            if ($formato === 'VALOR' || $formato === 'PESO') {
                $sheet->setCellValue('D' . $rowNum, $iU);
                $sheet->setCellValue('E' . $rowNum, $eU);
                $sheet->setCellValue('F' . $rowNum, $sU);
                $sheet->setCellValue('G' . $rowNum, $stU);

                $sheet->setCellValue('H' . $rowNum, $iE);
                $sheet->setCellValue('I' . $rowNum, $eE);
                $sheet->setCellValue('J' . $rowNum, $sE);
                $sheet->setCellValue('K' . $rowNum, $stE);
                $sheet->setCellValue('L' . $rowNum, '');

                $sheet->getStyle("A{$rowNum}:L{$rowNum}")->applyFromArray($style);
                $sheet->getStyle("D{$rowNum}:K{$rowNum}")->getAlignment()->setHorizontal(PHPExcel_Style_Alignment::HORIZONTAL_RIGHT);
                
                // Formatos
                $sheet->getStyle("D{$rowNum}:G{$rowNum}")->getNumberFormat()->setFormatCode('#,##0.00');
                $sheet->getStyle("H{$rowNum}:K{$rowNum}")->getNumberFormat()->setFormatCode('#,##0.00');

            } elseif ($formato === 'RESUMEN') {
                $sheet->setCellValue('D' . $rowNum, $stU);
                $sheet->setCellValue('E' . $rowNum, $stE);
                $sheet->setCellValue('F' . $rowNum, '');

                $sheet->getStyle("A{$rowNum}:F{$rowNum}")->applyFromArray($style);
                $sheet->getStyle("D{$rowNum}:E{$rowNum}")->getAlignment()->setHorizontal(PHPExcel_Style_Alignment::HORIZONTAL_RIGHT);
                $sheet->getStyle("D{$rowNum}:E{$rowNum}")->getNumberFormat()->setFormatCode('#,##0.00');

            } else {
                // UNIDADES
                $sheet->setCellValue('D' . $rowNum, $iU);
                $sheet->setCellValue('E' . $rowNum, $eU);
                $sheet->setCellValue('F' . $rowNum, $sU);
                $sheet->setCellValue('G' . $rowNum, '');
                $sheet->setCellValue('H' . $rowNum, $stU);
                $sheet->setCellValue('I' . $rowNum, '');
                $sheet->setCellValue('J' . $rowNum, '');

                $sheet->getStyle("A{$rowNum}:J{$rowNum}")->applyFromArray($style);
                $sheet->getStyle("D{$rowNum}:F{$rowNum}")->getAlignment()->setHorizontal(PHPExcel_Style_Alignment::HORIZONTAL_RIGHT);
                $sheet->getStyle("H{$rowNum}")->getAlignment()->setHorizontal(PHPExcel_Style_Alignment::HORIZONTAL_RIGHT);

                $sheet->getStyle("D{$rowNum}:F{$rowNum}")->getNumberFormat()->setFormatCode('#,##0.00');
                $sheet->getStyle("H{$rowNum}")->getNumberFormat()->setFormatCode('#,##0.00');
            }
        };

        if (is_array($data) || $data instanceof Traversable) {
            $totalItems = count($data);
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
                    $inyectarFilaTotal($sheet, $currentRow, 'TOTAL GRUPO :', $formato, $subIniU, $subEntU, $subSalU, $subStoU, $subIniExt, $subEntExt, $subSalExt, $subStoExt, false, $styleSubtotal, $styleTotal);
                    $currentRow++;

                    $subIniU = $subEntU = $subSalU = $subStoU = 0;
                    $subIniExt = $subEntExt = $subSalExt = $subStoExt = 0;
                }

                if ($valorQuiebreActual !== $valorControl) {
                    $sheet->setCellValue('A' . $currentRow, '   ' . $descripcionQuiebre);
                    $sheet->mergeCells("A{$currentRow}:{$maxCol}{$currentRow}");
                    $sheet->getStyle("A{$currentRow}:{$maxCol}{$currentRow}")->applyFromArray(array(
                        'font' => array('bold' => true, 'size' => 9, 'color' => array('rgb' => '0F172A')),
                        'fill' => array('type' => PHPExcel_Style_Fill::FILL_SOLID, 'color' => array('rgb' => 'E2E8F0')),
                    ));
                    $currentRow++;
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

                // Verificar si hay algún negativo para sombrear en amarillo
                $hasNegative = false;
                $negFields = [];

                if ($formato === 'VALOR' || $formato === 'PESO') {
                    $negFields = ['inicio_u' => $item['inicio_u'], 'entrada_u' => $item['entrada_u'], 'salida_u' => $item['salida_u'], 'stock_u' => $item['stock_u'], 'iniExt' => $iniExt, 'entExt' => $entExt, 'salExt' => $salExt, 'stoExt' => $stoExt, 'promedio' => $promedio];
                } elseif ($formato === 'RESUMEN') {
                    $negFields = ['stock_u' => $item['stock_u'], 'stock_v' => $item['stock_v'], 'precio_promedio' => $item['precio_promedio']];
                } else {
                    $negFields = ['inicio_u' => $item['inicio_u'], 'entrada_u' => $item['entrada_u'], 'salida_u' => $item['salida_u'], 'stock_u' => $item['stock_u']];
                }

                foreach ($negFields as $k => $v) {
                    if ((float)$v < -0.000001) {
                        $hasNegative = true;
                        break;
                    }
                }

                // Escribir fila de detalle
                $sheet->setCellValue('A' . $currentRow, $item['codigo']);
                $sheet->setCellValueExplicit('B' . $currentRow, $loteTexto, PHPExcel_Cell_DataType::TYPE_STRING);
                $sheet->setCellValue('C' . $currentRow, $item['descripcion']);

                if ($formato === 'VALOR' || $formato === 'PESO') {
                    $sheet->setCellValue('D' . $currentRow, (float)$item['inicio_u']);
                    $sheet->setCellValue('E' . $currentRow, (float)$item['entrada_u']);
                    $sheet->setCellValue('F' . $currentRow, (float)$item['salida_u']);
                    $sheet->setCellValue('G' . $currentRow, (float)$item['stock_u']);
                    $sheet->setCellValue('H' . $currentRow, (float)$iniExt);
                    $sheet->setCellValue('I' . $currentRow, (float)$entExt);
                    $sheet->setCellValue('J' . $currentRow, (float)$salExt);
                    $sheet->setCellValue('K' . $currentRow, (float)$stoExt);
                    $sheet->setCellValue('L' . $currentRow, (float)$promedio);

                    $sheet->getStyle('A' . $currentRow)->applyFromArray($styleDataCenter);
                    $sheet->getStyle('B' . $currentRow)->applyFromArray($styleDataCenter);
                    $sheet->getStyle('C' . $currentRow)->applyFromArray($styleDataLeft);
                    $sheet->getStyle('D' . $currentRow)->applyFromArray($styleDataRight);
                    $sheet->getStyle('E' . $currentRow)->applyFromArray($styleDataRight);
                    $sheet->getStyle('F' . $currentRow)->applyFromArray($styleDataRight);
                    $sheet->getStyle('G' . $currentRow)->applyFromArray($styleDataRight);
                    $sheet->getStyle('H' . $currentRow)->applyFromArray($styleDataRight);
                    $sheet->getStyle('I' . $currentRow)->applyFromArray($styleDataRight);
                    $sheet->getStyle('J' . $currentRow)->applyFromArray($styleDataRight);
                    $sheet->getStyle('K' . $currentRow)->applyFromArray($styleDataRight);
                    $sheet->getStyle('L' . $currentRow)->applyFromArray($styleDataRight);

                    // Formatos
                    $sheet->getStyle("D{$currentRow}:L{$currentRow}")->getNumberFormat()->setFormatCode('#,##0.00');

                    // Rojo si es negativo
                    if ((float)$item['inicio_u'] < -0.000001) $sheet->getStyle('D' . $currentRow)->getFont()->getColor()->setRGB('DC2626');
                    if ((float)$item['entrada_u'] < -0.000001) $sheet->getStyle('E' . $currentRow)->getFont()->getColor()->setRGB('DC2626');
                    if ((float)$item['salida_u'] < -0.000001) $sheet->getStyle('F' . $currentRow)->getFont()->getColor()->setRGB('DC2626');
                    if ((float)$item['stock_u'] < -0.000001) $sheet->getStyle('G' . $currentRow)->getFont()->getColor()->setRGB('DC2626');
                    if ((float)$iniExt < -0.000001) $sheet->getStyle('H' . $currentRow)->getFont()->getColor()->setRGB('DC2626');
                    if ((float)$entExt < -0.000001) $sheet->getStyle('I' . $currentRow)->getFont()->getColor()->setRGB('DC2626');
                    if ((float)$salExt < -0.000001) $sheet->getStyle('J' . $currentRow)->getFont()->getColor()->setRGB('DC2626');
                    if ((float)$stoExt < -0.000001) $sheet->getStyle('K' . $currentRow)->getFont()->getColor()->setRGB('DC2626');
                    if ((float)$promedio < -0.000001) $sheet->getStyle('L' . $currentRow)->getFont()->getColor()->setRGB('DC2626');

                } elseif ($formato === 'RESUMEN') {
                    $sheet->setCellValue('D' . $currentRow, (float)$item['stock_u']);
                    $sheet->setCellValue('E' . $currentRow, (float)$item['stock_v']);
                    $sheet->setCellValue('F' . $currentRow, (float)$item['precio_promedio']);

                    $sheet->getStyle('A' . $currentRow)->applyFromArray($styleDataCenter);
                    $sheet->getStyle('B' . $currentRow)->applyFromArray($styleDataCenter);
                    $sheet->getStyle('C' . $currentRow)->applyFromArray($styleDataLeft);
                    $sheet->getStyle('D' . $currentRow)->applyFromArray($styleDataRight);
                    $sheet->getStyle('E' . $currentRow)->applyFromArray($styleDataRight);
                    $sheet->getStyle('F' . $currentRow)->applyFromArray($styleDataRight);

                    // Formatos
                    $sheet->getStyle("D{$currentRow}:F{$currentRow}")->getNumberFormat()->setFormatCode('#,##0.00');

                    // Rojo si es negativo
                    if ((float)$item['stock_u'] < -0.000001) $sheet->getStyle('D' . $currentRow)->getFont()->getColor()->setRGB('DC2626');
                    if ((float)$item['stock_v'] < -0.000001) $sheet->getStyle('E' . $currentRow)->getFont()->getColor()->setRGB('DC2626');
                    if ((float)$item['precio_promedio'] < -0.000001) $sheet->getStyle('F' . $currentRow)->getFont()->getColor()->setRGB('DC2626');

                } else {
                    // UNIDADES
                    $sheet->setCellValue('D' . $currentRow, (float)$item['inicio_u']);
                    $sheet->setCellValue('E' . $currentRow, (float)$item['entrada_u']);
                    $sheet->setCellValue('F' . $currentRow, (float)$item['salida_u']);
                    $sheet->setCellValue('G' . $currentRow, 0.00); // Ajuste
                    $sheet->setCellValue('H' . $currentRow, (float)$item['stock_u']);
                    $sheet->setCellValue('I' . $currentRow, 0.00); // Diario
                    $sheet->setCellValue('J' . $currentRow, 0.00); // Alcance

                    $sheet->getStyle('A' . $currentRow)->applyFromArray($styleDataCenter);
                    $sheet->getStyle('B' . $currentRow)->applyFromArray($styleDataCenter);
                    $sheet->getStyle('C' . $currentRow)->applyFromArray($styleDataLeft);
                    $sheet->getStyle('D' . $currentRow)->applyFromArray($styleDataRight);
                    $sheet->getStyle('E' . $currentRow)->applyFromArray($styleDataRight);
                    $sheet->getStyle('F' . $currentRow)->applyFromArray($styleDataRight);
                    $sheet->getStyle('G' . $currentRow)->applyFromArray($styleDataRight);
                    $sheet->getStyle('H' . $currentRow)->applyFromArray($styleDataRight);
                    $sheet->getStyle('I' . $currentRow)->applyFromArray($styleDataRight);
                    $sheet->getStyle('J' . $currentRow)->applyFromArray($styleDataRight);

                    // Formatos
                    $sheet->getStyle("D{$currentRow}:J{$currentRow}")->getNumberFormat()->setFormatCode('#,##0.00');

                    // Rojo si es negativo
                    if ((float)$item['inicio_u'] < -0.000001) $sheet->getStyle('D' . $currentRow)->getFont()->getColor()->setRGB('DC2626');
                    if ((float)$item['entrada_u'] < -0.000001) $sheet->getStyle('E' . $currentRow)->getFont()->getColor()->setRGB('DC2626');
                    if ((float)$item['salida_u'] < -0.000001) $sheet->getStyle('F' . $currentRow)->getFont()->getColor()->setRGB('DC2626');
                    if ((float)$item['stock_u'] < -0.000001) $sheet->getStyle('H' . $currentRow)->getFont()->getColor()->setRGB('DC2626');
                }

                // Sombrear en amarillo la fila entera si tiene algún negativo
                if ($hasNegative) {
                    $sheet->getStyle("A{$currentRow}:{$maxCol}{$currentRow}")->getFill()->setFillType(PHPExcel_Style_Fill::FILL_SOLID)->getStartColor()->setRGB('FEF08A');
                }

                $currentRow++;

                if ($index === $totalItems - 1) {
                    $inyectarFilaTotal($sheet, $currentRow, 'TOTAL GRUPO :', $formato, $subIniU, $subEntU, $subSalU, $subStoU, $subIniExt, $subEntExt, $subSalExt, $subStoExt, false, $styleSubtotal, $styleTotal);
                    $currentRow++;

                    $inyectarFilaTotal($sheet, $currentRow, 'TOTAL GENERAL :', $formato, $totIniU, $totEntU, $totSalU, $totStoU, $totIniExt, $totEntExt, $totSalExt, $totStoExt, true, $styleSubtotal, $styleTotal);
                    $currentRow++;
                }
            }
        }

        // Anchos de columna
        $widths = [
            'A' => 15,
            'B' => 15,
            'C' => 35,
            'D' => 16,
            'E' => 16,
            'F' => 16,
            'G' => 16,
            'H' => 16,
            'I' => 16,
            'J' => 16,
            'K' => 16,
            'L' => 16
        ];

        foreach ($widths as $col => $w) {
            $sheet->getColumnDimension($col)->setWidth($w);
        }

        // Limpiar buffers
        if (ob_get_length()) {
            ob_end_clean();
        }

        // Cabeceras HTTP
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: max-age=0');
        header('Pragma: public');

        // Descarga
        $objWriter = PHPExcel_IOFactory::createWriter($objPHPExcel, 'Excel2007');
        $objWriter->save('php://output');
        exit;
    }
}
