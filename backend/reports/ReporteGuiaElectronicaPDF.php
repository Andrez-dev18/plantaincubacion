<?php
require_once __DIR__ . '/../libraries/fpdf/fpdf.php';

class PDFGuia extends FPDF {
    
    // Función para dibujar rectángulos con bordes redondeados
    function RoundedRect($x, $y, $w, $h, $r, $style = '') {
        $k = $this->k;
        $hp = $this->h;
        if($style == 'F') $op='f';
        elseif($style == 'FD' || $style == 'DF') $op='B';
        else $op='S';
        $MyArc = 4/3 * (sqrt(2) - 1);
        $this->_out(sprintf('%.2F %.2F m',($x+$r)*$k,($hp-$y)*$k ));
        $xc = $x+$w-$r ;
        $yc = $y+$r;
        $this->_out(sprintf('%.2F %.2F l', $xc*$k,($hp-$y)*$k ));
        $this->_Arc($xc + $r*$MyArc, $yc - $r, $xc + $r, $yc - $r*$MyArc, $xc + $r, $yc);
        $xc = $x+$w-$r ;
        $yc = $y+$h-$r;
        $this->_out(sprintf('%.2F %.2F l',($x+$w)*$k,($hp-$yc)*$k));
        $this->_Arc($xc + $r, $yc + $r*$MyArc, $xc + $r*$MyArc, $yc + $r, $xc, $yc + $r);
        $xc = $x+$r ;
        $yc = $y+$h-$r;
        $this->_out(sprintf('%.2F %.2F l',$xc*$k,($hp-($y+$h))*$k));
        $this->_Arc($xc - $r*$MyArc, $yc + $r, $xc - $r, $yc + $r*$MyArc, $xc - $r, $yc);
        $xc = $x+$r ;
        $yc = $y+$r;
        $this->_out(sprintf('%.2F %.2F l',($x)*$k,($hp-$yc)*$k ));
        $this->_Arc($xc - $r, $yc - $r*$MyArc, $xc - $r*$MyArc, $yc - $r, $xc, $yc - $r);
        $this->_out($op);
    }
    
    function _Arc($x1, $y1, $x2, $y2, $x3, $y3) {
        $h = $this->h;
        $this->_out(sprintf('%.2F %.2F %.2F %.2F %.2F %.2F c ', $x1*$this->k, ($h-$y1)*$this->k,
            $x2*$this->k, ($h-$y2)*$this->k, $x3*$this->k, ($h-$y3)*$this->k));
    }

    function HeaderCustom($datos) {
        $logoPath = __DIR__ . '/../libraries/img/logo.png';
        if (file_exists($logoPath)) {
            $this->Image($logoPath, 8, 5, 17, 27);
        }
        
        $this->SetFont('Arial', 'B', 12);
        $this->SetXY(38, 10);
        $this->Cell(95, 5, utf8_decode('GRANJA RINCONADA DEL SUR S.A.'), 0, 1, 'C');
        
        $this->SetFont('Arial', '', 7.5);
        $this->SetX(38);
        $this->Cell(95, 4, utf8_decode('CAL. BREA Y PARIÑAS NRO. 102 INT. 1102 URB. TAMBO DE MONTERRICO'), 0, 1, 'C');
        $this->SetX(38);
        $this->Cell(95, 4, utf8_decode('SANTIAGO DE SURCO - LIMA - LIMA'), 0, 1, 'C');
        $this->Ln(1);
        $this->SetX(38);
        $this->Cell(95, 4, utf8_decode('CALLE LA MAR S/N'), 0, 1, 'C');
        $this->SetX(38);
        $this->Cell(95, 4, utf8_decode('AREQUIPA - AREQUIPA - LA JOYA'), 0, 1, 'C');

        $this->RoundedRect(140, 10, 60, 25, 3, 'D'); 
        $this->Line(140, 17, 200, 17); 
        $this->Line(140, 26, 200, 26); 

        $this->SetFont('Arial', 'B', 10);
        $this->SetXY(140, 11.5);
        $this->Cell(60, 5, 'RUC: 20419158462', 0, 1, 'C');
        
        $this->SetXY(140, 18.5);
        $this->SetFont('Arial', 'B', 8.5);
        $this->Cell(60, 4, utf8_decode('GUÍA DE REMISIÓN'), 0, 1, 'C');
        $this->SetX(140);
        $this->Cell(60, 4, utf8_decode('REMITENTE ELECTRÓNICA'), 0, 1, 'C');
        
        $this->SetXY(140, 27.5);
        $this->SetFont('Arial', 'B', 11);
        $this->Cell(60, 5, $datos['serie'] . '-' . $datos['numero'], 0, 1, 'C');
        $this->Ln(10);
    }

