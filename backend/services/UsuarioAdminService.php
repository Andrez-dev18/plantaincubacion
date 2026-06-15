<?php
require_once __DIR__ . '/../repositories/UsuarioRepository.php';
require_once __DIR__ . '/../repositories/RolRepository.php';

class UsuarioAdminService {
    private $usuarioRepo;
    private $rolRepo;

    public function __construct($usuarioRepository, $rolRepository) {
        $this->usuarioRepo = $usuarioRepository;
        $this->rolRepo = $rolRepository;
    }

    /**
     * Listar todos los usuarios
     */
    public function listar() {
        try {
            $usuarios = $this->usuarioRepo->obtenerTodos();
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
     * Obtener un usuario por ID
     */
    public function obtenerPorId($idUsuario) {
        try {
            $usuario = $this->usuarioRepo->obtenerPorId($idUsuario);
            
            if (!$usuario) {
                return [
                    'success' => false,
                    'message' => 'Usuario no encontrado'
                ];
            }

            // Remover password de la respuesta
            unset($usuario['password']);

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
     * Crear un nuevo usuario con múltiples roles
     */
    public function crear($username, $password, $nombreCompleto, $idsRoles = [], $crea = 0, $modifica = 0, $elimina = 0, $anula = 0) {
        try {
            // Validaciones
            if (empty($username) || empty($password) || empty($nombreCompleto)) {
                return [
                    'success' => false,
                    'message' => 'Usuario, contraseña y nombre completo son obligatorios'
                ];
            }

            // IMPORTANTE: Validar longitud del username (limitado a 7 caracteres por estructura de BD)
            if (strlen($username) > 7) {
                return [
                    'success' => false,
                    'message' => 'El nombre de usuario no puede exceder 7 caracteres (limitación de base de datos)'
                ];
            }

            // Verificar que tenga al menos un rol
            if (empty($idsRoles)) {
                return [
                    'success' => false,
                    'message' => 'Debe seleccionar al menos un rol'
                ];
            }

            // Verificar que el username no exista
            if ($this->usuarioRepo->existeUsername($username)) {
                return [
                    'success' => false,
                    'message' => 'El nombre de usuario ya existe'
                ];
            }

            // Verificar que todos los roles existan
            foreach ($idsRoles as $idRol) {
                $rol = $this->rolRepo->obtenerPorId($idRol);
                if (!$rol) {
                    return [
                        'success' => false,
                        'message' => 'Uno de los roles especificados no existe'
                    ];
                }
            }

            // La contraseña se cifra con AES en el repositorio
            $resultado = $this->usuarioRepo->crearConRoles($username, $password, $nombreCompleto, $idsRoles, $crea, $modifica, $elimina, $anula);

            if ($resultado) {
                return [
                    'success' => true,
                    'message' => 'Usuario creado exitosamente'
                ];
            } else {
                return [
                    'success' => false,
                    'message' => 'Error al crear el usuario'
                ];
            }
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Error al crear usuario: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Actualizar un usuario con múltiples roles
     */
    public function actualizar($idUsuario, $username, $nombreCompleto, $idsRoles = [], $crea = 0, $modifica = 0, $elimina = 0, $anula = 0) {
        try {
            // Validaciones
            if (empty($username) || empty($nombreCompleto)) {
                return [
                    'success' => false,
                    'message' => 'Usuario y nombre completo son obligatorios'
                ];
            }

            // IMPORTANTE: Validar longitud del username (limitado a 7 caracteres por estructura de BD)
            if (strlen($username) > 7) {
                return [
                    'success' => false,
                    'message' => 'El nombre de usuario no puede exceder 7 caracteres (limitación de base de datos)'
                ];
            }

            // Verificar que tenga al menos un rol
            if (empty($idsRoles)) {
                return [
                    'success' => false,
                    'message' => 'Debe seleccionar al menos un rol'
                ];
            }

            // Verificar que el usuario exista
            $usuarioExistente = $this->usuarioRepo->obtenerPorId($idUsuario);
            if (!$usuarioExistente) {
                return [
                    'success' => false,
                    'message' => 'Usuario no encontrado'
                ];
            }

            // Verificar username duplicado
            if ($this->usuarioRepo->existeUsername($username, $idUsuario)) {
                return [
                    'success' => false,
                    'message' => 'Ya existe otro usuario con ese nombre de usuario'
                ];
            }

            // Verificar que todos los roles existan
            foreach ($idsRoles as $idRol) {
                $rol = $this->rolRepo->obtenerPorId($idRol);
                if (!$rol) {
                    return [
                        'success' => false,
                        'message' => 'Uno de los roles especificados no existe'
                    ];
                }
            }

            $resultado = $this->usuarioRepo->actualizarConRoles($idUsuario, $username, $nombreCompleto, $idsRoles, $crea, $modifica, $elimina, $anula);

            if ($resultado) {
                return [
                    'success' => true,
                    'message' => 'Usuario actualizado exitosamente'
                ];
            } else {
                return [
                    'success' => false,
                    'message' => 'Error al actualizar el usuario'
                ];
            }
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Error al actualizar usuario: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Cambiar contraseña de un usuario
     */
    public function cambiarPassword($idUsuario, $passwordNueva) {
        try {
            // Validaciones
            if (empty($passwordNueva)) {
                return [
                    'success' => false,
                    'message' => 'La contraseña es obligatoria'
                ];
            }

            // Verificar que el usuario exista
            $usuarioExistente = $this->usuarioRepo->obtenerPorId($idUsuario);
            if (!$usuarioExistente) {
                return [
                    'success' => false,
                    'message' => 'Usuario no encontrado'
                ];
            }

            // La contraseña se cifra con AES en el repositorio
            $resultado = $this->usuarioRepo->actualizarPassword($idUsuario, $passwordNueva);

            if ($resultado) {
                return [
                    'success' => true,
                    'message' => 'Contraseña actualizada exitosamente'
                ];
            } else {
                return [
                    'success' => false,
                    'message' => 'Error al actualizar la contraseña'
                ];
            }
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Error al cambiar contraseña: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Eliminar (desactivar) un usuario
     */
    public function eliminar($idUsuario) {
        try {
            // Verificar que el usuario exista
            $usuarioExistente = $this->usuarioRepo->obtenerPorId($idUsuario);
            if (!$usuarioExistente) {
                return [
                    'success' => false,
                    'message' => 'Usuario no encontrado'
                ];
            }

            $resultado = $this->usuarioRepo->eliminar($idUsuario);

            if ($resultado) {
                return [
                    'success' => true,
                    'message' => 'Usuario desactivado exitosamente'
                ];
            } else {
                return [
                    'success' => false,
                    'message' => 'Error al desactivar el usuario'
                ];
            }
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Error al desactivar usuario: ' . $e->getMessage()
            ];
        }
    }
}
