<?php
require_once __DIR__ . '/../libraries/fpdf/fpdf.php';
require_once __DIR__ . '/../libraries/phpqrcode/qrlib.php';

class PDFGuia extends FPDF
{

    // Función para dibujar rectángulos con bordes redondeados
    function RoundedRect($x, $y, $w, $h, $r, $style = '')
    {
        $k = $this->k;
        $hp = $this->h;
        if ($style == 'F') $op = 'f';
        elseif ($style == 'FD' || $style == 'DF') $op = 'B';
        else $op = 'S';
        $MyArc = 4 / 3 * (sqrt(2) - 1);
        $this->_out(sprintf('%.2F %.2F m', ($x + $r) * $k, ($hp - $y) * $k));
        $xc = $x + $w - $r;
        $yc = $y + $r;
        $this->_out(sprintf('%.2F %.2F l', $xc * $k, ($hp - $y) * $k));
        $this->_Arc($xc + $r * $MyArc, $yc - $r, $xc + $r, $yc - $r * $MyArc, $xc + $r, $yc);
        $xc = $x + $w - $r;
        $yc = $y + $h - $r;
        $this->_out(sprintf('%.2F %.2F l', ($x + $w) * $k, ($hp - $yc) * $k));
        $this->_Arc($xc + $r, $yc + $r * $MyArc, $xc + $r * $MyArc, $yc + $r, $xc, $yc + $r);
        $xc = $x + $r;
        $yc = $y + $h - $r;
        $this->_out(sprintf('%.2F %.2F l', $xc * $k, ($hp - ($y + $h)) * $k));
        $this->_Arc($xc - $r * $MyArc, $yc + $r, $xc - $r, $yc + $r * $MyArc, $xc - $r, $yc);
        $xc = $x + $r;
        $yc = $y + $r;
        $this->_out(sprintf('%.2F %.2F l', ($x) * $k, ($hp - $yc) * $k));
        $this->_Arc($xc - $r, $yc - $r * $MyArc, $xc - $r * $MyArc, $yc - $r, $xc, $yc - $r);
        $this->_out($op);
    }

    function _Arc($x1, $y1, $x2, $y2, $x3, $y3)
    {
        $h = $this->h;
        $this->_out(sprintf(
            '%.2F %.2F %.2F %.2F %.2F %.2F c ',
            $x1 * $this->k,
            ($h - $y1) * $this->k,
            $x2 * $this->k,
            ($h - $y2) * $this->k,
            $x3 * $this->k,
            ($h - $y3) * $this->k
        ));
    }

    function HeaderCustom($datos)
    {
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

        // BORDES GRUESOS PARA EL RUC (Ancho reducido a 55 y desplazado a X=145)
        $this->SetLineWidth(0.4);
        $this->RoundedRect(145, 10, 55, 25, 3, 'D');
        $this->Line(145, 17, 200, 17);
        $this->Line(145, 26, 200, 26);
        $this->SetLineWidth(0.2); // Restaurar el grosor de línea normal

        $this->SetFont('Arial', 'B', 10);
        $this->SetXY(145, 11.5);
        $this->Cell(55, 5, 'RUC: 20419158462', 0, 1, 'C');

        $this->SetXY(145, 18.5);
        $this->SetFont('Arial', 'B', 8.5);
        $this->Cell(55, 4, utf8_decode('GUÍA DE REMISIÓN'), 0, 1, 'C');
        $this->SetX(145);
        $this->Cell(55, 4, utf8_decode('REMITENTE ELECTRÓNICA'), 0, 1, 'C');

        $this->SetXY(145, 27.5);
        $this->SetFont('Arial', 'B', 11);
        $this->Cell(55, 5, $datos['serie'] . '-' . $datos['numero'], 0, 1, 'C');
        $this->Ln(10);
    }

