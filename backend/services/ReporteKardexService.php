<?php

require_once __DIR__ . '/../repositories/ReporteKardexRepository.php';

class ReporteKardexService
{
    private $repository;

    public function __construct($db)
    {
        $this->repository = new ReporteKardexRepository($db);
    }

    //FUNCIONES PARA FILTROS DEL REPORTE KARDEX
    public function listarAlmacenes()
    {
        return $this->repository->obtenerAlmacenesUnicos();
    }

    public function listarLineas()
    {
        return $this->repository->obtenerLineasUnicas();
    }

    public function listarArticulos()
    {
        return $this->repository->obtenerArticulosUnicos();
    }


    //FUNCION PARA REPORTE KARDEX
    public function generarKardex($filtros)
    {
        $saldosIniciales = $this->repository->obtenerSaldosIniciales($filtros);
        $movimientosCrudos = $this->repository->obtenerMovimientosRango($filtros);

        $kardexAgrupado = [];

        // 0. PRE-CARGAR LOTES DESDE LOS SALDOS INICIALES
        foreach ($saldosIniciales as $llave => $saldo) {
            $kardexAgrupado[$llave] = [
                'codigo'      => $saldo['codigo'],
                'descripcion' => $saldo['item_descri'] ?? 'SIN DESCRIPCION',
                'lote'        => !empty($saldo['lote']) ? $saldo['lote'] : '00000000',
                'movimientos' => []
            ];
        }

        // 1. Agrupar movimientos en sus respectivos lotes
        foreach ($movimientosCrudos as $row) {
            $codigo = $row['tcodigo'];
            $lote = !empty($row['tlote']) ? $row['tlote'] : '00000000';
            $llave = $codigo . '|' . $lote;

            if (!isset($kardexAgrupado[$llave])) {
                $kardexAgrupado[$llave] = [
                    'codigo'      => $codigo,
                    'descripcion' => $row['item_descri'] ?? 'SIN DESCRIPCION',
                    'lote'        => $lote,
                    'movimientos' => []
                ];
            }
            $kardexAgrupado[$llave]['movimientos'][] = $row;
        }

        $resultadoFinal = [];

        // 2. Procesar las sumas matemáticas
        foreach ($kardexAgrupado as $llave => $dataProducto) {
            $stockFisico = 0; // = tpeso  (lo que FoxPro muestra como "unidades")
            $stockValor  = 0; // = tkardex
            $stockPeso   = 0; // = tcantid (peso real en kg/sacos según tu sistema)

            $saldoData = $saldosIniciales[$llave] ?? null;

            if ($saldoData) {
                // cant_dia_cero = piniano = tpeso acumulado (unidades FoxPro)
                // peso_dia_cero = qiniano = tcantid acumulado
                // Los mov_cant_hist/mov_peso_hist también están intercambiados en el repo
                $stockFisico = round((float)$saldoData['cant_dia_cero'] + (float)$saldoData['mov_cant_hist'], 4);
                $stockValor  = round((float)$saldoData['val_dia_cero']  + (float)$saldoData['mov_val_hist'],  4);
                $stockPeso   = round((float)$saldoData['peso_dia_cero'] + (float)$saldoData['mov_peso_hist'], 4);
            }

            $puInicial = ($stockFisico != 0) ? round($stockValor / $stockFisico, 4) : 0;

            $productoFinal = [
                'codigo'      => $dataProducto['codigo'],
                'descripcion' => $dataProducto['descripcion'],
                'lote'        => $dataProducto['lote'],
                'saldo_inicial' => [
                    'cant' => $stockFisico,
                    'val'  => $stockValor,
                    'peso' => $stockPeso,
                    'pu'   => $puInicial
                ],
                'detalle' => []
            ];

            // 3. Iterar movimientos del lote
            foreach ($dataProducto['movimientos'] as $mov) {
                $esEntrada = (strtoupper(substr($mov['tcodtra'], 0, 1)) === 'E');

                // mov_cant = tpeso  (unidades FoxPro)
                // mov_peso = tcantid (peso real)
                $cant  = (float)$mov['mov_cant'];
                $val   = (float)$mov['tkardex'];
                $peso  = (float)$mov['mov_peso'];

                if ($esEntrada) {
                    $stockFisico += $cant;
                    $stockValor  += $val;
                    $stockPeso   += $peso;
                } else {
                    $stockFisico -= $cant;
                    $stockValor  -= $val;
                    $stockPeso   -= $peso;
                }

                $puActual = ($stockFisico != 0) ? round($stockValor / $stockFisico, 4) : 0;

                $productoFinal['detalle'][] = [
                    'fecha'    => $mov['tfectra'],
                    'codtra'   => $mov['tcodtra'],
                    'tnumfac'  => $mov['tnumfac'],
                    'docref'   => $mov['codcen'] ?? '',
                    'nomref'   => $mov['NOM'] ?? '',
                    'descri'   => $mov['tra_descri'],
                    'ent_cant' => $esEntrada ? $cant : 0,
                    'sal_cant' => !$esEntrada ? $cant : 0,
                    'sto_cant' => round($stockFisico, 4),
                    'ent_val'  => $esEntrada ? $val : 0,
                    'sal_val'  => !$esEntrada ? $val : 0,
                    'sto_val'  => round($stockValor, 4),
                    'ent_peso' => $esEntrada ? $peso : 0,
                    'sal_peso' => !$esEntrada ? $peso : 0,
                    'sto_peso' => round($stockPeso, 4),
                    'pu'       => $puActual
                ];
            }

            $resultadoFinal[] = $productoFinal;
        }

        return $resultadoFinal;
    }
}
