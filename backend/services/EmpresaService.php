<?php
require_once __DIR__ . '/../repositories/EmpresaRepository.php';

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
            
            $resultadoId = $this->repo->guardar($datos, $id);

            return [
                'success' => true,
                'message' => ($id !== null && $id > 0) ? 'Empresa actualizada exitosamente.' : 'Empresa creada exitosamente.',
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

            $resultado = $this->repo->eliminar((int)$id);
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

            $resultado = $this->repo->cambiarEstado((int)$id);
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
