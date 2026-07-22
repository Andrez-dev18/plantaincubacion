<?php
/**
 * DashboardModuloService
 *
 * Servicio para gestionar el menú del dashboard
 * Adaptado a la lógica del sistema mejorado con programa ID 2
 */

require_once __DIR__ . '/../repositories/DashboardModuloRepository.php';
require_once __DIR__ . '/LogsSistemaService.php';

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

        $permitidos = $this->repo->getMenuPermitido($usuarioCodigo, $epre, '2');

        $modulosAMostrar = [];
        $codigosAMostrar = [];

        // Primero, agregamos todos los permitidos directos
        foreach ($permitidos as $m) {
            if (!in_array($m['cod_mod'], $codigosAMostrar)) {
                $modulosAMostrar[] = $m;
                $codigosAMostrar[] = $m['cod_mod'];
            }
        }

        // Cargamos todos los módulos del programa para buscar padres
        $todosModulos = $this->repo->listarModulosMenu('2');
        $modulosPorCodigo = [];
        foreach ($todosModulos as $m) {
            $modulosPorCodigo[$m['cod_mod']] = $m;
        }

        // Buscamos y agregamos padres recursivamente (carpetas contenedoras)
        $cola = $codigosAMostrar;
        while (!empty($cola)) {
            $cod = array_shift($cola);
            if (isset($modulosPorCodigo[$cod])) {
                $parentCod = $modulosPorCodigo[$cod]['parent_cod'];
                if (!empty($parentCod) && !in_array($parentCod, $codigosAMostrar)) {
                    if (isset($modulosPorCodigo[$parentCod])) {
                        $modulosAMostrar[] = $modulosPorCodigo[$parentCod];
                        $codigosAMostrar[] = $parentCod;
                        $cola[] = $parentCod;
                    }
                }
            }
        }

        // Ordenamos los módulos a mostrar según el orden definido
        usort($modulosAMostrar, function($a, $b) {
            return (int)$a['orden'] - (int)$b['orden'];
        });

        return $this->construirArbol($modulosAMostrar);
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
            $elParent = $elemento['parent_cod'] ?? null;
            if ($elParent === '') {
                $elParent = null;
            }
            
            $targetParent = $parentId;
            if ($targetParent === '') {
                $targetParent = null;
            }

            if ($elParent == $targetParent) {
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
     * Listar todos los módulos del programa por defecto (ID 2)
     *
     * @return array
     */
    public function listarTodos() {
        return $this->repo->listarModulosMenu('2');
    }

    /**
     * Listar grupos de módulos del programa por defecto (ID 2)
     *
     * @return array
     */
    public function listarGrupos() {
        return $this->repo->listarModulosGrupos('2');
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

        $datos['id_programa'] = '2';

        $db = $this->repo->getConnection();
        $logsService = new LogsSistemaService($db);

        $isEdit = !empty($datos['id']);
        $datosPrevios = null;

        if ($isEdit) {
            $datosPrevios = $this->repo->obtenerPorId($datos['id']);
        }

        $resultado = $this->repo->guardar($datos);

        if ($resultado) {
            $id = $isEdit ? $datos['id'] : $datos['cod_mod'];
            if ($isEdit) {
                $logsService->logAction('UPDATE', 'amd_dashboard_modulos', $id, $datosPrevios, $datos, "Módulo del menú {$datos['cod_mod']} actualizado.");
            } else {
                $logsService->logAction('INSERT', 'amd_dashboard_modulos', $id, null, $datos, "Módulo del menú {$datos['cod_mod']} registrado.");
            }
        }

        return $resultado;
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

        if ($modulo['tipo'] === 'group' && $this->repo->tieneHijos($modulo['cod_mod'], '2')) {
            throw new Exception("No puedes eliminar este grupo porque tiene sub-módulos dentro. Elimina o mueve los sub-módulos primero.");
        }

        $db = $this->repo->getConnection();
        $logsService = new LogsSistemaService($db);

        $resultado = $this->repo->eliminar($id);

        if ($resultado) {
            $logsService->logAction('DELETE', 'amd_dashboard_modulos', $id, $modulo, null, "Módulo del menú con ID {$id} ({$modulo['cod_mod']}) eliminado.");
        }

        return $resultado;
    }
}
