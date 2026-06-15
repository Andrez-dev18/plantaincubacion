<?php
/**
 * ModuloRepository
 *
 * Repositorio para listar modulos por programa
 */

class ModuloRepository {
    private $conn;

    public function __construct($db) {
        $this->conn = $db;
    }

    /**
     * Listar modulos por programa con orden jerarquico
     *
     * @param string $programa
     * @return array
     */
    public function findAllByPrograma($programa) {
        try {
                $sql = "SELECT cod_mod, nivel0, nivel1, nivel2, nivel3, nom_mod
                    FROM modulos
                    WHERE LOWER(programa) = LOWER(?)
                    ORDER BY nivel0, nivel1, nivel2, nivel3";

            $stmt = $this->conn->prepare($sql);
            $stmt->execute([$programa]);

            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error en ModuloRepository::findAllByPrograma: " . $e->getMessage());
            throw new Exception("Error al obtener modulos: " . $e->getMessage());
        }
    }

    /**
     * Listar programas disponibles
     *
     * @return array
     */
    public function findProgramas() {
        try {
            $sql = "SELECT id_programa, nombre
                    FROM amd_programas
                    ORDER BY nombre";

            $stmt = $this->conn->prepare($sql);
            $stmt->execute();

            $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Formatear para mantener compatibilidad con el código existente
            return array_map(function($row) {
                return [
                    'id_programa' => $row['id_programa'],
                    'programa' => $row['nombre'],
                    'nombre' => $row['nombre']
                ];
            }, $result);
        } catch (PDOException $e) {
            error_log("Error en ModuloRepository::findProgramas: " . $e->getMessage());
            throw new Exception("Error al obtener programas: " . $e->getMessage());
        }
    }
}
