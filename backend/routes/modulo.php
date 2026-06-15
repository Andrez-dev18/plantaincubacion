<?php
/**
 * Rutas de Modulos
 */

return function($container) {
    $request = $_SERVER['REQUEST_METHOD'];
    
    // Extraer path - soporta tanto /ruta como index.php?/ruta
    $path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    $query = parse_url($_SERVER['REQUEST_URI'], PHP_URL_QUERY);
    
    // Si la ruta viene en query string, usarla
    if ($query && strpos($query, '/') === 0) {
        $path = $query;
    }

    // GET /modulos/programas - Listar programas disponibles
    if ($request === 'GET' && strpos($path, '/modulos/programas') !== false) {
        $container['controllers']['modulo']->listarProgramas();
        exit;
    }

    // GET /modulos - Listar modulos por programa
    if ($request === 'GET' && strpos($path, '/modulos') !== false) {
        $container['controllers']['modulo']->listar();
        exit;
    }
};
