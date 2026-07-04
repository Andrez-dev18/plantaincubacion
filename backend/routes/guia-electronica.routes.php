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
};
