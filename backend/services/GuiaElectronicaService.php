<?php
/**
 * Servicio para el módulo de Guías de Remisión Electrónica
 */
require_once __DIR__ . '/../repositories/GuiaElectronicaRepository.php';

class GuiaElectronicaService
{
    private $repository;

    public function __construct($db)
    {
        $this->repository = new GuiaElectronicaRepository($db);
    }

    /**
     * Lista todos los almacenes/zonas
     * 
     * @return array
     */
    public function listarAlmacenes(): array
    {
        return $this->repository->obtenerAlmacenes();
    }

    public function listarTiposTransporte(): array
    {
        return $this->repository->obtenerTiposTransporte();
    }
}
