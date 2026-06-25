<?php

/**
 * MovimientoAlmacenController
 */

require_once __DIR__ . '/../services/MovimientoAlmacenService.php';

class MovimientoAlmacenController
{
    private $service;

    public function __construct(MovimientoAlmacenService $service)
    {
        $this->service = $service;
    }

    private function json($data, int $code = 200): void
    {
        http_response_code($code);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => $code < 400, 'data' => $data]);
        exit;
    }

    private function error(string $msg, int $code = 400): void
    {
        http_response_code($code);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => false, 'error' => $msg]);
        exit;
    }

    private function resolveHttpCode($code, int $fallback): int
    {
        if (is_int($code) && $code >= 100 && $code <= 599) {
            return $code;
        }

        if (is_numeric($code)) {
            $num = (int)$code;
            if ($num >= 100 && $num <= 599) {
                return $num;
            }
        }

        return $fallback;
    }

    private function body(): array
    {
        return json_decode(file_get_contents('php://input'), true) ?? [];
    }

    // ─── MAESTROS ─────────────────────────────────────────────────────────────

    public function getAlmacenes(): void
    {
        $this->json($this->service->getAlmacenes());
    }

    public function getTransacciones(): void
    {
        $this->json($this->service->getTransacciones());
    }

    public function getTiposDocumento(): void
    {
        $this->json($this->service->getTiposDocumento());
    }

    public function getCentrosCosto(): void
    {
        $this->json($this->service->getCentrosCosto());
    }

    public function getClientesProveedores(): void
    {
        $q = trim((string)($_GET['q'] ?? ''));
        $this->json($this->service->getClientesProveedores($q));
    }

    public function getTipoCambio(): void
    {
        $fecha = $_GET['fecha'] ?? '';
        if ($fecha === '') {
            $this->error('Fecha requerida para el tipo de cambio.', 422);
        }
        $this->json($this->service->getTipoCambioPorFecha($fecha));
    }

    public function buscarProductos(): void
    {
        $termino = $_GET['q'] ?? '';
        $alma    = trim((string)($_GET['alma'] ?? ''));
        // Si no hay termino de búsqueda, devolver todos los productos (limitado a 200)
        if (empty($termino)) {
            $this->json($this->service->getProductos(200, 0, $alma));
        } else {
            $this->json($this->service->buscarProductos($termino, 200, 0, $alma));
        }
    }

    public function getLotes(): void
    {
        $alma = trim((string)($_GET['alma'] ?? ''));
        $codigo = trim((string)($_GET['codigo'] ?? ''));
        $fecha = trim((string)($_GET['fecha'] ?? ''));
        $this->json($this->service->getLotes($alma, $codigo, $fecha));
    }

    public function getAbc(array $params): void
    {
        $tipo = $params['tipo'] ?? '';
        $this->json($this->service->getAbc($tipo, $_GET));
    }

    // ─── VALIDACIONES ────────────────────────────────────────────────────────

    public function verificarFecha(): void
    {
        $fecha  = $_GET['fecha'] ?? '';
        $codigo = $this->service->verificarFecha($fecha);
        $this->json([
            'codigo'  => $codigo,
            'valida'  => $codigo === null,
            'mensaje' => $this->service->getMensajeVerificacion($codigo)
        ]);
    }

    public function verificarMes(): void
    {
        $fecha = $_GET['fecha'] ?? '';
        $cod   = $this->service->verificarMes($fecha);
        $this->json(['cerrado' => $cod === 1, 'codigo' => $cod]);
    }

    public function getNuevoReg(): void
    {
        $this->json($this->service->getNuevoReg());
    }

    // ─── MOVIMIENTOS ─────────────────────────────────────────────────────────

    public function listarMovimientos(): void
    {
        $filtros = [
            'talm'    => $_GET['talm']    ?? '',
            'fecini'  => $_GET['fecini']  ?? '',
            'fecfin'  => $_GET['fecfin']  ?? '',
            'tcodtra' => $_GET['tcodtra'] ?? '',
        ];
        $this->json($this->service->listarMovimientos($filtros));
    }

    public function listarMovimientosDashboard(): void
    {
        $filtros = [
            'talm'     => $_GET['talm'] ?? '',
            'tcodtra'  => $_GET['tcodtra'] ?? '',
            'fecini'   => $_GET['fecini'] ?? '',
            'fecfin'   => $_GET['fecfin'] ?? '',
            'q'        => $_GET['q'] ?? '',
            'page'     => (int)($_GET['page'] ?? 1),
            'per_page' => (int)($_GET['per_page'] ?? 25),
        ];

        $this->json($this->service->listarMovimientosDashboard($filtros));
    }

    public function getMovimiento(array $params): void
    {
        try {
            $treg = trim((string)($params['treg'] ?? ''));
            if ($treg === '') {
                $this->error('Registro no válido.', 422);
            }

            // Capturar opcionales para transferencias contextuadas
            $tcodtra = $_GET['tcodtra'] ?? null;
            $talm    = $_GET['talm'] ?? null;

            $this->json($this->service->getMovimiento($treg, $tcodtra, $talm));
        } catch (Exception $e) {
            $this->error($e->getMessage(), $this->resolveHttpCode($e->getCode(), 404));
        }
    }

    public function crearMovimiento(): void
    {
        try {
            $data = $this->body();
            if (empty($data['tfectra']) || empty($data['tcodtra']) || empty($data['talm'])) {
                $this->error('Fecha, transacción y almacén son obligatorios.');
            }
            $resultado = $this->service->crearMovimiento($data);
            $this->json($resultado, 201);
        } catch (Exception $e) {
            $this->error($e->getMessage(), $this->resolveHttpCode($e->getCode(), 422));
        }
    }

    public function actualizarMovimiento(array $params): void
    {
        try {
            $treg = trim((string)($params['treg'] ?? ''));
            $data = $this->body();

            if ($treg === '') {
                $this->error('Registro no válido.', 422);
            }
            if (empty($data['tfectra']) || empty($data['tcodtra']) || empty($data['talm'])) {
                $this->error('Fecha, transacción y almacén son obligatorios.', 422);
            }

            $resultado = $this->service->actualizarMovimiento($treg, $data);
            $this->json($resultado, 200);
        } catch (Exception $e) {
            $this->error($e->getMessage(), $this->resolveHttpCode($e->getCode(), 422));
        }
    }

    public function eliminarMovimiento(array $params): void
    {
        try {
            $treg = (int)($params['treg'] ?? 0);
            $this->service->eliminarMovimiento($treg);
            $this->json(['mensaje' => "Movimiento #{$treg} eliminado."]);
        } catch (Exception $e) {
            $this->error($e->getMessage(), $this->resolveHttpCode($e->getCode(), 400));
        }
    }

    public function getKardex(array $params): void
    {
        $codigo = $params['codigo'] ?? '';
        $lote   = $params['lote']   ?? '00000000';
        $alma   = $params['alma']   ?? '';
        $fecha  = $_GET['fecha'] ?? '';
        $codtra = $_GET['codtra'] ?? '';
        $this->json($this->service->getKardex($codigo, $lote, $alma, $fecha, $codtra));
    }

    public function getStockAlmacen(array $params): void
    {
        $alma = $params['alma'] ?? '';
        $this->json($this->service->getStockAlmacen($alma));
    }

    public function getReporteKardex(): void
    {
        $filtros = [
            'alma' => $_GET['alma'] ?? '',
            'fecha_desde' => $_GET['fecha_desde'] ?? '',
            'fecha_hasta' => $_GET['fecha_hasta'] ?? '',
            'codigo_desde' => $_GET['codigo_desde'] ?? '',
            'codigo_hasta' => $_GET['codigo_hasta'] ?? '',
            'modo_items' => $_GET['modo_items'] ?? 'todo',
            'stock_con' => $_GET['stock_con'] ?? 'valor',
            'tipo' => $_GET['tipo'] ?? 'detalle',
        ];

        $rows = $this->service->getReporteKardex($filtros);
        $this->json([
            'rows' => $rows,
            'meta' => [
                'almacen' => $filtros['alma'],
                'fecha_desde' => $filtros['fecha_desde'],
                'fecha_hasta' => $filtros['fecha_hasta'],
                'tipo' => $filtros['tipo'],
                'stock_con' => $filtros['stock_con'],
            ]
        ]);
    }

    public function getComprobantePdf(array $params): void
    {
        try {
            $tregRaw = trim((string)($params['treg'] ?? ''));
            if ($tregRaw === '') {
                $this->error('Registro no valido.', 422);
            }

            $parts = explode('?', $tregRaw);
            $treg = trim($parts[0]); // Aquí nos queda estrictamente "31424185"

            $formato = strtolower(trim((string)($_GET['formato'] ?? 'a4')));
            $esTicket80 = in_array($formato, ['80mm', '80', 'ticket80'], true);
            $forzarDescarga = ((int)($_GET['download'] ?? 1)) === 1;

            // CAPTURAR EL CONTEXTO EN EL SERVIDOR
            $tcodtra = $_GET['tcodtra'] ?? null;
            $talm    = $_GET['talm'] ?? null;

            // Pasamos las variables limpias al servicio para que filtre el detalle y adapte la cabecera
            $movimiento = $this->service->getMovimiento($treg, $tcodtra, $talm);
            $cabecera = $movimiento['cabecera'] ?? [];
            $detalle = is_array($movimiento['detalle'] ?? null) ? $movimiento['detalle'] : [];

            if (!class_exists('FPDF')) {
                require_once __DIR__ . '/../libraries/fpdf/fpdf.php';
            }

            $pdfClass = 'FPDF';
            $pdf = $esTicket80
                ? new $pdfClass('P', 'mm', [80, 297])
                : new $pdfClass('P', 'mm', 'A4');

            if ($esTicket80) {
                $pdf->SetMargins(3, 4, 3);
                $pdf->SetAutoPageBreak(true, 6);
            } else {
                $pdf->SetMargins(12, 10, 12);
                $pdf->SetAutoPageBreak(true, 12);
            }

            $pdf->AddPage();

            $toText = static function ($value): string {
                return mb_convert_encoding((string)($value ?? ''), 'ISO-8859-1', 'UTF-8');
            };

            $toNum = static function ($value, int $decimals = 2): string {
                return number_format((float)($value ?? 0), $decimals, '.', ',');
            };

            // CORRECCIÓN: Leemos de la cabecera ya procesada y adaptada por el servicio
            $fecha = (string)($cabecera['tfectra'] ?? '');
            $procli = (string)($cabecera['tprocli'] ?? '');
            $almacen = (string)($cabecera['nom_almacen'] ?? $cabecera['talm'] ?? ''); // Mostrar nombre descriptivo adaptado
            $codtra = (string)($cabecera['tcodtra'] ?? '');
            $glosa = (string)($cabecera['tglosa'] ?? '');
            $doc = trim(implode(' - ', array_filter([
                (string)($cabecera['tdoc'] ?? ''),
                (string)($cabecera['tserie'] ?? ''),
                (string)($cabecera['tnumfac'] ?? ''),
            ], static function ($v) {
                return $v !== '';
            })));

            // Resolver nombre del cliente/proveedor desde ccte
            $clienteNombre = '';
            if ($procli !== '') {
                $clienteInfo = $this->service->getClientePorCodigo($procli);
                $clienteNombre = $clienteInfo ? (string)($clienteInfo['nombre'] ?? '') : '';
            }
            if ($clienteNombre === '') {
                $clienteNombre = $procli;
            }

            // CORRECCIÓN DESTINO: Si es Entrada ('E'), la columna Destino del PDF debe mostrar un guion '-'
            $prefijoTrans = strtoupper(substr(trim($codtra), 0, 1));
            $esEntrada = $prefijoTrans === 'E';

            $destino = '-';
            if (!$esEntrada && !empty($detalle)) {
                $destinoRaw = trim((string)($detalle[0]['talr'] ?? ''));
                if ($destinoRaw !== '') {
                    $destino = $destinoRaw;
                }
            }

            $totalCantidad = 0.0;
            $totalImporte = 0.0;
            foreach ($detalle as $item) {
                $totalCantidad += (float)($item['tcantid'] ?? 0);
                $totalImporte += (float)($item['timport'] ?? 0);
            }

            $tipoMovimiento = $esEntrada ? 'MOVIMIENTO ENTRADA' : ($prefijoTrans === 'S' ? 'MOVIMIENTO SALIDA' : 'MOVIMIENTO ALMACEN');
            $codigoMovimiento = $esEntrada ? 'MT02' : ($prefijoTrans === 'S' ? 'MT01' : 'MT00');

            $toCantidad = static function ($value): string {
                $num = (float)($value ?? 0);
                if (abs($num - round($num)) < 0.000001) {
                    return number_format($num, 0, '.', ',');
                }
                return number_format($num, 2, '.', ',');
            };

            $buildQrTemp = static function (string $payload): ?string {
                $tmpBase = tempnam(sys_get_temp_dir(), 'mov_qr_');
                if ($tmpBase === false) {
                    return null;
                }
                $tmpPath = $tmpBase . '.png';
                @unlink($tmpBase);

                $qrUrl = 'https://api.qrserver.com/v1/create-qr-code/?size=220x220&data=' . rawurlencode($payload);
                $imgData = @file_get_contents($qrUrl);
                if ($imgData !== false) {
                    if (@file_put_contents($tmpPath, $imgData) !== false) {
                        return $tmpPath;
                    }
                }

                if (function_exists('imagecreatetruecolor')) {
                    $im = imagecreatetruecolor(220, 220);
                    $white = imagecolorallocate($im, 255, 255, 255);
                    $black = imagecolorallocate($im, 0, 0, 0);
                    imagefill($im, 0, 0, $white);
                    imagerectangle($im, 6, 6, 214, 214, $black);
                    imagestring($im, 5, 86, 102, 'QR', $black);
                    imagepng($im, $tmpPath);
                    imagedestroy($im);
                    return $tmpPath;
                }

                return null;
            };

            if ($esTicket80) {
                $usableW = 74;
                $leftX = 3;
                $cliente = $clienteNombre !== '' ? $clienteNombre : '-';
                $rucCli = preg_match('/^\d{8,11}$/', trim($procli)) ? trim($procli) : '-';

                $drawTicketHeader = function (bool $showDataBlock = true) use (
                    $pdf,
                    $toText,
                    $leftX,
                    $usableW,
                    $tipoMovimiento,
                    $codigoMovimiento,
                    $treg,
                    $rucCli,
                    $cliente,
                    $fecha,
                    $almacen,
                    $destino,
                    $codtra,
                    $doc
                ) {
                    $logoPath = __DIR__ . '/../../frontend/assets/images/Favicon.png';
                    if (file_exists($logoPath)) {
                        $pdf->Image($logoPath, $leftX + 1, $pdf->GetY(), 14);
                    }

                    $pdf->SetX($leftX + 16);
                    $pdf->SetFont('Arial', 'B', 8.6);
                    $pdf->Cell($usableW - 16, 4.4, $toText('GRANJA RINCONADA DEL SUR S.A.'), 0, 1, 'C');
                    $pdf->SetX($leftX + 16);
                    $pdf->SetFont('Arial', '', 7);
                    $pdf->Cell($usableW - 16, 3.7, $toText('LA MAR S/N LA JOYA-AQP.'), 0, 1, 'C');
                    $pdf->SetX($leftX + 16);
                    $pdf->Cell($usableW - 16, 3.7, $toText('TELEFAX 492124 - TELF. 492114'), 0, 1, 'C');

                    $pdf->SetY(max($pdf->GetY(), 22.0));
                    $pdf->Line($leftX, $pdf->GetY(), $leftX + $usableW, $pdf->GetY());
                    $pdf->Ln(1.2);

                    $pdf->SetFont('Arial', 'B', 9.2);
                    $pdf->Cell($usableW, 4.8, $toText($tipoMovimiento), 0, 1, 'C');
                    $pdf->SetFont('Arial', 'B', 8);
                    $pdf->Cell($usableW, 4.2, $toText($almacen . '  -  ' . (string)$treg), 0, 1, 'C');
                    $pdf->Line($leftX, $pdf->GetY() + 0.5, $leftX + $usableW, $pdf->GetY() + 0.5);
                    $pdf->Ln(1.4);

                    if ($showDataBlock) {
                        $pdf->SetFont('Arial', 'B', 7);
                        $pdf->Cell(18, 3.9, $toText('RUC:'), 0, 0, 'L');
                        $pdf->SetFont('Arial', '', 7);
                        $pdf->Cell($usableW - 18, 3.9, $toText($rucCli), 0, 1, 'L');

                        $pdf->SetFont('Arial', 'B', 7);
                        $pdf->Cell(18, 3.9, $toText('SEÑOR(ES):'), 0, 0, 'L');
                        $pdf->SetFont('Arial', '', 7);
                        $pdf->MultiCell($usableW - 18, 3.5, $toText($cliente), 0, 'L');

                        $pdf->SetFont('Arial', 'B', 7);
                        $pdf->Cell(18, 3.9, $toText('FECHA:'), 0, 0, 'L');
                        $pdf->SetFont('Arial', '', 7);
                        $pdf->Cell($usableW - 18, 3.9, $toText($fecha), 0, 1, 'L');

                        $pdf->SetFont('Arial', 'B', 7);
                        $pdf->Cell(18, 3.9, $toText('ALMACEN:'), 0, 0, 'L');
                        $pdf->SetFont('Arial', '', 7);
                        $pdf->Cell($usableW - 18, 3.9, $toText($almacen), 0, 1, 'L');

                        $pdf->SetFont('Arial', 'B', 7);
                        $pdf->Cell(18, 3.9, $toText('DESTINO:'), 0, 0, 'L');
                        $pdf->SetFont('Arial', '', 7);
                        $pdf->Cell($usableW - 18, 3.9, $toText($destino), 0, 1, 'L');

                        $pdf->SetFont('Arial', 'B', 7);
                        $pdf->Cell(18, 3.9, $toText('CODTRA:'), 0, 0, 'L');
                        $pdf->SetFont('Arial', '', 7);
                        $pdf->Cell($usableW - 18, 3.9, $toText($codtra !== '' ? $codtra : '-'), 0, 1, 'L');

                        $pdf->SetFont('Arial', 'B', 7);
                        $pdf->Cell(18, 3.9, $toText('DOC:'), 0, 0, 'L');
                        $pdf->SetFont('Arial', '', 7);
                        $pdf->Cell($usableW - 18, 3.9, $toText($doc !== '' ? $doc : '-'), 0, 1, 'L');

                        $pdf->Ln(0.8);
                    }
                };

                $drawTicketDetailHeader = function () use ($pdf, $toText, $leftX, $usableW) {
                    $pdf->Line($leftX, $pdf->GetY(), $leftX + $usableW, $pdf->GetY());
                    $pdf->Ln(0.9);

                    $pdf->SetFont('Arial', 'B', 7);
                    $pdf->Cell(15, 4, $toText('CODIGO'), 0, 0, 'L');
                    $pdf->Cell(42, 4, $toText('DESCRIPCION'), 0, 0, 'L');
                    $pdf->Cell(9, 4, 'LOTE', 0, 0, 'L');
                    $pdf->Cell(2, 4, '', 0, 0, 'L');
                    $pdf->Cell(6, 4, 'CANT.', 0, 1, 'R');

                    $pdf->Line($leftX, $pdf->GetY() + 0.1, $leftX + $usableW, $pdf->GetY() + 0.1);
                    $pdf->Ln(0.9);
                };

                $drawTicketHeader(true);
                $drawTicketDetailHeader();

                if (empty($detalle)) {
                    $pdf->SetFont('Arial', '', 7);
                    $pdf->Cell($usableW, 5, $toText('Sin items.'), 0, 1, 'C');
                } else {
                    foreach ($detalle as $item) {
                        if ($pdf->GetY() > 252) {
                            $pdf->AddPage();
                            $drawTicketHeader(false);
                            $drawTicketDetailHeader();
                        }

                        $descripcion = trim((string)($item['nom_producto'] ?? $item['tdescri'] ?? ''));
                        if (mb_strlen($descripcion) > 28) {
                            $descripcion = mb_substr($descripcion, 0, 28) . '...';
                        }

                        $codigoProducto = (string)($item['tcodigo'] ?? '');
                        $lote = (string)($item['tnumlot'] ?? $item['tlote'] ?? '');
                        $cantidad = $toCantidad($item['tcantid'] ?? 0);

                        $pdf->SetFont('Arial', '', 7);
                        $pdf->Cell(15, 3.8, $toText($codigoProducto), 0, 0, 'L');
                        $pdf->Cell(42, 3.8, $toText($descripcion), 0, 0, 'L');
                        $pdf->Cell(9, 3.8, $toText($lote), 0, 0, 'L');
                        $pdf->Cell(2, 3.8, '', 0, 0, 'L');
                        $pdf->Cell(6, 3.8, $toText($cantidad), 0, 1, 'R');
                        $pdf->Ln(0.3);
                    }
                }

                $pdf->Line($leftX, $pdf->GetY() + 0.5, $leftX + $usableW, $pdf->GetY() + 0.5);
                $pdf->Ln(2);

                $pdf->SetFont('Arial', 'B', 7.8);
                $pdf->Cell($usableW, 4.3, $toText('TOTAL CANTIDAD: ' . $toCantidad($totalCantidad)), 0, 1, 'L');
                $pdf->Cell($usableW, 4.3, $toText('TOTAL IMPORTE : ' . $toNum($totalImporte, 2)), 0, 1, 'L');

                $qrPayload = implode('|', [
                    'TREG:' . (string)$treg,
                    'TIPO:' . $tipoMovimiento,
                    'TRANS:' . $codtra,
                    'FECHA:' . $fecha,
                    'ALM:' . $almacen,
                    'TOTAL_CANT:' . $toCantidad($totalCantidad),
                    'TOTAL_IMP:' . $toNum($totalImporte, 2),
                ]);
                $qrTempPath = $buildQrTemp($qrPayload);

                $qrSize = 22;
                if ($pdf->GetY() + $qrSize + 10 > 292) {
                    $pdf->AddPage();
                    $drawTicketHeader(false);
                }

                $qrX = $leftX + (($usableW - $qrSize) / 2);
                $qrY = $pdf->GetY() + 1;
                // Déjalo simplemente así:
                if ($qrTempPath && file_exists($qrTempPath)) {
                    $pdf->Image($qrTempPath, $qrX, $qrY, $qrSize, $qrSize);
                } else {
                    $pdf->Rect($qrX, $qrY, $qrSize, $qrSize, 'D');
                    $pdf->SetFont('Arial', 'B', 7);
                    $pdf->SetXY($qrX, $qrY + 8.5);
                    $pdf->Cell($qrSize, 4, 'QR', 0, 1, 'C');
                }

                $pdf->SetY($qrY + $qrSize + 1);
                if ($glosa !== '') {
                    $pdf->SetFont('Arial', '', 7);
                    $pdf->MultiCell($usableW, 3.8, $toText('Glosa: ' . $glosa), 0, 'L');
                }
            } else {
                $pdf->SetAutoPageBreak(true, 14);

                $drawHeaderA4 = function (bool $showDataBlock = true) use ($pdf, $toText, $treg, $fecha, $almacen, $destino, $procli, $clienteNombre, $codtra, $tipoMovimiento, $codigoMovimiento) {
                    $left = 12;
                    $top = 10;
                    $usable = $pdf->GetPageWidth() - 24;

                    $logoPath = __DIR__ . '/../../frontend/assets/images/Favicon.png';
                    if (file_exists($logoPath)) {
                        $pdf->Image($logoPath, $left, $top + 1, 19);
                    }

                    $pdf->SetXY($left + 25, $top + 2);
                    $pdf->SetFont('Arial', 'B', 11.2);
                    $pdf->Cell(84, 6, $toText('GRANJA RINCONADA DEL SUR S.A.'), 0, 1, 'L');
                    $pdf->SetX($left + 25);
                    $pdf->SetFont('Arial', '', 7.8);
                    $pdf->Cell(84, 4.5, $toText('LA MAR S/N LA JOYA-AQP. - TELEFAX 492124 - -'), 0, 1, 'L');
                    $pdf->SetX($left + 25);
                    $pdf->Cell(84, 4.5, $toText('TELF.:492114'), 0, 1, 'L');

                    $boxW = 78;
                    $boxX = $left + $usable - $boxW;
                    $boxY = $top;
                    $boxH = 30;
                    $pdf->SetLineWidth(0.25);
                    $pdf->Rect($boxX, $boxY, $boxW, $boxH, 'D');
                    $pdf->Line($boxX, $boxY + 10, $boxX + $boxW, $boxY + 10);
                    $pdf->Line($boxX, $boxY + 20, $boxX + $boxW, $boxY + 20);

                    $pdf->SetXY($boxX, $boxY + 1.7);
                    $pdf->SetFont('Arial', 'B', 9.2);
                    $pdf->Cell($boxW, 6, $toText('RUC: 20419158462'), 0, 1, 'C');
                    $pdf->SetXY($boxX, $boxY + 11.2);
                    $pdf->SetFont('Arial', 'B', 11.2);
                    $pdf->Cell($boxW, 7, $toText($tipoMovimiento), 0, 1, 'C');
                    $pdf->SetXY($boxX, $boxY + 21.5);
                    $pdf->SetFont('Arial', 'B', 10);
                    $pdf->Cell($boxW, 6, $toText($almacen . '   -   ' . (string)$treg), 0, 1, 'C');

                    // Restore thin stroke for table/body borders.
                    $pdf->SetLineWidth(0.15);

                    $pdf->SetY($boxY + $boxH + 4);

                    if ($showDataBlock) {
                        $cliente = $clienteNombre !== '' ? $clienteNombre : '-';
                        $rucCli = preg_match('/^\d{8,11}$/', trim($procli)) ? trim($procli) : '-';

                        $pdf->SetFont('Arial', '', 8.4);
                        $pdf->Cell(22, 4.8, $toText('SEÑOR(ES):'), 0, 0, 'L');
                        $pdf->Cell(0, 4.8, $toText($cliente), 0, 1, 'L');
                        $pdf->Cell(22, 4.8, $toText('RUC :'), 0, 0, 'L');
                        $pdf->Cell(0, 4.8, $toText($rucCli), 0, 1, 'L');
                        $pdf->Cell(38, 4.8, $toText('FECHA DE EMISION:'), 0, 0, 'L');
                        $pdf->Cell(0, 4.8, $toText($fecha), 0, 1, 'L');
                        $pdf->Cell(22, 4.8, $toText('Almacen:'), 0, 0, 'L');
                        $pdf->Cell(0, 4.8, $toText($almacen), 0, 1, 'L');
                        $pdf->Cell(22, 4.8, $toText('Destino:'), 0, 0, 'L');
                        $pdf->Cell(0, 4.8, $toText($destino), 0, 1, 'L');
                        $pdf->Cell(22, 4.8, $toText('Codtra:'), 0, 0, 'L');
                        $pdf->Cell(0, 4.8, $toText($codtra !== '' ? $codtra : '-'), 0, 1, 'L');
                        $pdf->Ln(2);
                    }
                };

                $drawTablaHeader = function () use ($pdf, $toText) {
                    $pdf->SetFont('Arial', 'B', 7.2);
                    $pdf->SetFillColor(245, 245, 245);
                    $pdf->Cell(8,  7, 'NRO',         1, 0, 'C', true);
                    $pdf->Cell(18, 7, $toText('CODIGO'),      1, 0, 'C', true);
                    $pdf->Cell(64, 7, $toText('DESCRIPCION'), 1, 0, 'C', true);
                    $pdf->Cell(18, 7, $toText('CENCOS'),      1, 0, 'C', true);
                    $pdf->Cell(18, 7, 'ABC',          1, 0, 'C', true);
                    $pdf->Cell(24, 7, 'LOTE',         1, 0, 'C', true);
                    $pdf->Cell(18, 7, 'CANT.',        1, 0, 'C', true);
                    $pdf->Cell(18, 7, $toText('PESO KG'),     1, 1, 'C', true);
                };

                $drawHeaderA4(true);
                $drawTablaHeader();

                $pdf->SetFont('Arial', '', 7.2);
                $rowH = 5.0;

                if (empty($detalle)) {
                    $pdf->Cell(194, 7, $toText('Sin items.'), 1, 1, 'C');
                } else {
                    foreach ($detalle as $idx => $item) {
                        if ($pdf->GetY() + $rowH > 255) {
                            $pdf->AddPage();
                            $drawHeaderA4(false);
                            $drawTablaHeader();
                            $pdf->SetFont('Arial', '', 7.2);
                        }

                        $descripcion = trim((string)($item['nom_producto'] ?? $item['tdescri'] ?? ''));
                        if (mb_strlen($descripcion) > 36) {
                            $descripcion = mb_substr($descripcion, 0, 36) . '...';
                        }

                        $codigoProducto = (string)($item['tcodigo'] ?? '');
                        $cencos         = (string)($item['tcencos'] ?? '');
                        $abc            = (string)($item['tctabal'] ?? '');
                        $lote           = (string)($item['tnumlot'] ?? $item['tlote'] ?? '');
                        $cantidad       = $toCantidad($item['tcantid'] ?? 0);
                        $peso           = $toNum($item['tpeso']   ?? 0, 2);

                        $pdf->Cell(8,  $rowH, (string)($idx + 1),        1, 0, 'R');
                        $pdf->Cell(18, $rowH, $toText($codigoProducto),   1, 0, 'L');
                        $pdf->Cell(64, $rowH, $toText($descripcion),      1, 0, 'L');
                        $pdf->Cell(18, $rowH, $toText($cencos),           1, 0, 'C');
                        $pdf->Cell(18, $rowH, $toText($abc),              1, 0, 'C');
                        $pdf->Cell(24, $rowH, $toText($lote),             1, 0, 'C');
                        $pdf->Cell(18, $rowH, $toText($cantidad),         1, 0, 'R');
                        $pdf->Cell(18, $rowH, $toText($peso),             1, 1, 'R');
                    }
                }

                if ($pdf->GetY() > 248) {
                    $pdf->AddPage();
                    $drawHeaderA4(false);
                }

                $qrPayload = implode('|', [
                    'TREG:' . (string)$treg,
                    'TIPO:' . $tipoMovimiento,
                    'TRANS:' . $codtra,
                    'FECHA:' . $fecha,
                    'ALM:' . $almacen,
                    'TOTAL_CANT:' . $toCantidad($totalCantidad),
                    'TOTAL_IMP:' . $toNum($totalImporte, 2),
                ]);
                $qrTempPath = $buildQrTemp($qrPayload);

                $footerY = $pdf->GetY() + 4;
                if ($qrTempPath && file_exists($qrTempPath)) {
                    $pdf->Image($qrTempPath, 12, $footerY, 24, 24);
                } else {
                    $pdf->Rect(12, $footerY, 24, 24, 'D');
                    $pdf->SetFont('Arial', 'B', 7);
                    $pdf->SetXY(12, $footerY + 9);
                    $pdf->Cell(24, 4, 'QR', 0, 1, 'C');
                }

                $boxW = 54;
                $boxH = 9.5;
                $boxX = $pdf->GetPageWidth() - 12 - $boxW;
                $pdf->SetY($footerY + 13);
                $pdf->SetX($boxX);
                $pdf->SetFont('Arial', 'B', 11);
                $pdf->Rect($boxX, $pdf->GetY(), $boxW, $boxH, 'D');
                $pdf->Line($boxX + 32, $pdf->GetY(), $boxX + 32, $pdf->GetY() + $boxH);
                $pdf->Cell(32, $boxH, 'TOTAL:', 0, 0, 'R');
                $pdf->Cell($boxW - 32, $boxH, $toText($toCantidad($totalCantidad)), 0, 1, 'R');

                if ($glosa !== '') {
                    $pdf->SetFont('Arial', '', 9);
                    $pdf->SetY(max($pdf->GetY(), $footerY + 24));
                    $pdf->Cell(0, 5, $toText('Glosa: ' . $glosa), 0, 1, 'L');
                }
            }

            $filename = 'movimiento_' . preg_replace('/[^0-9A-Za-z_-]/', '_', (string)$treg) . '_' . date('Ymd_His') . '.pdf';
            
            if (!empty($qrTempPath) && file_exists($qrTempPath)) {
                @unlink($qrTempPath);
            }

            $pdf->Output($forzarDescarga ? 'D' : 'I', $filename);
            exit;
        } catch (Exception $e) {
            if (!empty($qrTempPath) && file_exists($qrTempPath)) {
                @unlink($qrTempPath);
            }
            $this->error('No se pudo generar el PDF del movimiento: ' . $e->getMessage(), 500);
        }
    }

    public function getReporteKardexPdf(): void
    {
        try {
            $filtros = [
                'alma' => $_GET['alma'] ?? '',
                'fecha_desde' => $_GET['fecha_desde'] ?? '',
                'fecha_hasta' => $_GET['fecha_hasta'] ?? '',
                'codigo_desde' => $_GET['codigo_desde'] ?? '',
                'codigo_hasta' => $_GET['codigo_hasta'] ?? '',
                'modo_items' => $_GET['modo_items'] ?? 'todo',
                'stock_con' => $_GET['stock_con'] ?? 'valor',
                'tipo' => $_GET['tipo'] ?? 'detalle',
            ];
            $formato = strtolower(trim((string)($_GET['formato'] ?? 'a4')));
            $esTicket80 = in_array($formato, ['80mm', '80', 'ticket80'], true);

            $rows = $this->service->getReporteKardex($filtros);

            require_once __DIR__ . '/../libraries/PDFExporter.php';

            if ($esTicket80) {
                $pdf = new PDFExporter('P', 'mm', [80, 297]);
                $pdf->SetMargins(2, 4, 2);
                $pdf->SetAutoPageBreak(true, 6);
            } else {
                $pdf = new PDFExporter('L', 'mm', 'A4');
            }

            $titulo = strtolower((string)$filtros['tipo']) === 'resumen'
                ? 'KARDEX PRODUCTOS VALORADOS - RESUMEN'
                : 'KARDEX PRODUCTOS VALORADOS';

            $pdf->setReportTitle($titulo);
            $pdf->setHeaderRightLines([
                'Almacen: ' . (string)($filtros['alma'] ?: '-'),
                'Desde: ' . (string)($filtros['fecha_desde'] ?: '-') . ' | Hasta: ' . (string)($filtros['fecha_hasta'] ?: '-'),
            ]);

            if ($esTicket80) {
                $pdf->setTableColumns(
                    ['FECHA', 'COD', 'DESCRIPCION', 'ENT', 'SAL', 'STK'],
                    [12, 9, 29, 8, 8, 8]
                );
            } else {
                $pdf->setTableColumns(
                    ['FECHA', 'COD', 'NRO.DOC', 'CLI/PRO', 'DESCRIPCION', 'ENTRADA', 'SALIDA', 'STOCK', 'V.ENTRADA', 'V.SALIDA', 'V.STOCK', 'VA.UNIT'],
                    [18, 18, 24, 28, 55, 18, 18, 18, 22, 22, 22, 14]
                );
            }

            $pdf->AddPage();

            $toText = static function ($value): string {
                return mb_convert_encoding((string)($value ?? ''), 'ISO-8859-1', 'UTF-8');
            };

            $toNum = static function ($value, int $decimals = 2): string {
                return number_format((float)($value ?? 0), $decimals, '.', ',');
            };

            if (empty($rows)) {
                if ($esTicket80) {
                    $pdf->SetFont('Arial', 'B', 7);
                    $pdf->Cell(74, 6, $toText('No hay datos para los filtros seleccionados.'), 1, 1, 'C');
                } else {
                    $pdf->SetFont('Arial', 'B', 10);
                    $pdf->Cell(277, 8, $toText('No hay datos para los filtros seleccionados.'), 1, 1, 'C');
                }
            } else {
                if ($esTicket80) {
                    $pdf->SetFont('Arial', '', 5.6);
                    foreach ($rows as $row) {
                        $descripcion = trim((string)($row['descripcion'] ?? ''));
                        if (mb_strlen($descripcion) > 24) {
                            $descripcion = mb_substr($descripcion, 0, 24) . '...';
                        }

                        $pdf->Cell(12, 4.5, $toText($row['fecha'] ?? ''), 1, 0, 'L');
                        $pdf->Cell(9, 4.5, $toText($row['codigo'] ?? ''), 1, 0, 'L');
                        $pdf->Cell(29, 4.5, $toText($descripcion), 1, 0, 'L');
                        $pdf->Cell(8, 4.5, $toNum($row['unid_entrada'] ?? 0, 0), 1, 0, 'R');
                        $pdf->Cell(8, 4.5, $toNum($row['unid_salida'] ?? 0, 0), 1, 0, 'R');
                        $pdf->Cell(8, 4.5, $toNum($row['unid_stock'] ?? 0, 0), 1, 1, 'R');
                    }
                } else {
                    $pdf->SetFont('Arial', '', 7);

                    foreach ($rows as $row) {
                        $pdf->Cell(18, 5, $toText($row['fecha'] ?? ''), 1, 0, 'L');
                        $pdf->Cell(18, 5, $toText($row['codigo'] ?? ''), 1, 0, 'L');
                        $pdf->Cell(24, 5, $toText($row['nrodoc'] ?? ''), 1, 0, 'L');
                        $pdf->Cell(28, 5, $toText($row['clipro'] ?? ''), 1, 0, 'L');
                        $pdf->Cell(55, 5, $toText($row['descripcion'] ?? ''), 1, 0, 'L');
                        $pdf->Cell(18, 5, $toNum($row['unid_entrada'] ?? 0, 2), 1, 0, 'R');
                        $pdf->Cell(18, 5, $toNum($row['unid_salida'] ?? 0, 2), 1, 0, 'R');
                        $pdf->Cell(18, 5, $toNum($row['unid_stock'] ?? 0, 2), 1, 0, 'R');
                        $pdf->Cell(22, 5, $toNum($row['val_entrada'] ?? 0, 2), 1, 0, 'R');
                        $pdf->Cell(22, 5, $toNum($row['val_salida'] ?? 0, 2), 1, 0, 'R');
                        $pdf->Cell(22, 5, $toNum($row['val_stock'] ?? 0, 2), 1, 0, 'R');
                        $pdf->Cell(14, 5, $toNum($row['va_unit'] ?? 0, 4), 1, 1, 'R');
                    }
                }
            }

            $filename = 'kardex_valorado_' . date('Ymd_His') . '.pdf';
            $pdf->Output('I', $filename);
            exit;
        } catch (Exception $e) {
            $this->error('No se pudo generar el PDF de kardex: ' . $e->getMessage(), 500);
        }
    }

    // ─── SALIDAS RÁPIDAS ─────────────────────────────────────────────────────

    public function getLineas(): void
    {
        $this->json($this->service->getLineas());
    }

    public function getProductosStockSalida(): void
    {
        $alma = trim($_GET['alma'] ?? '');
        if ($alma === '') {
            $this->json(['rows' => []]);
            return;
        }
        $termino = trim($_GET['q']     ?? '');
        $linea   = trim($_GET['linea'] ?? '');
        $rows = $this->service->buscarProductosStockSalida($alma, $termino, $linea);
        $this->json(['rows' => $rows, 'total' => count($rows)]);
    }

    public function getMisSalidas(): void
    {
        $usuario = $_GET['usuario'] ?? ($_SESSION['username'] ?? ($_SESSION['usuario'] ?? ''));
        $filtros = [
            'fecha'   => $_GET['fecha']   ?? date('Y-m-d'),
            'usuario' => $usuario,
        ];
        $this->json($this->service->getMisSalidas($filtros));
    }
}
