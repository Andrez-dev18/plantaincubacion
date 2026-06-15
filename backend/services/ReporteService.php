<?php

require_once __DIR__ . '/../repositories/ReporteRepository.php';

class ReporteService {
    private $repository;

    public function __construct($db) {
        $this->repository = new ReporteRepository($db);
    }

    /**
     * Genera estructura pivoteada para Excel
     */
    public function generarDatosPivotados($proyeccion, $limit = 500) {
        // 1. Obtener granjas únicas
        $granjas = $this->repository->obtenerGranjasUnicas($proyeccion);
        
        // 2. Obtener datos por semana (limitado a 500 registros para optimizar)
        $datos = $this->repository->obtenerDatosPorSemana($proyeccion, $limit);
        
        // 3. Crear estructura pivotada
        $reportePivot = [];
        $totalPorGranja = [];
        
        foreach ($datos as $row) {
            $key = $row['semana'] . '-' . $row['anio'];
            $granjaKey = $row['granja_completa'];
            
            // Inicializar semana si no existe
            if (!isset($reportePivot[$key])) {
                $reportePivot[$key] = [
                    'semana' => $row['semana'],
                    'anio' => $row['anio'],
                    'mes' => $this->obtenerNombreMes($row['mes']),
                    'n_cart' => $row['viajessem'],
                    'pollos_semana' => $row['pollossem'],
                    'feclima' => $this->formatearFechaExcel($row['feclima'] ?? null),
                    'fecaqp' => $this->formatearFechaExcel($row['fecaqp'] ?? null),
                    'granjas' => [],
                    'fechas' => [],
                    'detalles' => []  // Nuevo: detalles completos por granja
                ];
            }
            
            // Agregar datos de la granja
            if (!isset($reportePivot[$key]['granjas'][$granjaKey])) {
                $reportePivot[$key]['granjas'][$granjaKey] = 0;
            }
            $reportePivot[$key]['granjas'][$granjaKey] += $row['pollos'];
            
            // Agregar fecha de liquidación
            $reportePivot[$key]['fechas'][$granjaKey] = $this->formatearFecha($row['fecliqui']);
            
            // Nuevo: agregar detalles completos (código, galpón, campaña)
            $reportePivot[$key]['detalles'][$granjaKey] = [
                'codigo' => $row['codigo'],
                'galpon' => $row['galpon'],
                'campana' => $row['campana'] ?? '',
                'nombre' => $row['nombre']
            ];
            
            // Acumular totales por granja
            if (!isset($totalPorGranja[$granjaKey])) {
                $totalPorGranja[$granjaKey] = 0;
            }
            $totalPorGranja[$granjaKey] += $row['pollos'];
        }
        
        return [
            'granjas' => $granjas,
            'datos' => $reportePivot,
            'totales' => $totalPorGranja
        ];
    }

    /**
     * Genera resumen de oferta/demanda
     */
    public function generarResumenOfertaDemanda($proyeccion) {
        return $this->repository->obtenerResumenSemanal($proyeccion);
    }

    /**
     * Lista proyecciones disponibles
     */
    public function listarProyecciones() {
        return $this->repository->obtenerProyecciones();
    }

    /**
     * Valida si existe una proyección
     */
    public function validarProyeccion($proyeccion) {
        $proyecciones = $this->listarProyecciones();
        $nombres = array_map(function ($item) {
            if (is_array($item)) {
                return $item['nombre'] ?? null;
            }
            return $item;
        }, $proyecciones);

        return in_array($proyeccion, $nombres, true);
    }

    /**
     * Formatea fecha a formato legible
     */
    private function formatearFecha($fecha) {
        if (empty($fecha) || $fecha == '0000-00-00') {
            return '';
        }
        
        $timestamp = strtotime($fecha);
        $meses = ['ene', 'feb', 'mar', 'abr', 'may', 'jun', 
                  'jul', 'ago', 'sep', 'oct', 'nov', 'dic'];
        $dias = ['dom', 'lun', 'mar', 'mié', 'jue', 'vie', 'sáb'];
        
        $dia = date('d', $timestamp);
        $mes = $meses[date('n', $timestamp) - 1];
        $diaSemana = $dias[date('w', $timestamp)];
        
        return "$dia-$mes, $diaSemana";
    }

    /**
     * Formatea fecha para Excel (formato corto)
     */
    private function formatearFechaExcel($fecha) {
        if (empty($fecha) || $fecha == '0000-00-00') {
            return '';
        }
        
        $timestamp = strtotime($fecha);
        return date('d-m-Y', $timestamp);
    }

    /**
     * Obtiene nombre del mes
     */
    private function obtenerNombreMes($numeroMes) {
        $meses = ['', 'enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio',
                  'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'];
        return $meses[$numeroMes] ?? '';
    }

