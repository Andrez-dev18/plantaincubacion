<?php

require_once __DIR__ . '/../repositories/BorradorRepository.php';

class BorradorService
{
    private $repo;

    public function __construct($db)
    {
        $this->repo = new BorradorRepository($db);
    }

    /**
     * Obtener un borrador
     */
    public function obtenerBorrador(array $params): array
    {
        $idPrograma = trim($params['id_programa'] ?? '1');
        $formulario = trim($params['formulario'] ?? '');
        $usuario = trim($params['usuario'] ?? '');

        if (empty($formulario)) {
            throw new Exception("El identificador del formulario es obligatorio.");
        }
        if (empty($usuario)) {
            throw new Exception("El usuario es obligatorio.");
        }

        $draft = $this->repo->obtener($idPrograma, $formulario, $usuario);

        if ($draft) {
            // Decodificar el JSON antes de retornarlo
            $draft['json_data'] = json_decode($draft['json_data'], true);
            return [
                'success' => true,
                'data' => $draft
            ];
        }

        return [
            'success' => false,
            'message' => 'No se encontró ningún borrador para los parámetros especificados.'
        ];
    }

    /**
     * Guardar o actualizar un borrador
     */
    public function guardarBorrador(array $params): array
    {
        $idPrograma = trim($params['id_programa'] ?? '1');
        $formulario = trim($params['formulario'] ?? '');
        $usuario = trim($params['usuario'] ?? '');
        $jsonDataInput = $params['json_data'] ?? null;

        if (empty($formulario)) {
            throw new Exception("El identificador del formulario es obligatorio.");
        }
        if (empty($usuario)) {
            throw new Exception("El usuario es obligatorio.");
        }
        if ($jsonDataInput === null) {
            throw new Exception("El contenido del borrador (json_data) es obligatorio.");
        }

        // Si es un array u objeto, lo codificamos a JSON string
        if (is_array($jsonDataInput) || is_object($jsonDataInput)) {
            $jsonData = json_encode($jsonDataInput, JSON_UNESCAPED_UNICODE);
        } else {
            // Validar si es un string JSON válido
            $decoded = json_decode($jsonDataInput, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new Exception("El formato de json_data no es un JSON válido.");
            }
            $jsonData = $jsonDataInput;
        }

        $result = $this->repo->guardar($idPrograma, $formulario, $usuario, $jsonData);

        if ($result) {
            return [
                'success' => true,
                'message' => 'Borrador guardado correctamente.'
            ];
        }

        return [
            'success' => false,
            'message' => 'No se pudo guardar el borrador.'
        ];
    }

    /**
     * Eliminar un borrador
     */
    public function eliminarBorrador(array $params): array
    {
        $idPrograma = trim($params['id_programa'] ?? '1');
        $formulario = trim($params['formulario'] ?? '');
        $usuario = trim($params['usuario'] ?? '');

        if (empty($formulario)) {
            throw new Exception("El identificador del formulario es obligatorio.");
        }
        if (empty($usuario)) {
            throw new Exception("El usuario es obligatorio.");
        }

        $result = $this->repo->eliminar($idPrograma, $formulario, $usuario);

        if ($result) {
            return [
                'success' => true,
                'message' => 'Borrador eliminado correctamente.'
            ];
        }

        return [
            'success' => false,
            'message' => 'No se pudo eliminar el borrador o no existía.'
        ];
    }
}
?>
