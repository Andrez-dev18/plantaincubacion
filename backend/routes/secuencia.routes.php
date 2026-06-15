<?php

/**
 * Rutas del Módulo de Secuencia Base Proyección
 * Completamente independiente del módulo de reportes
 */

require_once __DIR__ . '/../controllers/SecuenciaBaseProyeccionController.php';

return function ($container) {
    // Obtener conexión de base de datos del container
    $db = $container['db'];

    // Instanciar controlador
    $controller = new SecuenciaBaseProyeccionController($db);

    // Obtener método y ruta
    $method = $_SERVER['REQUEST_METHOD'];
    $uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    
    // Remover los posibles prefijos
    $uri = str_replace('/plantaincubacion/backend/index.php', '', $uri);
    $uri = str_replace('/plantaincubacion/backend', '', $uri);
    $uri = str_replace('/backend', '', $uri);

    // ========================================
    // RUTAS GET
    // ========================================
    
    if ($method === 'GET' && $uri === '/api/secuencia/proyecciones') {
        $controller->listarProyecciones();
        exit;
    }
    
    if ($method === 'GET' && $uri === '/api/secuencia/base') {
        $controller->obtenerDatosBase();
        exit;
    }
    
    if ($method === 'GET' && $uri === '/api/secuencia/proyeccion') {
        $controller->obtenerDatosProyeccion();
        exit;
    }
    
    // ========================================
    // RUTAS POST
    // ========================================
    
    if ($method === 'POST' && $uri === '/api/secuencia/copiar-secuencia') {
        $controller->copiarSecuencia();
        exit;
    }
    
    if ($method === 'POST' && $uri === '/api/secuencia/crear-secuencia') {
        $controller->crearSecuencia();
        exit;
    }
    
    if ($method === 'POST' && $uri === '/api/secuencia/crear-calendario') {
        $controller->crearCalendario();
        exit;
    }
    
    if ($method === 'POST' && $uri === '/api/secuencia/copiar-calendario') {
        $controller->copiarCalendario();
        exit;
    }
    
    if ($method === 'POST' && $uri === '/api/secuencia/guardar-base') {
        $controller->guardarBase();
        exit;
    }
    
    if ($method === 'POST' && $uri === '/api/secuencia/eliminar-base') {
        $controller->eliminarBase();
        exit;
    }
    
    if ($method === 'POST' && $uri === '/api/secuencia/mover-base') {
        $controller->moverBase();
        exit;
    }
    
    if ($method === 'POST' && $uri === '/api/secuencia/mover-secuencia-a') {
        $controller->moverSecuenciaA();
        exit;
    }
    
    if ($method === 'POST' && $uri === '/api/secuencia/mover-proyeccion') {
        $controller->moverProyeccion();
        exit;
    }
    
    if ($method === 'POST' && $uri === '/api/secuencia/nueva-proyeccion') {
        $controller->nuevaProyeccion();
        exit;
    }
    
    if ($method === 'POST' && $uri === '/api/secuencia/editar-proyeccion') {
        $controller->editarProyeccion();
        exit;
    }
    
    if ($method === 'POST' && $uri === '/api/secuencia/eliminar-proyeccion') {
        $controller->eliminarProyeccion();
        exit;
    }
    
    if ($method === 'GET' && $uri === '/api/secuencia/galpones') {
        $controller->obtenerGalpones();
        exit;
    }

    if ($method === 'GET' && $uri === '/api/secuencia/ultimo-registro-granja-galpon') {
        $controller->obtenerUltimoRegistroGranjaGalpon();
        exit;
    }

    if ($method === 'GET' && $uri === '/api/secuencia/calendario') {
        $controller->obtenerCalendario();
        exit;
    }

    
};
