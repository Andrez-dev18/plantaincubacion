<?php
/**
 * Servicio para el módulo de Lista de Guías de Remisión Electrónica
 */
require_once __DIR__ . '/../repositories/ListaGuiaElectronicaRepository.php';
require_once __DIR__ . '/LogsSistemaService.php';

class ListaGuiaElectronicaService
{
    private $repo;
    private $logsService;

    public function __construct($db)
    {
        $this->repo = new ListaGuiaElectronicaRepository($db);
        $this->logsService = new LogsSistemaService($db);
    }

    /**
     * Obtiene las guías filtradas delegando en el repositorio
     */
    public function listarGuias(?string $search = null, ?string $almacen = null, ?string $desde = null, ?string $hasta = null, ?string $serie = null, ?string $numero = null, ?int $start = null, ?int $length = null): array
    {
        return $this->repo->listarGuias($search, $almacen, $desde, $hasta, $serie, $numero, $start, $length);
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

    /**
     * Elimina una guía por su código de registro treg
     */
    public function eliminarGuia(string $treg): bool
    {
        $datosPrevios = $this->repo->obtenerGuiaPorTreg($treg);
        $resultado = $this->repo->eliminarGuia($treg);

        if ($resultado) {
            $serieNumero = isset($datosPrevios['serie'], $datosPrevios['numero']) 
                ? "{$datosPrevios['serie']}-{$datosPrevios['numero']}" 
                : $treg;

            $this->logsService->logAction(
                'DELETE',
                'guia',
                $treg,
                $datosPrevios,
                null,
                "Guía electrónica {$serieNumero} (treg: {$treg}) eliminada."
            );
        }

        return $resultado;
    }
}