    function SeccionCaja($titulo, $y_start, $altura, $contenido_callback)
    {
        $this->SetDrawColor(149, 149, 149); // Color gris #959595

        // 1. Activar grosor de línea bold para los marcos
        $this->SetLineWidth(0.4);

        // 2. CAJA GRANDE EXTERIOR: Radio 3.5 (Bien redondeada)
        $this->RoundedRect(10, $y_start, 190, $altura, 3.5, 'D');

        // 3. Preparar el título
        $this->SetFont('Arial', 'B', 7.5);

        // Calcular el ancho en base al título referencial más largo
        $titulo_referencia = 'DATOS DEL PUNTO DE PARTIDA Y PUNTO DE LLEGADA';
        $ancho_fijo = $this->GetStringWidth($titulo_referencia) + 6;

        $this->SetFillColor(255, 255, 255);

        // 4. CAJITA DEL TÍTULO: Radio 0.8 (Casi cuadrada, puntas apenas suaves)
        $this->RoundedRect(14, $y_start - 2.5, $ancho_fijo, 5, 0.8, 'DF');

        // 5. Restaurar valores normales para el contenido interno
        $this->SetLineWidth(0.2);
        $this->SetDrawColor(0, 0, 0);

        // Imprimir el texto alineado a la izquierda
        $this->SetXY(16, $y_start - 2.5);
        $this->Cell($ancho_fijo - 2, 5, utf8_decode($titulo), 0, 0, 'L');

        // Bajar cursor para ejecutar el callback
        $this->SetXY(12, $y_start + 5);
        $contenido_callback($this);
    }

