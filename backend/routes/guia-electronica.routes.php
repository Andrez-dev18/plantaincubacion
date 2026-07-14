<?php
/**
 * Rutas del Módulo de Guías de Remisión Electrónica
 */
require_once __DIR__ . '/../controllers/GuiaElectronicaController.php';

return function ($container) {
    $db = $container['db'];
    
    // Obtener el controlador del contenedor de dependencias
    $controller = $container['controllers']['guiaElectronica'] ?? new GuiaElectronicaController($db);

    $method = $_SERVER['REQUEST_METHOD'];
    $uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

    // Limpiar URI de prefijos locales
    $uri = str_replace(['/plantaincubacion/backend/index.php', '/plantaincubacion/backend', '/backend'], '', $uri);
    $uri = preg_replace('#/+#', '/', $uri);
    $uri = rtrim(trim($uri), '/');

    // GET /api/guia-electronica/zonas
    if ($method === 'GET' && $uri === '/api/guia-electronica/zonas') {
        $controller->getZonas();
        exit;
    }

    // GET /api/guia-electronica/tipos-transporte
    if ($method === 'GET' && $uri === '/api/guia-electronica/tipos-transporte') {
        $controller->getTiposTransporte();
        exit;
    }

    // GET /api/guia-electronica/transportistas
    if ($method === 'GET' && $uri === '/api/guia-electronica/transportistas') {
        $controller->getTransportistas();
        exit;
    }

     // GET /api/guia-electronica/conductores
    if ($method === 'GET' && $uri === '/api/guia-electronica/conductores') {
        $controller->getConductores();
        exit;
    }

    // GET /api/guia-electronica/camiones
    if ($method === 'GET' && $uri === '/api/guia-electronica/camiones') {
        $controller->getCamiones();
        exit;
    }

    // GET /api/guia-electronica/clientes
    if ($method === 'GET' && $uri === '/api/guia-electronica/clientes') {
        $controller->getClientes();
        exit;
    }

    // GET /api/guia-electronica/articulos
    if ($method === 'GET' && $uri === '/api/guia-electronica/articulos') {
        $controller->getArticulos();
        exit;
    }

    // GET /api/guia-electronica/lotes
    if ($method === 'GET' && $uri === '/api/guia-electronica/lotes') {
        $controller->getLotes();
        exit;
    }

    // GET /api/guia-electronica/series
    if ($method === 'GET' && $uri === '/api/guia-electronica/series') {
        $controller->getSeries();
        exit;
    }

    // GET /api/guia-electronica/motivos-traslado
    if ($method === 'GET' && $uri === '/api/guia-electronica/motivos-traslado') {
        $controller->getMotivosTraslado();
        exit;
    }

    // GET /api/guia-electronica/clientes/direccion
    if ($method === 'GET' && $uri === '/api/guia-electronica/clientes/direccion') {
        $controller->getDireccionCliente();
        exit;
    }

    // GET /api/guia-electronica/cencos
    if ($method === 'GET' && $uri === '/api/guia-electronica/cencos') {
        $controller->getCencos();
        exit;
    }

    // GET /api/guia-electronica/galpones
    if ($method === 'GET' && $uri === '/api/guia-electronica/galpones') {
        $controller->getGalpones();
        exit;
    }

    // POST /api/guia-electronica/guardar
    if ($method === 'POST' && $uri === '/api/guia-electronica/guardar') {
        $controller->guardarGuia();
        exit;
    }

    // GET /api/guia-electronica/consultar
    if ($method === 'GET' && $uri === '/api/guia-electronica/consultar') {
        $controller->consultarGuia();
        exit;
    }
};
