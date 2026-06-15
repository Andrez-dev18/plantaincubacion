<?php

require_once __DIR__ . '/../repositories/SecuenciaBaseProyeccionRepository.php';

/**
 * Servicio de Secuencia Base Proyección
 * Lógica de negocio para gestión de secuencias base
 * Completamente independiente del módulo de reportes
 */
class SecuenciaBaseProyeccionService {
    private $repository;

    public function __construct($db) {
        $this->repository = new SecuenciaBaseProyeccionRepository($db);
    }

    /**
     * Lista todas las proyecciones disponibles
     */
    public function listarProyecciones() {
        return $this->repository->obtenerProyecciones();
    }

    /**
     * Obtiene datos de ccosbase con paginación
     */
    public function obtenerDatosBase($proyeccion, $page = 1, $limit = 200) {
        if (!$this->validarProyeccion($proyeccion)) {
            throw new Exception('Proyección no encontrada');
        }
        
        $offset = ($page - 1) * $limit;
        return $this->repository->obtenerDatosBase($proyeccion, $offset, $limit);
    }

    /**
     * Cuenta total de registros en ccosbase para una proyección
     */
    public function contarDatosBase($proyeccion) {
        return $this->repository->contarDatosBase($proyeccion);
    }

    /**
     * Obtiene datos de ccosproy (secuencia generada) con paginación
     */
    public function obtenerDatosProyeccion($proyeccion, $page = 1, $limit = 200) {
        if (!$this->validarProyeccion($proyeccion)) {
            throw new Exception('Proyección no encontrada');
        }
        
        $offset = ($page - 1) * $limit;
        return $this->repository->obtenerDatosProyeccion($proyeccion, $offset, $limit);
    }

    /**
     * Cuenta total de registros en ccosproy para una proyección
     */
    public function contarDatosProyeccion($proyeccion) {
        return $this->repository->contarDatosProyeccion($proyeccion);
    }

    /**
     * Copia secuencia de una proyección a otra
     */
    public function copiarSecuencia($proyeccionOrigen, $proyeccionDestino) {
        if (!$proyeccionOrigen || !$proyeccionDestino) {
            throw new Exception('Proyecciones origen y destino son requeridas');
        }
        
        return $this->repository->copiarSecuencia($proyeccionOrigen, $proyeccionDestino);
    }

    /**
     * Crea la secuencia base (ETAPA 1)
     */
    public function crearSecuencia($proyeccion, $hastaCiclo = 13) {
        if (!$proyeccion) {
            throw new Exception('Proyección es requerida');
        }
        
        return $this->repository->crearSecuencia($proyeccion, $hastaCiclo);
    }

    /**
     * Crea el calendario (ETAPA 2)
     */
    public function crearCalendario($proyeccion) {
        if (!$proyeccion) {
            throw new Exception('Proyección es requerida');
        }
        
        return $this->repository->crearCalendario($proyeccion);
    }

    /**
     * Copia el calendario de una proyección a otra
     */
    public function copiarCalendario($proyeccionOrigen, $proyeccionDestino) {
        if (!$proyeccionOrigen || !$proyeccionDestino) {
            throw new Exception('Proyecciones origen y destino son requeridas');
        }
        
        return $this->repository->copiarCalendario($proyeccionOrigen, $proyeccionDestino);
    }

    /**
     * Guarda un registro de ccosbase
     */
    public function guardarBase($datos) {
        if (!isset($datos['proyeccion'])) {
            throw new Exception('Proyección es requerida');
        }
        
        return $this->repository->guardarBase($datos);
    }

    /**
     * Elimina un registro de ccosbase
     */
    public function eliminarBase($proyeccion, $secuencia) {
        if (!$proyeccion || $secuencia === null || $secuencia === '') {
            throw new Exception('Proyección y secuencia son requeridos');
        }
        
        return $this->repository->eliminarBase($proyeccion, $secuencia);
    }

    /**
     * Mueve un registro de ccosbase arriba o abajo
     */
    public function moverBase($proyeccion, $secuencia, $direccion) {
        if (!$proyeccion || $secuencia === null || $secuencia === '' || !$direccion) {
            throw new Exception('Proyección, secuencia y dirección son requeridos');
        }
        
        return $this->repository->moverBase($proyeccion, $secuencia, $direccion);
    }

