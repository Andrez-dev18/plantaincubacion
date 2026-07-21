<?php
require_once __DIR__ . '/../repositories/UsuarioRepository.php';
require_once __DIR__ . '/../repositories/AsignacionRepository.php';
require_once __DIR__ . '/LogsSistemaService.php';

class UsuarioService {
    private $usuarioRepo;
    private $rolRepo;
    private $db;

    public function __construct($db) {
        $this->db = $db;
        $this->usuarioRepo = new UsuarioRepository($db);
        $this->rolRepo = new AsignacionRepository($db);
    }

    // ─────────────────────────────────────────────────────────────────────
    // DATATABLES (Lista optimizada)
    // ─────────────────────────────────────────────────────────────────────
    public function obtenerUsuariosServerSide($postData) {
        $start = $postData['start'] ?? 0;
        $length = $postData['length'] ?? 10;
        $searchValue = $postData['search']['value'] ?? '';
        $orderColumn = $postData['order'][0]['column'] ?? 0;
        $orderDir = $postData['order'][0]['dir'] ?? 'asc';

        return $this->usuarioRepo->getUsuariosServerSide($start, $length, $searchValue, $orderColumn, $orderDir);
    }

    // ─────────────────────────────────────────────────────────────────────
    // OBTENER INDIVIDUAL (Para el Modal de Edición)
    // ─────────────────────────────────────────────────────────────────────
    public function obtenerUsuarioConRoles($codigo) {
        try {
            $usuario = $this->usuarioRepo->obtenerPorCodigo($codigo);
            
            if (!$usuario) {
                return ['success' => false, 'message' => 'Usuario no encontrado'];
            }

            // Le inyectamos los roles que tiene asignados en Planta Incubación
            $usuario['roles'] = $this->rolRepo->obtenerRolesCodigo($codigo);

            return [
                'success' => true,
                'data' => $usuario
            ];
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Error: ' . $e->getMessage()];
        }
    }

    // ─────────────────────────────────────────────────────────────────────
    // CREAR / EDITAR USUARIO + ASIGNAR ROLES
    // ─────────────────────────────────────────────────────────────────────
    public function guardarUsuario($datos) {
        try {
            $codigo = trim($datos['codigo']);
            $isEdit = !empty($datos['is_edit']) && $datos['is_edit'] == '1';
            $idsRoles = isset($datos['roles']) ? (array)$datos['roles'] : [];

            // Validaciones básicas
            if (empty($codigo) || empty($datos['nombre'])) {
                return ['success' => false, 'message' => 'El código y nombre son obligatorios.'];
            }

            if (!$isEdit && empty($datos['password'])) {
                return ['success' => false, 'message' => 'La contraseña es obligatoria para usuarios nuevos.'];
            }

            $datosPrevios = null;
            if ($isEdit) {
                $datosPrevios = $this->usuarioRepo->obtenerPorCodigo($codigo);
            }

            // Guardar en base de datos (El repo ya sabe si hacer INSERT o UPDATE)
            $resultadoGuardar = $this->usuarioRepo->guardar($datos, $isEdit);

            if (!$resultadoGuardar) {
                return ['success' => false, 'message' => 'Error al guardar los datos base del usuario.'];
            }

            // Guardar Roles (Reemplaza los anteriores por los nuevos marcados si vienen en la petición)
            if (isset($datos['roles'])) {
                $usuarioLogueado = $_SESSION['usuario'] ?? 'SYSTEM';
                $this->rolRepo->guardarRolesUsuario($codigo, $idsRoles, $usuarioLogueado);
            }

            $logsService = new LogsSistemaService($this->db);
            if ($isEdit) {
                $logsService->logAction('UPDATE', 'usuarios', $codigo, $datosPrevios, $datos, "Usuario {$codigo} editado.");
            } else {
                $logsService->logAction('INSERT', 'usuarios', $codigo, null, $datos, "Usuario {$codigo} registrado.");
            }

            return [
                'success' => true,
                'message' => $isEdit ? 'Usuario actualizado correctamente.' : 'Usuario registrado exitosamente.'
            ];

        } catch (Exception $e) {
            // Si el código ya existía al hacer INSERT, la BD lanzará error de clave duplicada
            if (strpos($e->getMessage(), 'Duplicate entry') !== false) {
                return ['success' => false, 'message' => 'El código de usuario ya está registrado.'];
            }
            return ['success' => false, 'message' => 'Error interno: ' . $e->getMessage()];
        }
    }

