<?php

require_once __DIR__ . '/../repositories/UsuarioRolRepository.php';

class UsuarioRolService {
    private $usuarioRolRepo;

    public function __construct($db) {
        $this->usuarioRolRepo = new UsuarioRolRepository($db);
    }

    /**
     * Obtener todos los roles de un usuario
     */
    public function obtenerRolesPorUsuario($idUsuario) {
        return $this->usuarioRolRepo->obtenerRolesPorUsuario($idUsuario);
    }

    /**
     * Obtener usuarios asignados a un rol
     */
    public function obtenerUsuariosPorRol($idRol) {
        return $this->usuarioRolRepo->obtenerUsuariosPorRol($idRol);
    }

    /**
     * Asignar un rol a un usuario
     */
    public function asignarRol($idUsuario, $idRol) {
        if (!$idUsuario || !$idRol) {
            return [
                'success' => false,
                'message' => 'ID de usuario y rol son requeridos'
            ];
        }

        if ($this->usuarioRolRepo->existeAsignacion($idUsuario, $idRol)) {
            return [
                'success' => false,
                'message' => 'El usuario ya tiene asignado este rol'
            ];
        }

        $resultado = $this->usuarioRolRepo->asignarRol($idUsuario, $idRol);
        
        return [
            'success' => $resultado,
            'message' => $resultado ? 'Rol asignado exitosamente' : 'Error al asignar rol'
        ];
    }

    /**
     * Remover un rol de un usuario
     */
    public function removerRol($idUsuario, $idRol) {
        if (!$idUsuario || !$idRol) {
            return [
                'success' => false,
                'message' => 'ID de usuario y rol son requeridos'
            ];
        }

        $resultado = $this->usuarioRolRepo->removerRol($idUsuario, $idRol);
        
        return [
            'success' => $resultado,
            'message' => $resultado ? 'Rol removido exitosamente' : 'Error al remover rol'
        ];
    }

    /**
     * Asignar múltiples roles a un usuario (reemplaza existentes)
     */
    public function asignarRolesLote($idUsuario, $roles) {
        if (!$idUsuario) {
            return [
                'success' => false,
                'message' => 'ID de usuario es requerido'
            ];
        }

        if (!is_array($roles)) {
            return [
                'success' => false,
                'message' => 'Los roles deben ser un array'
            ];
        }

        $resultado = $this->usuarioRolRepo->asignarRolesLote($idUsuario, $roles);
        
        return [
            'success' => $resultado,
            'message' => $resultado ? 'Roles asignados exitosamente' : 'Error al asignar roles'
        ];
    }

    /**
     * Obtener roles de un usuario para un programa específico
     */
    public function obtenerRolesPorPrograma($idUsuario, $programa) {
        return $this->usuarioRolRepo->obtenerRolesPorPrograma($idUsuario, $programa);
    }

    /**
     * Verificar permisos CRUD de un usuario para un programa
     * (Combina permisos de todos los roles del usuario en ese programa)
     */
    public function obtenerPermisosCRUD($idUsuario, $programa) {
        $roles = $this->usuarioRolRepo->obtenerRolesPorPrograma($idUsuario, $programa);
        
        if (empty($roles)) {
            return [
                'p_insertar' => 0,
                'p_editar' => 0,
                'p_eliminar' => 0,
                'p_anular' => 0
            ];
        }

        // Si tiene al menos un rol con el permiso, lo tiene
        $permisos = [
            'p_insertar' => 0,
            'p_editar' => 0,
            'p_eliminar' => 0,
            'p_anular' => 0
        ];

        foreach ($roles as $rol) {
            if ($rol['p_insertar']) $permisos['p_insertar'] = 1;
            if ($rol['p_editar']) $permisos['p_editar'] = 1;
            if ($rol['p_eliminar']) $permisos['p_eliminar'] = 1;
            if ($rol['p_anular']) $permisos['p_anular'] = 1;
        }

        return $permisos;
    }
}
