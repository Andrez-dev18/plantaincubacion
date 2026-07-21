<?php
/**
 * Rutas — /api/logs/*
 */
return function($container) {
    $ctrl = $container['controllers']['logs_sistema'];

    $method = $_SERVER['REQUEST_METHOD'];
    $path   = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

    $basePath = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'])), '/');
    if ($basePath && strpos($path, $basePath) === 0) {
        $path = substr($path, strlen($basePath));
    }
    if (empty($path)) { $path = '/'; }

    if (($method === 'GET' || $method === 'POST') && preg_match('#^/api/logs/listar$#', $path))   { $ctrl->listar();   exit; }
    if ($method === 'POST' && preg_match('#^/api/logs/guardar$#', $path))                         { $ctrl->guardar();  exit; }
    if (($method === 'GET' || $method === 'POST') && preg_match('#^/api/logs/filtros$#', $path))  { $ctrl->filtros();  exit; }

    return false;
};
