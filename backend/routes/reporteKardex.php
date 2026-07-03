<?php

/**
 * Rutas del Módulo de Reporte de Stock de Almacenes
 */

require_once __DIR__ . '/../controllers/ReporteKardexController.php';

return function ($container) {
    // Obtener conexión de base de datos del container (Tal cual tu imagen de referencia)
    $db = $container['db'];

    // Instanciar controlador directamente pasándole la conexión
    $kardexController = new ReporteKardexController($db);

    // Obtener método y ruta de la petición HTTP
    $method = $_SERVER['REQUEST_METHOD'];
    $uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    
    // Remover prefijos de carpetas del servidor local
    $uri = str_replace(['/plantaincubacion/backend/index.php', '/plantaincubacion/backend', '/backend'], '', $uri);
    $uri = preg_replace('#/+#', '/', $uri); 
    $uri = rtrim(trim($uri), '/');          

    // ── ENDPOINTS PARA SELECTS DE FILTROS ──
    // Corregidos para apuntar a los métodos reales de tu controlador
    if ($method === 'GET' && $uri === '/api/reporte/kardex/almacenes/select') {
        $kardexController->getAlmacenes();
        exit;
    }

    if ($method === 'GET' && $uri === '/api/reporte/kardex/lineas/select') {
        $kardexController->getLineas();
        exit;
    }

    if ($method === 'GET' && $uri === '/api/reporte/kardex/articulos/select') {
        $kardexController->getArticulos();
        exit;
    }

    // ── ENDPOINT PRINCIPAL: Generar data del Kardex/Stock ──
    if ($method === 'GET' && $uri === '/api/reporte/kardex/generar') {
        $kardexController->getKardex();
        exit;
    }

    if ($method === 'POST' && $uri === '/api/reporte/kardex/exportar') {
        $kardexController->exportarPdf();
        exit;
    }

    if ($method === 'POST' && $uri === '/api/reporte/kardex/exportar-excel') {
        $kardexController->exportarExcel();
        exit;
    }
};