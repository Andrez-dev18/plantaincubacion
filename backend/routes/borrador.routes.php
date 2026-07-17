<?php
/**
 * Rutas - Borradores de Formularios
 */
return function($container) {
    /** @var BorradorController $ctrl */
    $ctrl = $container['controllers']['borrador'];

    $method = $_SERVER['REQUEST_METHOD'];

    // Extraer path limpiando los prefijos posibles
    $path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    $query = parse_url($_SERVER['REQUEST_URI'], PHP_URL_QUERY);
    
    // Soporte para formato index.php?/api/... (generado por .htaccess con QSA)
    if ($query && strpos($query, '/') === 0) {
        $path = $query;
    }
    $path = str_replace('/plantaincubacion/backend/index.php', '', $path);
    $path = str_replace('/plantaincubacion/backend', '', $path);
    $path = str_replace('/plantaincubacion', '', $path);
    $path = str_replace('/backend', '', $path);

    // Si la ruta no empieza con nuestro prefijo, salir
    if (strpos($path, '/api/borradores') !== 0) {
        return;
    }

    // Extraer la ruta relativa después del prefijo
    $route = substr($path, strlen('/api/borradores'));
    $route = trim($route, '/');

    // GET/POST /api/borradores/obtener
    if ($route === 'obtener' && ($method === 'GET' || $method === 'POST')) {
        $ctrl->obtener();
        exit;
    }

    // POST /api/borradores/guardar
    if ($route === 'guardar' && $method === 'POST') {
        $ctrl->guardar();
        exit;
    }

    // POST /api/borradores/eliminar
    if ($route === 'eliminar' && $method === 'POST') {
        $ctrl->eliminar();
        exit;
    }
};
?>
