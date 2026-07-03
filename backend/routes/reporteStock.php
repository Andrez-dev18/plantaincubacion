<?php

/**
 * Rutas del Módulo de Reporte de Stock de Almacenes
 */

require_once __DIR__ . '/../controllers/ReporteStockController.php';

return function ($container) {
    // Obtener conexión de base de datos del container (Tal cual tu imagen de referencia)
    $db = $container['db'];

    // Instanciar controlador directamente pasándole la conexión
    $stockController = new ReporteStockController($db);

    // Obtener método y ruta de la petición HTTP
    $method = $_SERVER['REQUEST_METHOD'];
    $uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    
    // Remover prefijos de carpetas del servidor local
    $uri = str_replace(['/plantaincubacion/backend/index.php', '/plantaincubacion/backend', '/backend'], '', $uri);
    $uri = preg_replace('#/+#', '/', $uri); 
    $uri = rtrim(trim($uri), '/');          

    // ── ENDPOINTS PARA SELECTS DE FILTROS ──
    // Corregidos para apuntar a los métodos reales de tu controlador
    if ($method === 'GET' && $uri === '/api/reporte/stock/almacenes/select') {
        $stockController->getAlmacenes();
        exit;
    }

    if ($method === 'GET' && $uri === '/api/reporte/stock/lineas/select') {
        $stockController->getLineas();
        exit;
    }

    if ($method === 'GET' && $uri === '/api/reporte/stock/articulos/select') {
        $stockController->getArticulos();
        exit;
    }

    // ── ENDPOINT PRINCIPAL: Generar data del Kardex/Stock ──
    if ($method === 'GET' && $uri === '/api/reporte/stock/generar') {
        $stockController->procesarReporte();
        exit;
    }

    if ($method === 'POST' && $uri === '/api/reporte/stock/exportar') {
        $stockController->exportarPdf();
        exit;
    }

    if ($method === 'POST' && $uri === '/api/reporte/stock/exportar-excel') {
        $stockController->exportarExcel();
        exit;
    }
};