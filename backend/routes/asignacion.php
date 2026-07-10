<?php
/**
 * Rutas — /api/asignacion/*
 */
return function($container) {
    $ctrl = $container['controllers']['asignacion'];

    $method = $_SERVER['REQUEST_METHOD'];
    $path   = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

    $basePath = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'])), '/');
    if ($basePath && strpos($path, $basePath) === 0) {
        $path = substr($path, strlen($basePath));
    }
    if (empty($path)) { $path = '/'; }

    if (($method === 'GET' || $method === 'POST') && preg_match('#^/api/asignacion/datatable$#', $path)) {
        $ctrl->datatable();
        exit;
    }
    if (($method === 'GET' || $method === 'POST') && preg_match('#^/api/asignacion/obtener$#', $path)) {
        $ctrl->obtenerDatosParaRoles();
        exit;
    }
    if ($method === 'POST' && preg_match('#^/api/asignacion/guardar$#', $path)) {
        $ctrl->guardar();
        exit;
    }

    return false;
};
?>
