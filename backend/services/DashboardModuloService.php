<?php
/**
 * DashboardModuloService
 *
 * Servicio para gestionar orden del dashboard
 */

require_once __DIR__ . '/../repositories/DashboardModuloRepository.php';

class DashboardModuloService {
    private $repo;

    public function __construct($dashboardModuloRepository) {
        $this->repo = $dashboardModuloRepository;
    }

    /**
     * Listar modulos del dashboard por programa
     *
     * @param string|null $programa
     * @param string|null $userCodigo  Si se provee, filtra solo módulos accesibles para ese usuario
     * @return array
     */
    public function listar($programa, string $userCodigo = null) {
        // Si hay usuario en sesión, filtrar por sus roles
        if ($userCodigo !== null && $userCodigo !== '') {
            return $this->repo->findAllByProgramaParaUsuario($programa, $userCodigo);
        }
        return $this->repo->findAllByPrograma($programa);
    }

    /**
     * Sembrar modulos desde tabla modulos si esta vacio
     *
     * @param string $programa
     * @return int
     */
    public function seedIfEmpty($programa) {
        $count = $this->repo->countByPrograma($programa);
        if ($count > 0) {
            return 0;
        }

        return $this->repo->seedFromModulos($programa);
    }

    /**
     * Mover un item en el orden
     *
     * @param string $programa
     * @param string $codMod
     * @param string $direction
     * @return array
     */
    public function mover($programa, $codMod, $direction) {
        $list = $this->repo->findOrdenList($programa);

        $index = -1;
        $current = null;

        foreach ($list as $i => $row) {
            if ($row['cod_mod'] === $codMod) {
                $index = $i;
                $current = $row;
                break;
            }
        }

        if ($index === -1 || !$current) {
            return [
                'success' => false,
                'message' => 'Modulo no encontrado'
            ];
        }

        if ($current['tipo'] === 'group') {
            $blocks = [];
            $currentBlockIndex = -1;

            foreach ($list as $row) {
                if ($row['tipo'] === 'group') {
                    $blocks[] = [
                        'groupCod' => $row['cod_mod'],
                        'rows' => [$row]
                    ];
                    if ($row['cod_mod'] === $current['cod_mod']) {
                        $currentBlockIndex = count($blocks) - 1;
                    }
                    continue;
                }

                $lastIndex = count($blocks) - 1;
                if ($lastIndex >= 0 && $blocks[$lastIndex]['groupCod'] && $row['parent_cod'] === $blocks[$lastIndex]['groupCod']) {
                    $blocks[$lastIndex]['rows'][] = $row;
                } else {
                    $blocks[] = [
                        'groupCod' => null,
                        'rows' => [$row]
                    ];
                }
            }

            if ($currentBlockIndex === -1) {
                return [
                    'success' => false,
                    'message' => 'No se pudo identificar el grupo'
                ];
            }

            $targetIndex = $direction === 'up' ? $currentBlockIndex - 1 : $currentBlockIndex + 1;
            while ($targetIndex >= 0 && $targetIndex < count($blocks)) {
                if ($blocks[$targetIndex]['groupCod']) {
                    break;
                }
                $targetIndex = $direction === 'up' ? $targetIndex - 1 : $targetIndex + 1;
            }

            if ($targetIndex < 0 || $targetIndex >= count($blocks)) {
                return [
                    'success' => false,
                    'message' => 'No se puede mover en esa direccion'
                ];
            }

            $movingBlock = $blocks[$currentBlockIndex];
            array_splice($blocks, $currentBlockIndex, 1);
            if ($direction === 'up') {
                array_splice($blocks, $targetIndex, 0, [$movingBlock]);
            } else {
                $insertIndex = $targetIndex;
                if ($currentBlockIndex < $targetIndex) {
                    $insertIndex = $targetIndex; // ya removido el bloque actual
                } else {
                    $insertIndex = $targetIndex + 1;
                }
                array_splice($blocks, $insertIndex, 0, [$movingBlock]);
            }

            $orderedCods = [];
            foreach ($blocks as $block) {
                foreach ($block['rows'] as $row) {
                    $orderedCods[] = $row['cod_mod'];
                }
            }

            $this->repo->updateOrdenFromList($programa, $orderedCods);

            return [
                'success' => true,
                'message' => 'Orden actualizado'
            ];
        }

        $siblings = array_values(array_filter($list, function ($row) use ($current) {
            $parentA = $row['parent_cod'] ?? null;
            $parentB = $current['parent_cod'] ?? null;

            return $row['tipo'] === $current['tipo'] && $parentA === $parentB;
        }));

        $siblingsIndex = -1;
        foreach ($siblings as $i => $row) {
            if ($row['cod_mod'] === $codMod) {
                $siblingsIndex = $i;
                break;
            }
        }

        $targetIndex = $direction === 'up' ? $siblingsIndex - 1 : $siblingsIndex + 1;
        if ($targetIndex < 0 || $targetIndex >= count($siblings)) {
            return [
                'success' => false,
                'message' => 'No se puede mover en esa direccion'
            ];
        }

        $target = $siblings[$targetIndex];

        $this->repo->swapOrden(
            $programa,
            $current['cod_mod'],
            $target['cod_mod'],
            (int)$current['orden'],
            (int)$target['orden']
        );

        return [
            'success' => true,
            'message' => 'Orden actualizado'
        ];
    }

    /**
     * Sincronizar el orden completo desde una lista
     *
     * @param string $programa
     * @param array $items
     * @return int
     */
    public function syncFromList($programa, $items) {
        return $this->repo->replaceForPrograma($programa, $items);
    }

    /**
     * Crear un nuevo módulo
     *
     * @param array $data
     * @return array
     */
    public function crear($data) {
        $programa = $data['programa'] ?? 'Planta de Incubacion';
        
        // Inferir niveles automáticamente si no están presentes
        if (empty($data['nivel0'])) {
            if ($data['tipo'] === 'group') {
                // Para grupos, extraer número de grp-X
                if (preg_match('/grp-(\\d+)/', $data['cod_mod'], $matches)) {
                    $data['nivel0'] = (int)$matches[1];
                }
            } elseif ($data['tipo'] === 'item' && !empty($data['parent_cod'])) {
                // Para items, extraer números de item-X-Y
                if (preg_match('/item-(\\d+)-(\\d+)/', $data['cod_mod'], $matches)) {
                    $data['nivel0'] = (int)$matches[1];
                    $data['nivel1'] = (int)$matches[2];
                }
            }
        }
        
        // Obtener el siguiente orden disponible
        $maxOrden = $this->repo->getMaxOrden($programa);
        $data['orden'] = $maxOrden + 1;
        
        return $this->repo->crear($data);
    }

    /**
     * Actualizar un módulo existente
     *
     * @param array $data
     * @return array
     */
    public function actualizar($data) {
        return $this->repo->actualizar($data);
    }

    /**
     * Eliminar un módulo
     *
     * @param string $codMod
     * @param string $programa
     * @return array
     */
    public function eliminar($codMod, $programa) {
        return $this->repo->eliminar($codMod, $programa);
    }
}
