<?php
date_default_timezone_set('America/Lima');
require_once __DIR__ . '/../libraries/fpdf/fpdf.php';

class ReporteKardexPdf extends FPDF
{
    public $filtros = [];

    public function Header()
    {
        // ── BARRA AZUL PRINCIPAL REFINADA (Ancho total 277mm) ──
        $this->SetFillColor(37, 99, 235);
        $this->Rect(10, 10, 277, 10, 'F');

        $this->SetTextColor(255, 255, 255);
        $this->SetFont('Arial', 'B', 9);

        // ── NUEVA LÓGICA DE TÍTULO IDÉNTICA AL ORIGINAL ──
        $formato = $this->filtros['formato'] ?? 'VALOR';
        $tipoKardex = $formato === 'PESO' ? 'PESO' : ($formato === 'CANTIDAD' ? 'CANTIDAD' : 'VALORADOS');
        
        // Convertimos guiones a barras (2026-06-21 -> 2026/06/21)
        $fIni = str_replace('-', '/', $this->filtros['fechaInicio'] ?? '');
        $fFin = str_replace('-', '/', $this->filtros['fechaFin'] ?? '');
        
        $tituloReporte = "KARDEX PRODUCTOS $tipoKardex DESDE $fIni HASTA $fFin";

        // Fila de la Franja Superior
        $this->SetXY(12, 10);
        $this->Cell(60, 10, utf8_decode('GRANJA RINCONADA DEL SUR S.A.'), 0, 0, 'L');
        $this->Cell(145, 10, utf8_decode($tituloReporte), 0, 0, 'C'); 
        $this->Cell(60, 10, date('d/m/Y H:i'), 0, 1, 'R');

        // Subtítulos de Filtros abajo de la barra
        $this->SetTextColor(100, 100, 100);
        $this->SetFont('Arial', '', 8);
        $this->SetX(10);

        $fechaRango = ($this->filtros['fechaInicio'] ?? '') . ' al ' . ($this->filtros['fechaFin'] ?? '');
        $almacen = $this->filtros['almacenNombre'] ?? $this->filtros['zona'] ?? '010';

        $this->Cell(138, 6, utf8_decode('Rango de Fechas: ' . $fechaRango), 0, 0, 'L');
        $this->Cell(139, 6, utf8_decode('Almacén: ' . $almacen), 0, 1, 'R');
        $this->Ln(2);

        // ── CABECERAS DINÁMICAS ADAPTATIVAS ──
        $this->SetFillColor(37, 99, 235);
        $this->SetTextColor(255, 255, 255);
        $this->SetDrawColor(255, 255, 255);
        $this->SetFont('Arial', 'B', 7); 

        if ($formato === 'CANTIDAD') {
            // Estructura Compacta de 7 Columnas: 16 + 14 + 18 + 161 + 48 = 257mm exactos
            $this->Cell(16, 8, 'FECHA', 1, 0, 'C', true);
            $this->Cell(14, 8, 'CODTRA', 1, 0, 'C', true);
            $this->Cell(18, 8, 'NRO. DOC', 1, 0, 'C', true);
            $this->Cell(161, 8, 'DESCRIPCION', 1, 0, 'L', true);
            
            $this->SetFillColor(30, 58, 138); // Fondo oscuro para denotar totales de cantidad
            $this->Cell(48, 4, '<--- UNIDADES --->', 'TLR', 1, 'C', true);
            
            $this->SetXY(219, 32); // Posicionamiento exacto para la subfila de Unidades
            $this->SetFillColor(37, 99, 235);
            $this->Cell(16, 4, 'Entrada', 1, 0, 'R', true);
            $this->Cell(16, 4, 'Salida', 1, 0, 'R', true);
            $this->Cell(16, 4, 'Stock', 1, 1, 'R', true);
        } else {
            // Estructura Completa de 11 Columnas (VALOR / PESO): 16 + 14 + 18 + 81 + 48 + 64 + 16 = 257mm exactos
            $tituloSec = $formato === 'PESO' ? 'PESO' : 'VALORADO';
            $this->SetFillColor(30, 58, 138); 

            // Fila 1 (Super Cabecera)
            $this->Cell(16, 5, 'FECHA', 'TLR', 0, 'C', true);
            $this->Cell(14, 5, 'CODTRA', 'TLR', 0, 'C', true);
            $this->Cell(18, 5, 'NRO. DOC', 'TLR', 0, 'C', true);
            $this->Cell(81, 5, 'DESCRIPCION', 'TLR', 0, 'C', true);
            
            $this->Cell(48, 5, '<--- UNIDADES --->', 1, 0, 'C', true);
            $this->Cell(64, 5, "<--- $tituloSec --->", 1, 0, 'C', true);
            $this->Cell(16, 5, 'VA_UNT', 'TLR', 1, 'C', true);

            // Fila 2 (Sub Columnas)
            $this->SetFillColor(37, 99, 235); 
            $this->Cell(16, 5, '', 'BLR', 0, 'C', true);
            $this->Cell(14, 5, '', 'BLR', 0, 'C', true);
            $this->Cell(18, 5, '', 'BLR', 0, 'C', true);
            $this->Cell(81, 5, '', 'BLR', 0, 'C', true);
            
            // Unidades
            $this->Cell(16, 5, 'Entrada', 1, 0, 'R', true);
            $this->Cell(16, 5, 'Salida', 1, 0, 'R', true);
            $this->Cell(16, 5, 'Stock', 1, 0, 'R', true);
            
            // Valorado/Peso
            $this->Cell(20, 5, 'Entrada', 1, 0, 'R', true);
            $this->Cell(20, 5, 'Salida', 1, 0, 'R', true);
            $this->Cell(24, 5, 'Stock', 1, 0, 'R', true);
            
            $this->Cell(16, 5, '', 'BLR', 1, 'C', true);
        }
    }

