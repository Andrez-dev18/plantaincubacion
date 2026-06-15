<?php
require_once __DIR__ . '/../repositories/RolRepository.php';

class RolService {
    private $repo;

    public function __construct($db) {
        $this->repo = new RolRepository($db);
    }

    /**
     * Listar todos los roles
     */
    public function listar() {
        try {
            $roles = $this->repo->obtenerTodos();
            return [
                'success' => true,
                'data' => $roles
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Error al obtener roles: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Listar roles de un programa específico
     */
    public function listarPorPrograma($programa) {
        try {
            $roles = $this->repo->obtenerPorPrograma($programa);
            return [
                'success' => true,
                'data' => $roles
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Error al obtener roles: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Obtener lista de programas
     */
    public function obtenerProgramas() {
        try {
            $programas = $this->repo->obtenerProgramas();
            return [
                'success' => true,
                'data' => $programas
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Error al obtener programas: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Obtener módulos de un programa
     */
    public function obtenerModulosPorPrograma($idPrograma) {
        try {
            $modulos = $this->repo->obtenerModulosPorPrograma($idPrograma);
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
     * Obtener módulos asignados a un rol agrupados por programa
     */
    public function obtenerModulosAgrupadosPorPrograma($idRol) {
        try {
            $programas = $this->repo->obtenerModulosAgrupadosPorPrograma($idRol);
            return [
                'success' => true,
                'data' => $programas
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Error al obtener módulos asignados: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Obtener módulos asignados a un rol
     */
    public function obtenerModulosAsignados($idRol) {
        try {
            $modulos = $this->repo->obtenerModulosAsignados($idRol);
            return [
                'success' => true,
                'data' => $modulos
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Error al obtener módulos asignados: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Asignar módulos a un rol
     */
    public function asignarModulos($idRol, $idPrograma, $modulos) {
        try {
            $resultado = $this->repo->asignarModulos($idRol, $idPrograma, $modulos);
            
            if ($resultado) {
                return [
                    'success' => true,
                    'message' => 'Módulos asignados exitosamente'
                ];
            } else {
                return [
                    'success' => false,
                    'message' => 'Error al asignar módulos'
                ];
            }
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Error al asignar módulos: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Obtener un rol por ID
     */
    public function obtenerPorId($idRol) {
        try {
            $rol = $this->repo->obtenerPorId($idRol);
            
            if (!$rol) {
                return [
                    'success' => false,
                    'message' => 'Rol no encontrado'
                ];
            }

            return [
                'success' => true,
                'data' => $rol
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Error al obtener rol: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Crear un nuevo rol
     */
    public function crear($nombreRol, $descripcion, $programas = []) {
        try {
            // Validaciones
            if (empty($nombreRol)) {
                return [
                    'success' => false,
                    'message' => 'El nombre del rol es obligatorio'
                ];
            }

            if (empty($programas)) {
                return [
                    'success' => false,
                    'message' => 'Debe asignar al menos un programa'
                ];
            }

            // Verificar que no exista
            if ($this->repo->existeNombre($nombreRol)) {
                return [
                    'success' => false,
                    'message' => 'Ya existe un rol con ese nombre'
                ];
            }

            $resultado = $this->repo->crear($nombreRol, '', $descripcion);

            if ($resultado && isset($resultado['id_rol'])) {
                $idRol = $resultado['id_rol'];
                
                // Asignar módulos para cada programa
                foreach ($programas as $programa) {
                    $idPrograma = $programa['id_programa'];
                    $modulos = $programa['modulos'] ?? [];
                    
                    if (!empty($modulos)) {
                        $this->repo->asignarModulos($idRol, $idPrograma, $modulos);
                    }
                }
                
                return [
                    'success' => true,
                    'message' => 'Rol creado exitosamente',
                    'id_rol' => $idRol
                ];
            } else {
                return [
                    'success' => false,
                    'message' => 'Error al crear el rol'
                ];
            }
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Error al crear rol: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Actualizar un rol
     */
    public function actualizar($idRol, $nombreRol, $descripcion, $programas = []) {
        try {
            // Validaciones
            if (empty($nombreRol)) {
                return [
                    'success' => false,
                    'message' => 'El nombre del rol es obligatorio'
                ];
            }

            if (empty($programas)) {
                return [
                    'success' => false,
                    'message' => 'Debe asignar al menos un programa'
                ];
            }

            // Verificar que el rol existe
            $rolExistente = $this->repo->obtenerPorId($idRol);
            if (!$rolExistente) {
                return [
                    'success' => false,
                    'message' => 'Rol no encontrado'
                ];
            }

            // Verificar nombre único
            if ($this->repo->existeNombre($nombreRol, $idRol)) {
                return [
                    'success' => false,
                    'message' => 'Ya existe un rol con ese nombre'
                ];
            }

            $resultado = $this->repo->actualizar($idRol, $nombreRol, '', $descripcion);

            if ($resultado) {
                // Primero eliminar todas las asignaciones existentes del rol
                $this->repo->eliminarModulosRol($idRol);
                
                // Asignar módulos para cada programa
                foreach ($programas as $programa) {
                    $idPrograma = $programa['id_programa'];
                    $modulos = $programa['modulos'] ?? [];
                    
                    if (!empty($modulos)) {
                        $this->repo->asignarModulos($idRol, $idPrograma, $modulos);
                    }
                }
                
                return [
                    'success' => true,
                    'message' => 'Rol actualizado exitosamente'
                ];
            } else {
                return [
                    'success' => false,
                    'message' => 'Error al actualizar el rol'
                ];
            }
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Error al actualizar rol: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Eliminar (desactivar) un rol
     */
    public function eliminar($idRol) {
        try {
            // Verificar que el rol existe
            $rolExistente = $this->repo->obtenerPorId($idRol);
            if (!$rolExistente) {
                return [
                    'success' => false,
                    'message' => 'Rol no encontrado'
                ];
            }

            $resultado = $this->repo->eliminar($idRol);

            if ($resultado) {
                return [
                    'success' => true,
                    'message' => 'Rol desactivado exitosamente'
                ];
            } else {
                return [
                    'success' => false,
                    'message' => 'Error al desactivar el rol'
                ];
            }
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Error al eliminar rol: ' . $e->getMessage()
            ];
        }
    }
}

