<?php
/**
 * ModuloService
 *
 * Servicio para listar modulos por programa
 */

require_once __DIR__ . '/../repositories/ModuloRepository.php';

class ModuloService {
    private $repo;

    public function __construct($moduloRepository) {
        $this->repo = $moduloRepository;
    }

    /**
     * Listar modulos por programa
     *
     * @param string $programa
     * @return array
     */
    public function listar($programa) {
        return $this->repo->findAllByPrograma($programa);
    }

    /**
     * Listar programas disponibles
     *
     * @return array
     */
    public function listarProgramas() {
        return $this->repo->findProgramas();
    }
}
