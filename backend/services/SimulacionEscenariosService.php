<?php
/**
 * SimulacionEscenariosService
 * 
 * Servicio con lógica de negocio para simulación de escenarios de carga
 * Implementa las fórmulas de cálculo de la Hoja 6.Calculo del Excel
 */

require_once __DIR__ . '/../repositories/SimulacionEscenariosRepository.php';

class SimulacionEscenariosService {

    private $repository;
    
    // Mortalidad estándar según el Excel (~3.61% promedio)
    const MORTALIDAD_DEFAULT = 0.0361;

    public function __construct($db) {
        $this->repository = new SimulacionEscenariosRepository($db);
    }

    // ─────────────────────────────────────────────
    // LISTAR escenarios de una proyección
    // ─────────────────────────────────────────────
    public function listarEscenarios(string $proyeccion): array {
        try {
            error_log("Service: Iniciando listarEscenarios para proyeccion: " . $proyeccion);
            $escenarios = $this->repository->listarPorProyeccion($proyeccion);
            error_log("Service: Escenarios obtenidos: " . count($escenarios));
            
            return [
                'success' => true,
                'data' => $escenarios
            ];
        } catch (Exception $e) {
            error_log("Service: Error en listarEscenarios - " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Error al listar escenarios: ' . $e->getMessage()
            ];
        }
    }

    // ─────────────────────────────────────────────
    // CALCULAR todos los indicadores de un escenario
    // ─────────────────────────────────────────────
    public function calcular(array $escenario): array {
        $g2640 = (int)   ($escenario['n_galpones_2640'] ?? 0);
        $g1800 = (int)   ($escenario['n_galpones_1800'] ?? 0);
        $pollosGalpon    = (int)   ($escenario['pollos_por_galpon'] ?? 0);
        $cargasSemanal   = (float) ($escenario['cargas_semanales'] ?? 0);
        $tpoLimpieza     = (float) ($escenario['tpo_limpieza_dias'] ?? 0);
        $zonas           = $escenario['zonas'] ?? [];

        // 1. Totales de infraestructura
        $totalGalpones = $g2640 + $g1800;
        $areaTotal     = ($g2640 * 2640) + ($g1800 * 1800);
        $galpStd2640   = $areaTotal > 0 ? (int) round($areaTotal / 2640) : 0;

        // 2. Producción semanal
        // Fórmula simplificada: pollos_por_galpon × cargas_semanales
        $pollosSemana  = $cargasSemanal > 0 ? (int) round($pollosGalpon * $cargasSemanal) : 0;
        $ofertaSemana  = (int) round($pollosSemana * (1 - self::MORTALIDAD_DEFAULT));

        // 3. Cargas diario (redondeado a 1 decimal)
        $cargasDiario  = round($cargasSemanal / 7, 1);

        // 4. Tiempo de crianza ponderado por zona
        $tpoCrianzaPonderado = $this->calcularCrianzaPonderada($zonas);

        // 5. Ciclo de crianza y descansos
        // Fórmula: ciclo = (galpones_std / cargas_semanales) × 7
        $tpoCicloCrianza    = ($galpStd2640 > 0 && $cargasSemanal > 0)
            ? round(($galpStd2640 / $cargasSemanal) * 7, 1)
            : 0.0;
            
        $tpoDescansoTotal   = (int) round($tpoCicloCrianza - $tpoCrianzaPonderado);
        $tpoDescansoEfectivo = (int) round($tpoDescansoTotal - $tpoLimpieza);

        return [
            'total_galpones'        => $totalGalpones,
            'area_total_m2'         => $areaTotal,
            'galp_std_2640'         => $galpStd2640,
            'pollos_semana'         => $pollosSemana,
            'oferta_pollos_semana'  => $ofertaSemana,
            'cargas_diario'         => $cargasDiario,
            'tpo_ciclo_crianza'     => $tpoCicloCrianza,
            'tpo_crianza_ponderado' => round($tpoCrianzaPonderado, 1),
            'tpo_descanso_total'    => $tpoDescansoTotal,
            'tpo_descanso_efectivo' => $tpoDescansoEfectivo,
        ];
    }

    // ─────────────────────────────────────────────
    // Crianza ponderada = Σ(dias_zona × %_zona)
    // ─────────────────────────────────────────────
    private function calcularCrianzaPonderada(array $zonas): float {
        $ponderado = 0.0;
        foreach ($zonas as $z) {
            $dias = (float)($z['tpo_crianza_dias'] ?? 0);
            $porcentaje = (float)($z['porcentaje_zona'] ?? 0);
            $ponderado += $dias * ($porcentaje / 100);
        }
        return $ponderado;
    }

    // ─────────────────────────────────────────────
    // GUARDAR escenario y auto-calcular resultado
    // ─────────────────────────────────────────────
    public function guardarEscenario(array $data, string $usuario): array {
        try {
            // Validaciones
            if (empty($data['proyeccion']) || empty($data['cod_escenario'])) {
                return [
                    'success' => false,
                    'message' => 'proyeccion y cod_escenario son requeridos'
                ];
            }

            // Guardar escenario
            $result = $this->repository->guardar($data, $usuario);

            if (!$result['success']) {
                return $result;
            }

            // Auto-calcular al guardar
            $escenario = $this->repository->obtenerPorId($result['id']);
            if ($escenario) {
                $calculado = $this->calcular($escenario);
                $this->repository->guardarResultado($result['id'], $calculado, $usuario);
                
                return [
                    'success' => true,
                    'id' => $result['id'],
                    'resultado' => $calculado
                ];
            }

            return $result;

        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Error al guardar escenario: ' . $e->getMessage()
            ];
        }
    }

    // ─────────────────────────────────────────────
    // CALCULAR y guardar resultado de un escenario
    // ─────────────────────────────────────────────
    public function calcularYGuardar(int $idEscenario, string $usuario): array {
        try {
            $escenario = $this->repository->obtenerPorId($idEscenario);
            
            if (!$escenario) {
                return [
                    'success' => false,
                    'message' => 'Escenario no encontrado'
                ];
            }

            $resultado = $this->calcular($escenario);
            $this->repository->guardarResultado($idEscenario, $resultado, $usuario);

            return [
                'success' => true,
                'escenario' => $escenario['cod_escenario'],
                'resultado' => $resultado
            ];

        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Error al calcular escenario: ' . $e->getMessage()
            ];
        }
    }

    // ─────────────────────────────────────────────
    // ELIMINAR escenario
    // ─────────────────────────────────────────────
    public function eliminarEscenario(int $id, string $usuario): array {
        try {
            $ok = $this->repository->eliminar($id, $usuario);
            
            return [
                'success' => $ok,
                'message' => $ok ? 'Escenario eliminado correctamente' : 'No se pudo eliminar el escenario'
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Error al eliminar escenario: ' . $e->getMessage()
            ];
        }
    }

    // ─────────────────────────────────────────────
    // OBTENER escenario por ID
    // ─────────────────────────────────────────────
    public function obtenerEscenario(int $id): array {
        try {
            $escenario = $this->repository->obtenerPorId($id);
            
            if (!$escenario) {
                return [
                    'success' => false,
                    'message' => 'Escenario no encontrado'
                ];
            }

            return [
                'success' => true,
                'data' => $escenario
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Error al obtener escenario: ' . $e->getMessage()
            ];
        }
    }
}