    /**
     * Calcula estadísticas del reporte
     */
    public function calcularEstadisticas($proyeccion) {
        $datos = $this->repository->obtenerDatosPorSemana($proyeccion);
        
        $totalPollos = 0;
        $totalSemanas = 0;
        $granjasActivas = [];
        
        foreach ($datos as $row) {
            $totalPollos += $row['pollos'];
            $granjasActivas[$row['codigo']] = true;
        }
        
        $semanas = array_unique(array_column($datos, 'semana'));
        $totalSemanas = count($semanas);
        
        return [
            'total_pollos' => $totalPollos,
            'total_semanas' => $totalSemanas,
            'total_granjas' => count($granjasActivas),
            'promedio_semana' => $totalSemanas > 0 ? round($totalPollos / $totalSemanas, 2) : 0
        ];
    }

    /**
     * Obtiene datos del calendario con paginación (server-side DataTables)
     */
    public function obtenerCalendarioPaginado($proyeccion, $start, $length, $search, $orderColumn, $orderDir) {
        // Obtener total de registros
        $total = $this->repository->contarRegistrosCalendario($proyeccion);
        
        // Obtener registros filtrados
        $filtered = $total;
        if (!empty($search)) {
            $filtered = $this->repository->contarRegistrosCalendarioFiltrados($proyeccion, $search);
        }
        
        // Obtener datos paginados
        $datos = $this->repository->obtenerCalendarioPaginado(
            $proyeccion, 
            $start, 
            $length, 
            $search, 
            $orderColumn, 
            $orderDir
        );
        
        // Formatear datos para DataTables
        $formattedData = [];
        foreach ($datos as $row) {
            $formattedData[] = [
                $row['semana'],
                $this->formatearFechaCorta($row['fecaqp']),
                $row['num_cargas'] ?? 1,
                $row['nombre'],
                $row['galpon'],
                number_format($row['pollos'], 0, ',', '.')
            ];
        }
        
        return [
            'total' => $total,
            'filtered' => $filtered,
            'data' => $formattedData
        ];
    }

    /**
     * Obtiene datos del resumen con paginación (server-side DataTables)
     */
    public function obtenerResumenPaginado($proyeccion, $start, $length, $search, $orderColumn, $orderDir) {
        // Obtener total de registros
        $total = $this->repository->contarRegistrosResumen($proyeccion);
        
        // Obtener registros filtrados
        $filtered = $total;
        if (!empty($search)) {
            $filtered = $this->repository->contarRegistrosResumenFiltrados($proyeccion, $search);
        }
        
        // Obtener datos paginados
        $datos = $this->repository->obtenerResumenPaginado(
            $proyeccion, 
            $start, 
            $length, 
            $search, 
            $orderColumn, 
            $orderDir
        );
        
        // Formatear datos para DataTables
        $formattedData = [];
        foreach ($datos as $row) {
            $diferencia = $row['diferencia'];
            $formattedData[] = [
                $row['anuo'],
                $row['sem'],
                number_format($row['oferta'], 0, ',', '.'),
                number_format($row['demanda'], 0, ',', '.'),
                number_format($diferencia, 0, ',', '.')
            ];
        }
        
        return [
            'total' => $total,
            'filtered' => $filtered,
            'data' => $formattedData
        ];
    }

    /**
     * Formatea fecha a formato corto (dd-mmm, día)
     */
    private function formatearFechaCorta($fecha) {
        if (empty($fecha) || $fecha == '0000-00-00') {
            return '';
        }
        
        $timestamp = strtotime($fecha);
        $meses = ['ene', 'feb', 'mar', 'abr', 'may', 'jun', 
                  'jul', 'ago', 'sep', 'oct', 'nov', 'dic'];
        $dias = ['dom', 'lun', 'mar', 'mié', 'jue', 'vie', 'sáb'];
        
        $dia = date('d', $timestamp);
        $mes = $meses[date('n', $timestamp) - 1];
        $diaSemana = $dias[date('w', $timestamp)];
        
        return "$dia-$mes, $diaSemana";
    }

    /**
     * Obtiene datos de fechaproy (calendario de fechas y cargas)
     */
    public function obtenerFechaProy($proyeccion) {
        return $this->repository->obtenerFechaProy($proyeccion);
    }

    /**
     * Actualiza cargas de una fecha específica
     */
    public function actualizarCargas($proyeccion, $fecha, $cargas) {
        return $this->repository->actualizarCargas($proyeccion, $fecha, $cargas);
    }

    /**
     * Recalcula el calendario completo basado en secuencia y cargas
     * Replica la lógica del VB6 CmdRecalcular_Click
     */
    public function recalcularCalendario($proyeccion, $fechaDesde = null, $fechaHasta = null) {
        return $this->repository->recalcularCalendario($proyeccion, $fechaDesde, $fechaHasta);
    }

    /**
     * Importa calendario completo (elimina el anterior y lo reemplaza)
     */
    public function importarCalendario($proyeccion, $registros) {
        return $this->repository->importarCalendario($proyeccion, $registros);
    }

    /**
     * Obtiene los datos de la tabla ccoscargapollo en formato para el detalle
     */
    public function obtenerDatosTablaDetalle($proyeccion) {
        return $this->repository->obtenerDatosDetalleTabla($proyeccion);
    }

    /**
     * Obtiene datos agrupados por semana para PDF con totales
     */
    public function obtenerDatosPorSemanaParaPDF($proyeccion) {
        return $this->repository->obtenerDatosPorSemanaParaPDF($proyeccion);
    }
}

