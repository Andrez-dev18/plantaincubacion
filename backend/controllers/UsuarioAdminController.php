<?php
require_once __DIR__ . '/../services/UsuarioAdminService.php';

class UsuarioAdminController {
    private $service;

    public function __construct($usuarioAdminService) {
        $this->service = $usuarioAdminService;
    }

    /**
     * Listar todos los usuarios
     */
    public function listar() {
        $resultado = $this->service->listar();
        echo json_encode($resultado);
    }

    /**
     * Obtener un usuario por ID
     */
    public function obtener($data) {
        $idUsuario = $data['id_usuario'] ?? null;

        if (!$idUsuario) {
            echo json_encode([
                'success' => false,
                'message' => 'ID de usuario es requerido'
            ]);
            return;
        }

        $resultado = $this->service->obtenerPorId($idUsuario);
        echo json_encode($resultado);
    }

    /**
     * Crear un nuevo usuario
     */
    public function crear($data) {
        $username = $data['username'] ?? '';
        $password = $data['password'] ?? '';
        $nombreCompleto = $data['nombre_completo'] ?? '';
        // Ahora ids_roles es un array de IDs de roles
        $idsRoles = $data['ids_roles'] ?? [];
        $crea = isset($data['crea']) ? (int)$data['crea'] : 0;
        $modifica = isset($data['modifica']) ? (int)$data['modifica'] : 0;
        $elimina = isset($data['elimina']) ? (int)$data['elimina'] : 0;
        $anula = isset($data['anula']) ? (int)$data['anula'] : 0;

        $resultado = $this->service->crear($username, $password, $nombreCompleto, $idsRoles, $crea, $modifica, $elimina, $anula);
        echo json_encode($resultado);
    }

    /**
     * Actualizar un usuario
     */
    public function actualizar($data) {
        $idUsuario = $data['id_usuario'] ?? null;
        $username = $data['username'] ?? '';
        $nombreCompleto = $data['nombre_completo'] ?? '';
        // Ahora ids_roles es un array de IDs de roles
        $idsRoles = $data['ids_roles'] ?? [];
        $crea = isset($data['crea']) ? (int)$data['crea'] : 0;
        $modifica = isset($data['modifica']) ? (int)$data['modifica'] : 0;
        $elimina = isset($data['elimina']) ? (int)$data['elimina'] : 0;
        $anula = isset($data['anula']) ? (int)$data['anula'] : 0;

        if (!$idUsuario) {
            echo json_encode([
                'success' => false,
                'message' => 'ID de usuario es requerido'
            ]);
            return;
        }

        $resultado = $this->service->actualizar($idUsuario, $username, $nombreCompleto, $idsRoles, $crea, $modifica, $elimina, $anula);
        echo json_encode($resultado);
    }

    /**
     * Cambiar contraseña de un usuario
     */
    public function cambiarPassword($data) {
        $idUsuario = $data['id_usuario'] ?? null;
        $passwordNueva = $data['password'] ?? '';

        if (!$idUsuario) {
            echo json_encode([
                'success' => false,
                'message' => 'ID de usuario es requerido'
            ]);
            return;
        }

        $resultado = $this->service->cambiarPassword($idUsuario, $passwordNueva);
        echo json_encode($resultado);
    }

    /**
     * Eliminar un usuario
     */
    public function eliminar($data) {
        $idUsuario = $data['id_usuario'] ?? null;

        if (!$idUsuario) {
            echo json_encode([
                'success' => false,
                'message' => 'ID de usuario es requerido'
            ]);
            return;
        }

        $resultado = $this->service->eliminar($idUsuario);
        echo json_encode($resultado);
    }
}
