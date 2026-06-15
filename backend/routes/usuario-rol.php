<?php
/**
 * Rutas — /api/usuario-rol/*
 */
return function($container) {
    $ctrl = $container['controllers']['usuarioRol'];

    $method = $_SERVER['REQUEST_METHOD'];
    $path   = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

    $basePath = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'])), '/');
    if ($basePath && strpos($path, $basePath) === 0) {
        $path = substr($path, strlen($basePath));
    }
    if (empty($path)) { $path = '/'; }

    if ($method === 'GET'  && preg_match('#^/api/usuario-rol/usuarios-con-roles$#', $path)) { $ctrl->usuariosConRoles(); exit; }
    if ($method === 'GET'  && preg_match('#^/api/usuario-rol/roles-activos$#', $path))      { $ctrl->rolesActivos();     exit; }
    if ($method === 'GET'  && preg_match('#^/api/usuario-rol/roles-codigo$#', $path))       { $ctrl->rolesCodigo();      exit; }
    if ($method === 'POST' && preg_match('#^/api/usuario-rol/guardar$#', $path))            { $ctrl->guardar();          exit; }

    return false;
};
