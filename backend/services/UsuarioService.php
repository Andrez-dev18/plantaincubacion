<?php
require_once __DIR__ . '/../repositories/UsuarioRepository.php';
require_once __DIR__ . '/../repositories/UsuarioRolRepository.php';
require_once __DIR__ . '/../repositories/NavegacionRepository.php';

class UsuarioService {
    private $repo;
    private $usuarioRolRepo;
    private $navegacionRepo;

    public function __construct($db) {
        $this->repo = new UsuarioRepository($db);
        $this->usuarioRolRepo = new UsuarioRolRepository($db);
        $this->navegacionRepo = new NavegacionRepository($db);
    }

    /**
     * Autenticar (método original - mantener compatibilidad)
     * DEPRECADO: Usar autenticarConRol() para el nuevo sistema
     */
    public function autenticar($usuario, $password, $ubicacion) {
        $result = $this->repo->login($usuario, $password);

        if ($result) {
            return [
                'success' => true,
                'data' => $result
            ];
        } else {
            return [
                'success' => false,
                'message' => 'Usuario o contraseña incorrectos'
            ];
        }
    }

    /**
     * Autenticar con sistema multi-programa
     * Retorna usuario con todos sus roles y módulos
     */
    public function autenticarConRol($username, $password) {
        try {
            // La contraseña se cifra con AES en el repositorio
            $result = $this->repo->loginConRol($username, $password);

            if ($result) {
                // Obtener todos los roles del usuario
                $roles = $this->usuarioRolRepo->obtenerRolesPorUsuario($result['id_usuario']);
                
                // Obtener módulos visibles (usa tablas adm_usuario_rol + amd_dashboard_modulos)
                $modulos = $this->navegacionRepo->obtenerMenuJerarquico($result['id_usuario']);

                return [
                    'success' => true,
                    'data' => [
                        'id_usuario' => $result['id_usuario'],
                        'username' => $result['username'],
                        'nombre_completo' => $result['nombre_completo'],
                        'estado' => $result['estado'],
                        'roles' => $roles,
                        'modulos' => $modulos
                    ]
                ];
            } else {
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

    /**
     * Obtener menú jerárquico del usuario
     */
    public function obtenerMenu($idUsuario, $programa = null) {
        try {
            $modulos = $this->navegacionRepo->obtenerMenuJerarquico($idUsuario, $programa);

            return [
                'success' => true,
                'data' => $modulos
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Error al obtener menú: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Obtener todos los usuarios
     */
    public function obtenerTodos() {
        try {
            $usuarios = $this->repo->obtenerTodos();
            
            // Agregar roles a cada usuario
            foreach ($usuarios as &$usuario) {
                $usuario['roles'] = $this->usuarioRolRepo->obtenerRolesPorUsuario($usuario['id_usuario']);
            }
            
            return [
                'success' => true,
                'data' => $usuarios
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Error al obtener usuarios: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Obtener usuario por ID
     */
    public function obtenerPorId($idUsuario) {
        try {
            $usuario = $this->repo->obtenerPorId($idUsuario);
            
            if ($usuario) {
                $usuario['roles'] = $this->usuarioRolRepo->obtenerRolesPorUsuario($idUsuario);
            }
            
            return [
                'success' => true,
                'data' => $usuario
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Error al obtener usuario: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Crear usuario
     */
    public function crear($username, $password, $nombreCompleto, $roles = []) {
        try {
            // Verificar username único
            if ($this->repo->existeUsername($username)) {
                return [
                    'success' => false,
                    'message' => 'El username ya existe'
                ];
            }

            // Crear usuario
            $idUsuario = $this->repo->crear($username, $password, $nombreCompleto);
            
            if (!$idUsuario) {
                return [
                    'success' => false,
                    'message' => 'Error al crear usuario'
                ];
            }

            // Asignar roles si se proporcionaron
            if (!empty($roles)) {
                $this->usuarioRolRepo->asignarRolesLote($idUsuario, $roles);
            }

            return [
                'success' => true,
                'message' => 'Usuario creado exitosamente',
                'id_usuario' => $idUsuario
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Error al crear usuario: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Actualizar usuario
     */
    public function actualizar($idUsuario, $username, $nombreCompleto, $roles = null) {
        try {
            // Verificar username único
            if ($this->repo->existeUsername($username, $idUsuario)) {
                return [
                    'success' => false,
                    'message' => 'El username ya existe'
                ];
            }

            // Actualizar usuario
            $resultado = $this->repo->actualizar($idUsuario, $username, $nombreCompleto);
            
            if (!$resultado) {
                return [
                    'success' => false,
                    'message' => 'Error al actualizar usuario'
                ];
            }

            // Actualizar roles si se proporcionaron
            if ($roles !== null) {
                $this->usuarioRolRepo->asignarRolesLote($idUsuario, $roles);
            }

            return [
                'success' => true,
                'message' => 'Usuario actualizado exitosamente'
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Error al actualizar usuario: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Cambiar contraseña
     */
    public function cambiarPassword($idUsuario, $password) {
        try {
            $resultado = $this->repo->actualizarPassword($idUsuario, $password);
            
            return [
                'success' => $resultado,
                'message' => $resultado ? 'Contraseña actualizada exitosamente' : 'Error al actualizar contraseña'
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Error al cambiar contraseña: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Eliminar (desactivar) usuario
     */
    public function eliminar($idUsuario) {
        try {
            $resultado = $this->repo->eliminar($idUsuario);
            
            return [
                'success' => $resultado,
                'message' => $resultado ? 'Usuario desactivado exitosamente' : 'Error al desactivar usuario'
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Error al eliminar usuario: ' . $e->getMessage()
            ];
        }
    }
}
