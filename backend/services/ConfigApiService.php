<?php
/**
 * ConfigApiService
 * 
 * Servicio con la lógica de negocio para la gestión de APIs.
 */

require_once __DIR__ . '/../repositories/ConfigApiRepository.php';
require_once __DIR__ . '/LogsSistemaService.php';

class ConfigApiService {
    private $repo;
    private $db;

    public function __construct($db) {
        $this->db = $db;
        $this->repo = new ConfigApiRepository($db);
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

    public function obtenerCredencialesNubeFact(){
        try {
            $credenciales = $this->repo->obtenerCredencialesNubeFact();
            return [
                'success' => true,
                'data' => $credenciales
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Error al obtener credenciales: ' . $e->getMessage()
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

            $datosPrevios = null;
            if ($isEdit) {
                $datosPrevios = $this->repo->findById($id);
            }

            if ($isEdit) {
                if (!$id) {
                    return ['success' => false, 'message' => 'ID de API no válido para actualizar.'];
                }
                $resultado = $this->repo->update($id, $nom, $ruta);
                if ($resultado) {
                    $logsService = new LogsSistemaService($this->db);
                    $logsService->logAction('UPDATE', 'amd_config_apis_pic', $id, $datosPrevios, $data, "Configuración de API {$nom} actualizada.");
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
                    $logsService = new LogsSistemaService($this->db);
                    $logsService->logAction('INSERT', 'amd_config_apis_pic', $nuevoId, null, $data, "Configuración de API {$nom} registrada.");
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

            $datosPrevios = $this->repo->findById($id);
            $resultado = $this->repo->updateToken($id, $token);
            if ($resultado) {
                $logsService = new LogsSistemaService($this->db);
                $logsService->logAction('UPDATE TOKEN', 'amd_config_apis_pic', $id, $datosPrevios, ['token' => $token], "Token de API con ID {$id} (" . ($datosPrevios['nom'] ?? '') . ") actualizado.");
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

            $datosPrevios = $this->repo->findById($id);
            $resultado = $this->repo->delete($id);
            if ($resultado) {
                $logsService = new LogsSistemaService($this->db);
                $logsService->logAction('DELETE', 'amd_config_apis_pic', $id, $datosPrevios, null, "Configuración de API con ID {$id} (" . ($datosPrevios['nom'] ?? '') . ") eliminada.");
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