    function GenerarCabecerasGenerales($d)
    {
        $y = 40;

        // 1. DESTINATARIO
        $this->SeccionCaja('DESTINATARIO', $y, 14, function ($pdf) use ($d) {
            $pdf->SetFont('Arial', 'B', 7.5);
            $pdf->Cell(28, 4, 'RUC: ', 0, 0, 'R');
            $pdf->SetFont('Arial', '', 7.5);
            $pdf->Cell(150, 4, $d['cliente_ruc'] ?? '', 0, 1, 'L');

            $pdf->SetX(12);
            $pdf->SetFont('Arial', 'B', 7.5);
            $pdf->Cell(28, 4, utf8_decode('Denominación: '), 0, 0, 'R');
            $pdf->SetFont('Arial', '', 7.5);
            $pdf->Cell(150, 4, utf8_decode($d['cliente_razon_social'] ?? ''), 0, 1, 'L');
        });

        // 2. DATOS DEL TRASLADO (Ajuste extremo a la derecha)
        $y += 17.5;
        $this->SeccionCaja('DATOS DEL TRASLADO', $y, 19, function ($pdf) use ($d) {
            $pdf->SetFont('Arial', 'B', 7.5);
            $pdf->Cell(32, 4, utf8_decode('Fecha Emisión: '), 0, 0, 'R');
            $pdf->SetFont('Arial', '', 7.5);
            $pdf->Cell(85, 4, $d['fecha_emision'] ?? '', 0, 0, 'L'); // Ensanchado

            $pdf->SetFont('Arial', 'B', 7.5);
            $pdf->Cell(40, 4, 'Fecha Traslado: ', 0, 0, 'R'); // Empuja a la derecha
            $pdf->SetFont('Arial', '', 7.5);
            $pdf->Cell(30, 4, $d['fecha_traslado'] ?? '', 0, 1, 'R');

            $pdf->SetX(12);
            $pdf->SetFont('Arial', 'B', 7.5);
            $pdf->Cell(32, 4, 'Motivo Traslado: ', 0, 0, 'R');
            $pdf->SetFont('Arial', '', 7.5);
            $pdf->Cell(85, 4, utf8_decode($d['motivo'] ?? ''), 0, 0, 'L');

            $pdf->SetFont('Arial', 'B', 7.5);
            $pdf->Cell(40, 4, 'Mod. Transp.: ', 0, 0, 'R');
            $pdf->SetFont('Arial', '', 7.5);
            $pdf->Cell(30, 4, utf8_decode($d['modalidad_transporte'] ?? ''), 0, 1, 'R');

            $pdf->SetX(12);
            $pdf->SetFont('Arial', 'B', 7.5);
            $pdf->Cell(32, 4, 'P. B. Total(KGM): ', 0, 0, 'R');
            $pdf->SetFont('Arial', '', 7.5);
            $pdf->Cell(85, 4, number_format((float)($d['peso_total'] ?? 0), 2), 0, 0, 'L');

            $pdf->SetFont('Arial', 'B', 7.5);
            $pdf->Cell(40, 4, utf8_decode('N° Bultos.: '), 0, 0, 'R');
            $pdf->SetFont('Arial', '', 7.5);
            $pdf->Cell(30, 4, intval($d['bultos'] ?? 0), 0, 1, 'R');
        });

        // 3. DATOS DEL TRANSPORTE
        $y += 22.5;
        $this->SeccionCaja('DATOS DEL TRANSPORTE', $y, 19, function ($pdf) use ($d) {
            $pdf->SetFont('Arial', 'B', 7.5);
            $pdf->Cell(28, 4, 'Transportista: ', 0, 0, 'R');
            $pdf->SetFont('Arial', '', 7.5);
            $pdf->Cell(150, 4, utf8_decode('RUC: ' . ($d['transp_ruc'] ?? '') . ' - ' . ($d['transp_nombre'] ?? '')), 0, 1, 'L');

            $pdf->SetX(12);
            $pdf->SetFont('Arial', 'B', 7.5);
            $pdf->Cell(28, 4, utf8_decode('Vehículo: '), 0, 0, 'R');
            $pdf->SetFont('Arial', '', 7.5);
            $pdf->Cell(150, 4, utf8_decode('Placa: ' . ($d['vehiculo_placa'] ?? '') . ' - Marca: ' . ($d['vehiculo_marca'] ?? '') . ' - NTM: ' . ($d['vehiculo_ntm'] ?? '') . ' - Configuración Vehicular: ' . ($d['vehiculo_conf'] ?? '')), 0, 1, 'L');

            $pdf->SetX(12);
            $pdf->SetFont('Arial', 'B', 7.5);
            $pdf->Cell(28, 4, 'Conductor: ', 0, 0, 'R');
            $pdf->SetFont('Arial', '', 7.5);
            $pdf->Cell(150, 4, utf8_decode('DNI: ' . ($d['cond_dni'] ?? '') . ' - Licencia: ' . ($d['cond_licencia'] ?? '') . ' - ' . ($d['cond_nombre'] ?? '')), 0, 1, 'L');
        });

        // 4. DATOS DEL PUNTO DE PARTIDA Y PUNTO DE LLEGADA
        $y += 22.5;
        $this->SeccionCaja('DATOS DEL PUNTO DE PARTIDA Y PUNTO DE LLEGADA', $y, 15, function ($pdf) use ($d) {
            $pdf->SetFont('Arial', 'B', 7.5);
            $pdf->Cell(32, 4, 'Punto de Partida: ', 0, 0, 'R');
            $pdf->SetFont('Arial', '', 7.5);
            $pdf->Cell(145, 4, utf8_decode($d['punto_partida'] ?? ''), 0, 1, 'L');

            $pdf->SetX(12);
            $pdf->SetFont('Arial', 'B', 7.5);
            $pdf->Cell(32, 4, 'Punto de Llegada: ', 0, 0, 'R');
            $pdf->SetFont('Arial', '', 7.5);
            $pdf->Cell(145, 4, utf8_decode($d['punto_llegada'] ?? ''), 0, 1, 'L');
        });
    }

