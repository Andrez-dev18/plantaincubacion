<?php
require_once __DIR__ . '/../repositories/PermisoRepository.php';
require_once __DIR__ . '/../repositories/DashboardModuloRepository.php';

class PermisoService {
    private $permisoRepo;
    private $moduloRepo;

    public function __construct($permisoRepository, $moduloRepository) {
        $this->permisoRepo = $permisoRepository;
        $this->moduloRepo = $moduloRepository;
    }

    /**
     * Obtener permisos de un rol
     */
    public function obtenerPorRol($idRol) {
        try {
            $permisos = $this->permisoRepo->obtenerPorRol($idRol);
            return [
                'success' => true,
                'data' => $permisos
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Error al obtener permisos: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Obtener módulos disponibles con permisos actuales del rol
     */
    public function obtenerModulosDisponibles($idRol) {
        try {
            $modulos = $this->permisoRepo->obtenerModulosDisponibles($idRol);
            return [
                'success' => true,
                'data' => $modulos
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Error al obtener módulos: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Asignar o actualizar permiso
     */
    public function asignarPermiso($idRol, $codMod, $insertar, $editar, $eliminar, $anular, $leer = 1) {
        try {
            $resultado = $this->permisoRepo->asignarPermiso($idRol, $codMod, $insertar, $editar, $eliminar, $anular, $leer);

            if ($resultado) {
                return [
                    'success' => true,
                    'message' => 'Permiso asignado exitosamente'
                ];
            } else {
                return [
                    'success' => false,
                    'message' => 'Error al asignar permiso'
                ];
            }
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Error al asignar permiso: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Asignar permisos en lote (múltiples módulos a la vez)
     */
    public function asignarPermisosLote($idRol, $permisos) {
        try {
            // $permisos es un array de: [{cod_mod, leer, insertar, editar, eliminar, anular}, ...]
            $errores = [];
            $exitosos = 0;

            foreach ($permisos as $permiso) {
                // Aceptar ambos formatos: con p_ y sin p_
                $leer = $permiso['p_leer'] ?? ($permiso['leer'] ?? 1); // Por defecto 1 para compatibilidad
                $insertar = $permiso['p_insertar'] ?? ($permiso['insertar'] ?? 0);
                $editar = $permiso['p_editar'] ?? ($permiso['editar'] ?? 0);
                $eliminar = $permiso['p_eliminar'] ?? ($permiso['eliminar'] ?? 0);
                $anular = $permiso['p_anular'] ?? ($permiso['anular'] ?? 0);
                
                $resultado = $this->permisoRepo->asignarPermiso(
                    $idRol, 
                    $permiso['cod_mod'], 
                    $insertar, 
                    $editar, 
                    $eliminar, 
                    $anular,
                    $leer
                );

                if ($resultado) {
                    $exitosos++;
                } else {
                    $errores[] = $permiso['cod_mod'];
                }
            }

            if (count($errores) > 0) {
                return [
                    'success' => false,
                    'message' => "Se asignaron $exitosos permisos, pero fallaron: " . implode(', ', $errores)
                ];
            }

            return [
                'success' => true,
                'message' => "Se asignaron $exitosos permisos exitosamente"
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Error al asignar permisos: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Eliminar un permiso específico
     */
    public function eliminarPermiso($idRol, $codMod) {
        try {
            $resultado = $this->permisoRepo->eliminarPermiso($idRol, $codMod);

            if ($resultado) {
                return [
                    'success' => true,
                    'message' => 'Permiso eliminado exitosamente'
                ];
            } else {
                return [
                    'success' => false,
                    'message' => 'Error al eliminar permiso'
                ];
            }
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Error al eliminar permiso: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Eliminar todos los permisos de un rol
     */
    public function eliminarTodosLosPermisos($idRol) {
        try {
            $resultado = $this->permisoRepo->eliminarPorRol($idRol);

            if ($resultado) {
                return [
                    'success' => true,
                    'message' => 'Todos los permisos fueron eliminados'
                ];
            } else {
                return [
                    'success' => false,
                    'message' => 'Error al eliminar permisos'
                ];
            }
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Error al eliminar permisos: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Verificar si un rol tiene un permiso específico
     */
    public function tienePermiso($idRol, $codMod, $tipoPermiso) {
        try {
            $permiso = $this->permisoRepo->obtenerPermiso($idRol, $codMod);

            if (!$permiso) {
                return false;
            }

            switch ($tipoPermiso) {
                case 'insertar':
                    return $permiso['p_insertar'] == 1;
                case 'editar':
                    return $permiso['p_editar'] == 1;
                case 'eliminar':
                    return $permiso['p_eliminar'] == 1;
                case 'anular':
                    return $permiso['p_anular'] == 1;
                default:
                    return false;
            }
        } catch (Exception $e) {
            return false;
        }
    }
}
