<?php
/**
 * Rutas — /api/contribuyentes/*
 */
return function($container) {
    $ctrl = $container['controllers']['contribuyentes'];

    $method = $_SERVER['REQUEST_METHOD'];
    $path   = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

    $basePath = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'])), '/');
    if ($basePath && strpos($path, $basePath) === 0) {
        $path = substr($path, strlen($basePath));
    }
    if (empty($path)) { $path = '/'; }

    if (($method === 'GET' || $method === 'POST') && preg_match('#^/api/contribuyentes/listar$#', $path))             { $ctrl->listar();           exit; }
    if ($method === 'POST' && preg_match('#^/api/contribuyentes/obtener$#', $path))            { $ctrl->obtener();          exit; }
    if ($method === 'POST' && preg_match('#^/api/contribuyentes/guardar$#', $path))            { $ctrl->guardar();          exit; }
    if ($method === 'POST' && preg_match('#^/api/contribuyentes/toggle$#', $path))             { $ctrl->toggleActivo();     exit; }
    if ($method === 'POST' && preg_match('#^/api/contribuyentes/eliminar$#', $path))           { $ctrl->eliminar();         exit; }

    return false;
};
?>
