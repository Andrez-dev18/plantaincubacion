<?php
/**
 * Rutas — /api/rol/*
 */
return function($container) {
    $ctrl = $container['controllers']['rol'];

    $method = $_SERVER['REQUEST_METHOD'];
    $path   = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

    $basePath = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'])), '/');
    if ($basePath && strpos($path, $basePath) === 0) {
        $path = substr($path, strlen($basePath));
    }
    if (empty($path)) { $path = '/'; }

    if (($method === 'GET' || $method === 'POST') && preg_match('#^/api/rol/listar$#', $path))             { $ctrl->listar();           exit; }
    if (($method === 'GET' || $method === 'POST') && preg_match('#^/api/rol/arbol$#', $path))              { $ctrl->obtenerModulosArbol(); exit; }
    if ($method === 'POST' && preg_match('#^/api/rol/obtener$#', $path))            { $ctrl->obtener();          exit; }
    if ($method === 'POST' && preg_match('#^/api/rol/guardar$#', $path))            { $ctrl->guardar();          exit; }
    if ($method === 'POST' && preg_match('#^/api/rol/toggle$#', $path))             { $ctrl->toggleActivo();     exit; }
    if ($method === 'POST' && preg_match('#^/api/rol/eliminar$#', $path))           { $ctrl->eliminar();         exit; }

    return false;
};
