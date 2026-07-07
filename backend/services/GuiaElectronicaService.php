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

    public function listarConductores(?string $search = null, ?string $rucTransportista = null, bool $mostrarTodos = false): array{
        return $this->repo->obtenerConductores($search, $rucTransportista, $mostrarTodos);
    }

    public function listarCamiones(?string $search = null, ?string $rucTransportista = null): array
    {
        return $this->repo->obtenerCamiones($search, $rucTransportista);
    }

    public function listarClientes(?string $search = null): array
    {
        return $this->repo->obtenerClientes($search);
    }

    public function listarArticulos(?string $search = null): array
    {
        return $this->repo->obtenerArticulos($search);
    }

    public function listarLotes(string $almacen, string $codigoArticulo, int $anio): array
    {
        return $this->repo->obtenerLotesPorArticulo($almacen, $codigoArticulo, $anio);
    }

    public function listarSeries(string $almacen, string $cliente): array
    {
        return $this->repo->obtenerSeriesConCorrelativo($almacen, $cliente);
    }

    public function listarMotivosTraslado(): array
    {
        return $this->repo->obtenerMotivosTraslado();
    }

    public function obtenerDireccionCliente(string $codigoCliente): ?array
    {
        return $this->repo->obtenerDireccionCliente($codigoCliente);
    }

    public function listarCencos(?string $search = null): array
    {
        return $this->repo->obtenerCencos($search);
    }

    public function listarGalponesPorCencos(string $cencos): array
    {
        return $this->repo->obtenerGalponesPorCencos($cencos);
    }

    public function guardarGuia(array $cabecera, array $detalle): bool
    {
        return $this->repo->guardarGuia($cabecera, $detalle);
    }
}
