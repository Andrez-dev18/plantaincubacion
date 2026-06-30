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

        // 0. PRE-CARGAR PRODUCTOS DESDE LOS SALDOS INICIALES
        foreach ($saldosIniciales as $codigo => $saldo) {
            $kardexAgrupado[$codigo] = [
                'codigo'      => $codigo,
                'descripcion' => $saldo['item_descri'] ?? 'SIN DESCRIPCION',
                'lote'        => '00000000',
                'movimientos' => []
            ];
        }

        // 1. Agrupar movimientos por producto
        foreach ($movimientosCrudos as $row) {
            $codigo = $row['tcodigo'];
            if (!isset($kardexAgrupado[$codigo])) {
                $kardexAgrupado[$codigo] = [
                    'codigo'      => $codigo,
                    'descripcion' => $row['item_descri'],
                    'lote'        => $row['tlote'] ?? '00000000',
                    'movimientos' => []
                ];
            } else {
                // Si ya existía, actualizamos el lote por si acaso viene en los movimientos
                $kardexAgrupado[$codigo]['lote'] = $row['tlote'] ?? $kardexAgrupado[$codigo]['lote'];
            }
            $kardexAgrupado[$codigo]['movimientos'][] = $row;
        }

        $resultadoFinal = [];

        // 2. Procesar las sumas matemáticas (Running Totales)
        foreach ($kardexAgrupado as $codigo => $dataProducto) {
            $saldoData = $saldosIniciales[$codigo] ?? null;

            $stockFisico = 0;
            $stockValor = 0;
            $stockPeso = 0;

            if ($saldoData) {
                $stockFisico = round((float)$saldoData['cant_dia_cero'] + (float)$saldoData['mov_cant_hist'], 4);
                $stockValor  = round((float)$saldoData['val_dia_cero'] + (float)$saldoData['mov_val_hist'], 4);
                $stockPeso   = round((float)$saldoData['peso_dia_cero'] + (float)$saldoData['mov_peso_hist'], 4);
            }

            $puInicial = ($stockFisico != 0) ? round($stockValor / $stockFisico, 4) : 0;

            $productoFinal = [
                'codigo' => $codigo,
                'descripcion' => $dataProducto['descripcion'],
                'lote' => $dataProducto['lote'],
                'saldo_inicial' => [
                    'cant' => $stockFisico,
                    'val'  => $stockValor,
                    'peso' => $stockPeso,
                    'pu'   => $puInicial
                ],
                'detalle' => []
            ];

            // Iterar movimientos
            foreach ($dataProducto['movimientos'] as $mov) {
                $esEntrada = (strtoupper(substr($mov['tcodtra'], 0, 1)) === 'E');

                $cant = (float)$mov['tcantid'];
                $val = (float)$mov['tkardex'];
                $peso = (float)$mov['tpeso'];

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
                    // LEEMOS LOS ALIAS EXACTOS QUE CREAMOS EN EL SQL
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
