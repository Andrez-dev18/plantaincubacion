<?php
/**
 * Rutas de Dashboard Modulos
 */

return function($container) {
    $request = $_SERVER['REQUEST_METHOD'];
    
    // Soporte para method override (para PUT y DELETE)
    if ($request === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true);
        if (isset($input['_method'])) {
            $request = strtoupper($input['_method']);
        } elseif (isset($_SERVER['HTTP_X_HTTP_METHOD_OVERRIDE'])) {
            $request = strtoupper($_SERVER['HTTP_X_HTTP_METHOD_OVERRIDE']);
        }
    }
    
    // Extraer path - soporta tanto /ruta como index.php?/ruta
    $path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    $query = parse_url($_SERVER['REQUEST_URI'], PHP_URL_QUERY);
    
    // Si la ruta viene en query string, usarla
    if ($query && strpos($query, '/') === 0) {
        $path = $query;
    }
    
    // Normalizar path: eliminar prefijos comunes
    $path = preg_replace('#^.*/backend/index\.php#', '', $path);
    $path = preg_replace('#^.*/backend#', '', $path);

    // POST /dashboard-modulos/seed - Sembrar desde modulos
    if ($request === 'POST' && strpos($path, '/dashboard-modulos/seed') !== false) {
        $container['controllers']['dashboard_modulo']->seed();
        exit;
    }

    // POST /dashboard-modulos/sync - Sincronizar desde lista
    if ($request === 'POST' && strpos($path, '/dashboard-modulos/sync') !== false) {
        $container['controllers']['dashboard_modulo']->sync();
        exit;
    }

    // PATCH /dashboard-modulos/orden - Mover orden
    if ($request === 'PATCH' && strpos($path, '/dashboard-modulos/orden') !== false) {
        $container['controllers']['dashboard_modulo']->mover();
        exit;
    }

    // POST /dashboard-modulos - Crear módulo (debe ir después de las rutas específicas)
    if ($request === 'POST' && preg_match('#^/?dashboard-modulos/?$#', $path)) {
        $container['controllers']['dashboard_modulo']->crear();
        exit;
    }

    // PUT /dashboard-modulos - Actualizar módulo
    if ($request === 'PUT' && preg_match('#^/?dashboard-modulos/?$#', $path)) {
        $container['controllers']['dashboard_modulo']->actualizar();
        exit;
    }

    // DELETE /dashboard-modulos - Eliminar módulo
    if ($request === 'DELETE' && preg_match('#^/?dashboard-modulos/?$#', $path)) {
        $container['controllers']['dashboard_modulo']->eliminar();
        exit;
    }

    // GET /dashboard-modulos - Listar (debe ir al final)
    if ($request === 'GET' && strpos($path, '/dashboard-modulos') !== false) {
        $container['controllers']['dashboard_modulo']->listar();
        exit;
    }
};
