<?php
require_once __DIR__ . '/../libraries/fpdf/fpdf.php';

class ReporteTransaccionesPdf extends FPDF
{
    private $fechaRango = '';

    public function Header()
    {
        // ── BARRA AZUL PRINCIPAL REFINADA (Ancho total 277mm) ──
        $this->SetFillColor(37, 99, 235); // Azul Real de tu segunda foto
        $this->Rect(10, 10, 277, 10, 'F');

        $this->SetTextColor(255, 255, 255);
        $this->SetFont('Arial', 'B', 9);

        // Fila de la Franja Superior
        $this->SetXY(12, 10);
        $this->Cell(60, 10, utf8_decode('PLANTA INUBACION'), 0, 0, 'L');
        $this->Cell(145, 10, utf8_decode('REPORTE DE TRANSACCIONES'), 0, 0, 'C');
        $this->Cell(60, 10, date('d/m/Y H:i'), 0, 1, 'R');

        // Rango de fechas abajo de la barra
        $this->SetTextColor(100, 100, 100);
        $this->SetFont('Arial', '', 9);
        $this->SetX(10);
        $this->Cell(277, 8, utf8_decode('Rango: ' . $this->fechaRango), 0, 1, 'L');
        $this->Ln(1);

        // ── CABECERAS DE LA TABLA ESTILO WEB ──
        $this->SetFillColor(37, 99, 235);
        $this->SetTextColor(255, 255, 255);
        $this->SetDrawColor(255, 255, 255); // Bordes blancos temporales para separar las cabeceras
        $this->SetFont('Arial', 'B', 7.5);

        $cols = [
            ['FECHA', 15],
            ['NRO. DOC', 22],
            ['REF', 10],
            ['COD. PRO', 25],
            ['PROVEEDOR / CLIENTE', 45],
            ['CENCOS', 15],
            ['CÓDIGO', 15],
            ['DESCRIPCIÓN', 35],
            ['CANTIDAD', 20],
            ['C. UNIT', 20],
            ['C. KARDEX', 20],
            ['COSTO TOTAL', 20],
            ['CC DEST', 15]
        ];

        foreach ($cols as $col) {
            $this->Cell($col[1], 6, utf8_decode($col[0]), 1, 0, 'C', true);
        }
        $this->Ln();
    }

