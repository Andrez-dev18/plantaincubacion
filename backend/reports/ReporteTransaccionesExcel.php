<?php
date_default_timezone_set('America/Lima');

// Importar la librería PHPExcel desde la ubicación correcta
require_once __DIR__ . '/../libraries/Classes/PHPExcel.php';

class ReporteTransaccionesExcel
{
    public function exportarExcel($filtros, $data)
    {
        $fechaRango = ($filtros['fechaInicio'] ?? '') . ' al ' . ($filtros['fechaFin'] ?? '');
        $transaccionNombre = !empty($filtros['transaccionNombre']) ? $filtros['transaccionNombre'] : 'TODAS';
        $agruparPor = $filtros['agruparPor'] ?? 'FECHA';

        $filename = 'Reporte_Transacciones_' . date('Ymd_His') . '.xlsx';

        // Instanciar PHPExcel
        $objPHPExcel = new PHPExcel();
        $objPHPExcel->setActiveSheetIndex(0);
        $sheet = $objPHPExcel->getActiveSheet();
        $sheet->setTitle('Transacciones');

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
                'color' => array('rgb' => 'F1F5F9')
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
        $sheet->mergeCells('A1:N1');
        $sheet->getStyle('A1')->getFont()->setSize(14)->setBold(true)->setColor(new PHPExcel_Style_Color('1E3A8A'));

        $sheet->setCellValue('A2', 'REPORTE DE TRANSACCIONES');
        $sheet->mergeCells('A2:N2');
        $sheet->getStyle('A2')->getFont()->setSize(12)->setBold(true)->setColor(new PHPExcel_Style_Color('3B82F6'));

        $sheet->setCellValue('A3', 'Fecha Emisión:');
        $sheet->getStyle('A3')->getFont()->setBold(true)->setColor(new PHPExcel_Style_Color('475569'));
        $sheet->setCellValue('C3', date('d/m/Y H:i'));

        $sheet->setCellValue('A4', 'Rango Fechas:');
        $sheet->getStyle('A4')->getFont()->setBold(true)->setColor(new PHPExcel_Style_Color('475569'));
        $sheet->setCellValue('C4', $fechaRango);

        $sheet->setCellValue('A5', 'Transacción:');
        $sheet->getStyle('A5')->getFont()->setBold(true)->setColor(new PHPExcel_Style_Color('475569'));
        $sheet->setCellValue('C5', $transaccionNombre);

        $sheet->setCellValue('A6', 'Agrupado Por:');
        $sheet->getStyle('A6')->getFont()->setBold(true)->setColor(new PHPExcel_Style_Color('475569'));
        $sheet->setCellValue('C6', $agruparPor);

        // Fila 7 vacía

        // Fila 8: Cabeceras de tabla
        $headers = [
            'A' => 'FECHA',
            'B' => 'NRO. DOC',
            'C' => 'REF',
            'D' => 'COD. PRO',
            'E' => 'PROVEEDOR / CLIENTE',
            'F' => 'CENCOS',
            'G' => 'CÓDIGO',
            'H' => 'LOTE',
            'I' => 'DESCRIPCIÓN',
            'J' => 'CANTIDAD',
            'K' => 'C. UNIT',
            'L' => 'C. KARDEX',
            'M' => 'COSTO TOTAL',
            'N' => 'CC DEST'
        ];

        foreach ($headers as $col => $title) {
            $sheet->setCellValue($col . '8', $title);
        }
        $sheet->getStyle('A8:N8')->applyFromArray($styleHeader);

        // Llenado de datos
        $currentRow = 9;

        $sumaCantidad = 0;
        $sumaCUnit = 0;
        $sumaCKardex = 0;
        $sumaCostoTotal = 0;

        $subtotalCantidad = 0;
        $subtotalCUnit = 0;
        $subtotalCKardex = 0;
        $subtotalCosto = 0;

        $valorControlActual = '';
        $labelSubtotalActual = '';

        // Helper para inyectar subtotal
        $inyectarFilaSubtotal = function ($sheet, $rowNum, $labelTexto, $cant, $totCUnit, $totCKardex, $costo, $styleSubtotal) {
            $sheet->setCellValue('A' . $rowNum, "TOTALES POR FECHA: " . $labelTexto);
            $sheet->mergeCells("A{$rowNum}:I{$rowNum}");

            $sheet->setCellValue('J' . $rowNum, $cant);
            $sheet->setCellValue('K' . $rowNum, $totCUnit);
            $sheet->setCellValue('L' . $rowNum, $totCKardex);
            $sheet->setCellValue('M' . $rowNum, $costo);

            $sheet->getStyle("A{$rowNum}:N{$rowNum}")->applyFromArray($styleSubtotal);
            
            // Alinear a la derecha las celdas numéricas del subtotal
            $sheet->getStyle("J{$rowNum}:M{$rowNum}")->getAlignment()->setHorizontal(PHPExcel_Style_Alignment::HORIZONTAL_RIGHT);
            
            // Formatos
            $sheet->getStyle('J' . $rowNum)->getNumberFormat()->setFormatCode('#,##0.00');
            $sheet->getStyle('K' . $rowNum)->getNumberFormat()->setFormatCode('#,##0.000');
            $sheet->getStyle('L' . $rowNum)->getNumberFormat()->setFormatCode('#,##0.000');
            $sheet->getStyle('M' . $rowNum)->getNumberFormat()->setFormatCode('#,##0.000');
        };

        if (is_array($data) || $data instanceof Traversable) {
            $totalItems = count($data);
            foreach ($data as $index => $item) {
                $cantidad = (float)($item['cantidad'] ?? 0);
                $cTotal = (float)($item['costo_total'] ?? 0);
                $cUnit = (float)($item['c_unit'] ?? 0);
                $cKardex = (float)($item['c_kardex'] ?? 0);

                $cUnitCrudo = ($cantidad != 0) ? ($cTotal / $cantidad) : 0;

                $valorControlFila = '';
                $labelParaMostrar = '';

                if ($agruparPor === 'PRODUCTO') {
                    $valorControlFila = $item['codigo'] ?? '';
                    $labelParaMostrar = $item['descripcion'] ?? 'SIN DESCRIPCIÓN';
                } elseif ($agruparPor === 'ZONA_DESTINO') {
                    $valorControlFila = $item['cc_dest'] ?? '';
                    $labelParaMostrar = $item['cc_dest'] ?? 'SIN ZONA';
                } elseif ($agruparPor === 'LINEA') {
                    $valorControlFila = $item['linea_codigo'] ?? '';
                    $labelParaMostrar = $item['linea_codigo'] ?? 'SIN LÍNEA';
                } else { // FECHA
                    $valorControlFila = $item['fecha_formato'] ?? '';
                    $fechaParaMostrar = $item['fecha_formato'] ?? '';
                    if (strpos($fechaParaMostrar, '/') !== false) {
                        $partes = explode('/', $fechaParaMostrar);
                        $anio = date('Y');
                        $fechaParaMostrar = $partes[1] . '/' . $partes[0] . '/' . $anio;
                    }
                    $labelParaMostrar = $fechaParaMostrar;
                }

                if ($agruparPor !== 'TOTALES') {
                    if ($valorControlActual !== '' && $valorControlActual !== $valorControlFila) {
                        $inyectarFilaSubtotal($sheet, $currentRow, $labelSubtotalActual, $subtotalCantidad, $subtotalCUnit, $subtotalCKardex, $subtotalCosto, $styleSubtotal);
                        $currentRow++;
                        
                        $subtotalCantidad = 0;
                        $subtotalCUnit = 0;
                        $subtotalCKardex = 0;
                        $subtotalCosto = 0;
                    }
                }

                $valorControlActual = $valorControlFila;
                $labelSubtotalActual = $labelParaMostrar;

                $sumaCantidad += $cantidad;
                $sumaCUnit += $cUnitCrudo;
                $sumaCKardex += $cKardex;
                $sumaCostoTotal += $cTotal;

                $subtotalCantidad += $cantidad;
                $subtotalCUnit += $cUnitCrudo;
                $subtotalCKardex += $cKardex;
                $subtotalCosto += $cTotal;

                $lote = !empty($item['lote']) ? $item['lote'] : '00000000';
                $docRef = !empty($item['doc_ref']) ? $item['doc_ref'] : '0';

                // Llenar celdas del detalle
                $sheet->setCellValue('A' . $currentRow, $item['fecha_formato'] ?? '');
                $sheet->setCellValue('B' . $currentRow, $item['nro_doc'] ?? '');
                $sheet->setCellValueExplicit('C' . $currentRow, $docRef, PHPExcel_Cell_DataType::TYPE_STRING);
                $sheet->setCellValueExplicit('D' . $currentRow, $item['cod_pro'] ?? '', PHPExcel_Cell_DataType::TYPE_STRING);
                $sheet->setCellValue('E' . $currentRow, $item['proveedor_cliente'] ?? '');
                $sheet->setCellValueExplicit('F' . $currentRow, $item['cencos'] ?? '', PHPExcel_Cell_DataType::TYPE_STRING);
                $sheet->setCellValueExplicit('G' . $currentRow, $item['codigo'] ?? '', PHPExcel_Cell_DataType::TYPE_STRING);
                $sheet->setCellValueExplicit('H' . $currentRow, $lote, PHPExcel_Cell_DataType::TYPE_STRING);
                $sheet->setCellValue('I' . $currentRow, $item['descripcion'] ?? 'SIN DESCRIPCIÓN');
                $sheet->setCellValue('J' . $currentRow, $cantidad);
                $sheet->setCellValue('K' . $currentRow, $cUnit);
                $sheet->setCellValue('L' . $currentRow, $cKardex);
                $sheet->setCellValue('M' . $currentRow, $cTotal);
                $sheet->setCellValueExplicit('N' . $currentRow, $item['cc_dest'] ?? '', PHPExcel_Cell_DataType::TYPE_STRING);

                // Aplicar estilos
                $sheet->getStyle('A' . $currentRow)->applyFromArray($styleDataCenter);
                $sheet->getStyle('B' . $currentRow)->applyFromArray($styleDataCenter);
                $sheet->getStyle('C' . $currentRow)->applyFromArray($styleDataCenter);
                $sheet->getStyle('D' . $currentRow)->applyFromArray($styleDataCenter);
                $sheet->getStyle('E' . $currentRow)->applyFromArray($styleDataLeft);
                $sheet->getStyle('F' . $currentRow)->applyFromArray($styleDataCenter);
                $sheet->getStyle('G' . $currentRow)->applyFromArray($styleDataCenter);
                $sheet->getStyle('H' . $currentRow)->applyFromArray($styleDataCenter);
                $sheet->getStyle('I' . $currentRow)->applyFromArray($styleDataLeft);
                $sheet->getStyle('J' . $currentRow)->applyFromArray($styleDataRight);
                $sheet->getStyle('K' . $currentRow)->applyFromArray($styleDataRight);
                $sheet->getStyle('L' . $currentRow)->applyFromArray($styleDataRight);
                $sheet->getStyle('M' . $currentRow)->applyFromArray($styleDataRight);
                $sheet->getStyle('N' . $currentRow)->applyFromArray($styleDataCenter);

                // Formatear números
                $sheet->getStyle('J' . $currentRow)->getNumberFormat()->setFormatCode('#,##0.00');
                $sheet->getStyle('K' . $currentRow)->getNumberFormat()->setFormatCode('#,##0.000');
                $sheet->getStyle('L' . $currentRow)->getNumberFormat()->setFormatCode('#,##0.000');
                $sheet->getStyle('M' . $currentRow)->getNumberFormat()->setFormatCode('#,##0.000');

                $currentRow++;

                if ($index === $totalItems - 1) {
                    if ($agruparPor === 'TOTALES') {
                        $inyectarFilaSubtotal($sheet, $currentRow, "RANGO SELECCIONADO", $subtotalCantidad, $subtotalCUnit, $subtotalCKardex, $subtotalCosto, $styleSubtotal);
                    } else {
                        $inyectarFilaSubtotal($sheet, $currentRow, $labelSubtotalActual, $subtotalCantidad, $subtotalCUnit, $subtotalCKardex, $subtotalCosto, $styleSubtotal);
                    }
                    $currentRow++;
                }
            }
        }

        // Fila de Total General
        $sheet->setCellValue('A' . $currentRow, "TOTAL GENERAL :");
        $sheet->mergeCells("A{$currentRow}:I{$currentRow}");
        $sheet->setCellValue('J' . $currentRow, $sumaCantidad);
        $sheet->setCellValue('K' . $currentRow, $sumaCUnit);
        $sheet->setCellValue('L' . $currentRow, $sumaCKardex);
        $sheet->setCellValue('M' . $currentRow, $sumaCostoTotal);

        $sheet->getStyle("A{$currentRow}:N{$currentRow}")->applyFromArray($styleTotal);
        $sheet->getStyle("J{$currentRow}:M{$currentRow}")->getAlignment()->setHorizontal(PHPExcel_Style_Alignment::HORIZONTAL_RIGHT);
        
        $sheet->getStyle('J' . $currentRow)->getNumberFormat()->setFormatCode('#,##0.00');
        $sheet->getStyle('K' . $currentRow)->getNumberFormat()->setFormatCode('#,##0.000');
        $sheet->getStyle('L' . $currentRow)->getNumberFormat()->setFormatCode('#,##0.000');
        $sheet->getStyle('M' . $currentRow)->getNumberFormat()->setFormatCode('#,##0.000');

        // Configurar anchos de columna
        $widths = [
            'A' => 12,
            'B' => 18,
            'C' => 8,
            'D' => 12,
            'E' => 35,
            'F' => 10,
            'G' => 15,
            'H' => 15,
            'I' => 35,
            'J' => 15,
            'K' => 15,
            'L' => 15,
            'M' => 15,
            'N' => 12
        ];

        foreach ($widths as $col => $w) {
            $sheet->getColumnDimension($col)->setWidth($w);
        }

        // Limpiar buffers de salida antes de enviar cabeceras
        if (ob_get_length()) {
            ob_end_clean();
        }

        // Cabeceras HTTP para forzar la descarga de Excel 2007 (.xlsx)
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: max-age=0');
        header('Pragma: public');

        // Guardar y descargar
        $objWriter = PHPExcel_IOFactory::createWriter($objPHPExcel, 'Excel2007');
        $objWriter->save('php://output');
        exit;
    }
}
