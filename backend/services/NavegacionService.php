<?php

require_once __DIR__ . '/../repositories/NavegacionRepository.php';

class NavegacionService {
    private $navegacionRepo;

    public function __construct($db) {
        $this->navegacionRepo = new NavegacionRepository($db);
    }

    /**
     * Obtener módulos visibles para un rol
     */
    public function obtenerModulosPorRol($idRol) {
        return $this->navegacionRepo->obtenerModulosPorRol($idRol);
    }

    /**
     * Obtener módulos visibles para un usuario (todos sus roles)
     */
    public function obtenerModulosPorUsuario($idUsuario) {
        return $this->navegacionRepo->obtenerModulosPorUsuario($idUsuario);
    }

    /**
     * Obtener menú jerárquico para un usuario
     */
    public function obtenerMenuJerarquico($idUsuario, $programa = null) {
        $modulos = $this->navegacionRepo->obtenerMenuJerarquico($idUsuario, $programa);
        return $this->construirArbolMenu($modulos);
    }

    /**
     * Construir estructura de árbol jerárquico
     */
    private function construirArbolMenu($modulos) {
        $tree = [];
        $lookup = [];

        // Primer paso: crear lookup de todos los módulos
        foreach ($modulos as $modulo) {
            $modulo['children'] = [];
            $lookup[$modulo['cod_mod']] = $modulo;
        }

        // Segundo paso: construir árbol
        foreach ($lookup as $codMod => &$modulo) {
            if ($modulo['parent_cod'] && isset($lookup[$modulo['parent_cod']])) {
                $lookup[$modulo['parent_cod']]['children'][] = &$modulo;
            } else {
                $tree[] = &$modulo;
            }
        }

        return $tree;
    }

    /**
     * Verificar si un usuario tiene acceso a un módulo
     */
    public function usuarioTieneAcceso($idUsuario, $codMod) {
        return $this->navegacionRepo->usuarioTieneAcceso($idUsuario, $codMod);
    }

    /**
     * Asignar acceso a un módulo para un rol
     */
    public function asignarModulo($idRol, $codMod) {
        if (!$idRol || !$codMod) {
            return [
                'success' => false,
                'message' => 'ID de rol y código de módulo son requeridos'
            ];
        }

        if ($this->navegacionRepo->tieneAccesoModulo($idRol, $codMod)) {
            return [
                'success' => false,
                'message' => 'El rol ya tiene acceso a este módulo'
            ];
        }

        $resultado = $this->navegacionRepo->asignarModulo($idRol, $codMod);
        
        return [
            'success' => $resultado,
            'message' => $resultado ? 'Módulo asignado exitosamente' : 'Error al asignar módulo'
        ];
    }

    /**
     * Remover acceso a un módulo para un rol
     */
    public function removerModulo($idRol, $codMod) {
        if (!$idRol || !$codMod) {
            return [
                'success' => false,
                'message' => 'ID de rol y código de módulo son requeridos'
            ];
        }

        $resultado = $this->navegacionRepo->removerModulo($idRol, $codMod);
        
        return [
            'success' => $resultado,
            'message' => $resultado ? 'Módulo removido exitosamente' : 'Error al remover módulo'
        ];
    }

    /**
     * Asignar múltiples módulos a un rol (reemplaza existentes)
     */
    public function asignarModulosLote($idRol, $modulos) {
        if (!$idRol) {
            return [
                'success' => false,
                'message' => 'ID de rol es requerido'
            ];
        }

        if (!is_array($modulos)) {
            return [
                'success' => false,
                'message' => 'Los módulos deben ser un array'
            ];
        }

        $resultado = $this->navegacionRepo->asignarModulosLote($idRol, $modulos);
        
        return [
            'success' => $resultado,
            'message' => $resultado ? 'Módulos asignados exitosamente' : 'Error al asignar módulos'
        ];
    }

    /**
     * Obtener módulos disponibles de un programa para asignar
     */
    public function obtenerModulosDisponiblesPorPrograma($programa) {
        return $this->navegacionRepo->obtenerModulosDisponiblesPorPrograma($programa);
    }

    /**
     * Obtener módulos asignados y disponibles para un rol
     * (útil para interfaces de asignación)
     */
    public function obtenerModulosParaAsignacion($idRol, $programa) {
        $asignados = $this->navegacionRepo->obtenerModulosPorRol($idRol);
        $disponibles = $this->navegacionRepo->obtenerModulosDisponiblesPorPrograma($programa);
        
        $codsAsignados = array_column($asignados, 'cod_mod');
        
        // Marcar los disponibles que ya están asignados
        foreach ($disponibles as &$modulo) {
            $modulo['asignado'] = in_array($modulo['cod_mod'], $codsAsignados);
        }
        
        return [
            'asignados' => $asignados,
            'disponibles' => $disponibles
        ];
    }
}
