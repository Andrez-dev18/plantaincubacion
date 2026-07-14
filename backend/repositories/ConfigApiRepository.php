<?php
/**
 * ConfigApiRepository
 * 
 * Repositorio para la gestión de APIs en el sistema.
 * Tabla: config_api
 */

class ConfigApiRepository {
    private $conn;

    public function __construct($db) {
        $this->conn = $db;
    }

    /**
     * Obtener todas las APIs (excluyendo el token por seguridad)
     * 
     * @return array
     */
    public function findAll() {
        try {
            $sql = "SELECT id, nom, ruta FROM config_api ORDER BY id ASC";
            $stmt = $this->conn->prepare($sql);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error en ConfigApiRepository::findAll: " . $e->getMessage());
            throw new Exception("Error al obtener las APIs: " . $e->getMessage());
        }
    }

    /**
     * Obtener una API por su ID (excluyendo el token por seguridad)
     * 
     * @param int $id
     * @return array|null
     */
    public function findById($id) {
        try {
            $sql = "SELECT id, nom, ruta FROM config_api WHERE id = ?";
            $stmt = $this->conn->prepare($sql);
            $stmt->execute([$id]);
            return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
        } catch (PDOException $e) {
            error_log("Error en ConfigApiRepository::findById: " . $e->getMessage());
            throw new Exception("Error al obtener la API: " . $e->getMessage());
        }
    }

    /**
     * Crear una nueva API con su token
     * 
     * @param string $nom
     * @param string $ruta
     * @param string $token
     * @return int ID generado
     */
    public function create($nom, $ruta, $token) {
        try {
            $sql = "INSERT INTO config_api (nom, ruta, token) VALUES (?, ?, ?)";
            $stmt = $this->conn->prepare($sql);
            $stmt->execute([$nom, $ruta, $token]);
            return $this->conn->lastInsertId();
        } catch (PDOException $e) {
            error_log("Error en ConfigApiRepository::create: " . $e->getMessage());
            throw new Exception("Error al registrar la API: " . $e->getMessage());
        }
    }

    /**
     * Actualizar los datos de una API (excepto el token)
     * 
     * @param int $id
     * @param string $nom
     * @param string $ruta
     * @return bool
     */
    public function update($id, $nom, $ruta) {
        try {
            $sql = "UPDATE config_api SET nom = ?, ruta = ? WHERE id = ?";
            $stmt = $this->conn->prepare($sql);
            return $stmt->execute([$nom, $ruta, $id]);
        } catch (PDOException $e) {
            error_log("Error en ConfigApiRepository::update: " . $e->getMessage());
            throw new Exception("Error al actualizar la API: " . $e->getMessage());
        }
    }

    /**
     * Actualizar exclusivamente el token de una API
     * 
     * @param int $id
     * @param string $token
     * @return bool
     */
    public function updateToken($id, $token) {
        try {
            $sql = "UPDATE config_api SET token = ? WHERE id = ?";
            $stmt = $this->conn->prepare($sql);
            return $stmt->execute([$token, $id]);
        } catch (PDOException $e) {
            error_log("Error en ConfigApiRepository::updateToken: " . $e->getMessage());
            throw new Exception("Error al actualizar el token de la API: " . $e->getMessage());
        }
    }

    /**
     * Eliminar una API
     * 
     * @param int $id
     * @return bool
     */
    public function delete($id) {
        try {
            $sql = "DELETE FROM config_api WHERE id = ?";
            $stmt = $this->conn->prepare($sql);
            return $stmt->execute([$id]);
        } catch (PDOException $e) {
            error_log("Error en ConfigApiRepository::delete: " . $e->getMessage());
            throw new Exception("Error al eliminar la API: " . $e->getMessage());
        }
    }

    /**
     * Obtener credenciales completas (Ruta y Token) para uso interno del Backend
     */
    public function obtenerCredencialesNubeFact() {
        try {
            // Ajusta el 'nom' o 'id' según cómo lo hayas guardado en tu BD
            $sql = "SELECT ruta, token FROM config_api WHERE id = 1 LIMIT 1"; 
            $stmt = $this->conn->prepare($sql);
            $stmt->execute();
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error al obtener credenciales API: " . $e->getMessage());
            return null;
        }
    }
}
