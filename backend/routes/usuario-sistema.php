<?php
/**
 * Rutas — /api/usuario-sistema/*
 */
return function($container) {
    $ctrl = $container['controllers']['usuarioSistema'];

    $method = $_SERVER['REQUEST_METHOD'];
    $path   = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

    $basePath = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'])), '/');
    if ($basePath && strpos($path, $basePath) === 0) {
        $path = substr($path, strlen($basePath));
    }
    if (empty($path)) { $path = '/'; }

    if ($method === 'GET'  && preg_match('#^/api/usuario-sistema/listar$#', $path)) { $ctrl->listar();           exit; }
    if ($method === 'POST' && preg_match('#^/api/usuario-sistema/crear$#',  $path)) { $ctrl->crear();            exit; }
    if ($method === 'POST' && preg_match('#^/api/usuario-sistema/actualizar$#', $path)) { $ctrl->actualizar();   exit; }
    if ($method === 'POST' && preg_match('#^/api/usuario-sistema/toggle$#', $path)) { $ctrl->toggleActivo();    exit; }
    if ($method === 'POST' && preg_match('#^/api/usuario-sistema/password$#', $path)) { $ctrl->cambiarPassword(); exit; }

    return false;
};
