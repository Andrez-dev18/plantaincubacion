<?php
/**
 * Rutas unificadas — /api/usuario/*
 */
return function($container) {
    // Usaremos el controlador unificado
    $ctrl = $container['controllers']['usuario'];

    $method = $_SERVER['REQUEST_METHOD'];
    $path   = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

    $basePath = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'])), '/');
    if ($basePath && strpos($path, $basePath) === 0) {
        $path = substr($path, strlen($basePath));
    }
    if (empty($path)) { $path = '/'; }

    // Endpoints consumidos por JS / Datatables
    if ($method === 'POST' && preg_match('#^/api/usuario/listar$#', $path)) { $ctrl->listarServerSide(); exit; }
    if ($method === 'POST' && preg_match('#^/api/usuario/obtener$#', $path)) { $ctrl->obtener(); exit; }
    if ($method === 'POST' && preg_match('#^/api/usuario/guardar$#', $path)) { $ctrl->guardar(); exit; }
    if ($method === 'POST' && preg_match('#^/api/usuario/toggle$#', $path)) { $ctrl->toggleEstado(); exit; }
    if ($method === 'POST' && preg_match('#^/api/usuario/reset-password$#', $path)) { $ctrl->resetPassword(); exit; }
    
    // Catálogos
    if ($method === 'GET'  && preg_match('#^/api/usuario/roles$#', $path)) { $ctrl->obtenerRoles(); exit; }

    return false;
};