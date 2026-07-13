<?php
/**
 * MovimientoAlmacenService
 * Lógica de negocio: VerificaFecha, Redondear, grabar movimiento completo
 */

require_once __DIR__ . '/../repositories/MovimientoAlmacenRepository.php';

class MovimientoAlmacenService {
    private $repo;

    public function __construct($db) {
        $this->repo = new MovimientoAlmacenRepository($db);
    }

    // ─── VERIFICACIÓN DE FECHA (equivalente VBA VerificaFecha) ───────────────
    /**
     * Retorna:
     *   null  = fecha válida, período abierto ✅
     *   1     = fecha inválida
     *   2     = mes cerrado (indi.cierre = 'C')
     *   3     = día cerrado para La Joya (dola.cerrajoya = 'C')
     *   4     = año no coincide con conempre.eano
     */
    public function verificarFecha(string $fecha): ?int {
        if (!strtotime($fecha)) return 1;

        $ts  = strtotime($fecha);
        $ano = (int)date('Y', $ts);
        $mes = (int)date('n', $ts);

        // Validar mes (bug corregido: AND en lugar de OR)
        if ($mes < 1 || $mes > 12) return 1;

        // Validar año del sistema
        $anoSistema = (int)$this->repo->getAnoSistema();
        if ($anoSistema !== $ano) return 4;

        // Verificar mes cerrado
        $fechaMes = date('Y/m', $ts);
        if ($this->repo->getMesCerrado($fechaMes)) return 2;

        // Verificar día cerrado (zona La Joya)
        $fechaDia = date('Y/m/d', $ts);
        if ($this->repo->getDiaCerradoJoya($fechaDia)) return 3;

        return null; // Fecha válida
    }

    public function getMensajeVerificacion(?int $codigo): string {
        switch ($codigo) {
            case 1:    return 'La fecha ingresada no es válida.';
            case 2:    return 'El mes está cerrado. No se pueden registrar movimientos.';
            case 3:    return 'El día está cerrado para el almacén La Joya.';
            case 4:    return 'El año no corresponde al año activo del sistema.';
            case null: return 'Fecha válida.';
            default:   return 'Error desconocido de validación de fecha.';
        }
    }

    // ─── VERIFICAR MES PARA CAUSAL (VerificaMesACausal) ─────────────────────
    // 0 = mes abierto, 1 = mes cerrado
    public function verificarMes(string $fecha): int {
        if (!strtotime($fecha)) return 1;
        $fechaMes = date('Y/m', strtotime($fecha));
        return $this->repo->getMesCerrado($fechaMes) ? 1 : 0;
    }

    // ─── REDONDEAR (equivalente VBA Redondear) ───────────────────────────────
    public function redondear(float $numero): float {
        // Redondeo a 2 decimales con lógica del tercer decimal
        $parteEntera   = (int)$numero;
        $parteDecStr   = (string)$numero;
        $puntoPos      = strpos($parteDecStr, '.');

        if ($puntoPos === false) return (float)$parteEntera;

        $parteDecimal = substr($parteDecStr, $puntoPos + 1);
        $parteDecimal = str_pad($parteDecimal, 3, '0');

        if (strlen($parteDecimal) >= 3) {
            $tercer = (int)substr($parteDecimal, 2, 1);
            $dos    = (int)substr($parteDecimal, 0, 2);
            if ($tercer >= 5) {
                $dos += 1;
                if ($dos >= 100) {
                    $parteEntera += 1;
                    $dos = 0;
                }
            }
            $parteDecimal = str_pad((string)$dos, 2, '0', STR_PAD_LEFT);
        } else {
            $parteDecimal = substr(str_pad($parteDecimal, 2, '0'), 0, 2);
        }

        return (float)("{$parteEntera}.{$parteDecimal}");
    }

    // ─── MAESTROS ─────────────────────────────────────────────────────────────

