<?php
require_once __DIR__ . '/../repositories/ContribuyentesRepository.php';
require_once __DIR__ . '/LogsSistemaService.php';

class ContribuyentesService
{
    private $repo;
    private $db;

    public function __construct($db)
    {
        $this->db = $db;
        $this->repo = new ContribuyentesRepository($db);
    }

    /**
     * Listar contribuyentes con paginación y filtro.
     * 
     * @param string $q
     * @param int $page
     * @param int $pageSize
     * @return array
     */
    public function listarContribuyentes(string $q = '', int $page = 1, int $pageSize = 25, string $nombre = '', string $ruc = '')
    {
        try {
            $resultado = $this->repo->listar($this->db, $q, $page, $pageSize, $nombre, $ruc);
            return [
                'success' => true,
                'data' => $resultado['rows'],
                'total' => $resultado['total']
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Error al obtener contribuyentes: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Obtener un contribuyente por su código.
     * 
     * @param string $codigo
     * @return array
     */
    public function obtenerPorId(string $codigo)
    {
        try {
            if (empty($codigo)) {
                return ['success' => false, 'message' => 'El código no fue proporcionado.'];
            }

            $contribuyente = $this->repo->obtener($this->db, $codigo);
            if (!$contribuyente) {
                return ['success' => false, 'message' => 'Contribuyente no encontrado.'];
            }

            return [
                'success' => true,
                'data' => $contribuyente
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Error al obtener contribuyente: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Guardar o actualizar un contribuyente.
     * 
     * @param array $datos
     * @return array
     */
    public function guardarContribuyente(array $datos)
    {
        try {
            $isEdit = !empty($datos['is_edit']) && ($datos['is_edit'] === 'true' || $datos['is_edit'] === '1' || $datos['is_edit'] === true);
            $codigo = trim((string)($datos['codigo'] ?? ''));

            if ($codigo === '') {
                return ['success' => false, 'message' => 'El código de contribuyente es obligatorio.'];
            }

            $datosPrevios = null;
            if ($isEdit) {
                $datosPrevios = $this->repo->obtener($this->db, $codigo);
            }

            if ($isEdit) {
                $this->repo->actualizar($this->db, $codigo, $datos);
                $msg = 'Contribuyente actualizado exitosamente.';
                $logsService = new LogsSistemaService($this->db);
                $logsService->logAction('UPDATE', 'ccte', $codigo, $datosPrevios, $datos, "Contribuyente {$codigo} editado.");
            } else {
                // Validar duplicado
                $existe = $this->repo->existe($this->db, $codigo);
                if ($existe) {
                    return ['success' => false, 'message' => "El código '{$codigo}' ya se encuentra registrado."];
                }
                $this->repo->insertar($this->db, $datos);
                $msg = 'Contribuyente creado exitosamente.';
                $logsService = new LogsSistemaService($this->db);
                $logsService->logAction('INSERT', 'ccte', $codigo, null, $datos, "Contribuyente {$codigo} registrado.");
            }

            return [
                'success' => true,
                'message' => $msg
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Error al guardar contribuyente: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Eliminar un contribuyente.
     * 
     * @param string $codigo
     * @return array
     */
    public function eliminarContribuyente(string $codigo)
    {
        try {
            if (empty($codigo)) {
                return ['success' => false, 'message' => 'El código no fue proporcionado.'];
            }

            $datosPrevios = $this->repo->obtener($this->db, $codigo);
            $resultado = $this->repo->eliminar($this->db, $codigo);
            if ($resultado) {
                $logsService = new LogsSistemaService($this->db);
                $logsService->logAction('DELETE', 'ccte', $codigo, $datosPrevios, null, "Contribuyente {$codigo} (" . ($datosPrevios['nombre'] ?? '') . ") eliminado.");
            }
            return [
                'success' => $resultado,
                'message' => 'Contribuyente eliminado exitosamente.'
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Error al eliminar contribuyente: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Alternar el estado (activo/inactivo) de un contribuyente.
     * 
     * @param string $codigo
     * @return array
     */
    public function cambiarEstado(string $codigo)
    {
        try {
            if (empty($codigo)) {
                return ['success' => false, 'message' => 'El código no fue proporcionado.'];
            }

            $datosPrevios = $this->repo->obtener($this->db, $codigo);
            $resultado = $this->repo->cambiarEstado($this->db, $codigo);
            if ($resultado) {
                $logsService = new LogsSistemaService($this->db);
                $logsService->logAction('TOGGLE STATUS', 'ccte', $codigo, $datosPrevios, null, "Estado del contribuyente {$codigo} (" . ($datosPrevios['nombre'] ?? '') . ") alternado.");
            }
            return [
                'success' => $resultado,
                'message' => 'Estado de contribuyente actualizado exitosamente.'
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Error al cambiar estado del contribuyente: ' . $e->getMessage()
            ];
        }
    }
}
?>