    /**
     * Mueve una secuencia a una posición específica (drag & drop)
     */
    public function moverSecuenciaA($proyeccion, $secuenciaOrigen, $nuevaPosicion) {
        if (!$proyeccion || $secuenciaOrigen === null || $nuevaPosicion === null) {
            throw new Exception('Proyección, secuencia origen y nueva posición son requeridos');
        }
        
        return $this->repository->moverSecuenciaA($proyeccion, $secuenciaOrigen, $nuevaPosicion);
    }

    /**
     * Mueve una entrada en ccosproy intercambiando posiciones (drag & drop en modal)
     */
    public function moverProyeccion($proyeccion, $secuenciaOrigen, $secuenciaDestino, $posicion = 'despues') {
        if (!$proyeccion || $secuenciaOrigen === null || $secuenciaDestino === null) {
            throw new Exception('Proyección y secuencias son requeridas');
        }
        
        return $this->repository->moverProyeccion($proyeccion, $secuenciaOrigen, $secuenciaDestino, $posicion);
    }

    /**
     * Crea una nueva proyección
     */
    public function crearProyeccion($nombre, $indicador = 'PENDIENTE') {
        if (!$nombre) {
            throw new Exception('Nombre de proyección es requerido');
        }
        
        return $this->repository->crearProyeccion($nombre, $indicador);
    }

    /**
     * Edita una proyección existente
     * @param string $proyeccion Nombre actual
     * @param string $nuevoNombre Nuevo nombre
     * @param string|null $indicador Nuevo indicador (opcional)
     */
    public function editarProyeccion($proyeccion, $nuevoNombre, $indicador = null) {
        if (!$proyeccion || !$nuevoNombre) {
            throw new Exception('Proyección y nuevo nombre son requeridos');
        }
        
        return $this->repository->editarProyeccion($proyeccion, $nuevoNombre, $indicador);
    }

    /**
     * Elimina una proyección
     */
    public function eliminarProyeccion($proyeccion) {
        if (!$proyeccion) {
            throw new Exception('Proyección es requerida');
        }
        
        return $this->repository->eliminarProyeccion($proyeccion);
    }

    /**
     * Obtiene lista de galpones disponibles
     */
    public function obtenerGalpones() {
        return $this->repository->obtenerGalpones();
    }

    /**
     * Obtiene el último registro de una granja/galpón
     */
    public function obtenerUltimoRegistroGranjaGalpon($codigo, $galpon) {
        if (!$codigo || !$galpon) {
            throw new Exception('Código y galpón son requeridos');
        }
        
        return $this->repository->obtenerUltimoRegistroGranjaGalpon($codigo, $galpon);
    }

    /**
     * Obtiene calendario de una proyección
     */
    public function obtenerCalendario($proyeccion) {
        if (!$proyeccion) {
            throw new Exception('Proyección es requerida');
        }
        
        return $this->repository->obtenerCalendario($proyeccion);
    }

    /**
     * Obtiene calendario con paginación
     */
    public function obtenerCalendarioPaginado($proyeccion, $page = 1, $limit = 100) {
        if (!$proyeccion) {
            throw new Exception('Proyección es requerida');
        }
        
        $offset = ($page - 1) * $limit;
        $datos = $this->repository->obtenerCalendarioPaginado($proyeccion, $offset, $limit);
        $total = $this->repository->contarCalendario($proyeccion);
        
        return [
            'data' => $datos,
            'pagination' => [
                'current_page' => (int)$page,
                'per_page' => (int)$limit,
                'total' => $total,
                'total_pages' => ceil($total / $limit)
            ]
        ];
    }

    /**
     * Valida si existe una proyección
     */
    private function validarProyeccion($proyeccion) {
        $proyecciones = $this->listarProyecciones();
        $nombres = array_map(function ($item) {
            return $item['nombre'] ?? null;
        }, $proyecciones);

        return in_array($proyeccion, $nombres, true);
    }
}
?>
