<?php
require_once __DIR__ . '/../repositories/ServiciosRepository.php';

class ServiciosService
{
    private $repo;
    private $db;

    public function __construct($db)
    {
        $this->db = $db;
        $this->repo = new ServiciosRepository($db);
    }

    /**
     * Listar servicios con paginación y filtro opcional.
     * 
     * @param string $q
     * @param int $page
     * @param int $pageSize
     * @return array
     */
    public function listarServicios(string $q = '', int $page = 1, int $pageSize = 25)
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
                'message' => 'Error al obtener servicios: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Obtener un servicio por su código.
     * 
     * @param string $codi
     * @return array
     */
    public function obtenerPorId(string $codi)
    {
        try {
            if (empty($codi)) {
                return ['success' => false, 'message' => 'El código del servicio no fue proporcionado.'];
            }

            $servicio = $this->repo->obtener($this->db, $codi);
            if (!$servicio) {
                return ['success' => false, 'message' => 'Servicio no encontrado.'];
            }

            return [
                'success' => true,
                'data' => $servicio
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Error al obtener servicio: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Guardar o actualizar un servicio.
     * 
     * @param array $datos
     * @return array
     */
    public function guardarServicio(array $datos)
    {
        try {
            $isEdit = !empty($datos['is_edit']) && ($datos['is_edit'] === 'true' || $datos['is_edit'] === '1' || $datos['is_edit'] === true);
            $codi = trim((string)($datos['codi'] ?? ''));

            if ($codi === '') {
                return ['success' => false, 'message' => 'El código de servicio es obligatorio.'];
            }

            if ($isEdit) {
                $this->repo->actualizar($this->db, $codi, $datos);
                $msg = 'Servicio actualizado exitosamente.';
            } else {
                // Validar duplicado
                $existe = $this->repo->obtener($this->db, $codi);
                if ($existe) {
                    return ['success' => false, 'message' => "El código de servicio '{$codi}' ya se encuentra registrado."];
                }
                $this->repo->insertar($this->db, $datos);
                $msg = 'Servicio creado exitosamente.';
            }

            return [
                'success' => true,
                'message' => $msg
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Error al guardar el servicio: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Eliminar un servicio.
     * 
     * @param string $codi
     * @return array
     */
    public function eliminarServicio(string $codi)
    {
        try {
            if (empty($codi)) {
                return ['success' => false, 'message' => 'El código del servicio no fue proporcionado.'];
            }

            $resultado = $this->repo->eliminar($this->db, $codi);
            return [
                'success' => $resultado,
                'message' => 'Servicio eliminado exitosamente.'
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Error al eliminar el servicio: ' . $e->getMessage()
            ];
        }
    }
}
?>
