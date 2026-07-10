<?php
/**
 * Rutas de Menú Dinámico
 */

return function($container) {
    $request = $_SERVER['REQUEST_METHOD'];
    
    // Soporte para method override (si es necesario)
    if ($request === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true);
        if (isset($input['_method'])) {
            $request = strtoupper($input['_method']);
        } elseif (isset($_SERVER['HTTP_X_HTTP_METHOD_OVERRIDE'])) {
            $request = strtoupper($_SERVER['HTTP_X_HTTP_METHOD_OVERRIDE']);
        }
    }
    
    // Extraer path
    $path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    $query = parse_url($_SERVER['REQUEST_URI'], PHP_URL_QUERY);
    
    // Si la ruta viene en query string, usarla
    if ($query && strpos($query, '/') === 0) {
        $path = $query;
    }
    
    // Normalizar path
    $path = preg_replace('#^.*/backend/index\.php#', '', $path);
    $path = preg_replace('#^.*/backend#', '', $path);

    $ctrl = $container['controllers']['dashboard_modulo'];

    // GET /menu/obtener - Obtener menú jerárquico del usuario logueado
    if ($request === 'GET' && preg_match('#^/?menu/obtener/?$#', $path)) {
        $ctrl->obtener();
        exit;
    }

    // GET /menu/listar - Listar todos los módulos
    if ($request === 'GET' && preg_match('#^/?menu/listar/?$#', $path)) {
        $ctrl->listar();
        exit;
    }

    // GET /menu/grupos - Listar grupos de módulos
    if ($request === 'GET' && preg_match('#^/?menu/grupos/?$#', $path)) {
        $ctrl->listarGrupos();
        exit;
    }

    // GET /menu/obtenerPorId - Obtener un módulo por su ID
    if ($request === 'GET' && preg_match('#^/?menu/obtenerPorId/?$#', $path)) {
        $ctrl->obtenerModulosId();
        exit;
    }

    // POST /menu/guardar - Guardar un módulo (crear o editar)
    if ($request === 'POST' && preg_match('#^/?menu/guardar/?$#', $path)) {
        $ctrl->guardar();
        exit;
    }

    // POST /menu/eliminar - Eliminar un módulo
    if ($request === 'POST' && preg_match('#^/?menu/eliminar/?$#', $path)) {
        $ctrl->eliminar();
        exit;
    }
};
