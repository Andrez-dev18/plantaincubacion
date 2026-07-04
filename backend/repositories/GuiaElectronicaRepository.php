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
}