    // ─────────────────────────────────────────────────────────────────────
    // CAMBIO DE ESTADO Y CONTRASEÑA
    // ─────────────────────────────────────────────────────────────────────
    public function cambiarEstado($codigo) {
        try {
            $resultado = $this->usuarioRepo->toggleEstado($codigo);
            if ($resultado) {
                $logsService = new LogsSistemaService($this->db);
                $logsService->logAction('TOGGLE STATUS', 'usuarios', $codigo, null, null, "Estado del usuario {$codigo} alternado.");
            }
            return [
                'success' => $resultado,
                'message' => $resultado ? 'Estado actualizado.' : 'No se pudo actualizar el estado.'
            ];
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Error: ' . $e->getMessage()];
        }
    }

    public function resetearPassword($codigo, $newPassword) {
        try {
            if (empty($newPassword)) {
                return ['success' => false, 'message' => 'La contraseña no puede estar vacía.'];
            }

            $resultado = $this->usuarioRepo->adminResetPassword($codigo, $newPassword);
            if ($resultado) {
                $logsService = new LogsSistemaService($this->db);
                $logsService->logAction('RESET PASSWORD', 'usuarios', $codigo, null, null, "Contraseña del usuario {$codigo} restablecida.");
            }
            return [
                'success' => $resultado,
                'message' => $resultado ? 'Contraseña actualizada exitosamente.' : 'No se pudo actualizar la contraseña.'
            ];
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Error: ' . $e->getMessage()];
        }
    }

    // ─────────────────────────────────────────────────────────────────────
    // CATÁLOGOS (Para pintar los Checkboxes en el frontend)
    // ─────────────────────────────────────────────────────────────────────
    public function obtenerCatalogoRoles() {
        try {
            $roles = $this->rolRepo->obtenerRolesActivos();
            return [
                'success' => true,
                'data' => $roles
            ];
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Error: ' . $e->getMessage()];
        }
    }

    public function autenticarConRol($username, $password) {
        try {
            // Llama a la función real del repositorio
            $result = $this->usuarioRepo->loginConRol($username, $password);
            $logsService = new LogsSistemaService($this->db);

            if ($result) {
                // Obtener los roles específicos de Planta Incubación (programa 1)
                $roles = $this->rolRepo->obtenerRolesCodigo($result['id_usuario']);
                
                $userData = [
                    'id_usuario' => $result['id_usuario'],
                    'username' => $result['username'],
                    'nombre_completo' => $result['nombre_completo'],
                    'estado' => $result['estado'],
                    'roles' => $roles
                ];

                // Registrar log de login exitoso
                $logsService->logActionLogin(
                    $result['id_usuario'],
                    $result['nombre_completo'],
                    'LOGIN SUCCESSFUL',
                    'usuarios',
                    $result['id_usuario'],
                    null,
                    $userData,
                    'Inicio de sesión exitoso en Planta Incubación.'
                );

                return [
                    'success' => true,
                    'data' => [
                        'id_usuario' => $result['id_usuario'],
                        'username' => $result['username'],
                        'nombre_completo' => $result['nombre_completo'],
                        'estado' => $result['estado'],
                        'roles' => $roles
                    ]
                ];
            } else {
                // Registrar log de login fallido
                $logsService->logActionLogin(
                    $username,
                    '-',
                    'LOGIN FAILED',
                    'usuarios',
                    null,
                    null,
                    null,
                    "Intento de inicio de sesión fallido para el usuario: {$username}."
                );

                return [
                    'success' => false,
                    'message' => 'Usuario o contraseña incorrectos'
                ];
            }
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Error al autenticar: ' . $e->getMessage()
            ];
        }
    }

    public function registrarLogout($idUsuario, $nombreCompleto) {
        try {
            $logsService = new LogsSistemaService($this->db);
            $userData = [
                'id_usuario' => $idUsuario,
                'nombre_completo' => $nombreCompleto
            ];
            
            $logsService->logActionLogin(
                $idUsuario,
                $nombreCompleto,
                'LOGOUT',
                'usuarios',
                $idUsuario,
                null,
                $userData,
                'Cierre de sesión del usuario.'
            );
        } catch (Exception $e) {
            error_log("Error al registrar log de logout: " . $e->getMessage());
        }
    }
}
?>