    function TablaDetalleCabecera()
    {
        $y_position = $this->GetY() + 4;
        $this->SetY($y_position);

        // CONFIGURAR COLORES Y GROSOR
        $this->SetFillColor(220, 220, 220); // Gris claro para el fondo de la cabecera
        $this->SetDrawColor(149, 149, 149); // Borde gris #959595
        $this->SetLineWidth(0.4);           // Borde grueso (bold)

        // 1. CAJITA DE LA CABECERA (Relleno gris y contorno)
        // El parámetro 'DF' indica Draw (dibujar borde) y Fill (rellenar fondo)
        $this->RoundedRect(10, $y_position, 190, 6, 1.5, 'DF');

        // 2. CONTENEDOR GRANDE PARA LOS PRODUCTOS
        // Bajamos 7mm para dejar 1mm de separación visual con la cabecera.
        // Le damos un alto fijo de 115mm para que llene la hoja y un radio de 3.5 para las curvas.
        $this->RoundedRect(10, $y_position + 7, 190, 115, 3.5, 'D');

        // RESTAURAR GROSOR Y COLOR DE LÍNEA PARA EL TEXTO
        $this->SetLineWidth(0.2);
        $this->SetDrawColor(0, 0, 0);

        // IMPRIMIR TEXTOS DE CABECERA
        $this->SetFont('Arial', 'B', 8);
        $this->Cell(25, 6, utf8_decode('Código'), 0, 0, 'C');
        $this->Cell(75, 6, utf8_decode('Descripción'), 0, 0, 'C');
        $this->Cell(30, 6, 'Det. Adic.', 0, 0, 'C');
        $this->Cell(20, 6, 'Glp. Und.', 0, 0, 'C');
        $this->Cell(20, 6, 'Cantidad', 0, 0, 'C');
        $this->Cell(20, 6, 'Peso', 0, 1, 'C');

        // SALTO DE LÍNEA ADICIONAL
        // Esto empuja el cursor hacia abajo para que el primer ítem no quede pegado al borde superior de la caja grande
        $this->Ln(3);
    }

    function RowDetalle($item)
    {
        $this->SetFont('Arial', '', 7.5);
        $this->Cell(25, 5, $item['codigo'], 0, 0, 'C');
        $this->Cell(75, 5, utf8_decode($item['descripcion']), 0, 0, 'L');
        $this->Cell(30, 5, utf8_decode($item['observacion']), 0, 0, 'C');
        $this->Cell(20, 5, utf8_decode($item['galpon'] . ' UND'), 0, 0, 'C');
        $this->Cell(20, 5, number_format($item['cantidad'], 2), 0, 0, 'R');
        $this->Cell(20, 5, number_format($item['peso'], 2), 0, 1, 'R');
    }


