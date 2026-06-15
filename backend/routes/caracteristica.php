<?php
/**
 * Rutas de Características
 * 
 * Define todas las rutas relacionadas con gestión de características
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

    // GET /caracteristicas/tipos-datos - Obtener tipos de datos disponibles
    if ($request === 'GET' && strpos($path, '/caracteristicas/tipos-datos') !== false) {
        $container['controllers']['caracteristica']->tiposDatos();
        exit;
    }

    // GET /caracteristicas/{id}/verificar-uso - Verificar si característica está en uso
    if ($request === 'GET' && preg_match('#/caracteristicas/(\d+)/verificar-uso#', $path)) {
        $container['controllers']['caracteristica']->verificarUso();
        exit;
    }

    // GET /caracteristicas/buscar/{termino} - Buscar características
    if ($request === 'GET' && preg_match('#/caracteristicas/buscar/(\w+)#', $path, $matches)) {
        $searchTerm = $matches[1];
        $container['controllers']['caracteristica']->buscar($searchTerm);
        exit;
    }

    // GET /caracteristicas/{id} - Obtener característica específica
    if ($request === 'GET' && preg_match('#/caracteristicas/(\d+)#', $path)) {
        $container['controllers']['caracteristica']->obtener();
        exit;
    }

    // GET /caracteristicas - Listar características
    if ($request === 'GET' && strpos($path, '/caracteristicas') !== false) {
        $container['controllers']['caracteristica']->listar();
        exit;
    }

    // POST /caracteristicas - Crear nueva característica
    if ($request === 'POST' && strpos($path, '/caracteristicas') !== false) {
        $container['controllers']['caracteristica']->crear();
        exit;
    }

    // PUT /caracteristicas/{id} - Actualizar característica
    if ($request === 'PUT' && preg_match('#/caracteristicas/(\d+)#', $path)) {
        $container['controllers']['caracteristica']->actualizar();
        exit;
    }

    // DELETE /caracteristicas/{id} - Eliminar característica
    if ($request === 'DELETE' && preg_match('#/caracteristicas/(\d+)#', $path)) {
        $container['controllers']['caracteristica']->eliminar();
        exit;
    }
};