    public function getAlmacenes(): array        { return $this->repo->getAlmacenes(); }
    public function getTransacciones(): array    { return $this->repo->getTransacciones(); }
    public function getTiposDocumento(): array   { return $this->repo->getTiposDocumento(); }
    public function getCentrosCosto(): array     { return $this->repo->getCentrosCosto(); }
    public function getClientesProveedores(string $termino = ''): array { return $this->repo->getClientesProveedores($termino); }
    public function getProductos(int $limit = 200, int $offset = 0, string $alma = '', string $codtra = ''): array {
        return $this->repo->getProductos($limit, $offset, $alma, $codtra);
    }
    public function getLotes(string $alma = '', string $codigo = '', string $fecha = ''): array {
        return $this->repo->getLotes($alma, $codigo, $fecha);
    }
    public function buscarProductos(string $t, int $limit = 200, int $offset = 0, string $alma = '', string $codtra = ''): array {
        return $this->repo->buscarProductos($t, $limit, $offset, $alma, $codtra);
    }
    public function getTipoCambioPorFecha(string $fecha): ?array { return $this->repo->getTipoCambioPorFecha($fecha); }
    public function getClientePorCodigo(string $codigo): ?array  { return $this->repo->getClientePorCodigo($codigo); }
    public function getCorrelativo(string $tdoc, string $tserie): array {
        return ['correlativo' => $this->repo->getCorrelativo($tdoc, $tserie)];
    }

    public function getAbc(string $tipo, array $params): array {
        switch ($tipo) {
            case 'procesos':    return $this->repo->getProcesos();
            case 'subprocesos': return $this->repo->getSubprocesos($params['proc'] ?? '');
            case 'actividades': return $this->repo->getActividades($params['proc'] ?? '', $params['subp'] ?? '');
            case 'tareas':      return $this->repo->getTareas($params['proc'] ?? '', $params['subp'] ?? '', $params['acti'] ?? '');
            default:            return [];
        }
    }

    // ─── NÚMERO DE REGISTRO ───────────────────────────────────────────────────

    public function getNuevoReg(): array {
        $nuevo = $this->repo->getNuevoReg();
        $valido = $this->repo->validarConsistenciaReg($nuevo);
        return ['treg' => $nuevo, 'valido' => $valido];
    }

    // ─── CABECERA ─────────────────────────────────────────────────────────────

    public function listarMovimientos(array $filtros): array {
        return $this->repo->listarMovimientos($filtros);
    }

    public function listarMovimientosDashboard(array $filtros): array {
        return $this->repo->listarMovimientosDashboard($filtros);
    }

    public function getMovimiento(string $treg): array {
    $cabecera = $this->repo->getMovimientoPorReg($treg);
    if (!$cabecera) throw new Exception("Movimiento #{$treg} no encontrado.", 404);

    $detalle = $this->repo->getDetallePorReg($treg);

    // 🔍 CORRECCIÓN CLAVE: Leer los parámetros directamente desde $_GET
    $tcodtraFilter = $_GET['tcodtra'] ?? null;
    $talmFilter    = $_GET['talm'] ?? null;

    if (!empty($tcodtraFilter) && !empty($talmFilter)) {
        // 1. Filtrar el detalle de forma estricta para que SOLO muestre la fila que coincide con la transacción Y el almacén pulsado
        $detalle = array_values(array_filter($detalle, function($item) use ($tcodtraFilter, $talmFilter) {
            return $item['tcodtra'] === $tcodtraFilter && $item['talm'] === $talmFilter;
        }));

        // 2. Adaptar la cabecera visual para que coincida exactamente con la fila seleccionada
        if ($tcodtraFilter !== $cabecera['tcodtra']) {
            $cabecera['tcodtra'] = $tcodtraFilter;
            
            if ($tcodtraFilter === 'E005') {
                $cabecera['nom_transaccion'] = "INGRESO POR TRANSFERENCIA";
            }
        }

        // 3. Forzar el almacén y su nombre en la cabecera del modal
        if ($talmFilter !== $cabecera['talm']) {
            $cabecera['talm'] = $talmFilter;
            $almacenObj = $this->repo->getAlmacenById($talmFilter);
            $cabecera['nom_almacen'] = $almacenObj ? $almacenObj['descri'] : 'ALMACÉN DESTINO';
        }
    }

    return ['cabecera' => $cabecera, 'detalle' => $detalle];
}

