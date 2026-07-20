<?php
require_once __DIR__ . '/../repositories/ArticulosRepository.php';

class ArticulosService
{
    private $repo;
    private $db;

    public function __construct($db)
    {
        $this->db = $db;
        $this->repo = new ArticulosRepository($db);
    }

    /**
     * Listar artículos con paginación y filtro.
     * 
     * @param string $q
     * @param int $page
     * @param int $pageSize
     * @return array
     */
    public function listarArticulos(string $q = '', int $page = 1, int $pageSize = 25)
    {
        try {
            $resultado = $this->repo->listar($this->db, $q, $page, $pageSize);
            return [
                'success' => true,
                'data' => $resultado['rows'],
                'total' => $resultado['total']
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Error al obtener artículos: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Obtener un artículo por su código.
     * 
     * @param string $codigo
     * @return array
     */
    public function obtenerPorId(string $codigo)
    {
        try {
            if (empty($codigo)) {
                return ['success' => false, 'message' => 'El código del artículo no fue proporcionado.'];
            }

            $articulo = $this->repo->obtener($this->db, $codigo);
            if (!$articulo) {
                return ['success' => false, 'message' => 'Artículo no encontrado.'];
            }

            return [
                'success' => true,
                'data' => $articulo
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Error al obtener artículo: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Guardar o actualizar un artículo.
     * 
     * @param array $datos
     * @return array
     */
    public function guardarArticulo(array $datos)
    {
        try {
            $isEdit = !empty($datos['is_edit']) && ($datos['is_edit'] === 'true' || $datos['is_edit'] === '1' || $datos['is_edit'] === true);
            $codigo = trim((string)($datos['codigo'] ?? ''));

            if ($codigo === '') {
                return ['success' => false, 'message' => 'El código de artículo es obligatorio.'];
            }

            if ($isEdit) {
                $this->repo->actualizar($this->db, $codigo, $datos);
                $msg = 'Artículo actualizado exitosamente.';
            } else {
                // Validar duplicado
                $existe = $this->repo->obtener($this->db, $codigo);
                if ($existe) {
                    return ['success' => false, 'message' => "El código de artículo '{$codigo}' ya se encuentra registrado."];
                }
                $this->repo->insertar($this->db, $datos);
                $msg = 'Artículo creado exitosamente.';
            }

            return [
                'success' => true,
                'message' => $msg
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Error al guardar el artículo: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Eliminar un artículo.
     * 
     * @param string $codigo
     * @return array
     */
    public function eliminarArticulo(string $codigo)
    {
        try {
            if (empty($codigo)) {
                return ['success' => false, 'message' => 'El código del artículo no fue proporcionado.'];
            }

            $resultado = $this->repo->eliminar($this->db, $codigo);
            return [
                'success' => $resultado,
                'message' => 'Artículo eliminado exitosamente.'
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Error al eliminar el artículo: ' . $e->getMessage()
            ];
        }
    }
}
?>
