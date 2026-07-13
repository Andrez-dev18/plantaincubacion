<?php
/**
 * ConfigApiService
 * 
 * Servicio con la lógica de negocio para la gestión de APIs.
 */

require_once __DIR__ . '/../repositories/ConfigApiRepository.php';

class ConfigApiService {
    private $repo;

    public function __construct($configApiRepository) {
        $this->repo = $configApiRepository;
    }

    /**
     * Listar todas las APIs
     * 
     * @return array
     */
    public function listar() {
        try {
            $apis = $this->repo->findAll();
            return [
                'success' => true,
                'data' => $apis
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Error al listar las APIs: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Obtener una API por su ID
     * 
     * @param int $id
     * @return array
     */
    public function obtenerPorId($id) {
        try {
            $api = $this->repo->findById($id);
            if (!$api) {
                return [
                    'success' => false,
                    'message' => 'API no encontrada'
                ];
            }
            return [
                'success' => true,
                'data' => $api
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Error al obtener la API: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Guardar una API (Crear o Editar)
     * 
     * @param array $data
     * @return array
     */
    public function guardar($data) {
        try {
            $id = isset($data['id']) ? (int)$data['id'] : null;
            $isEdit = !empty($data['is_edit']) && ($data['is_edit'] === 'true' || $data['is_edit'] === true || $data['is_edit'] == 1);
            $nom = trim($data['nom'] ?? '');
            $ruta = trim($data['ruta'] ?? '');

            // Validaciones básicas
            if (empty($nom)) {
                return ['success' => false, 'message' => 'El nombre de la API es requerido.'];
            }
            if (empty($ruta)) {
                return ['success' => false, 'message' => 'La ruta de la API es requerida.'];
            }

            if ($isEdit) {
                if (!$id) {
                    return ['success' => false, 'message' => 'ID de API no válido para actualizar.'];
                }
                $resultado = $this->repo->update($id, $nom, $ruta);
                if ($resultado) {
                    return [
                        'success' => true,
                        'message' => 'API actualizada exitosamente.'
                    ];
                } else {
                    return ['success' => false, 'message' => 'No se realizaron cambios o no se pudo actualizar la API.'];
                }
            } else {
                $token = trim($data['token'] ?? '');
                if (empty($token)) {
                    return ['success' => false, 'message' => 'El token es obligatorio para registros nuevos.'];
                }
                $nuevoId = $this->repo->create($nom, $ruta, $token);
                if ($nuevoId) {
                    return [
                        'success' => true,
                        'message' => 'API registrada exitosamente.',
                        'data' => ['id' => $nuevoId]
                    ];
                } else {
                    return ['success' => false, 'message' => 'No se pudo registrar la API.'];
                }
            }
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Error al guardar la API: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Actualizar exclusivamente el token de una API
     * 
     * @param int $id
     * @param string $token
     * @return array
     */
    public function actualizarToken($id, $token) {
        try {
            $token = trim($token);
            if (empty($token)) {
                return ['success' => false, 'message' => 'El token no puede estar vacío.'];
            }
            if (!$id) {
                return ['success' => false, 'message' => 'ID de API no válido.'];
            }

            $resultado = $this->repo->updateToken($id, $token);
            if ($resultado) {
                return [
                    'success' => true,
                    'message' => 'Token de API actualizado correctamente.'
                ];
            } else {
                return ['success' => false, 'message' => 'No se pudo actualizar el token.'];
            }
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Error al actualizar el token: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Eliminar una API
     * 
     * @param int $id
     * @return array
     */
    public function eliminar($id) {
        try {
            if (!$id) {
                return ['success' => false, 'message' => 'ID de API no válido.'];
            }

            $resultado = $this->repo->delete($id);
            if ($resultado) {
                return [
                    'success' => true,
                    'message' => 'API eliminada correctamente.'
                ];
            } else {
                return ['success' => false, 'message' => 'No se pudo eliminar la API.'];
            }
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Error al eliminar la API: ' . $e->getMessage()
            ];
        }
    }
}