    public function crearMovimiento(array $data): array {
        // Validar fecha
        $codVerif = $this->verificarFecha($data['tfectra'] ?? '');
        if ($codVerif !== null) {
            throw new Exception($this->getMensajeVerificacion($codVerif), 422);
        }

        // Obtener y validar treg
        $nuevo = $this->getNuevoReg();
        if (!$nuevo['valido']) {
            throw new Exception('Error generando número de registro. Intente nuevamente.', 500);
        }

        // Calcular totales del detalle
        $detalle    = $data['detalle'] ?? [];
        $totalPeso  = 0;
        $totalImporte = 0;
        foreach ($detalle as $item) {
            $totalPeso    += (float)($item['tpeso'] ?? 0);
            $totalImporte += (float)($item['timport'] ?? 0);
        }

        $cabData = [
            'treg'             => $nuevo['treg'],
            'tfectra'          => $data['tfectra'],
            'tcodtra'          => $data['tcodtra'],
            'talm'             => $data['talm'],
            'tprocli'          => $data['tprocli']          ?? '',
            'tdoc'             => $data['tdoc']             ?? '',
            'tserie'           => strtoupper($data['tserie'] ?? ''),
            'tnumfac'          => $data['tnumfac']          ?? 0,
            'tfecfac'          => $data['tfecfac']          ?? $data['tfectra'],
            'tmon'             => $data['tmon']             ?? 'S/',
            'tlib'             => $data['tlib']             ?? '',
            'tordcom'          => $data['tordcom']          ?? 0,
            'tglosa'           => $data['tglosa']           ?? '',
            'tcostmin'         => $data['tcostmin']         ?? 0,
            'tpesotot'         => $this->redondear($totalPeso),
            'timport'          => $this->redondear($totalImporte),
            'tcod_conductor'   => $data['tcod_conductor']   ?? '',
            'tplaca'           => $data['tplaca']           ?? '',
            'tmotivo_traslado' => $data['tmotivo_traslado'] ?? '',
            'tuser'            => $data['tuser']            ?? 'SYS',
        ];

        $this->repo->crearCabecera($cabData);

        // Insertar detalle y actualizar stock
        // La primera letra del código determina la dirección: 'E'=ENTRADA, 'S'=SALIDA
        $esEntrada = (strtoupper(substr($data['tcodtra'], 0, 1)) === 'E');

        // Verificar si la transacción genera contra-asiento (gentsa=1)
        $transObj = $this->repo->getTransaccionById($data['tcodtra']);
        $esTransferencia = $transObj ? ((int)($transObj['gentsa'] ?? 0) === 1) : false;

        // Los registros E auto-generados (CW2) usan count a partir del total de items S
        // para evitar colisión en (treg, count) si hay clave única
        $totalItems = count($detalle);
        $countE = $totalItems; // primer E usará $totalItems + 1

        foreach ($detalle as $idx => $item) {
            $count   = $idx + 1;
            $importe = $this->redondear((float)($item['tcantid'] ?? 0) * (float)($item['tpreuni'] ?? 0));

            $detalleBase = [
                'treg'        => $nuevo['treg'],
                'count'       => $count,
                'tcodigo'     => $item['tcodigo'],
                'tfectra'     => $data['tfectra'],
                'tcodtra'     => $data['tcodtra'],
                'talm'        => $data['talm'],
                'talr'        => $item['talr']        ?? '',
                'tcantid'     => (float)($item['tcantid']  ?? 0),
                'tpreuni'     => (float)($item['tpreuni']  ?? 0),
                'timport'     => $importe,
                'tpeso'       => $this->redondear((float)($item['tpeso'] ?? 0)),
                'tkardex'     => $importe, // valor del movimiento = importe
                'tmon'        => $data['tmon'] ?? 'S/',
                'tcencos'     => $item['tcencos']     ?? '',
                'tsacos'      => (float)($item['tsacos']   ?? 0),
                'tnumlot'     => $item['tnumlot']     ?? '',
                'tlote'       => $item['tlote']       ?? '00000000',
                'tdf'         => $item['tdf']         ?? '0',
                'tctabal'     => $item['tctabal']     ?? '',
                'tfecfac'     => $item['tfecfac']     ?? $data['tfectra'],
                'tcodproc'    => $item['tcodproc']    ?? '00',
                'tcodsubproc' => $item['tcodsubproc'] ?? '00',
                'tcodacti'    => $item['tcodacti']    ?? '00',
                'tcodtarea'   => $item['tcodtarea']   ?? '00',
                'ttoneladas'  => (float)($item['ttoneladas'] ?? 0),
                'tprod'       => $item['tprod']       ?? 'P',
                'tglosa'      => $item['tglosa']      ?? '',
                'tuser'       => $data['tuser']       ?? 'SYS',
                'tlib'        => $data['tlib']        ?? 'AL',
                'tnumreg'     => $nuevo['treg'],
                'tdoc'        => $data['tdoc']        ?? '',
                'tserie'      => $data['tserie']      ?? '',
                'tnumfac'     => $data['tnumfac']     ?? 0,
            ];

            $this->repo->agregarDetalle($detalleBase); // mark='CW1' por defecto

            // Actualizar stock en mzon
            $this->repo->actualizarStock(
                $item['tcodigo'],
                $item['tlote']  ?? '00000000',
                $data['talm'],
                (float)($item['tcantid'] ?? 0),
                $this->redondear((float)($item['tpeso'] ?? 0)),
                $importe,
                $esEntrada ? 'E' : 'S'
            );

            // Para transferencias (gentsa=1): auto-generar contra-asiento Entrada en almacén destino
            if ($esTransferencia && !empty($item['talr'])) {
                // Código de entrada: reemplazar primer carácter por 'E' (S005→E005, S400→E400, etc.)
                $tcodtraE = 'E' . substr($data['tcodtra'], 1);
                $countE++; // count distinto al S para evitar duplicate key

                $detalleE = array_merge($detalleBase, [
                    'count'   => $countE,
                    'tcodtra' => $tcodtraE,
                    'talm'    => $item['talr'],        // destino pasa a ser origen
                    'talr'    => $data['talm'],        // origen pasa a ser destino
                ]);

                $this->repo->agregarDetalle($detalleE, 'CW2'); // mark='CW2'

                // Sumar stock en almacén destino
                $this->repo->actualizarStock(
                    $item['tcodigo'],
                    $item['tlote'] ?? '00000000',
                    $item['talr'],
                    (float)($item['tcantid'] ?? 0),
                    $this->redondear((float)($item['tpeso'] ?? 0)),
                    $importe,
                    'E'
                );
            }
        }

        return ['treg' => $nuevo['treg'], 'mensaje' => 'Movimiento grabado correctamente.'];
    }

