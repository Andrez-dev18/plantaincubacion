<?php
/**
 * Repositorio para la gestión de Guías de Remisión Electrónica
 */
class GuiaElectronicaRepository
{
    private $db;

    public function __construct($db)
    {
        $this->db = $db;
    }

    public function obtenerAlmacenes(): array
    {
        $sql = "SELECT codalm AS codigo, descri AS descripcion, descri AS descri 
                FROM alma 
                WHERE codalm IS NOT NULL AND codalm != ''
                ORDER BY codalm ASC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function obtenerTiposTransporte(): array
    {
        $sql = "SELECT cod AS codigo, nom AS descripcion, cod, nom 
                FROM dtipo_transporte 
                WHERE est = 'A'
                ORDER BY cod ASC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function obtenerTransportistas(?string $search = null): array
    {
        $sql = "SELECT ruc, nombre, nombrecomercial, tuc, testado 
                FROM dtransportista WHERE testado = 'A'";
        
        $params = [];
        if ($search !== null && trim($search) !== '') {
            $sql .= " WHERE ruc LIKE ? OR nombre LIKE ? OR nombrecomercial LIKE ?";
            $term = '%' . trim($search) . '%';
            $params = [$term, $term, $term];
        }
        
        $sql .= " ORDER BY nombre ASC LIMIT 100";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function obtenerConductores(?string $search = null): array
    {
        $sql = "SELECT dni, nombre, licencia, testado 
                FROM dchofer WHERE testado = 'A'";
        
        $params = [];
        if ($search !== null && trim($search) !== '') {
            $sql .= " WHERE dni LIKE ? OR nombre LIKE ?";
            $term = '%' . trim($search) . '%';
            $params = [$term, $term];
        }
        
        $sql .= " ORDER BY nombre ASC LIMIT 100";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);

    }

    public function obtenerCamiones(?string $search = null): array
    {
        $sql = "SELECT placa, marca, cinscripcion, confvehicular, ntm, soat, fechaisoat, testado 
                FROM dcamion 
                WHERE testado = 'A'";
        
        $params = [];
        if ($search !== null && trim($search) !== '') {
            $sql .= " AND (placa LIKE ? OR marca LIKE ?)";
            $term = '%' . trim($search) . '%';
            $params = [$term, $term];
        }
        
        $sql .= " ORDER BY placa ASC LIMIT 100";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function obtenerClientes(?string $search = null): array
    {
        $sql = "SELECT codigo, nombre, direcc
                FROM ccte";
        
        $params = [];
        if ($search !== null && trim($search) !== '') {
            $sql .= " WHERE codigo LIKE ? OR nombre LIKE ?";
            $term = '%' . trim($search) . '%';
            $params = [$term, $term];
        }
        
        $sql .= " ORDER BY codigo ASC LIMIT 100";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