    public function exportarPDF($filtros, $data)
    {
        // Formatear rango de fechas de forma elegante
        $this->fechaRango = ($filtros['fechaInicio'] ?? '') . ' al ' . ($filtros['fechaFin'] ?? '');

        $this->AddPage('L', 'A4');
        $this->SetFont('Arial', '', 7.5);

        // Cambiar el color de dibujo a un gris muy sutil para la cuadrícula interna
        $this->SetDrawColor(229, 231, 235);
        $this->SetTextColor(55, 65, 81); // Gris oscuro elegante para el texto plano

        $sumaCantidad = 0;
        $sumaCUnit = 0;
        $sumaCKardex = 0;
        $sumaCostoTotal = 0;
        $subtotalCantidad = 0;
        $subtotalCUnit = 0;
        $subtotalCKardex = 0;
        $subtotalCosto = 0;

        $fechaControl = '';
        $agruparPor = $filtros['agruparPor'] ?? 'FECHA';

        if (is_array($data) || $data instanceof Traversable) {
            foreach ($data as $index => $item) {
                $cantidad = (float)($item['cantidad'] ?? 0);
                $cTotal = (float)($item['costo_total'] ?? 0);
                $cUnit = (float)($item['c_unit'] ?? 0);
                $cKardex = (float)($item['c_kardex'] ?? 0);

                $cUnitCrudo = ($cantidad != 0) ? ($cTotal / $cantidad) : 0;

                if ($fechaControl != '' && $fechaControl != $item['fecha_formato'] && $agruparPor == 'FECHA') {
                    $this->imprimirSubtotal($fechaControl, $subtotalCantidad, $subtotalCUnit, $subtotalCKardex, $subtotalCosto);
                    $subtotalCantidad = 0;
                    $subtotalCUnit = 0;
                    $subtotalCKardex = 0;
                    $subtotalCosto = 0;
                }

                $fechaControl = $item['fecha_formato'] ?? '';

                $sumaCantidad += $cantidad;
                $sumaCUnit += $cUnitCrudo;
                $sumaCKardex += $cKardex;
                $sumaCostoTotal += $cTotal;

                $subtotalCantidad += $cantidad;
                $subtotalCUnit += $cUnitCrudo;
                $subtotalCKardex += $cKardex;
                $subtotalCosto += $cTotal;

                // Restablecer color de cuadrícula sutil antes de cada fila
                $this->SetDrawColor(229, 231, 235);
                $this->SetTextColor(55, 65, 81);

                // Filas normales de datos con bordes grises
                $this->Cell(15, 5.5, $item['fecha_formato'] ?? '', 1, 0, 'C');
                $this->Cell(22, 5.5, $item['nro_doc'] ?? '', 1, 0, 'C');
                $this->Cell(10, 5.5, '0', 1, 0, 'C');
                $this->Cell(25, 5.5, $item['cod_pro'] ?? '', 1, 0, 'C');
                $this->Cell(45, 5.5, ' ' . substr(utf8_decode($item['proveedor_cliente'] ?? ''), 0, 28), 1, 0, 'L');
                $this->Cell(15, 5.5, $item['cencos'] ?? '', 1, 0, 'C');
                $this->Cell(15, 5.5, $item['codigo'] ?? '', 1, 0, 'C');
                $this->Cell(35, 5.5, ' ' . substr(utf8_decode($item['descripcion'] ?? ''), 0, 24), 1, 0, 'L');

                // Formato numérico idéntico a la grilla web
                $this->Cell(20, 5.5, number_format($cantidad, 2), 1, 0, 'R');
                $this->Cell(20, 6, number_format($cUnit, 3), 1, 0, 'R');
                $this->Cell(20, 5.5, number_format($cKardex, 3), 1, 0, 'R');
                $this->Cell(20, 5.5, number_format($cTotal, 3), 1, 0, 'R');
                $this->Cell(15, 5.5, $item['cc_dest'] ?? '', 1, 1, 'C');

                if ($index == count($data) - 1) {
                    $this->imprimirSubtotal($fechaControl, $subtotalCantidad, $subtotalCUnit, $subtotalCKardex, $subtotalCosto);
                }
            }

            // ── ✅ EL TOTAL GENERAL DE CIERRE ──
            $this->SetFillColor(30, 64, 175); // Azul oscuro principal para el gran cierre
            $this->SetDrawColor(30, 64, 175);
            $this->SetTextColor(255, 255, 255); // Texto blanco
            $this->SetFont('Arial', 'B', 8);

            $this->Cell(182, 6.5, utf8_decode("TOTAL GENERAL :"), 1, 0, 'R', true);
            $this->Cell(20, 6.5, number_format($sumaCantidad, 2), 1, 0, 'R', true);
            $this->Cell(20, 6.5, number_format($sumaCUnit, 3), 1, 0, 'R', true);
            $this->Cell(20, 6.5, number_format($sumaCKardex, 3), 1, 0, 'R', true);
            $this->Cell(20, 6.5, number_format($sumaCostoTotal, 3), 1, 0, 'R', true);
            $this->Cell(15, 6.5, '', 1, 1, 'R', true);
        }

        if (ob_get_length()) ob_end_clean();
        $this->Output('I', 'Reporte_Transacciones.pdf');
    }

    private function imprimirSubtotal($fecha, $cant, $unit, $kardex, $costo)
    {
        $this->SetFillColor(248, 250, 252); // Fondo gris azulado ultra sutil
        $this->SetDrawColor(229, 231, 235); // Mantener la cuadrícula sutil
        $this->SetTextColor(15, 23, 42);    // Texto pizarra
        $this->SetFont('Arial', 'B', 7.5);

        $fechaParaMostrar = $fecha;
        if (strpos($fecha, '/') !== false) {
            $partes = explode('/', $fecha);
            // Tomamos el año actual del sistema o el año del filtro
            $anio = date('Y');
            $fechaParaMostrar = $partes[1] . '/' . $partes[0] . '/' . $anio;
        }

        $this->Cell(182, 6, utf8_decode("TOTALES POR FECHA :  ") . $fechaParaMostrar, 1, 0, 'R', true);

        // Cantidad (Azul)
        $this->SetTextColor(29, 78, 216);
        $this->Cell(20, 6, number_format($cant, 2), 1, 0, 'R', true);

        // Costos (Negro Pizarra)
        $this->SetTextColor(15, 23, 42);
        $this->Cell(20, 6, number_format($unit, 3), 1, 0, 'R', true);
        $this->Cell(20, 6, number_format($kardex, 3), 1, 0, 'R', true);
        $this->Cell(20, 6, number_format($costo, 3), 1, 0, 'R', true);

        // CC DEST (Vacío)
        $this->Cell(15, 6, '', 1, 1, 'R', true);

        // Reestablecer font normal para las filas de datos siguientes
        $this->SetFont('Arial', '', 7.5);
    }
}