    function SeccionCaja($titulo, $y_start, $altura, $contenido_callback) {
        $this->RoundedRect(10, $y_start, 190, $altura, 2, 'D');
        $this->SetXY(14, $y_start - 2);
        $this->SetFont('Arial', 'B', 7.5);
        $this->SetFillColor(255, 255, 255);
        $this->Cell($this->GetStringWidth($titulo) + 4, 3.5, utf8_decode($titulo), 0, 0, 'C', true);
        
        $this->SetXY(12, $y_start + 3.5);
        $contenido_callback($this);
    }

    function GenerarCabecerasGenerales($d) {
        $y = 40;
        
        // 1. DESTINATARIO
        $this->SeccionCaja('DESTINATARIO', $y, 11, function($pdf) use ($d) {
            $pdf->SetFont('Arial', 'B', 7.5);
            $pdf->Cell(24, 4, 'RUC: ', 0, 0, 'R');
            $pdf->SetFont('Arial', '', 7.5);
            $pdf->Cell(150, 4, $d['cliente_ruc'] ?? '', 0, 1, 'L');
            
            $pdf->SetFont('Arial', 'B', 7.5);
            $pdf->SetX(12);
            $pdf->Cell(24, 4, utf8_decode('Denominación: '), 0, 0, 'R');
            $pdf->SetFont('Arial', '', 7.5);
            $pdf->Cell(150, 4, utf8_decode($d['cliente_razon_social'] ?? ''), 0, 1, 'L');
        });

        // 2. DATOS DEL TRASLADO
        $y += 15;
        $this->SeccionCaja('DATOS DEL TRASLADO', $y, 16, function($pdf) use ($d) {
            $pdf->SetFont('Arial', 'B', 7.5);
            $pdf->Cell(24, 4, utf8_decode('Fecha Emisión: '), 0, 0, 'R');
            $pdf->SetFont('Arial', '', 7.5);
            $pdf->Cell(70, 4, $d['fecha_emision'] ?? '', 0, 0, 'L');
            
            $pdf->SetFont('Arial', 'B', 7.5);
            $pdf->Cell(55, 4, 'Fecha Traslado: ', 0, 0, 'R');
            $pdf->SetFont('Arial', '', 7.5);
            $pdf->Cell(30, 4, $d['fecha_traslado'] ?? '', 0, 1, 'R'); 

            $pdf->SetX(12);
            $pdf->SetFont('Arial', 'B', 7.5);
            $pdf->Cell(24, 4, 'Motivo Traslado: ', 0, 0, 'R');
            $pdf->SetFont('Arial', '', 7.5);
            $pdf->Cell(70, 4, utf8_decode($d['motivo'] ?? ''), 0, 0, 'L'); // Variable dinámica real
            
            $pdf->SetFont('Arial', 'B', 7.5);
            $pdf->Cell(55, 4, 'Mod. Transp.: ', 0, 0, 'R');
            $pdf->SetFont('Arial', '', 7.5);
            $pdf->Cell(30, 4, utf8_decode($d['modalidad_transporte'] ?? ''), 0, 1, 'R');

            $pdf->SetX(12);
            $pdf->SetFont('Arial', 'B', 7.5);
            $pdf->Cell(24, 4, 'P. B. Total(KGM): ', 0, 0, 'R');
            $pdf->SetFont('Arial', '', 7.5);
            $pdf->Cell(70, 4, number_format((float)($d['peso_total'] ?? 0), 2), 0, 0, 'L');
            
            $pdf->SetFont('Arial', 'B', 7.5);
            $pdf->Cell(55, 4, utf8_decode('N° Bultos.: '), 0, 0, 'R');
            $pdf->SetFont('Arial', '', 7.5);
            $pdf->Cell(30, 4, intval($d['bultos'] ?? 0), 0, 1, 'R');
        });

        // 3. DATOS DEL TRANSPORTE
        $y += 20;
        $this->SeccionCaja('DATOS DEL TRANSPORTE', $y, 16, function($pdf) use ($d) {
            $pdf->SetFont('Arial', 'B', 7.5);
            $pdf->Cell(24, 4, 'Transportista: ', 0, 0, 'R');
            $pdf->SetFont('Arial', '', 7.5);
            $pdf->Cell(150, 4, utf8_decode('RUC: ' . ($d['transp_ruc'] ?? '') . ' - ' . ($d['transp_nombre'] ?? '')), 0, 1, 'L');
            
            $pdf->SetX(12);
            $pdf->SetFont('Arial', 'B', 7.5);
            $pdf->Cell(24, 4, utf8_decode('Vehículo: '), 0, 0, 'R');
            $pdf->SetFont('Arial', '', 7.5);
            $pdf->Cell(150, 4, utf8_decode('Placa: ' . ($d['vehiculo_placa'] ?? '') . ' - Marca: ' . ($d['vehiculo_marca'] ?? '') . ' - NTM: ' . ($d['vehiculo_ntm'] ?? '') . ' - Configuración Vehicular: ' . ($d['vehiculo_conf'] ?? '')), 0, 1, 'L');

            $pdf->SetX(12);
            $pdf->SetFont('Arial', 'B', 7.5);
            $pdf->Cell(24, 4, 'Conductor: ', 0, 0, 'R');
            $pdf->SetFont('Arial', '', 7.5);
            $pdf->Cell(150, 4, utf8_decode('DNI: ' . ($d['cond_dni'] ?? '') . ' - Licencia: ' . ($d['cond_licencia'] ?? '') . ' - ' . ($d['cond_nombre'] ?? '')), 0, 1, 'L');
        });

        // 4. DATOS DEL PUNTO DE PARTIDA Y PUNTO DE LLEGADA
        $y += 20;
        $this->SeccionCaja('DATOS DEL PUNTO DE PARTIDA Y PUNTO DE LLEGADA', $y, 12, function($pdf) use ($d) {
            $pdf->SetFont('Arial', 'B', 7.5);
            $pdf->Cell(24, 4, 'Punto de Partida: ', 0, 0, 'R');
            $pdf->SetFont('Arial', '', 7.5);
            $pdf->Cell(150, 4, utf8_decode($d['punto_partida'] ?? ''), 0, 1, 'L');
            
            $pdf->SetX(12);
            $pdf->SetFont('Arial', 'B', 7.5);
            $pdf->Cell(24, 4, 'Punto de Llegada: ', 0, 0, 'R');
            $pdf->SetFont('Arial', '', 7.5);
            $pdf->Cell(150, 4, utf8_decode($d['punto_llegada'] ?? ''), 0, 1, 'L');
        });
    }

