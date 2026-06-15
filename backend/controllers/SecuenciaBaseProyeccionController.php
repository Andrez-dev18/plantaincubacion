<?php

require_once __DIR__ . '/../services/SecuenciaBaseProyeccionService.php';
require_once __DIR__ . '/../repositories/ReporteRepository.php';

/**
 * Controlador de Secuencia Base Proyección
 * Maneja peticiones HTTP del módulo /api/secuencia/*
 * Completamente independiente del módulo de reportes
 */
class SecuenciaBaseProyeccionController {
    private $service;
    private $db;

    public function __construct($db) {
        $this->service = new SecuenciaBaseProyeccionService($db);
        $this->db = $db;
    }

    /**
     * GET /api/secuencia/proyecciones
     * Lista todas las proyecciones disponibles
     */
    public function listarProyecciones() {
        try {
            $proyecciones = $this->service->listarProyecciones();
            
            $this->jsonResponse([
                'success' => true,
                'data' => $proyecciones
            ]);
        } catch (Exception $e) {
            $this->jsonResponse([
                'success' => false,
                'message' => 'Error al obtener proyecciones: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * GET /api/secuencia/base?proyeccion=XXXX&page=1&limit=200
     * Obtiene datos de ccosbase con paginación
     */
    public function obtenerDatosBase() {
        try {
            $proyeccion = $_GET['proyeccion'] ?? null;
            $page = (int)($_GET['page'] ?? 1);
            $limit = (int)($_GET['limit'] ?? 200);
            
            if (!$proyeccion) {
                $this->jsonResponse([
                    'success' => false,
                    'message' => 'Parámetro proyeccion es requerido'
                ], 400);
                return;
            }

            $datos = $this->service->obtenerDatosBase($proyeccion, $page, $limit);
            $total = $this->service->contarDatosBase($proyeccion);
            
            $totalPages = ceil($total / $limit);
            $hasNext = $page < $totalPages;
            
            $this->jsonResponse([
                'success' => true,
                'data' => $datos,
                'pagination' => [
                    'page' => $page,
                    'limit' => $limit,
                    'total' => $total,
                    'total_pages' => $totalPages,
                    'has_next' => $hasNext
                ]
            ]);
        } catch (Exception $e) {
            $this->jsonResponse([
                'success' => false,
                'message' => 'Error al obtener datos base: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * GET /api/secuencia/proyeccion?proyeccion=XXXX&page=1&limit=200
     * Obtiene datos de ccosproy (secuencia generada)
     */
    public function obtenerDatosProyeccion() {
        try {
            $proyeccion = $_GET['proyeccion'] ?? null;
            $page = (int)($_GET['page'] ?? 1);
            $limit = (int)($_GET['limit'] ?? 200);
            
            if (!$proyeccion) {
                $this->jsonResponse([
                    'success' => false,
                    'message' => 'Parámetro proyeccion es requerido'
                ], 400);
                return;
            }

            $datos = $this->service->obtenerDatosProyeccion($proyeccion, $page, $limit);
            $total = $this->service->contarDatosProyeccion($proyeccion);
            
            $totalPages = ceil($total / $limit);
            $hasNext = $page < $totalPages;
            
            $this->jsonResponse([
                'success' => true,
                'data' => $datos,
                'pagination' => [
                    'page' => $page,
                    'limit' => $limit,
                    'total' => $total,
                    'total_pages' => $totalPages,
                    'has_next' => $hasNext
                ]
            ]);
        } catch (Exception $e) {
            $this->jsonResponse([
                'success' => false,
                'message' => 'Error al obtener datos proyección: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * POST /api/secuencia/copiar-secuencia
     * Copia la secuencia de otra proyección
     */
    public function copiarSecuencia() {
        try {
            $data = json_decode(file_get_contents('php://input'), true);
            $resultado = $this->service->copiarSecuencia(
                $data['proyeccion_origen'] ?? null,
                $data['proyeccion_destino'] ?? null
            );
            
            $mensaje = sprintf(
                'Secuencia copiada correctamente: %d registros copiados',
                $resultado['filas_copiadas'] ?? 0
            );
            
            $this->jsonResponse([
                'success' => true,
                'message' => $mensaje,
                'data' => $resultado
            ]);
        } catch (Exception $e) {
            $this->jsonResponse([
                'success' => false,
                'message' => 'Error al copiar secuencia: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * POST /api/secuencia/crear-secuencia
     * Crea la secuencia base (ETAPA 1)
     */
    public function crearSecuencia() {
        try {
            $data = json_decode(file_get_contents('php://input'), true);
            $proyeccion = $data['proyeccion'] ?? null;
            $hastaCiclo = (int)($data['hasta_ciclo'] ?? $data['hastaCiclo'] ?? 13);
            
            if (!$proyeccion) {
                $this->jsonResponse([
                    'success' => false,
                    'message' => 'Parámetro proyeccion es requerido'
                ], 400);
                return;
            }

            $resultado = $this->service->crearSecuencia($proyeccion, $hastaCiclo);
            
            $this->jsonResponse([
                'success' => true,
                'message' => 'Secuencia creada correctamente',
                'registros_creados' => $resultado,
                'data' => $resultado
            ]);
        } catch (Exception $e) {
            $this->jsonResponse([
                'success' => false,
                'message' => 'Error al crear secuencia: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * POST /api/secuencia/crear-calendario
     * Crea el calendario (ETAPA 2)
     */
    public function crearCalendario() {
        try {
            $data = json_decode(file_get_contents('php://input'), true);
            $proyeccion = $data['proyeccion'] ?? null;
            
            if (!$proyeccion) {
                $this->jsonResponse([
                    'success' => false,
                    'message' => 'Parámetro proyeccion es requerido'
                ], 400);
                return;
            }

            $resultado = $this->service->crearCalendario($proyeccion);

            // NOTA: Ya no llamamos a recalcularCalendario() aquí porque crearCalendario()
            // en el repositorio ahora usa generarCargaPolloPorDia() que es mucho más eficiente.
            // recalcularCalendario() solo debe usarse cuando se modifican fechas/cargas manualmente.
            
            $this->jsonResponse([
                'success' => true,
                'message' => 'Calendario creado correctamente',
                'registros_creados' => $resultado,
                'data' => $resultado
            ]);
        } catch (Exception $e) {
            $this->jsonResponse([
                'success' => false,
                'message' => 'Error al crear calendario: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * POST /api/secuencia/copiar-calendario
     * Copia el calendario de otra proyección
     */
    public function copiarCalendario() {
        try {
            $data = json_decode(file_get_contents('php://input'), true);
            $resultado = $this->service->copiarCalendario(
                $data['proyeccion_origen'] ?? null,
                $data['proyeccion_destino'] ?? null
            );
            
            $mensaje = sprintf(
                'Calendario copiado correctamente: %d fechas, %d semanas, %d cargas',
                $resultado['fechas_copiadas'] ?? 0,
                $resultado['semanas_copiadas'] ?? 0,
                $resultado['cargas_copiadas'] ?? 0
            );
            
            $this->jsonResponse([
                'success' => true,
                'message' => $mensaje,
                'data' => $resultado
            ]);
        } catch (Exception $e) {
            $this->jsonResponse([
                'success' => false,
                'message' => 'Error al copiar calendario: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * POST /api/secuencia/guardar-base
     * Guarda un registro de ccosbase
     */
    public function guardarBase() {
        try {
            $data = json_decode(file_get_contents('php://input'), true);
            $resultado = $this->service->guardarBase($data);
            
            $this->jsonResponse([
                'success' => true,
                'message' => 'Registro guardado correctamente',
                'data' => $resultado
            ]);
        } catch (Exception $e) {
            $this->jsonResponse([
                'success' => false,
                'message' => 'Error al guardar registro: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * POST /api/secuencia/eliminar-base
     * Elimina un registro de ccosbase
     */
    public function eliminarBase() {
        try {
            $data = json_decode(file_get_contents('php://input'), true);
            $resultado = $this->service->eliminarBase(
                $data['proyeccion'] ?? null,
                $data['secuencia'] ?? null
            );
            
            $this->jsonResponse([
                'success' => true,
                'message' => 'Registro eliminado correctamente',
                'data' => $resultado
            ]);
        } catch (Exception $e) {
            $this->jsonResponse([
                'success' => false,
                'message' => 'Error al eliminar registro: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * POST /api/secuencia/mover-base
     * Mueve un registro de ccosbase arriba o abajo
     */
    public function moverBase() {
        try {
            $data = json_decode(file_get_contents('php://input'), true);
            $resultado = $this->service->moverBase(
                $data['proyeccion'] ?? null,
                $data['secuencia'] ?? null,
                $data['direccion'] ?? null
            );
            
            $this->jsonResponse([
                'success' => true,
                'message' => 'Registro movido correctamente',
                'data' => $resultado
            ]);
        } catch (Exception $e) {
            $this->jsonResponse([
                'success' => false,
                'message' => 'Error al mover registro: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * POST /api/secuencia/mover-secuencia-a
     * Mueve una secuencia a una posición específica (drag & drop)
     */
    public function moverSecuenciaA() {
        try {
            $data = json_decode(file_get_contents('php://input'), true);
            $resultado = $this->service->moverSecuenciaA(
                $data['proyeccion'] ?? null,
                $data['secuencia_origen'] ?? null,
                $data['nueva_posicion'] ?? null
            );
            
            $this->jsonResponse([
                'success' => true,
                'message' => 'Secuencia reordenada correctamente',
                'data' => $resultado
            ]);
        } catch (Exception $e) {
            $this->jsonResponse([
                'success' => false,
                'message' => 'Error al reordenar secuencia: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * POST /api/secuencia/mover-proyeccion
     * Mueve una entrada en ccosproy intercambiando posiciones (drag & drop en modal)
     */
    public function moverProyeccion() {
        try {
            $data = json_decode(file_get_contents('php://input'), true);
            $resultado = $this->service->moverProyeccion(
                $data['proyeccion'] ?? null,
                $data['secuencia_origen'] ?? null,
                $data['secuencia_destino'] ?? null,
                $data['posicion'] ?? 'despues'
            );
            
            $this->jsonResponse([
                'success' => true,
                'message' => 'Posición actualizada correctamente',
                'data' => $resultado
            ]);
        } catch (Exception $e) {
            $this->jsonResponse([
                'success' => false,
                'message' => 'Error al mover registro: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * POST /api/secuencia/nueva-proyeccion
     * Crea una nueva proyección
     * Body: { nombre, indicador }
     */
    public function nuevaProyeccion() {
        try {
            $data = json_decode(file_get_contents('php://input'), true);
            $nombre = $data['nombre'] ?? null;
            $indicador = $data['indicador'] ?? 'PENDIENTE';
            
            $resultado = $this->service->crearProyeccion($nombre, $indicador);
            
            $this->jsonResponse([
                'success' => true,
                'message' => 'Proyección creada correctamente',
                'data' => $resultado
            ]);
        } catch (Exception $e) {
            $this->jsonResponse([
                'success' => false,
                'message' => 'Error al crear proyección: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * POST /api/secuencia/editar-proyeccion
     * Edita una proyección existente
     * Body: { proyeccionActual, proyeccionNueva, indicador? } o { proyeccion, nuevoNombre, indicador? }
     */
    public function editarProyeccion() {
        try {
            $data = json_decode(file_get_contents('php://input'), true);
            
            // Soportar ambos formatos de parámetros para compatibilidad
            $proyeccionActual = $data['proyeccionActual'] ?? $data['proyeccion'] ?? null;
            $proyeccionNueva = $data['proyeccionNueva'] ?? $data['nuevoNombre'] ?? null;
            $indicador = $data['indicador'] ?? null; // Opcional
            
            if (!$proyeccionActual || !$proyeccionNueva) {
                $this->jsonResponse([
                    'success' => false,
                    'message' => 'Parámetros proyeccionActual y proyeccionNueva son requeridos'
                ], 400);
                return;
            }
            
            $resultado = $this->service->editarProyeccion($proyeccionActual, $proyeccionNueva, $indicador);
            
            $this->jsonResponse([
                'success' => true,
                'message' => 'Proyección actualizada correctamente',
                'data' => $resultado
            ]);
        } catch (Exception $e) {
            $this->jsonResponse([
                'success' => false,
                'message' => 'Error al editar proyección: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * POST /api/secuencia/eliminar-proyeccion
     * Elimina una proyección
     */
    public function eliminarProyeccion() {
        try {
            $data = json_decode(file_get_contents('php://input'), true);
            $resultado = $this->service->eliminarProyeccion($data['proyeccion'] ?? null);
            
            $this->jsonResponse([
                'success' => true,
                'message' => 'Proyección eliminada correctamente',
                'data' => $resultado
            ]);
        } catch (Exception $e) {
            $this->jsonResponse([
                'success' => false,
                'message' => 'Error al eliminar proyección: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * GET /api/secuencia/galpones
     * Obtiene lista de galpones disponibles
     */
    public function obtenerGalpones() {
        try {
            $galpones = $this->service->obtenerGalpones();
            
            $this->jsonResponse([
                'success' => true,
                'data' => $galpones
            ]);
        } catch (Exception $e) {
            $this->jsonResponse([
                'success' => false,
                'message' => 'Error al obtener galpones: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * GET /api/secuencia/ultimo-registro-granja-galpon
     * Obtiene último registro de una granja/galpón específica
     */
    public function obtenerUltimoRegistroGranjaGalpon() {
        try {
            $codigo = $_GET['codigo'] ?? null;
            $galpon = $_GET['galpon'] ?? null;
            
            if (!$codigo || !$galpon) {
                $this->jsonResponse([
                    'success' => false,
                    'message' => 'Parámetros codigo y galpon son requeridos'
                ], 400);
                return;
            }

            $registro = $this->service->obtenerUltimoRegistroGranjaGalpon($codigo, $galpon);
            
            $this->jsonResponse([
                'success' => true,
                'data' => $registro
            ]);
        } catch (Exception $e) {
            $this->jsonResponse([
                'success' => false,
                'message' => 'Error al obtener registro: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * GET /api/secuencia/calendario
     * Obtiene calendario de una proyección
     */
    public function obtenerCalendario() {
        try {
            $proyeccion = $_GET['proyeccion'] ?? null;
            $page = (int)($_GET['page'] ?? 1);
            $limit = (int)($_GET['limit'] ?? 0);
            
            if (!$proyeccion) {
                $this->jsonResponse([
                    'success' => false,
                    'message' => 'Parámetro proyeccion es requerido'
                ], 400);
                return;
            }

            // Si no se especifica limit o es 0, devolver todos los registros (comportamiento original)
            if ($limit === 0) {
                $calendario = $this->service->obtenerCalendario($proyeccion);
                $this->jsonResponse([
                    'success' => true,
                    'data' => $calendario
                ]);
            } else {
                // Paginado
                $resultado = $this->service->obtenerCalendarioPaginado($proyeccion, $page, $limit);
                $this->jsonResponse([
                    'success' => true,
                    'data' => $resultado['data'],
                    'pagination' => $resultado['pagination']
                ]);
            }
        } catch (Exception $e) {
            $this->jsonResponse([
                'success' => false,
                'message' => 'Error al obtener calendario: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Envía respuesta JSON al cliente
     */
    private function jsonResponse($data, $status = 200) {
        http_response_code($status);
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}
?>
