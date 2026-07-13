<?php
/**
 * Rutas para Configuración de APIs — /api/config-api/*
 */
return function($container) {
    $ctrl = $container['controllers']['configApi'];

    $method = $_SERVER['REQUEST_METHOD'];
    $path   = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

    $basePath = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'])), '/');
    if ($basePath && strpos($path, $basePath) === 0) {
        $path = substr($path, strlen($basePath));
    }
    if (empty($path)) { $path = '/'; }

    // Endpoints
    if ($method === 'POST' && preg_match('#^/api/config-api/listar$#', $path)) { $ctrl->listar(); exit; }
    if ($method === 'POST' && preg_match('#^/api/config-api/obtener$#', $path)) { $ctrl->obtener(); exit; }
    if ($method === 'POST' && preg_match('#^/api/config-api/guardar$#', $path)) { $ctrl->guardar(); exit; }
    if ($method === 'POST' && preg_match('#^/api/config-api/actualizar-token$#', $path)) { $ctrl->actualizarToken(); exit; }
    if ($method === 'POST' && preg_match('#^/api/config-api/eliminar$#', $path)) { $ctrl->eliminar(); exit; }

    return false;
};
