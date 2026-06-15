<?php

/**
 * Generador de archivos Excel para Reportes
 * Exporta como HTML+XLS manteniendo compatibilidad con Excel
 */
class ExcelGenerator {
    
    /**
     * Genera Excel de Carga de Pollos BB
     */
    public function generarReporteCargaPollo($datos, $proyeccion, $estadisticas = []) {
        
        // Encabezados para forzar descarga (XLS compatible)
        header('Content-Type: application/vnd.ms-excel; charset=UTF-8');
        header(
            'Content-Disposition: attachment; filename="Programa-de-Carga_' .
            $this->sanitizeFilename($proyeccion) . '_' . date('Ymd_His') . '.xls"'
        );
        header('Cache-Control: max-age=0');
        header('Pragma: public');
        
        // BOM UTF‑8
        echo "\xEF\xBB\xBF";
        
        // Datos base
        $granjas = $datos['granjas'] ?? [];
        $rows    = $datos['datos']   ?? [];

        // Ordenar igual que en el dashboard: año, semana
        usort($rows, function($a, $b) {
            if ($a['anio'] != $b['anio']) {
                return $a['anio'] - $b['anio'];
            }
            return $a['semana'] - $b['semana'];
        });
        ?>
<html xmlns:x="urn:schemas-microsoft-com:office:excel">
<head>
    <meta http-equiv="content-type" content="application/vnd.ms-excel; charset=UTF-8">
    <xml>
        <x:ExcelWorkbook>
            <x:ExcelWorksheets>
                <x:ExcelWorksheet>
                    <x:Name>1. Prog. Carga</x:Name>
                    <x:WorksheetOptions>
                        <x:Selected/>
                        <x:FreezePanes/>
                        <x:FrozenNoSplit/>
                        <x:SplitHorizontal>6</x:SplitHorizontal>
                        <x:TopRowBottomPane>6</x:TopRowBottomPane>
                        <x:ActivePane>2</x:ActivePane>
                        <x:ProtectContents>False</x:ProtectContents>
                        <x:ProtectObjects>False</x:ProtectObjects>
                        <x:ProtectScenarios>False</x:ProtectScenarios>
                    </x:WorksheetOptions>
                </x:ExcelWorksheet>
            </x:ExcelWorksheets>
        </x:ExcelWorkbook>
    </xml>
    <style>
        table {
            font-family: Arial, sans-serif;
            font-size: 9pt;
        }
        .hdr-main {
            font-weight:bold;
            font-size:11pt;
            background-color:#1e3a8a;
            color:#ffffff;
            text-align:center;
            padding:8px;
        }
        .hdr-sub {
            font-weight:bold;
            font-size:9pt;
            background-color:#1d4ed8;
            color:#ffffff;
            text-align:center;
            vertical-align:middle;
        }
        .hdr-granja {
            font-weight:bold;
            font-size:8pt;
            background-color:#3b82f6;
            color:#ffffff;
            text-align:center;
            vertical-align:middle;
        }
        .hdr-pollos {
            font-weight:bold;
            font-size:7.5pt;
            background-color:#60a5fa;
            color:#ffffff;
            text-align:center;
            vertical-align:middle;
            padding:4px 2px;
        }
        .hdr-fecha {
            font-weight:bold;
            font-size:7.5pt;
            background-color:#fbbf24;
            color:#000000;
            text-align:center;
            vertical-align:middle;
            padding:4px 2px;
        }
        .celda-semana {
            text-align:center;
            font-weight:bold;
            font-size:10pt;
            background-color:#e0f2fe;
        }
        .celda-mes {
            text-align:left;
            padding-left:8px;
        }
        .num-right {
            text-align:right;
            padding-right:6px;
        }
        .pollos-sem {
            text-align:right;
            background-color:#fef3c7;
            font-weight:bold;
            color:#7c3aed;
            padding-right:6px;
        }
        .celda-granja {
            text-align:right;
            background-color:#eff6ff;
            padding-right:6px;
        }
        .celda-fecha {
            text-align:center;
            background-color:#fffbeb;
            font-size:8pt;
        }
        .celda-total {
            text-align:right;
            background-color:#dcfce7;
            font-weight:bold;
            color:#166534;
            font-size:10pt;
            padding-right:6px;
        }
        .fila-par {
            background-color:#f9fafb;
        }
        .totales-hdr {
            background-color:#10b981;
            color:#ffffff;
            font-weight:bold;
            font-size:10pt;
        }
        .texto-pequeno {
            font-size:8pt;
        }
    </style>
</head>
<body>

    <!-- Encabezado similar a 1. Prog. Carga -->
    <table border="0" cellpadding="2" cellspacing="0">
        <tr>
            <td colspan="<?php echo 9 + (count($granjas) * 5); ?>"
                style="font-size:11pt;font-weight:bold;">
                Granja Rinconada del Sur S.A.
            </td>
        </tr>
        <tr>
            <td colspan="<?php echo 9 + (count($granjas) * 5); ?>"
                style="font-size:10pt;">
                Producción aves : Programa de carga de pollos - Anual.
            </td>
        </tr>
        <tr>
            <td style="font-size:8pt;">Actualización</td>
            <td style="font-size:8pt;"><?php echo date('d-M-y'); ?></td>
            <td style="font-size:8pt;">Sistema GRS</td>
            <td>&nbsp;</td>
            <td colspan="<?php echo 5 + (count($granjas) * 5); ?>"
                class="hdr-main">
                <?php echo htmlspecialchars($proyeccion, ENT_QUOTES, 'UTF-8'); ?>
            </td>
        </tr>

        <?php if (!empty($estadisticas)): ?>
        <tr><td colspan="<?php echo 9 + (count($granjas) * 5); ?>">&nbsp;</td></tr>
        <tr class="texto-pequeno">
            <td style="font-weight:bold;">Total pollos:</td>
            <td><?php echo number_format($estadisticas['total_pollos'] ?? 0); ?></td>
            <td>&nbsp;</td>
            <td style="font-weight:bold;">Total semanas:</td>
            <td><?php echo $estadisticas['total_semanas'] ?? 0; ?></td>
            <td>&nbsp;</td>
            <td style="font-weight:bold;">Total granjas:</td>
            <td><?php echo $estadisticas['total_granjas'] ?? 0; ?></td>
            <td>&nbsp;</td>
            <td style="font-weight:bold;">Promedio / semana:</td>
            <td><?php echo number_format($estadisticas['promedio_semana'] ?? 0, 0); ?></td>
        </tr>
        <?php endif; ?>
    </table>

    <br/>

    <!-- Tabla principal tipo "1. Prog. Carga" -->
    <table border="1" cellpadding="3" cellspacing="0" style="border-collapse:collapse;">
        <thead>
            <!-- Fila de headers fijos + bloques de granja -->
            <tr class="hdr-sub">
                <th rowspan="3" style="width:45px;">Sem</th>
                <th rowspan="3" style="width:40px;">Año</th>
                <th rowspan="3" style="width:70px;">Mes</th>
                <th rowspan="3" style="width:45px;">N° Carg</th>
                <th rowspan="3" style="width:75px;">Pollos × Sem</th>
                <th rowspan="3" style="width:75px;">Fecha Límite</th>
                <th rowspan="3" style="width:75px;">F. AQP</th>
                <th rowspan="3" style="width:35px;">N°</th>

                <?php foreach ($granjas as $granja): ?>
                    <th colspan="5" class="hdr-granja">
                        <?php echo htmlspecialchars($granja['granja_completa'], ENT_QUOTES, 'UTF-8'); ?>
                    </th>
                <?php endforeach; ?>

                <th rowspan="3" style="background-color:#15803d; color:#fff;">Total</th>
            </tr>
            <tr>
                <?php foreach ($granjas as $granja): ?>
                    <th class="hdr-pollos" rowspan="2">Granja</th>
                    <th class="hdr-pollos" rowspan="2">Galp</th>
                    <th class="hdr-pollos" rowspan="2">Camp</th>
                    <th class="hdr-pollos" rowspan="2">Pollos BB</th>
                    <th class="hdr-fecha" rowspan="2">F. Liquidación</th>
                <?php endforeach; ?>
            </tr>
            <tr>
                <!-- Fila vacía para completar rowspan -->
            </tr>
        </thead>
        <tbody>
            <?php
            $fila = 0;
            foreach ($rows as $semana):
                $fila++;
                $esPar = ($fila % 2 === 0);
                $totalFila = 0;
            ?>
            <tr class="<?php echo $esPar ? 'fila-par' : ''; ?>">
                <!-- Columnas fijas -->
                <td class="celda-semana"><?php echo $semana['semana']; ?></td>
                <td style="text-align:center;"><?php echo $semana['anio']; ?></td>
                <td class="celda-mes"><?php echo htmlspecialchars($semana['mes'], ENT_QUOTES, 'UTF-8'); ?></td>
                <td class="num-right"><?php echo number_format($semana['n_cart']); ?></td>
                <td class="pollos-sem"><?php echo number_format($semana['pollos_semana']); ?></td>
                
                <!-- Fechas nuevas -->
                <td class="texto-pequeno" style="text-align:center;">
                    <?php echo htmlspecialchars($semana['feclima'] ?? '', ENT_QUOTES, 'UTF-8'); ?>
                </td>
                <td class="texto-pequeno" style="text-align:center;">
                    <?php echo htmlspecialchars($semana['fecaqp'] ?? '', ENT_QUOTES, 'UTF-8'); ?>
                </td>
                <td class="num-right"><?php echo $fila; ?></td>

                <?php foreach ($granjas as $granja): 
                    $key   = $granja['granja_completa'];
                    $pollos = $semana['granjas'][$key] ?? 0;
                    $fecha  = $semana['fechas'][$key]  ?? '';
                    $detalles = $semana['detalles'][$key] ?? null;
                    $totalFila += $pollos;
                ?>
                    <!-- Código granja -->
                    <td class="texto-pequeno" style="text-align:center;">
                        <?php echo htmlspecialchars($detalles['codigo'] ?? '', ENT_QUOTES, 'UTF-8'); ?>
                    </td>
                    <!-- Galpón -->
                    <td class="texto-pequeno" style="text-align:center; font-weight:600;">
                        <?php echo htmlspecialchars($detalles['galpon'] ?? '', ENT_QUOTES, 'UTF-8'); ?>
                    </td>
                    <!-- Campaña -->
                    <td class="texto-pequeno" style="text-align:center;">
                        <?php echo htmlspecialchars($detalles['campana'] ?? '', ENT_QUOTES, 'UTF-8'); ?>
                    </td>
                    <!-- Pollos -->
                    <td class="celda-granja">
                        <?php echo $pollos > 0 ? number_format($pollos) : ''; ?>
                    </td>
                    <!-- Fecha liquidación -->
                    <td class="celda-fecha">
                        <?php echo htmlspecialchars($fecha, ENT_QUOTES, 'UTF-8'); ?>
                    </td>
                <?php endforeach; ?>

                <!-- Total fila -->
                <td class="celda-total">
                    <?php echo number_format($totalFila); ?>
                </td>
            </tr>
            <?php endforeach; ?>

            <!-- Totales por granja -->
            <tr class="totales-hdr">
                <td colspan="5" class="num-right">TOTALES:</td>
                <td colspan="3">&nbsp;</td>
                <?php
                $granTotal = 0;
                foreach ($granjas as $granja):
                    $key   = $granja['granja_completa'];
                    $total = $datos['totales'][$key] ?? 0;
                    $granTotal += $total;
                ?>
                    <td colspan="3">&nbsp;</td>
                    <td class="num-right">
                        <?php echo number_format($total); ?>
                    </td>
                    <td>&nbsp;</td>
                <?php endforeach; ?>
                <td class="num-right">
                    <?php echo number_format($granTotal); ?>
                </td>
            </tr>
        </tbody>
    </table>

    <br><br>
    <table>
        <tr>
            <td class="texto-pequeno" style="color:#666;">
                Generado por Sistema GRS - <?php echo date('d/m/Y H:i:s'); ?>
            </td>
        </tr>
    </table>

</body>
</html>
<?php
        exit;
    }
    
    /**
     * Sanitiza nombre de archivo
     */
    private function sanitizeFilename($filename) {
        $filename = str_replace(
            [' ', '/', '\\', ':', '*', '?', '"', '<', '>', '|'],
            '_',
            $filename
        );
        $filename = preg_replace('/[^A-Za-z0-9_\-]/', '', $filename);
        return substr($filename, 0, 50);
    }
}
?>
