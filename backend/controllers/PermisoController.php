<?php
require_once __DIR__ . '/../services/PermisoService.php';

class PermisoController {
    private $service;

    public function __construct($permisoService) {
        $this->service = $permisoService;
    }

    /**
     * Obtener permisos de un rol
     */
    public function obtenerPorRol($data) {
        $idRol = $data['id_rol'] ?? null;

        if (!$idRol) {
            echo json_encode([
                'success' => false,
                'message' => 'ID de rol es requerido'
            ]);
            return;
        }

        $resultado = $this->service->obtenerPorRol($idRol);
        echo json_encode($resultado);
    }

    /**
     * Obtener módulos disponibles con permisos actuales
     */
    public function obtenerModulosDisponibles($data) {
        $idRol = $data['id_rol'] ?? null;

        if (!$idRol) {
            echo json_encode([
                'success' => false,
                'message' => 'ID de rol es requerido'
            ]);
            return;
        }

        $resultado = $this->service->obtenerModulosDisponibles($idRol);
        echo json_encode($resultado);
    }

    /**
     * Asignar o actualizar un permiso
     */
    public function asignarPermiso($data) {
        $idRol = $data['id_rol'] ?? null;
        $codMod = $data['cod_mod'] ?? null;
        // Aceptar ambos formatos: con p_ y sin p_
        $insertar = isset($data['p_insertar']) ? (int)$data['p_insertar'] : (isset($data['insertar']) ? (int)$data['insertar'] : 0);
        $editar = isset($data['p_editar']) ? (int)$data['p_editar'] : (isset($data['editar']) ? (int)$data['editar'] : 0);
        $eliminar = isset($data['p_eliminar']) ? (int)$data['p_eliminar'] : (isset($data['eliminar']) ? (int)$data['eliminar'] : 0);
        $anular = isset($data['p_anular']) ? (int)$data['p_anular'] : (isset($data['anular']) ? (int)$data['anular'] : 0);

        if (!$idRol || !$codMod) {
            echo json_encode([
                'success' => false,
                'message' => 'ID de rol y código de módulo son requeridos'
            ]);
            return;
        }

        $resultado = $this->service->asignarPermiso($idRol, $codMod, $insertar, $editar, $eliminar, $anular);
        echo json_encode($resultado);
    }

    /**
     * Asignar permisos en lote
     */
    public function asignarPermisosLote($data) {
        $idRol = $data['id_rol'] ?? null;
        $permisos = $data['permisos'] ?? [];

        if (!$idRol || empty($permisos)) {
            echo json_encode([
                'success' => false,
                'message' => 'ID de rol y permisos son requeridos'
            ]);
            return;
        }

        $resultado = $this->service->asignarPermisosLote($idRol, $permisos);
        echo json_encode($resultado);
    }

    /**
     * Eliminar un permiso
     */
    public function eliminarPermiso($data) {
        $idRol = $data['id_rol'] ?? null;
        $codMod = $data['cod_mod'] ?? null;

        if (!$idRol || !$codMod) {
            echo json_encode([
                'success' => false,
                'message' => 'ID de rol y código de módulo son requeridos'
            ]);
            return;
        }

        $resultado = $this->service->eliminarPermiso($idRol, $codMod);
        echo json_encode($resultado);
    }

    /**
     * Eliminar todos los permisos de un rol
     */
    public function eliminarTodosLosPermisos($data) {
        $idRol = $data['id_rol'] ?? null;

        if (!$idRol) {
            echo json_encode([
                'success' => false,
                'message' => 'ID de rol es requerido'
            ]);
            return;
        }

        $resultado = $this->service->eliminarTodosLosPermisos($idRol);
        echo json_encode($resultado);
    }
}
