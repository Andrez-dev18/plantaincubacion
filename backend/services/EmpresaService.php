<?php
require_once __DIR__ . '/../repositories/EmpresaRepository.php';
require_once __DIR__ . '/LogsSistemaService.php';

class EmpresaService
{
    private $repo;
    private $db;

    public function __construct($db)
    {
        $this->db = $db;
        $this->repo = new EmpresaRepository($db);
    }

    /**
     * Listar empresas con filtro opcional de búsqueda.
     * 
     * @param string|null $q
     * @return array
     */
    public function listarEmpresas(?string $q = null)
    {
        try {
            $empresas = $this->repo->listar($q);
            return [
                'success' => true,
                'data' => $empresas
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Error al obtener empresas: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Obtener una empresa por su identificador.
     * 
     * @param mixed $id
     * @return array
     */
    public function obtenerPorId($id)
    {
        try {
            if (empty($id)) {
                return ['success' => false, 'message' => 'El identificador de la empresa no fue proporcionado.'];
            }

            $empresa = $this->repo->obtenerPorId((int)$id);
            if (!$empresa) {
                return ['success' => false, 'message' => 'Empresa no encontrada.'];
            }

            return [
                'success' => true,
                'data' => $empresa
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Error al obtener empresa: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Guardar o actualizar una empresa.
     * 
     * @param array $datos
     * @return array
     */
    public function guardarEmpresa($datos)
    {
        try {
            $id = !empty($datos['id']) ? (int)$datos['id'] : null;
            $isEdit = ($id !== null && $id > 0);
            $datosPrevios = null;
            if ($isEdit) {
                $datosPrevios = $this->repo->obtenerPorId((int)$id);
            }

            $resultadoId = $this->repo->guardar($datos, $id);

            if ($resultadoId) {
                $logsService = new LogsSistemaService($this->db);
                if ($isEdit) {
                    $logsService->logAction('UPDATE', 'empresa', $id, $datosPrevios, $datos, "Empresa con ID {$id} (" . ($datos['nom_empresa'] ?? '') . ") editada.");
                } else {
                    $logsService->logAction('INSERT', 'empresa', $resultadoId, null, $datos, "Empresa registrada exitosamente con ID {$resultadoId}.");
                }
            }

            return [
                'success' => true,
                'message' => $isEdit ? 'Empresa actualizada exitosamente.' : 'Empresa creada exitosamente.',
                'data' => ['id' => $resultadoId]
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Error al guardar la empresa: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Eliminar una empresa.
     * 
     * @param mixed $id
     * @return array
     */
    public function eliminarEmpresa($id)
    {
        try {
            if (empty($id)) {
                return ['success' => false, 'message' => 'El identificador de la empresa no fue proporcionado.'];
            }

            $datosPrevios = $this->repo->obtenerPorId((int)$id);
            $resultado = $this->repo->eliminar((int)$id);
            if ($resultado) {
                $logsService = new LogsSistemaService($this->db);
                $logsService->logAction('DELETE', 'empresa', $id, $datosPrevios, null, "Empresa con ID {$id} (" . ($datosPrevios['nom_empresa'] ?? '') . ") eliminada.");
            }
            return [
                'success' => $resultado,
                'message' => 'Empresa eliminada exitosamente.'
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Error al eliminar la empresa: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Activar o desactivar el estado de una empresa.
     * 
     * @param mixed $id
     * @return array
     */
    public function cambiarEstado($id)
    {
        try {
            if (empty($id)) {
                return ['success' => false, 'message' => 'El identificador de la empresa no fue proporcionado.'];
            }

            $datosPrevios = $this->repo->obtenerPorId((int)$id);
            $resultado = $this->repo->cambiarEstado((int)$id);
            if ($resultado) {
                $logsService = new LogsSistemaService($this->db);
                $logsService->logAction('TOGGLE STATUS', 'empresa', $id, $datosPrevios, null, "Estado de la empresa con ID {$id} (" . ($datosPrevios['nom_empresa'] ?? '') . ") alternado.");
            }
            return [
                'success' => $resultado,
                'message' => 'Estado de la empresa actualizado.'
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Error al cambiar estado: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Listar CCTE (clientes/proveedores) con paginación y búsqueda.
     * 
     * @param string|null $q
     * @param int $page
     * @param int $pageSize
     * @return array
     */
    public function listarCCTE(?string $q = '', int $page = 1, int $pageSize = 20)
    {
        try {
            $qStr = (string)($q ?? '');
            $resultado = $this->repo->listarCCTE($this->db, $qStr, $page, $pageSize);
            return [
                'success' => true,
                'data' => $resultado['rows'],
                'total' => $resultado['total']
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Error al obtener CCTE: ' . $e->getMessage()
            ];
        }
    }
}
?>
