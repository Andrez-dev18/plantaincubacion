<?php

require_once __DIR__ . '/../repositories/ReporteTransaccionesRepository.php';

class ReporteTransaccionService{
    private $repository;

    public function __construct($db) {
        $this->repository = new ReporteTransaccionesRepository($db);
    }


    //FILTROS
    public function listarTransacciones() {
        return $this->repository->obtenerTransaccionesUnicas();
    }

    public function listarAlmacenes() {
        return $this->repository->obtenerAlmacenesUnicos();
    }

    public function listarCencos() {
        return $this->repository->obtenerCencosUnicos();
    }

    public function listarCuentasCorrientes() {
        return $this->repository->obtenerCuentasCorrientesUnicas();
    }

    public function listarLineas() {
        return $this->repository->obtenerLineasUnicas();
    }

    public function listarArticulos() {
        return $this->repository->obtenerArticulosUnicos();
    }

    //END FILTROS

    //REPORTE
    public function generarReporteGrid($filtros) {
        return $this->repository->obtenerReporteTransacciones($filtros);
    }

}

