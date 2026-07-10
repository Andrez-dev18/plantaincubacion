<?php
/**
 * DashboardModuloService
 *
 * Servicio para gestionar el menú del dashboard
 * Adaptado a la lógica del sistema mejorado con programa ID 1
 */

require_once __DIR__ . '/../repositories/DashboardModuloRepository.php';

class DashboardModuloService {
    private $repo;

    public function __construct($dashboardModuloRepository) {
        $this->repo = $dashboardModuloRepository;
    }

    /**
     * Obtener menú jerárquico para el usuario
     *
     * @param string $usuarioCodigo
     * @param string $epre
     * @return array
     */
    public function obtenerMenuJerarquico($usuarioCodigo, $epre) {
        if (empty($usuarioCodigo) || empty($epre)) {
            throw new Exception("Faltan datos de identificación del usuario.");
        }

        $modulosPlanos = $this->repo->getMenuPermitido($usuarioCodigo, $epre, '1');

        return $this->construirArbol($modulosPlanos);
    }

    /**
     * Función recursiva para armar el árbol de carpetas e items
     *
     * @param array $elementos
     * @param string|null $parentId
     * @return array
     */
    private function construirArbol(array $elementos, $parentId = null) {
        $branch = array();

        foreach ($elementos as $elemento) {
            if ($elemento['parent_cod'] == $parentId) {
                $children = $this->construirArbol($elementos, $elemento['cod_mod']);

                if ($children) {
                    $elemento['children'] = $children;
                } else {
                    $elemento['children'] = [];
                }

                $branch[] = $elemento;
            }
        }
        return $branch;
    }

    /**
     * Listar todos los módulos del programa por defecto (ID 1)
     *
     * @return array
     */
    public function listarTodos() {
        return $this->repo->listarModulosMenu('1');
    }

    /**
     * Listar grupos de módulos del programa por defecto (ID 1)
     *
     * @return array
     */
    public function listarGrupos() {
        return $this->repo->listarModulosGrupos('1');
    }

    /**
     * Obtener un módulo por ID
     *
     * @param int|string $id
     * @return array|false
     */
    public function obtenerPorId($id) {
        return $this->repo->obtenerPorId($id);
    }

    /**
     * Guardar (insertar o actualizar) un módulo
     *
     * @param array $datos
     * @return bool
     */
    public function guardarModulo($datos) {
        // Validaciones básicas
        if (empty($datos['cod_mod']) || empty($datos['nom_mod']) || empty($datos['tipo']) || empty($datos['orden'])) {
            throw new Exception("Los campos Código, Nombre, Tipo y Orden son obligatorios.");
        }

        if ($datos['tipo'] === 'group') {
            $datos['url'] = null;
            $datos['tipo_param'] = null;
        }

        $datos['id_programa'] = '1';

        return $this->repo->guardar($datos);
    }

    /**
     * Eliminar un módulo por ID
     *
     * @param int|string $id
     * @return bool
     */
    public function eliminarModulo($id) {
        $modulo = $this->repo->obtenerPorId($id);

        if (!$modulo) {
            throw new Exception("El módulo no existe.");
        }

        if ($modulo['tipo'] === 'group' && $this->repo->tieneHijos($modulo['cod_mod'], '1')) {
            throw new Exception("No puedes eliminar este grupo porque tiene sub-módulos dentro. Elimina o mueve los sub-módulos primero.");
        }

        return $this->repo->eliminar($id);
    }
}
