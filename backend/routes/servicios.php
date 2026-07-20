<?php
/**
 * Rutas — /api/servicios/*
 */
return function($container) {
    $ctrl = $container['controllers']['servicios'];

    $method = $_SERVER['REQUEST_METHOD'];
    $path   = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

    $basePath = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'])), '/');
    if ($basePath && strpos($path, $basePath) === 0) {
        $path = substr($path, strlen($basePath));
    }
    if (empty($path)) { $path = '/'; }

    if (($method === 'GET' || $method === 'POST') && preg_match('#^/api/servicios/listar$#', $path))             { $ctrl->listar();           exit; }
    if ($method === 'POST' && preg_match('#^/api/servicios/obtener$#', $path))            { $ctrl->obtener();          exit; }
    if ($method === 'POST' && preg_match('#^/api/servicios/guardar$#', $path))            { $ctrl->guardar();          exit; }
    if ($method === 'POST' && preg_match('#^/api/servicios/eliminar$#', $path))           { $ctrl->eliminar();         exit; }

    return false;
};
?>
