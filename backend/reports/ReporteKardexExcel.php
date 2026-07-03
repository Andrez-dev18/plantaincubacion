<?php
date_default_timezone_set('America/Lima');

// Importar la librería PHPExcel desde la ubicación correcta
require_once __DIR__ . '/../libraries/Classes/PHPExcel.php';

class ReporteKardexExcel
{
    public function exportarExcel($filtros, $data)
    {
        $fechaRango = ($filtros['fechaInicio'] ?? '') . ' al ' . ($filtros['fechaFin'] ?? '');
        $almacen = $filtros['almacenNombre'] ?? $filtros['zona'] ?? '';

        $fIni = str_replace('-', '/', $filtros['fechaInicio'] ?? '');
        $fFin = str_replace('-', '/', $filtros['fechaFin'] ?? '');
        $tituloReporte = "KARDEX DE PRODUCCION DESDE $fIni HASTA $fFin";

        $filename = 'Reporte_Kardex_' . date('Ymd_His') . '.xlsx';

        // Instanciar PHPExcel
        $objPHPExcel = new PHPExcel();
        $objPHPExcel->setActiveSheetIndex(0);
        $sheet = $objPHPExcel->getActiveSheet();
        $sheet->setTitle('Kardex');

        // Configurar orientación horizontal para impresión (Landscape)
        $sheet->getPageSetup()->setOrientation(PHPExcel_Worksheet_PageSetup::ORIENTATION_LANDSCAPE);

        // Estilos
        $styleHeader = array(
            'font' => array(
                'bold' => true,
                'color' => array('rgb' => 'FFFFFF'),
                'size' => 9,
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

        $styleSaldo = array(
            'font' => array(
                'bold' => true,
                'size' => 9,
                'color' => array('rgb' => '334155'),
                'name' => 'Arial'
            ),
            'fill' => array(
                'type' => PHPExcel_Style_Fill::FILL_SOLID,
                'color' => array('rgb' => 'F8FAFC')
            ),
            'borders' => array(
                'allborders' => array(
                    'style' => PHPExcel_Style_Border::BORDER_THIN,
                    'color' => array('rgb' => 'CBD5E1')
                )
            )
        );

        $styleTotal = array(
            'font' => array(
                'bold' => true,
                'size' => 9.5,
                'color' => array('rgb' => '0F172A'),
                'name' => 'Arial'
            ),
            'fill' => array(
                'type' => PHPExcel_Style_Fill::FILL_SOLID,
                'color' => array('rgb' => 'F1F5F9')
            ),
            'borders' => array(
                'allborders' => array(
                    'style' => PHPExcel_Style_Border::BORDER_THIN,
                    'color' => array('rgb' => '94A3B8')
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
        $sheet->setCellValue('A1', 'GRANJA RINCONADA DEL SUR S.A.');
        $sheet->getStyle('A1')->getFont()->setSize(14)->setBold(true)->setColor(new PHPExcel_Style_Color('1E3A8A'));

        $sheet->setCellValue('A2', $tituloReporte);
        $sheet->getStyle('A2')->getFont()->setSize(12)->setBold(true)->setColor(new PHPExcel_Style_Color('3B82F6'));

        $sheet->setCellValue('A3', 'Fecha Emisión:');
        $sheet->getStyle('A3')->getFont()->setBold(true)->setColor(new PHPExcel_Style_Color('475569'));
        $sheet->setCellValue('C3', date('d/m/Y H:i'));

        $sheet->setCellValue('A4', 'Rango Fechas:');
        $sheet->getStyle('A4')->getFont()->setBold(true)->setColor(new PHPExcel_Style_Color('475569'));
        $sheet->setCellValue('C4', $fechaRango);

        $sheet->setCellValue('A5', 'Almacén:');
        $sheet->getStyle('A5')->getFont()->setBold(true)->setColor(new PHPExcel_Style_Color('475569'));
        $sheet->setCellValue('C5', $almacen);

        // Fila 8: Cabeceras compuestas (Super Cabecera)
        $sheet->setCellValue('A8', 'FECHA');
        $sheet->mergeCells('A8:A9');
        $sheet->setCellValue('B8', 'NRO.DOC');
        $sheet->mergeCells('B8:B9');
        $sheet->setCellValue('C8', 'CODTRA');
        $sheet->mergeCells('C8:C9');
        $sheet->setCellValue('D8', 'DESCRIPCION');
        $sheet->mergeCells('D8:D9');

        $sheet->setCellValue('E8', 'UNIDADES');
        $sheet->mergeCells('E8:G8');

        $sheet->setCellValue('H8', 'PESO');
        $sheet->mergeCells('H8:J8');

        $sheet->setCellValue('K8', 'IMPORTE');
        $sheet->mergeCells('K8:M8');

        $sheet->setCellValue('N8', 'COS.UNIT');
        $sheet->mergeCells('N8:N9');

        // Fila 9: Sub-Cabeceras
        $subHeaders = [
            'E' => 'ENTRADA', 'F' => 'SALIDA', 'G' => 'STOCK',
            'H' => 'ENTRADA', 'I' => 'SALIDA', 'J' => 'STOCK',
            'K' => 'ENTRADA', 'L' => 'SALIDA', 'M' => 'STOCK'
        ];
        foreach ($subHeaders as $col => $title) {
            $sheet->setCellValue($col . '9', $title);
        }

        // Estilos cabeceras
        $sheet->getStyle('A8:N9')->applyFromArray($styleHeader);

        $currentRow = 10;

        $isNeg = function($val) {
            return (float)$val < -0.000001;
        };

        $codigoActual = '';

        if (is_array($data) || $data instanceof Traversable) {
            foreach ($data as $producto) {
                $codigo      = $producto['codigo'];
                $descripcion = $producto['descripcion'];
                $lote        = (!empty($producto['lote']) && $producto['lote'] !== '00000000') ? $producto['lote'] : '';

                // ── CABECERA DE PRODUCTO (Solo si cambia el código del artículo) ──
                if ($codigoActual !== $codigo) {
                    // Fila Almacén
                    $sheet->setCellValue('A' . $currentRow, "   ALMACÉN: " . $almacen);
                    $sheet->mergeCells("A{$currentRow}:N{$currentRow}");
                    $sheet->getStyle("A{$currentRow}:N{$currentRow}")->applyFromArray(array(
                        'font' => array('bold' => true, 'size' => 8, 'color' => array('rgb' => '64748B')),
                        'fill' => array('type' => PHPExcel_Style_Fill::FILL_SOLID, 'color' => array('rgb' => 'F1F5F9')),
                        'borders' => array('allborders' => array('style' => PHPExcel_Style_Border::BORDER_THIN, 'color' => array('rgb' => 'E2E8F0')))
                    ));
                    $currentRow++;

                    // Fila Código - Descripción
                    $sheet->setCellValue('A' . $currentRow, "   " . $codigo . " - " . $descripcion);
                    $sheet->mergeCells("A{$currentRow}:N{$currentRow}");
                    $sheet->getStyle("A{$currentRow}:N{$currentRow}")->applyFromArray(array(
                        'font' => array('bold' => true, 'size' => 10, 'color' => array('rgb' => '0F172A')),
                        'fill' => array('type' => PHPExcel_Style_Fill::FILL_SOLID, 'color' => array('rgb' => 'F1F5F9')),
                        'borders' => array('allborders' => array('style' => PHPExcel_Style_Border::BORDER_THIN, 'color' => array('rgb' => 'E2E8F0')))
                    ));
                    $currentRow++;

                    $codigoActual = $codigo;
                }

                // ── SUB-CABECERA DE LOTE (Si viene un lote válido y diferente de vacío) ──
                if ($lote !== '') {
                    $sheet->setCellValue('A' . $currentRow, "   LOTE: " . $lote);
                    $sheet->mergeCells("A{$currentRow}:N{$currentRow}");
                    $sheet->getStyle("A{$currentRow}:N{$currentRow}")->applyFromArray(array(
                        'font' => array('bold' => true, 'size' => 9, 'color' => array('rgb' => '1E293B')),
                        'fill' => array('type' => PHPExcel_Style_Fill::FILL_SOLID, 'color' => array('rgb' => 'F8FAFC')),
                        'borders' => array('allborders' => array('style' => PHPExcel_Style_Border::BORDER_THIN, 'color' => array('rgb' => 'E2E8F0')))
                    ));
                    $currentRow++;
                }

                // ── Fila Saldo Inicial ──
                $cantSaldo = (float)($producto['saldo_inicial']['cant'] ?? 0);
                $pesoSaldo = (float)($producto['saldo_inicial']['peso'] ?? 0);
                $valSaldo  = (float)($producto['saldo_inicial']['val'] ?? 0);
                $puSaldo   = (float)($producto['saldo_inicial']['pu'] ?? 0);

                $sheet->setCellValue('A' . $currentRow, 'SALDO:');
                $sheet->mergeCells("A{$currentRow}:D{$currentRow}");
                $sheet->setCellValue('E' . $currentRow, '');
                $sheet->setCellValue('F' . $currentRow, '');
                $sheet->setCellValue('G' . $currentRow, $cantSaldo);
                $sheet->setCellValue('H' . $currentRow, '');
                $sheet->setCellValue('I' . $currentRow, '');
                $sheet->setCellValue('J' . $currentRow, $pesoSaldo);
                $sheet->setCellValue('K' . $currentRow, '');
                $sheet->setCellValue('L' . $currentRow, '');
                $sheet->setCellValue('M' . $currentRow, $valSaldo);
                $sheet->setCellValue('N' . $currentRow, $puSaldo);

                // Aplicar estilos
                $sheet->getStyle("A{$currentRow}:N{$currentRow}")->applyFromArray($styleSaldo);
                $sheet->getStyle("A{$currentRow}:D{$currentRow}")->getAlignment()->setHorizontal(PHPExcel_Style_Alignment::HORIZONTAL_RIGHT);
                $sheet->getStyle("G{$currentRow}")->getAlignment()->setHorizontal(PHPExcel_Style_Alignment::HORIZONTAL_RIGHT);
                $sheet->getStyle("J{$currentRow}")->getAlignment()->setHorizontal(PHPExcel_Style_Alignment::HORIZONTAL_RIGHT);
                $sheet->getStyle("M{$currentRow}")->getAlignment()->setHorizontal(PHPExcel_Style_Alignment::HORIZONTAL_RIGHT);
                $sheet->getStyle("N{$currentRow}")->getAlignment()->setHorizontal(PHPExcel_Style_Alignment::HORIZONTAL_RIGHT);

                // Formatos
                $sheet->getStyle("G{$currentRow}")->getNumberFormat()->setFormatCode('#,##0.00');
                $sheet->getStyle("J{$currentRow}")->getNumberFormat()->setFormatCode('#,##0.00');
                $sheet->getStyle("M{$currentRow}")->getNumberFormat()->setFormatCode('#,##0.00');
                $sheet->getStyle("N{$currentRow}")->getNumberFormat()->setFormatCode('#,##0.00');

                // Color rojo si es negativo
                $hasNegativeSaldo = $isNeg($cantSaldo) || $isNeg($pesoSaldo) || $isNeg($valSaldo) || $isNeg($puSaldo);
                if ($isNeg($cantSaldo)) $sheet->getStyle('G' . $currentRow)->getFont()->getColor()->setRGB('DC2626');
                if ($isNeg($pesoSaldo)) $sheet->getStyle('J' . $currentRow)->getFont()->getColor()->setRGB('DC2626');
                if ($isNeg($valSaldo))  $sheet->getStyle('M' . $currentRow)->getFont()->getColor()->setRGB('DC2626');
                if ($isNeg($puSaldo))   $sheet->getStyle('N' . $currentRow)->getFont()->getColor()->setRGB('DC2626');

                if ($hasNegativeSaldo) {
                    $sheet->getStyle("A{$currentRow}:N{$currentRow}")->getFill()->setFillType(PHPExcel_Style_Fill::FILL_SOLID)->getStartColor()->setRGB('FEF08A');
                }

                $currentRow++;

                // ── Totales locales ──
                $sumEntU = 0; $sumSalU = 0;
                $sumEntP = 0; $sumSalP = 0;
                $sumEntI = 0; $sumSalI = 0;

                // ── Detalle de movimientos ──
                foreach ($producto['detalle'] as $mov) {
                    $fPts     = explode('-', $mov['fecha']);
                    $fechaFmt = (count($fPts) == 3) ? $fPts[2] . '/' . $fPts[1] : $mov['fecha'];
                    $descriMov = (!empty($mov['nomref']) && trim($mov['nomref']) !== '') ? $mov['nomref'] : $mov['descri'];

                    $entCant = (float)$mov['ent_cant'];
                    $salCant = (float)$mov['sal_cant'];
                    $stoCant = (float)$mov['sto_cant'];
                    $entPeso = (float)$mov['ent_peso'];
                    $salPeso = (float)$mov['sal_peso'];
                    $stoPeso = (float)$mov['sto_peso'];
                    $entVal  = (float)$mov['ent_val'];
                    $salVal  = (float)$mov['sal_val'];
                    $stoVal  = (float)$mov['sto_val'];
                    $puVal   = (float)$mov['pu'];

                    $sumEntU += $entCant; $sumSalU += $salCant;
                    $sumEntP += $entPeso; $sumSalP += $salPeso;
                    $sumEntI += $entVal;  $sumSalI += $salVal;

                    $hasNegative = (
                        $isNeg($entCant) || $isNeg($salCant) || $isNeg($stoCant) ||
                        $isNeg($entPeso) || $isNeg($salPeso) || $isNeg($stoPeso) ||
                        $isNeg($entVal)  || $isNeg($salVal)  || $isNeg($stoVal)  || $isNeg($puVal)
                    );

                    $sheet->setCellValue('A' . $currentRow, $fechaFmt);
                    $sheet->setCellValue('B' . $currentRow, $mov['tnumfac']);
                    $sheet->setCellValueExplicit('C' . $currentRow, $mov['codtra'], PHPExcel_Cell_DataType::TYPE_STRING);
                    $sheet->setCellValue('D' . $currentRow, $descriMov);

                    $sheet->setCellValue('E' . $currentRow, $entCant !== 0.0 ? $entCant : '');
                    $sheet->setCellValue('F' . $currentRow, $salCant !== 0.0 ? $salCant : '');
                    $sheet->setCellValue('G' . $currentRow, $stoCant);

                    $sheet->setCellValue('H' . $currentRow, $entPeso !== 0.0 ? $entPeso : '');
                    $sheet->setCellValue('I' . $currentRow, $salPeso !== 0.0 ? $salPeso : '');
                    $sheet->setCellValue('J' . $currentRow, $stoPeso);

                    $sheet->setCellValue('K' . $currentRow, $entVal !== 0.0 ? $entVal : '');
                    $sheet->setCellValue('L' . $currentRow, $salVal !== 0.0 ? $salVal : '');
                    $sheet->setCellValue('M' . $currentRow, $stoVal);

                    $sheet->setCellValue('N' . $currentRow, $puVal !== 0.0 ? $puVal : '');

                    // Aplicar alineaciones
                    $sheet->getStyle('A' . $currentRow)->applyFromArray($styleDataCenter);
                    $sheet->getStyle('B' . $currentRow)->applyFromArray($styleDataCenter);
                    $sheet->getStyle('C' . $currentRow)->applyFromArray($styleDataCenter);
                    $sheet->getStyle('D' . $currentRow)->applyFromArray($styleDataLeft);
                    
                    $sheet->getStyle("E{$currentRow}:N{$currentRow}")->applyFromArray($styleDataRight);

                    // Formatos
                    $sheet->getStyle("E{$currentRow}:N{$currentRow}")->getNumberFormat()->setFormatCode('#,##0.00');

                    // Rojo si es negativo
                    if ($isNeg($entCant)) $sheet->getStyle('E' . $currentRow)->getFont()->getColor()->setRGB('DC2626');
                    if ($isNeg($salCant)) $sheet->getStyle('F' . $currentRow)->getFont()->getColor()->setRGB('DC2626');
                    if ($isNeg($stoCant)) $sheet->getStyle('G' . $currentRow)->getFont()->getColor()->setRGB('DC2626');
                    if ($isNeg($entPeso)) $sheet->getStyle('H' . $currentRow)->getFont()->getColor()->setRGB('DC2626');
                    if ($isNeg($salPeso)) $sheet->getStyle('I' . $currentRow)->getFont()->getColor()->setRGB('DC2626');
                    if ($isNeg($stoPeso)) $sheet->getStyle('J' . $currentRow)->getFont()->getColor()->setRGB('DC2626');
                    if ($isNeg($entVal))  $sheet->getStyle('K' . $currentRow)->getFont()->getColor()->setRGB('DC2626');
                    if ($isNeg($salVal))  $sheet->getStyle('L' . $currentRow)->getFont()->getColor()->setRGB('DC2626');
                    if ($isNeg($stoVal))  $sheet->getStyle('M' . $currentRow)->getFont()->getColor()->setRGB('DC2626');
                    if ($isNeg($puVal))   $sheet->getStyle('N' . $currentRow)->getFont()->getColor()->setRGB('DC2626');

                    if ($hasNegative) {
                        $sheet->getStyle("A{$currentRow}:N{$currentRow}")->getFill()->setFillType(PHPExcel_Style_Fill::FILL_SOLID)->getStartColor()->setRGB('FEF08A');
                    }

                    $currentRow++;
                }

                // ── Fila de Totales por Producto ──
                if (!empty($producto['detalle'])) {
                    $sheet->setCellValue('A' . $currentRow, 'TOTALES:');
                    $sheet->mergeCells("A{$currentRow}:D{$currentRow}");

                    $sheet->setCellValue('E' . $currentRow, $sumEntU);
                    $sheet->setCellValue('F' . $currentRow, $sumSalU);
                    $sheet->setCellValue('G' . $currentRow, '');

                    $sheet->setCellValue('H' . $currentRow, $sumEntP);
                    $sheet->setCellValue('I' . $currentRow, $sumSalP);
                    $sheet->setCellValue('J' . $currentRow, '');

                    $sheet->setCellValue('K' . $currentRow, $sumEntI);
                    $sheet->setCellValue('L' . $currentRow, $sumSalI);
                    $sheet->setCellValue('M' . $currentRow, '');
                    $sheet->setCellValue('N' . $currentRow, '');

                    // Estilo
                    $sheet->getStyle("A{$currentRow}:N{$currentRow}")->applyFromArray($styleTotal);
                    $sheet->getStyle("A{$currentRow}:D{$currentRow}")->getAlignment()->setHorizontal(PHPExcel_Style_Alignment::HORIZONTAL_RIGHT);
                    $sheet->getStyle("E{$currentRow}:N{$currentRow}")->getAlignment()->setHorizontal(PHPExcel_Style_Alignment::HORIZONTAL_RIGHT);

                    // Formatos
                    $sheet->getStyle("E{$currentRow}:N{$currentRow}")->getNumberFormat()->setFormatCode('#,##0.00');

                    // Rojo si es negativo
                    if ($isNeg($sumEntU)) $sheet->getStyle('E' . $currentRow)->getFont()->getColor()->setRGB('DC2626');
                    if ($isNeg($sumSalU)) $sheet->getStyle('F' . $currentRow)->getFont()->getColor()->setRGB('DC2626');
                    if ($isNeg($sumEntP)) $sheet->getStyle('H' . $currentRow)->getFont()->getColor()->setRGB('DC2626');
                    if ($isNeg($sumSalP)) $sheet->getStyle('I' . $currentRow)->getFont()->getColor()->setRGB('DC2626');
                    if ($isNeg($sumEntI)) $sheet->getStyle('K' . $currentRow)->getFont()->getColor()->setRGB('DC2626');
                    if ($isNeg($sumSalI)) $sheet->getStyle('L' . $currentRow)->getFont()->getColor()->setRGB('DC2626');

                    $currentRow++;
                }

                // Espaciador entre productos
                $currentRow++;
            }
        }

        // Anchos de columna
        $widths = [
            'A' => 12,
            'B' => 18,
            'C' => 10,
            'D' => 35,
            'E' => 14,
            'F' => 14,
            'G' => 14,
            'H' => 14,
            'I' => 14,
            'J' => 14,
            'K' => 16,
            'L' => 16,
            'M' => 16,
            'N' => 14
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