    public function actualizarMovimiento(string $treg, array $data): array {
        $cabActual = $this->repo->getMovimientoPorReg($treg);
        if (!$cabActual) {
            throw new Exception("Movimiento #{$treg} no encontrado.", 404);
        }

        if (!strtotime($data['tfectra'] ?? '')) {
            throw new Exception('La fecha ingresada no es válida.', 422);
        }

        $detalleNuevo = $data['detalle'] ?? [];

        // 1) Revertir stock del detalle anterior.
        $detalleAnterior = $this->repo->getDetallePorReg($treg);
        // La primera letra del código determina la dirección: 'E'=ENTRADA, 'S'=SALIDA
        $eraEntrada = (strtoupper(substr($cabActual['tcodtra'], 0, 1)) === 'E');

        foreach ($detalleAnterior as $item) {
            $this->repo->actualizarStock(
                $item['tcodigo'],
                $item['tlote'] ?? '00000000',
                $cabActual['talm'],
                (float)($item['tcantid'] ?? 0),
                (float)($item['tpeso'] ?? 0),
                (float)($item['timport'] ?? 0),
                $eraEntrada ? 'S' : 'E'
            );
        }

        // 2) Reemplazar cabecera y detalle.
        $totalPeso = 0;
        $totalImporte = 0;
        foreach ($detalleNuevo as $item) {
            $totalPeso += (float)($item['tpeso'] ?? 0);
            $totalImporte += (float)($item['timport'] ?? 0);
        }

        $cabData = [
            'tfectra'          => $data['tfectra'],
            'tcodtra'          => $data['tcodtra'],
            'talm'             => $data['talm'],
            'tprocli'          => $data['tprocli']          ?? '',
            'tdoc'             => $data['tdoc']             ?? '',
            'tserie'           => strtoupper($data['tserie'] ?? ''),
            'tnumfac'          => $data['tnumfac']          ?? 0,
            'tfecfac'          => $data['tfecfac']          ?? $data['tfectra'],
            'tmon'             => $data['tmon']             ?? 'S/',
            'tlib'             => $data['tlib']             ?? '',
            'tordcom'          => $data['tordcom']          ?? 0,
            'tglosa'           => $data['tglosa']           ?? '',
            'tcostmin'         => $data['tcostmin']         ?? 0,
            'tpesotot'         => $this->redondear($totalPeso),
            'timport'          => $this->redondear($totalImporte),
            'tcod_conductor'   => $data['tcod_conductor']   ?? '',
            'tplaca'           => $data['tplaca']           ?? '',
            'tmotivo_traslado' => $data['tmotivo_traslado'] ?? '',
        ];

        $this->repo->actualizarCabecera((int)$treg, $cabData);
        $this->repo->eliminarDetalleCompleto((int)$treg);

        // 3) Insertar detalle nuevo y aplicar stock según nueva transacción.
        // La primera letra del código determina la dirección: 'E'=ENTRADA, 'S'=SALIDA
        $esEntradaNueva = (strtoupper(substr($data['tcodtra'], 0, 1)) === 'E');

        // Verificar si la transacción genera contra-asiento (gentsa=1)
        $transObj = $this->repo->getTransaccionById($data['tcodtra']);
        $esTransferencia = $transObj ? ((int)($transObj['gentsa'] ?? 0) === 1) : false;

        $totalItems = count($detalleNuevo);
        $countE = $totalItems;

        foreach ($detalleNuevo as $idx => $item) {
            $count = $idx + 1;
            $importe = $this->redondear((float)($item['tcantid'] ?? 0) * (float)($item['tpreuni'] ?? 0));

            $detalleBase = [
                'treg'        => (int)$treg,
                'count'       => $count,
                'tcodigo'     => $item['tcodigo'],
                'tfectra'     => $data['tfectra'],
                'tcodtra'     => $data['tcodtra'],
                'talm'        => $data['talm'],
                'talr'        => $item['talr']        ?? '',
                'tcantid'     => (float)($item['tcantid']  ?? 0),
                'tpreuni'     => (float)($item['tpreuni']  ?? 0),
                'timport'     => $importe,
                'tpeso'       => $this->redondear((float)($item['tpeso'] ?? 0)),
                'tkardex'     => $importe,
                'tmon'        => $data['tmon'] ?? 'S/',
                'tcencos'     => $item['tcencos']     ?? '',
                'tsacos'      => (float)($item['tsacos']   ?? 0),
                'tnumlot'     => $item['tnumlot']     ?? '',
                'tlote'       => $item['tlote']       ?? '00000000',
                'tdf'         => $item['tdf']         ?? '0',
                'tctabal'     => $item['tctabal']     ?? '',
                'tfecfac'     => $item['tfecfac']     ?? $data['tfectra'],
                'tcodproc'    => $item['tcodproc']    ?? '00',
                'tcodsubproc' => $item['tcodsubproc'] ?? '00',
                'tcodacti'    => $item['tcodacti']    ?? '00',
                'tcodtarea'   => $item['tcodtarea']   ?? '00',
                'ttoneladas'  => (float)($item['ttoneladas'] ?? 0),
                'tprod'       => $item['tprod']       ?? 'P',
                'tglosa'      => $item['tglosa']      ?? '',
                'tuser'       => $data['tuser']       ?? 'SYS',
                'tlib'        => $data['tlib']        ?? 'AL',
                'tnumreg'     => (int)$treg,
                'tdoc'        => $data['tdoc']        ?? '',
                'tserie'      => $data['tserie']      ?? '',
                'tnumfac'     => $data['tnumfac']     ?? 0,
            ];

            $this->repo->agregarDetalle($detalleBase); // mark='CW1'

            $this->repo->actualizarStock(
                $item['tcodigo'],
                $item['tlote']  ?? '00000000',
                $data['talm'],
                (float)($item['tcantid'] ?? 0),
                $this->redondear((float)($item['tpeso'] ?? 0)),
                $importe,
                $esEntradaNueva ? 'E' : 'S'
            );

            // Para transferencias (gentsa=1): auto-generar contra-asiento Entrada
            if ($esTransferencia && !empty($item['talr'])) {
                $tcodtraE = 'E' . substr($data['tcodtra'], 1);
                $countE++;

                $detalleE = array_merge($detalleBase, [
                    'count'   => $countE,
                    'tcodtra' => $tcodtraE,
                    'talm'    => $item['talr'],
                    'talr'    => $data['talm'],
                ]);

                $this->repo->agregarDetalle($detalleE, 'CW2');

                $this->repo->actualizarStock(
                    $item['tcodigo'],
                    $item['tlote'] ?? '00000000',
                    $item['talr'],
                    (float)($item['tcantid'] ?? 0),
                    $this->redondear((float)($item['tpeso'] ?? 0)),
                    $importe,
                    'E'
                );
            }
        }

        return ['treg' => (int)$treg, 'mensaje' => 'Movimiento actualizado correctamente.'];
    }

