<?php
/**
 * Rutas de Galpones
 * 
 * Define todas las rutas relacionadas con gestión de galpones:
 * - Listar (con filtros)
 * - Obtener por ID
 * - Crear
 * - Actualizar (UPSERT inteligente)
 * - Eliminar
 * - Catálogo de características
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

    // GET /galpones/exportar-pdf - Exportar galpones a PDF
    // IMPORTANTE: Esta ruta debe estar ANTES de otras rutas para evitar conflictos
    if ($request === 'GET' && strpos($path, '/galpones/exportar-pdf') !== false) {
        $container['controllers']['galpon']->exportarPDF();
        exit;
    }

    // GET /galpones/exportar-excel - Exportar galpones a Excel
    // IMPORTANTE: Esta ruta debe estar ANTES de otras rutas para evitar conflictos
    if ($request === 'GET' && strpos($path, '/galpones/exportar-excel') !== false) {
        $container['controllers']['galpon']->exportarExcel();
        exit;
    }

    // GET /galpones/granjas - Obtener lista de granjas
    // IMPORTANTE: Esta ruta debe estar ANTES de /galpones para evitar conflictos
    if ($request === 'GET' && strpos($path, '/galpones/granjas') !== false) {
        $container['controllers']['galpon']->granjas();
        exit;
    }

    // GET /galpones/galpones-por-granja/{tcencos} - Obtener galpones de una granja
    // IMPORTANTE: Esta ruta debe estar ANTES de /galpones/{id} para evitar conflictos
    if ($request === 'GET' && preg_match('#/galpones/galpones-por-granja/(\w+)#', $path, $matches)) {
        $tcencos = $matches[1];
        $container['controllers']['galpon']->galponesPorGranja($tcencos);
        exit;
    }

    // GET /galpones/caracteristicas - Obtener catálogo de características
    // IMPORTANTE: Esta ruta debe estar ANTES de /galpones para evitar conflictos
    if ($request === 'GET' && strpos($path, '/galpones/caracteristicas') !== false) {
        $container['controllers']['galpon']->caracteristicas();
        exit;
    }

    // GET /galpones/{id} - Obtener galpón específico (ID compuesto "granja-galpon")
    if ($request === 'GET' && preg_match('#/galpones/([\d-]+)#', $path)) {
        $container['controllers']['galpon']->obtener();
        exit;
    }

    // GET /galpones - Listar galpones con filtros opcionales
    if ($request === 'GET' && strpos($path, '/galpones') !== false) {
        $container['controllers']['galpon']->listar();
        exit;
    }

    // POST /galpones - Crear nuevo galpón
    if ($request === 'POST' && strpos($path, '/galpones') !== false) {
        $data = json_decode(file_get_contents('php://input'), true);
        $container['controllers']['galpon']->crear($data);
        exit;
    }

    // PUT /galpones/{id} - Actualizar galpón (UPSERT inteligente, ID compuesto)
    if ($request === 'PUT' && preg_match('#/galpones/([\d-]+)#', $path)) {
        $data = json_decode(file_get_contents('php://input'), true);
        $container['controllers']['galpon']->actualizar($data);
        exit;
    }

    // DELETE /galpones/{id} - Eliminar características del galpón (ID compuesto)
    if ($request === 'DELETE' && preg_match('#/galpones/([\d-]+)#', $path)) {
        $container['controllers']['galpon']->eliminar();
        exit;
    }
};
