<?php

require_once __DIR__ . '/../repositories/ReporteStockRepository.php';

class ReporteStockService
{
    private $repository;

    public function __construct($db)
    {
        $this->repository = new ReporteStockRepository($db);
    }

    //FUNCIONES PARA FILTROS DEL REPORTE STOCK
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


    //FUNCION PARA REPORTE STOCK
    public function procesarReporteGrid($filtros)
    {
        $resultadosRaw = $this->repository->obtenerReporteStock($filtros);
        $dataProcesada = [];

        // Capturamos el formato solicitado (Por defecto UNIDADES como indicaste)
        $formato = $filtros['formato'] ?? 'UNIDADES';

        foreach ($resultadosRaw as $row) {
            // A. Cálculos Base Matemáticos (Unidades y Valores)
            $inicioUnidades = round((float)$row['stock_dia_cero'] + (float)$row['historia_unidades'], 4);
            $inicioValor    = round((float)$row['valor_dia_cero'] + (float)$row['historia_valor'], 4);

            $entradaUnidades = round((float)$row['entrada_unidades'], 4);
            $entradaValor    = round((float)$row['entrada_valor'], 4);
            $salidaUnidades  = round((float)$row['salida_unidades'], 4);
            $salidaValor     = round((float)$row['salida_valor'], 4);

            // Como ya redondeamos arriba, el stock final será un 0 absoluto
            $stockUnidades = round($inicioUnidades + $entradaUnidades - $salidaUnidades, 4);
            $stockValor    = round($inicioValor + $entradaValor - $salidaValor, 4);

            $precioPromedio = ($stockUnidades != 0) ? ($stockValor / $stockUnidades) : 0;

            // ── NUEVO: Capturamos el peso base del artículo ──
            $pesoArticulo = (float)$row['peso_dia_cero'];

            // Si hay peso, calculamos. Si no hay, devolvemos vacío para imitar a FoxPro
            $inicioP  = $pesoArticulo > 0 ? round($inicioUnidades * $pesoArticulo, 4) : '';
            $entradaP = $pesoArticulo > 0 ? round($entradaUnidades * $pesoArticulo, 4) : '';
            $salidaP  = $pesoArticulo > 0 ? round($salidaUnidades * $pesoArticulo, 4) : '';
            $stockP   = $pesoArticulo > 0 ? round($stockUnidades * $pesoArticulo, 4) : '';

            // FoxPro tampoco pinta el Promedio si es la vista de Peso sin datos
            $promedio = ($filtros['formato'] === 'PESO' && $pesoArticulo == 0) ? '' : $precioPromedio;

            $dataProcesada[] = [
                'codigo'          => $row['codigo'],
                'descripcion'     => $row['descripcion'],
                'lote'            => $row['lote'] ?? '00000000',
                'alma_codigo'     => $row['alma_codigo'] ?: '000',
                'alma_descri'     => $row['alma_descri'] ?: 'SIN ALMACÉN',
                'linea_codigo'    => $row['linea_codigo'],
                'linea_descri'    => $row['linea_descri'],
                'cuenta_codigo'   => $row['cuenta_codigo'],
                'cuenta_descri'   => $row['cuenta_descri'],

                'inicio_u'        => $inicioUnidades,
                'entrada_u'       => $entradaUnidades,
                'salida_u'        => $salidaUnidades,
                'stock_u'         => $stockUnidades,

                'inicio_v'        => $inicioValor,
                'entrada_v'       => $entradaValor,
                'salida_v'        => $salidaValor,
                'stock_v'         => $stockValor,

                // ── NUEVO: Pesos con vacíos controlados ──
                'inicio_p'        => $inicioP,
                'entrada_p'       => $entradaP,
                'salida_p'        => $salidaP,
                'stock_p'         => $stockP,

                'precio_promedio' => $promedio,
                'peso_articulo'   => $pesoArticulo
            ];
        }

        return $dataProcesada;
    }
}
