<?php

/**
 * Rutas del Módulo de Reporte de Transacciones
 */

return function ($container) {
    // Extraer el controlador ya construido de forma segura desde el contenedor
    $transaccionController = $container['controllers']['reporteTransaccion'];

    // Obtener método y ruta de la petición HTTP
    $method = $_SERVER['REQUEST_METHOD'];
    $uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    
    // Remover prefijos de carpetas del servidor local
    $uri = str_replace(['/plantaincubacion/backend/index.php', '/plantaincubacion/backend', '/backend'], '', $uri);
    $uri = preg_replace('#/+#', '/', $uri); 
    $uri = rtrim(trim($uri), '/');          

    // ── ENDPOINT PARA FILTROS ──
    if ($method === 'GET' && $uri === '/api/reporte/transacciones/select') {
        $transaccionController->getTransacciones();
        exit;
    }

    if ($method === 'GET' && $uri === '/api/reporte/almacenes/select') {
        $transaccionController->getAlmacenes();
        exit;
    }

    // ── ENDPOINT: Generar la data de la grilla principal del reporte ──
    if ($method === 'GET' && $uri === '/api/reporte/transacciones/generar') {
        $transaccionController->procesarReporte();
        exit;
    }

    if ($method === 'GET' && $uri === '/api/reporte/cencos/select') {
        $transaccionController->getCencos();
        exit;
    }

    if ($method === 'GET' && $uri === '/api/reporte/cuentas-corrientes/select') {
        $transaccionController->getCuentasCorrientes();
        exit;
    }

    if ($method === 'GET' && $uri === '/api/reporte/lineas/select') {
        $transaccionController->getLineas();
        exit;
    }

    // ── ENDPOINT: Listar Códigos de Artículo para el select ──
    if ($method === 'GET' && $uri === '/api/reporte/articulos/select') {
        $transaccionController->getArticulos();
        exit;
    }

    if ($method === 'POST' && $uri === '/api/reporte/exportar') {
        $transaccionController->exportarPdf();
        exit;
    }

};