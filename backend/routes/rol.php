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

    if ($method === 'GET'  && preg_match('#^/api/rol/listar$#', $path))             { $ctrl->listar();           exit; }
    if ($method === 'GET'  && preg_match('#^/api/rol/programas$#', $path))          { $ctrl->listarProgramas();  exit; }
    if ($method === 'GET'  && preg_match('#^/api/rol/menus-disponibles$#', $path))  { $ctrl->menusDisponibles(); exit; }
    if ($method === 'GET'  && preg_match('#^/api/rol/modulos$#', $path))            { $ctrl->obtenerModulos();   exit; }
    if ($method === 'POST' && preg_match('#^/api/rol/crear$#', $path))              { $ctrl->crear();            exit; }
    if ($method === 'POST' && preg_match('#^/api/rol/actualizar$#', $path))         { $ctrl->actualizar();       exit; }
    if ($method === 'POST' && preg_match('#^/api/rol/toggle$#', $path))             { $ctrl->toggleActivo();     exit; }
    if ($method === 'POST' && preg_match('#^/api/rol/guardar-modulos$#', $path))    { $ctrl->guardarModulos();   exit; }
    if ($method === 'POST' && preg_match('#^/api/rol/eliminar$#', $path))           { $ctrl->eliminar();         exit; }

    return false;
};