    public function eliminarMovimiento(int $treg): bool {
        // Revertir stock antes de eliminar
        $detalle = $this->repo->getDetallePorReg($treg);
        $cab     = $this->repo->getMovimientoPorReg($treg);
        if (!$cab) throw new Exception("Movimiento #{$treg} no encontrado.", 404);

        $transaccion = $this->repo->getTransaccionById($cab['tcodtra']);
        // La primera letra del código determina la dirección: 'E'=ENTRADA, 'S'=SALIDA
        $esEntrada   = (strtoupper(substr($cab['tcodtra'], 0, 1)) === 'E');

        foreach ($detalle as $item) {
            $this->repo->actualizarStock(
                $item['tcodigo'],
                $item['tlote'] ?? '00000000',
                $cab['talm'],
                (float)$item['tcantid'],
                (float)$item['tpeso'],
                (float)$item['timport'],
                $esEntrada ? 'S' : 'E' // invertir para revertir
            );
        }

        $this->repo->eliminarDetalleCompleto($treg);
        return $this->repo->eliminarCabecera($treg);
    }

    public function getReporteKardex(array $filtros): array {
        $rows = $this->repo->getKardexMovimientosReporte($filtros);
        if (empty($rows)) {
            return [];
        }

        $detalle = [];
        $saldos = [];

        foreach ($rows as $row) {
            $codigo = (string)($row['tcodigo'] ?? '');
            $cantidad = (float)($row['tcantid'] ?? 0);
            $valor = (float)($row['timport'] ?? 0);
            $esSalida = (int)($row['gentsa'] ?? 0) === 1;

            if (!isset($saldos[$codigo])) {
                $saldos[$codigo] = [
                    'cant' => 0.0,
                    'valor' => 0.0,
                    'descripcion' => (string)($row['descripcion'] ?? '')
                ];
            }

            $unidEntrada = $esSalida ? 0.0 : $cantidad;
            $unidSalida = $esSalida ? $cantidad : 0.0;
            $valEntrada = $esSalida ? 0.0 : $valor;
            $valSalida = $esSalida ? $valor : 0.0;

            $saldos[$codigo]['cant'] += ($unidEntrada - $unidSalida);
            $saldos[$codigo]['valor'] += ($valEntrada - $valSalida);

            $stockCantidad = $saldos[$codigo]['cant'];
            $stockValor = $saldos[$codigo]['valor'];
            $valorUnitario = $stockCantidad != 0.0 ? ($stockValor / $stockCantidad) : (float)($row['tpreuni'] ?? 0);

            $doc = trim((string)($row['tdoc'] ?? ''));
            $serie = trim((string)($row['tserie'] ?? ''));
            $numero = trim((string)($row['tnumfac'] ?? ''));
            $nrodoc = trim($doc . ' ' . $serie . '-' . $numero);

            $detalle[] = [
                'fecha' => (string)($row['tfectra'] ?? ''),
                'codigo' => $codigo,
                'nrodoc' => $nrodoc,
                'clipro' => (string)($row['clipro'] ?? ''),
                'descripcion' => (string)($row['descripcion'] ?? ''),
                'unid_entrada' => $unidEntrada,
                'unid_salida' => $unidSalida,
                'unid_stock' => $stockCantidad,
                'val_entrada' => $valEntrada,
                'val_salida' => $valSalida,
                'val_stock' => $stockValor,
                'va_unit' => $valorUnitario,
            ];
        }

        $stockCon = strtolower((string)($filtros['stock_con'] ?? 'valor'));
        if ($stockCon === 'negativos') {
            $detalle = array_values(array_filter($detalle, function ($row) {
                return (float)$row['unid_stock'] < 0;
            }));
        }

        $tipo = strtolower((string)($filtros['tipo'] ?? 'detalle'));
        if ($tipo === 'resumen') {
            $resumen = [];
            foreach ($detalle as $row) {
                $codigo = $row['codigo'];
                if (!isset($resumen[$codigo])) {
                    $resumen[$codigo] = [
                        'fecha' => '',
                        'codigo' => $codigo,
                        'nrodoc' => '',
                        'clipro' => '',
                        'descripcion' => $row['descripcion'],
                        'unid_entrada' => 0.0,
                        'unid_salida' => 0.0,
                        'unid_stock' => 0.0,
                        'val_entrada' => 0.0,
                        'val_salida' => 0.0,
                        'val_stock' => 0.0,
                        'va_unit' => 0.0,
                    ];
                }

                $resumen[$codigo]['unid_entrada'] += (float)$row['unid_entrada'];
                $resumen[$codigo]['unid_salida'] += (float)$row['unid_salida'];
                $resumen[$codigo]['unid_stock'] = (float)$row['unid_stock'];
                $resumen[$codigo]['val_entrada'] += (float)$row['val_entrada'];
                $resumen[$codigo]['val_salida'] += (float)$row['val_salida'];
                $resumen[$codigo]['val_stock'] = (float)$row['val_stock'];
                $resumen[$codigo]['va_unit'] = (float)$row['va_unit'];
            }

            $detalle = array_values($resumen);
        }

        return $detalle;
    }

