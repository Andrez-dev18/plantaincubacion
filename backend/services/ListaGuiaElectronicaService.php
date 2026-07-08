<?php
/**
 * Servicio para el módulo de Lista de Guías de Remisión Electrónica
 */
require_once __DIR__ . '/../repositories/ListaGuiaElectronicaRepository.php';

class ListaGuiaElectronicaService
{
    private $repo;

    public function __construct($db)
    {
        $this->repo = new ListaGuiaElectronicaRepository($db);
    }

    /**
     * Obtiene las guías filtradas delegando en el repositorio
     */
    public function listarGuias(?string $search = null, ?string $almacen = null, ?string $desde = null, ?string $hasta = null, ?string $serie = null, ?string $numero = null): array
    {
        return $this->repo->listarGuias($search, $almacen, $desde, $hasta, $serie, $numero);
    }

    /**
     * Obtiene el detalle de ítems de una guía de remisión
     */
    public function obtenerDetalleGuia($treg): array
    {
        return $this->repo->obtenerDetalleGuia($treg);
    }

    /**
     * Obtiene la cabecera de una guía por su código de registro único treg
     */
    public function obtenerGuiaPorTreg(string $treg): ?array
    {
        return $this->repo->obtenerGuiaPorTreg($treg);
    }
}
