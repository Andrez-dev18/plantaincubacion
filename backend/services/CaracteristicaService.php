<?php
/**
 * CaracteristicaService
 * 
 * Servicio con lógica de negocio para características
 */

require_once __DIR__ . '/../repositories/CaracteristicaRepository.php';

class CaracteristicaService {
    
    private $repo;

    public function __construct($caracteristicaRepository) {
        $this->repo = $caracteristicaRepository;
    }

    /**
     * Listar todas las características
     * 
     * @return array
     */
    public function listar() {
        try {
            $caracteristicas = $this->repo->findAll();
            
            return [
                'success' => true,
                'data' => $caracteristicas
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Error al listar características: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Obtener una característica por ID
     * 
     * @param int $id
     * @return array
     */
    public function obtenerPorId($id) {
        try {
            $caracteristica = $this->repo->findById($id);
            
            if (!$caracteristica) {
                return [
                    'success' => false,
                    'message' => 'Característica no encontrada'
                ];
            }
            
            return [
                'success' => true,
                'data' => $caracteristica
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Error al obtener característica: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Crear una nueva característica
     * 
     * @param array $data Datos de la característica
     * @return array
     */
    public function crear($data) {
        try {
            // Validar datos requeridos
            if (empty($data['nombre'])) {
                return [
                    'success' => false,
                    'message' => 'El nombre de la característica es requerido'
                ];
            }
            
            // Agregar usuario si existe sesión
            if (isset($_SESSION['usuario'])) {
                $data['usuario_crea'] = $_SESSION['usuario'];
            }
            
            $id = $this->repo->create($data);
            
            if (!$id) {
                return [
                    'success' => false,
                    'message' => 'No se pudo crear la característica'
                ];
            }
            
            return [
                'success' => true,
                'message' => 'Característica creada exitosamente',
                'id' => $id
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Error al crear característica: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Actualizar una característica
     * 
     * @param int $id ID de la característica
     * @param array $data Datos a actualizar
     * @return array
     */
    public function actualizar($id, $data) {
        try {
            // Verificar que la característica existe
            if (!$this->repo->exists($id)) {
                return [
                    'success' => false,
                    'message' => 'Característica no encontrada'
                ];
            }
            
            // Validar datos requeridos
            if (empty($data['nombre'])) {
                return [
                    'success' => false,
                    'message' => 'El nombre de la característica es requerido'
                ];
            }
            
            // Agregar usuario si existe sesión
            if (isset($_SESSION['usuario'])) {
                $data['usuario_modifica'] = $_SESSION['usuario'];
            }
            
            $resultado = $this->repo->update($id, $data);
            
            if (!$resultado) {
                return [
                    'success' => false,
                    'message' => 'No se pudo actualizar la característica'
                ];
            }
            
            return [
                'success' => true,
                'message' => 'Característica actualizada exitosamente'
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Error al actualizar característica: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Eliminar una característica
     * 
     * @param int $id ID de la característica
     * @return array
     */
    /**
     * Verificar si una característica está en uso
     * 
     * @param int $id ID de la característica
     * @return array
     */
    public function verificarUso($id) {
        try {
            if (!$this->repo->exists($id)) {
                return [
                    'success' => false,
                    'message' => 'Característica no encontrada'
                ];
            }
            
            $uso = $this->repo->isInUse($id);
            
            return [
                'success' => true,
                'data' => $uso
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Error al verificar uso: ' . $e->getMessage()
            ];
        }
    }

    public function eliminar($id, $forzar = false) {
        try {
            // Verificar que la característica existe
            if (!$this->repo->exists($id)) {
                return [
                    'success' => false,
                    'message' => 'Característica no encontrada'
                ];
            }
            
            // Si es eliminación forzada, usar forceDelete
            if ($forzar) {
                $resultado = $this->repo->forceDelete($id);
                
                if (!$resultado) {
                    return [
                        'success' => false,
                        'message' => 'No se pudo eliminar la característica y sus datos'
                    ];
                }
                
                return [
                    'success' => true,
                    'message' => 'Característica y sus datos asociados eliminados exitosamente'
                ];
            }
            
            // Eliminación normal (solo si no está en uso)
            $resultado = $this->repo->delete($id);
            
            if (!$resultado) {
                // Verificar si está en uso para mensaje específico
                $uso = $this->repo->isInUse($id);
                if ($uso['en_uso']) {
                    return [
                        'success' => false,
                        'message' => 'No se puede eliminar una característica que está siendo utilizada en galpones',
                        'en_uso' => true,
                        'count' => $uso['count']
                    ];
                }
                
                return [
                    'success' => false,
                    'message' => 'No se pudo eliminar la característica'
                ];
            }
            
            return [
                'success' => true,
                'message' => 'Característica eliminada exitosamente'
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Error al eliminar característica: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Buscar características
     * 
     * @param string $searchTerm Término de búsqueda
     * @return array
     */
    public function buscar($searchTerm) {
        try {
            $caracteristicas = $this->repo->search($searchTerm);
            
            return [
                'success' => true,
                'data' => $caracteristicas
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Error al buscar características: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Obtener tipos de datos disponibles
     * 
     * @return array
     */
    public function obtenerTiposDatos() {
        return [
            'success' => true,
            'data' => $this->repo->getTiposDatos()
        ];
    }
}