    public function getKardex(string $codigo, string $lote, string $alma, string $fecha = '', string $codtra = ''): array {
        $stock = $this->repo->getKardex($codigo, $lote, $alma);
        $result = $stock ?? [
            'qiniano' => 0, 'piniano' => 0, 'viniano' => 0,
            'qstock'  => 0, 'pstock'  => 0, 'vstock'  => 0,
            'cosuni'  => 0
        ];

        $result['res_cantidad'] = 0;
        $result['res_peso'] = 0;
        $result['res_valor'] = 0;

        $esEntrada = strtoupper(substr(trim($codtra), 0, 1)) === 'E';
        if ($esEntrada && $fecha !== '') {
            $resumen = $this->repo->getResumenHastaFecha($codigo, $lote, $alma, $fecha);
            if ($resumen) {
                $result['res_cantidad'] = (float)($resumen['cantidad'] ?? 0);
                $result['res_peso'] = (float)($resumen['peso'] ?? 0);
                $result['res_valor'] = (float)($resumen['valor'] ?? 0);
            }
        }

        return $result;
    }

    public function getStockAlmacen(string $alma): array {
        return $this->repo->getStockPorAlmacen($alma);
    }

    // ─── SALIDAS RÁPIDAS ─────────────────────────────────────────────────────

    public function getLineas(): array {
        return $this->repo->getLineas();
    }

    public function getProductosStockSalida(string $alma, string $linea = ''): array {
        return $this->repo->getProductosStockSalida($alma, $linea);
    }

    public function buscarProductosStockSalida(string $alma, string $termino, string $linea = ''): array {
        if ($termino === '') {
            return $this->repo->getProductosStockSalida($alma, $linea);
        }
        return $this->repo->buscarProductosStockSalida($alma, $termino, $linea);
    }

    public function getMisSalidas(array $filtros): array {
        $fecha   = isset($filtros['fecha']) && $filtros['fecha'] ? $filtros['fecha'] : date('Y-m-d');
        $usuario = isset($filtros['usuario']) ? trim($filtros['usuario']) : '';
        $rows = $this->repo->getMisSalidasUsuario($fecha, $usuario);
        return ['rows' => $rows, 'total' => count($rows), 'fecha' => $fecha];
    }
}

