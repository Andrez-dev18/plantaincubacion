<?php
date_default_timezone_set('America/Lima');

class BorradorRepository
{
    private $conn;

    public function __construct($db)
    {
        $this->conn = $db;
    }

    /**
     * Obtener un borrador por programa, formulario y usuario.
     */
    public function obtener(string $idPrograma, string $formulario, string $usuario): ?array
    {
        $sql = "SELECT id, id_programa, formulario, usuario, json_data, fecha_hora 
                FROM com_borradores 
                WHERE id_programa = :id_programa 
                  AND formulario = :formulario 
                  AND usuario = :usuario 
                LIMIT 1";
        
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([
            ':id_programa' => $idPrograma,
            ':formulario' => $formulario,
            ':usuario' => $usuario
        ]);

        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ? $result : null;
    }

    /**
     * Guardar o actualizar un borrador.
     */
    public function guardar(string $idPrograma, string $formulario, string $usuario, string $jsonData): bool
    {
        $sql = "INSERT INTO com_borradores (id_programa, formulario, usuario, json_data) 
                VALUES (:id_programa, :formulario, :usuario, :json_data) 
                ON DUPLICATE KEY UPDATE 
                    json_data = :json_data_update,
                    fecha_hora = CURRENT_TIMESTAMP";

        $stmt = $this->conn->prepare($sql);
        return $stmt->execute([
            ':id_programa' => $idPrograma,
            ':formulario' => $formulario,
            ':usuario' => $usuario,
            ':json_data' => $jsonData,
            ':json_data_update' => $jsonData
        ]);
    }

    /**
     * Eliminar un borrador.
     */
    public function eliminar(string $idPrograma, string $formulario, string $usuario): bool
    {
        $sql = "DELETE FROM com_borradores 
                WHERE id_programa = :id_programa 
                  AND formulario = :formulario 
                  AND usuario = :usuario";

        $stmt = $this->conn->prepare($sql);
        return $stmt->execute([
            ':id_programa' => $idPrograma,
            ':formulario' => $formulario,
            ':usuario' => $usuario
        ]);
    }
}
?>