    function GenerarFooter($d)
    {
        // 1. APAGAR EL SALTO DE PÁGINA AUTOMÁTICO
        $this->SetAutoPageBreak(false);

        // 2. Subimos 10 milímetros extra (de -46 a -56) para pegarlo al ras de la tabla
        $this->SetY(-56);

        // 3. Línea de Observaciones
        $this->SetFont('Arial', 'B', 8);
        $this->Cell(24, 4, 'Observaciones: ', 0, 0, 'L');
        $this->SetFont('Arial', '', 8);
        $observacion = !empty($d['observacion']) ? $d['observacion'] : '-';
        $this->Cell(166, 4, utf8_decode($observacion), 0, 1, 'L');

        // Damos un salto hacia abajo para separar las cajitas del texto superior
        $this->Ln(3);
        $y_actual = $this->GetY();

        // 4A. CONSTRUCCIÓN DEL QR 1 (ORIGINAL CON PIPES - IZQUIERDA)
        $ruc_empresa = '20419158462';
        $tipo_documento = '09';
        $serie = $d['serie'] ?? '';
        $numero = $d['numero'] ?? '';
        $igv = '0';
        $total = '0';
        $fecha_emision = $d['fecha_emision'] ?? '';
        $tipo_doc_tercero = '6';
        $ruc_transportista = $d['transp_ruc'] ?? '';

        $cadena_qr_original = "$ruc_empresa|$tipo_documento|$serie|$numero|$igv|$total|$fecha_emision|$tipo_doc_tercero|$ruc_transportista";
        $ruta_qr_temp_1 = __DIR__ . '/../libraries/img/temp_qr1_' . $serie . '_' . $numero . '.png';
        QRcode::png($cadena_qr_original, $ruta_qr_temp_1, QR_ECLEVEL_L, 3, 1);

        // 4B. CONSTRUCCIÓN DEL QR 2 (URL NUBEFACT/SUNAT - DERECHA)
        if (!empty($d['qr_nubefact']) && $d['qr_nubefact'] !== 'Pendiente' && $d['qr_nubefact'] !== '(NULL)') {
            $cadena_qr_nubefact = $d['qr_nubefact'];
        } else {
            $cadena_qr_nubefact = 'Documento en proceso de validacion SUNAT';
        }
        $ruta_qr_temp_2 = __DIR__ . '/../libraries/img/temp_qr2_' . $serie . '_' . $numero . '.png';
        $mostrar_qr_derecho = false;

        // Solo generamos el QR derecho si existe información y es una URL válida (empieza con http)
        if (!empty($d['qr_nubefact']) && strpos(trim($d['qr_nubefact']), 'http') === 0) {
            QRcode::png(trim($d['qr_nubefact']), $ruta_qr_temp_2, QR_ECLEVEL_L, 3, 1);
            $mostrar_qr_derecho = true;
        }

        // 5. DIBUJAR LOS CUADROS DEL PIE DE PÁGINA
        $this->SetDrawColor(149, 149, 149);
        $this->SetLineWidth(0.4);

        // Cuadro izquierdo (Para alojar el QR pequeño)
        $this->RoundedRect(10, $y_actual, 30, 24, 2, 'D');

        // Título "GRS" cortando la línea del cuadro izquierdo
        $this->SetFillColor(255, 255, 255);
        $this->SetXY(18, $y_actual - 2);
        $this->SetFont('Arial', 'B', 7.5);
        $this->Cell(14, 4, 'GRS', 0, 0, 'C', true);

        // Cuadro central (Textos de Nubefact)
        $this->RoundedRect(44, $y_actual, 126, 24, 2, 'D');
        $this->SetLineWidth(0.2); // Restaurar grosor

        // Textos del cuadro central
        $this->SetXY(44, $y_actual + 5);
        $this->SetFont('Arial', '', 7.5);
        $this->Cell(126, 4, utf8_decode('Representación impresa de la GUÍA DE REMISIÓN REMITENTE ELECTRÓNICA, visita'), 0, 1, 'C');

        $this->SetX(44);
        $this->SetFont('Arial', 'B', 8);
        $this->Cell(126, 4, 'www.nubefact.com/20419158462', 0, 1, 'C');

        $this->SetX(44);
        $this->SetFont('Arial', '', 7.5);
        $this->Cell(126, 4, utf8_decode('Autorizado mediante Resolución de Intendencia No.034-005-0005315'), 0, 1, 'C');

        // 6. INSERTAR LAS IMÁGENES QR Y ELIMINAR LOS TEMPORALES
        if (file_exists($ruta_qr_temp_1)) {
            // QR Izquierdo (X=16)
            $this->Image($ruta_qr_temp_1, 16, $y_actual + 3, 18, 18, 'PNG');
            unlink($ruta_qr_temp_1);
        }

        // Solo insertamos la imagen si pasó la validación de URL
        if ($mostrar_qr_derecho && file_exists($ruta_qr_temp_2)) {
            // QR Derecho (X=176)
            $this->Image($ruta_qr_temp_2, 176, $y_actual + 3, 18, 18, 'PNG');
            unlink($ruta_qr_temp_2);
        }

        // 7. RESTAURAR SALTO DE PÁGINA AUTOMÁTICO
        $this->SetAutoPageBreak(true, 20);
    }
}
