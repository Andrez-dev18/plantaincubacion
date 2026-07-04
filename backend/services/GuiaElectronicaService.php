<?php
/**
 * Servicio para el módulo de Guías de Remisión Electrónica
 */
require_once __DIR__ . '/../repositories/GuiaElectronicaRepository.php';

class GuiaElectronicaService
{
    private $repo;

    public function __construct($db)
    {
        $this->repo = new GuiaElectronicaRepository($db);
    }

    public function listarAlmacenes(): array
    {
        return $this->repo->obtenerAlmacenes();
    }

    public function listarTiposTransporte(): array
    {
        return $this->repo->obtenerTiposTransporte();
    }

    public function listarTransportistas(?string $search = null): array
    {
        return $this->repo->obtenerTransportistas($search);
    }

    public function listarConductores(?string $search = null): array{
        return $this->repo->obtenerConductores($search);
    }

    public function listarCamiones(?string $search = null): array
    {
        return $this->repo->obtenerCamiones($search);
    }

    public function listarClientes(?string $search = null): array
    {
        return $this->repo->obtenerClientes($search);
    }
}