    public function exportarPDF($filtros, $data)
    {
        $this->filtros = $filtros;
        $formato = $filtros['formato'] ?? 'VALOR';

        $this->SetTitle(utf8_decode('Reporte de Kardex - ' . $formato));
        $this->AddPage('L', 'A4');
        $this->SetFont('Arial', '', 7.5);

        if (empty($data)) {
            $this->SetTextColor(100, 100, 100);
            $this->Cell(277, 10, 'No se encontraron registros para los filtros aplicados.', 1, 1, 'C');
            $this->Output('I', 'Reporte_Kardex.pdf');
            return;
        }

        $fmt = function($num) {
            return ($num != 0 && $num != '') ? number_format((float)$num, 2, '.', ',') : '';
        };

        foreach ($data as $producto) {
            // ── FILA DEL PRODUCTO (Gris suave en 3 niveles) ──
            $this->SetFillColor(241, 245, 249); 
            $this->SetDrawColor(229, 231, 235); 
            
            $almacenFiltro = $this->filtros['almacenNombre'] ?? $this->filtros['zona'] ?? '010';
            $tituloProd = $producto['codigo'] . ' - ' . $producto['descripcion'];
            $lote = $producto['lote'] ?? '00000000';

            // Ajustamos el bloque del título completo a los 257mm distribuidos
            $this->SetTextColor(100, 100, 100); 
            $this->SetFont('Arial', '', 6.5);
            $this->Cell(257, 4, utf8_decode('   ALMACÉN: ' . $almacenFiltro), 'TLR', 1, 'L', true);

            $this->SetTextColor(15, 23, 42);    
            $this->SetFont('Arial', 'B', 8);
            $this->Cell(257, 5, utf8_decode('   ' . $tituloProd), 'LR', 1, 'L', true);

            $this->SetTextColor(100, 100, 100); 
            $this->SetFont('Arial', '', 6.5);
            $this->Cell(257, 4, utf8_decode('   LOTE: ' . $lote), 'BLR', 1, 'L', true);

            // ── FILA SALDO INICIAL ──
            $this->SetDrawColor(229, 231, 235); 
            $this->SetTextColor(55, 65, 81);
            $this->SetFont('Arial', 'B', 7);

            if ($formato === 'CANTIDAD') {
                // Modo Cantidad: Suma de columnas previas (16+14+18+161 = 209mm)
                $this->Cell(209, 5.5, 'SALDO: ', 1, 0, 'R'); 
                $this->Cell(16, 5.5, '', 1, 0, 'R'); 
                $this->Cell(16, 5.5, '', 1, 0, 'R'); 
                $this->Cell(16, 5.5, number_format((float)$producto['saldo_inicial']['cant'], 2), 1, 1, 'R'); 
            } else {
                // Modo Valor/Peso: Suma de columnas previas (16+14+18+81 = 129mm)
                $saldoExt = $formato === 'PESO' ? $producto['saldo_inicial']['peso'] : $producto['saldo_inicial']['val'];

                $this->Cell(129, 5.5, 'SALDO: ', 1, 0, 'R'); 
                $this->Cell(16, 5.5, '', 1, 0, 'R'); 
                $this->Cell(16, 5.5, '', 1, 0, 'R'); 
                $this->Cell(16, 5.5, number_format((float)$producto['saldo_inicial']['cant'], 2), 1, 0, 'R'); 
                
                $this->Cell(20, 5.5, '', 1, 0, 'R'); 
                $this->Cell(20, 5.5, '', 1, 0, 'R'); 
                $this->Cell(24, 5.5, number_format((float)$saldoExt, 2), 1, 0, 'R'); 
                $this->SetFont('Arial', '', 7);
                $this->Cell(16, 5.5, number_format((float)$producto['saldo_inicial']['pu'], 2), 1, 1, 'R');
            }

            // ── VARIABLES TOTALIZADORAS ──
            $sumEntU = 0; $sumSalU = 0;
            $sumEntExt = 0; $sumSalExt = 0;

            // ── DETALLE DE MOVIMIENTOS ──
            $this->SetFont('Arial', '', 7);
            foreach ($producto['detalle'] as $mov) {
                $fPts = explode('-', $mov['fecha']);
                $fechaFmt = (count($fPts) == 3) ? $fPts[2] . '/' . $fPts[1] : $mov['fecha'];

                $sumEntU += $mov['ent_cant'];
                $sumSalU += $mov['sal_cant'];

                // Dibujar columnas fijas de la izquierda
                $this->Cell(16, 5, $fechaFmt, 1, 0, 'C');
                $this->Cell(14, 5, $mov['codtra'], 1, 0, 'C');
                $this->Cell(18, 5, $mov['tnumfac'], 1, 0, 'C');
                
                $descriMov = (!empty($mov['nomref']) && trim($mov['nomref']) !== '') ? $mov['nomref'] : $mov['descri'];

                if ($formato === 'CANTIDAD') {
                    // Descripción extendida a 161mm para rellenar la hoja
                    $desc = substr(utf8_decode($descriMov), 0, 100); 
                    $this->Cell(161, 5, $desc, 1, 0, 'L');

                    // Cantidades físicas
                    $this->SetTextColor(37, 99, 235); 
                    $this->Cell(16, 5, $fmt($mov['ent_cant']), 1, 0, 'R');
                    $this->SetTextColor(220, 38, 38); 
                    $this->Cell(16, 5, $fmt($mov['sal_cant']), 1, 0, 'R');
                    $this->SetTextColor(15, 23, 42);  
                    $this->SetFont('Arial', 'B', 7);
                    $this->Cell(16, 5, $fmt($mov['sto_cant']), 1, 1, 'R'); // Salto de línea aquí
                    $this->SetFont('Arial', '', 7);
                } else {
                    // Descripción normal a 81mm
                    $desc = substr(utf8_decode($descriMov), 0, 50); 
                    $this->Cell(81, 5, $desc, 1, 0, 'L');

                    // Cantidades físicas
                    $this->SetTextColor(37, 99, 235); 
                    $this->Cell(16, 5, $fmt($mov['ent_cant']), 1, 0, 'R');
                    $this->SetTextColor(220, 38, 38); 
                    $this->Cell(16, 5, $fmt($mov['sal_cant']), 1, 0, 'R');
                    $this->SetTextColor(15, 23, 42);  
                    $this->SetFont('Arial', 'B', 7);
                    $this->Cell(16, 5, $fmt($mov['sto_cant']), 1, 0, 'R');
                    $this->SetFont('Arial', '', 7);

                    // Variables valoradas
                    $entExt = $formato === 'PESO' ? $mov['ent_peso'] : $mov['ent_val'];
                    $salExt = $formato === 'PESO' ? $mov['sal_peso'] : $mov['sal_val'];
                    $stoExt = $formato === 'PESO' ? $mov['sto_peso'] : $mov['sto_val'];

                    $sumEntExt += $entExt;
                    $sumSalExt += $salExt;

                    $this->SetTextColor(37, 99, 235);
                    $this->Cell(20, 5, $fmt($entExt), 1, 0, 'R');
                    $this->SetTextColor(220, 38, 38); 
                    $this->Cell(20, 5, $fmt($salExt), 1, 0, 'R');
                    $this->SetTextColor(15, 23, 42); 
                    $this->SetFont('Arial', 'B', 7);
                    $this->Cell(24, 5, $fmt($stoExt), 1, 0, 'R');
                    $this->SetFont('Arial', '', 7);
                    $this->SetTextColor(100, 100, 100); 
                    $this->Cell(16, 5, $fmt($mov['pu']), 1, 1, 'R');
                }
                
                $this->SetTextColor(55, 65, 81); 
            }

            // ── FILA DE TOTALES DINÁMICA ──
            if (!empty($producto['detalle'])) {
                $this->SetFont('Arial', 'B', 7);
                $this->SetTextColor(15, 23, 42);
                
                if ($formato === 'CANTIDAD') {
                    $this->Cell(209, 6, '', 1, 0); 
                    $this->SetTextColor(37, 99, 235);
                    $this->Cell(16, 6, $fmt($sumEntU), 1, 0, 'R');
                    $this->SetTextColor(220, 38, 38);
                    $this->Cell(16, 6, $fmt($sumSalU), 1, 0, 'R');
                    $this->Cell(16, 6, '', 1, 1, 'R'); 
                } else {
                    $this->Cell(129, 6, '', 1, 0); 
                    $this->SetTextColor(37, 99, 235);
                    $this->Cell(16, 6, $fmt($sumEntU), 1, 0, 'R');
                    $this->SetTextColor(220, 38, 38);
                    $this->Cell(16, 6, $fmt($sumSalU), 1, 0, 'R');
                    $this->Cell(16, 6, '', 1, 0, 'R'); 
                    
                    $this->SetTextColor(37, 99, 235);
                    $this->Cell(20, 6, $fmt($sumEntExt), 1, 0, 'R');
                    $this->SetTextColor(220, 38, 38);
                    $this->Cell(20, 6, $fmt($sumSalExt), 1, 0, 'R');
                    $this->Cell(40, 6, '', 1, 1, 'R'); 
                }
            } else {
                $this->Ln(0.5);
            }
            
            $this->Ln(3); 
        }

        if (ob_get_length()) ob_end_clean();
        $this->Output('I', 'Reporte_Kardex.pdf');
    }
}