    function TablaDetalleCabecera() {
        $y_position = $this->GetY() + 4; // Auto-calcula para ponerse debajo de la última caja
        $this->SetY($y_position);
        $this->RoundedRect(10, $y_position, 190, 6, 1, 'D');
        $this->SetFont('Arial', 'B', 8);
        $this->Cell(25, 6, utf8_decode('Código'), 0, 0, 'C');
        $this->Cell(75, 6, utf8_decode('Descripción'), 0, 0, 'C');
        $this->Cell(30, 6, 'Det. Adic.', 0, 0, 'C');
        $this->Cell(20, 6, 'Glp. Und.', 0, 0, 'C');
        $this->Cell(20, 6, 'Cantidad', 0, 0, 'C');
        $this->Cell(20, 6, 'Peso', 0, 1, 'C');
    }

    function RowDetalle($item) {
        $this->SetFont('Arial', '', 7.5);
        $this->Cell(25, 5, $item['codigo'], 0, 0, 'C');
        $this->Cell(75, 5, utf8_decode($item['descripcion']), 0, 0, 'L');
        $this->Cell(30, 5, utf8_decode($item['observacion']), 0, 0, 'C');
        $this->Cell(20, 5, utf8_decode($item['galpon'] . ' UND'), 0, 0, 'C');
        $this->Cell(20, 5, number_format($item['cantidad'], 2), 0, 0, 'R');
        $this->Cell(20, 5, number_format($item['peso'], 2), 0, 1, 'R');
    }
}