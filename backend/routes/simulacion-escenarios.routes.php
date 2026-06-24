<?php

/**
 * Rutas del Módulo de Simulación de Escenarios de Carga
 */

require_once __DIR__ . '/../controllers/SimulacionEscenariosController.php';

return function ($container) {
    // Obtener conexión de base de datos del container
    $db = $container['db'];

    // Instanciar controlador
    $controller = new SimulacionEscenariosController($db);

    // Obtener método y ruta
    $method = $_SERVER['REQUEST_METHOD'];
    $uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    
    // Remover los posibles prefijos
    $uri = str_replace('/plantaincubacion/backend/index.php', '', $uri);
    $uri = str_replace('/plantaincubacion/backend', '', $uri);
    $uri = str_replace('/backend', '', $uri);

    // ========================================
    // RUTAS GET
    // ========================================
    
    if ($method === 'GET' && $uri === '/api/simulacion-escenarios/listar') {
        $controller->listar();
        exit;
    }
    
    if ($method === 'GET' && preg_match('#^/api/simulacion-escenarios/obtener/(\d+)$#', $uri)) {
        $controller->obtener();
        exit;
    }
    
    if ($method === 'GET' && $uri === '/api/simulacion-escenarios/exportar-pdf') {
        $controller->exportarPDF();
        exit;
    }
    
    if ($method === 'GET' && $uri === '/api/simulacion-escenarios/exportar-excel') {
        $controller->exportarExcel();
        exit;
    }
    
    // ========================================
    // RUTAS POST
    // ========================================
    
    if ($method === 'POST' && $uri === '/api/simulacion-escenarios/calcular') {
        $controller->calcular();
        exit;
    }
    
    if ($method === 'POST' && $uri === '/api/simulacion-escenarios/guardar') {
        $controller->guardar();
        exit;
    }
    
    // ========================================
    // RUTAS DELETE
    // ========================================
    
    if ($method === 'DELETE' && preg_match('#^/api/simulacion-escenarios/eliminar/(\d+)$#', $uri)) {
        $controller->eliminar();
        exit;
    }
    

};
