<?php
require_once __DIR__ . '/../repositories/RolRepository.php';
require_once __DIR__ . '/LogsSistemaService.php';

class RolService
{
    private $repo;
    private $db;

    public function __construct($db)
    {
        $this->db = $db;
        $this->repo = new RolRepository($db);
    }

    // ─────────────────────────────────────────────────────────────────────
    // LISTADOS Y OBTENCIÓN INDIVIDUAL
    // ─────────────────────────────────────────────────────────────────────
    public function listarRoles()
    {
        try {
            $roles = $this->repo->listarRoles();
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

    public function obtenerModulosArbol()
    {
        try {
            $modulos = $this->repo->obtenerModulosPrograma();
            return [
                'success' => true,
                'data' => $modulos
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Error al obtener el árbol de módulos: ' . $e->getMessage()
            ];
        }
    }

    public function obtenerPorId($id)
    {
        try {
            if (empty($id)) {
                return ['success' => false, 'message' => 'El identificador del rol no fue proporcionado.'];
            }

            $rol = $this->repo->obtenerPorId($id);
            if (!$rol) {
                return ['success' => false, 'message' => 'Rol no encontrado.'];
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

    // ─────────────────────────────────────────────────────────────────────
    // CREAR Y EDITAR ROL
    // ─────────────────────────────────────────────────────────────────────
    public function guardarRol($datos)
    {
        try {
            $isEdit = !empty($datos['is_edit']) && ($datos['is_edit'] === 'true' || $datos['is_edit'] === '1' || $datos['is_edit'] === true);

            if (empty($datos['cod_rol']) || empty($datos['nom_rol'])) {
                return ['success' => false, 'message' => 'El código y nombre del rol son obligatorios.'];
            }

            // Normalización estricta (Mayúsculas y sin espacios)
            $datos['cod_rol'] = strtoupper(trim(preg_replace('/\s+/', '_', $datos['cod_rol'])));

            // Validación de duplicados
            $idExcluir = $isEdit ? $datos['id'] : null;
            if ($this->repo->existeCodRol($datos['cod_rol'], $idExcluir)) {
                return [
                    'success' => false, 
                    'message' => "El código de rol '{$datos['cod_rol']}' ya se encuentra registrado en Planta Incubación."
                ];
            }

            $datosPrevios = null;
            if ($isEdit) {
                $datosPrevios = $this->repo->obtenerPorId($datos['id']);
            }

            // Limpieza perimetral de módulos
            $modulosPermitidos = isset($datos['modulos_permitidos']) ? array_unique($datos['modulos_permitidos']) : [];

            $resultado = $this->repo->guardar($datos, $modulosPermitidos, $isEdit);

            if ($resultado) {
                $logsService = new LogsSistemaService($this->db);
                if ($isEdit) {
                    $logsService->logAction('UPDATE', 'adm_rol_pic', $datos['id'], $datosPrevios, $datos, "Rol {$datos['cod_rol']} editado.");
                } else {
                    $logsService->logAction('INSERT', 'adm_rol_pic', $datos['cod_rol'], null, $datos, "Rol {$datos['cod_rol']} registrado.");
                }
            }

            return [
                'success' => $resultado,
                'message' => $isEdit ? 'Rol actualizado exitosamente.' : 'Rol creado exitosamente.'
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Error al guardar el rol: ' . $e->getMessage()
            ];
        }
    }

    // ─────────────────────────────────────────────────────────────────────
    // VALIDACIONES DE SEGURIDAD Y ESTADOS
    // ─────────────────────────────────────────────────────────────────────
    public function eliminarRol($id)
    {
        try {
            // Verificamos primero si intentan borrar el registro administrativo
            $rolActual = $this->repo->obtenerPorId($id);

            if ($rolActual) {
                $codRol = strtoupper($rolActual['cod_rol'] ?? '');
                if ($id == 1 || $codRol === 'ADMIN-PLANTA' || $codRol === 'ADMIN_PLANTA' || $codRol === 'ADMIN') {
                    return [
                        'success' => false, 
                        'message' => 'El rol de Administrador está blindado y no se puede eliminar por seguridad.'
                    ];
                }
            }

            $resultado = $this->repo->eliminar($id);
            if ($resultado) {
                $logsService = new LogsSistemaService($this->db);
                $logsService->logAction('DELETE', 'adm_rol_pic', $id, $rolActual, null, "Rol con ID {$id} (" . ($rolActual['cod_rol'] ?? '') . ") eliminado.");
            }
            return [
                'success' => $resultado,
                'message' => 'Rol eliminado exitosamente.'
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage() // Aquí el repositorio lanza la alerta si hay usuarios asignados
            ];
        }
    }

    public function cambiarEstado($id)
    {
        try {
            $rolActual = $this->repo->obtenerPorId($id);

            if ($rolActual) {
                $codRol = strtoupper($rolActual['cod_rol'] ?? '');
                // Protegemos el rol ADMIN de ser desactivado
                if (($id == 1 || $codRol === 'ADMIN-PLANTA' || $codRol === 'ADMIN_PLANTA' || $codRol === 'ADMIN') && (int)$rolActual['activo'] === 1) {
                    return [
                        'success' => false, 
                        'message' => 'Protección del Sistema: No se puede desactivar el acceso del rol Administrador principal.'
                    ];
                }
            }

            $resultado = $this->repo->cambiarEstado($id);
            if ($resultado) {
                $logsService = new LogsSistemaService($this->db);
                $logsService->logAction('TOGGLE STATUS', 'adm_rol_pic', $id, $rolActual, null, "Estado del rol con ID {$id} (" . ($rolActual['cod_rol'] ?? '') . ") alternado.");
            }
            return [
                'success' => $resultado,
                'message' => 'Estado del rol actualizado.'
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Error al cambiar estado: ' . $e->getMessage()
            ];
        }
    }
}
?>