<?php

/**
 * Rutas del Módulo de Cargapollo por Día
 */

require_once __DIR__ . '/../controllers/ReporteController.php';

return function ($container) {
    // Obtener conexión de base de datos del container
    $db = $container['db'];

    // Instanciar controlador
    $reporteController = new ReporteController($db);

    // Obtener método y ruta
    $method = $_SERVER['REQUEST_METHOD'];
    $uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    
    // Remover los posibles prefijos
    $uri = str_replace('/plantaincubacion/backend/index.php', '', $uri);
    $uri = str_replace('/plantaincubacion/backend', '', $uri);
    $uri = str_replace('/backend', '', $uri);

    // Rutas del reporte
    if ($method === 'GET' && $uri === '/api/cargapollo/proyecciones') {
        $reporteController->listarProyecciones();
        exit;
    }
    
    if ($method === 'GET' && $uri === '/api/cargapollo/datos') {
        $reporteController->obtenerDatos();
        exit;
    }
    
    if ($method === 'GET' && $uri === '/api/cargapollo/resumen') {
        $reporteController->obtenerResumen();
        exit;
    }
    
    // Nuevas rutas para server-side processing
    if ($method === 'GET' && $uri === '/api/cargapollo/calendario-paginated') {
        $reporteController->obtenerCalendarioPaginado();
        exit;
    }
    
    if ($method === 'GET' && $uri === '/api/cargapollo/resumen-paginated') {
        $reporteController->obtenerResumenPaginado();
        exit;
    }
    
    if ($method === 'GET' && $uri === '/api/cargapollo/excel') {
        $reporteController->generarExcel();
        exit;
    }
    
    if ($method === 'GET' && $uri === '/api/cargapollo/pdf') {
        $reporteController->generarPDF();
        exit;
    }
    
    if ($method === 'POST' && $uri === '/api/cargapollo/excel-custom') {
        $reporteController->generarExcelPersonalizado();
        exit;
    }

    // Rutas para el calendario editable (fechaproy)
    if ($method === 'GET' && $uri === '/api/cargapollo/fechaproy') {
        $reporteController->obtenerFechaProy();
        exit;
    }

    if ($method === 'PUT' && $uri === '/api/cargapollo/actualizar-cargas') {
        $reporteController->actualizarCargas();
        exit;
    }

    if ($method === 'POST' && $uri === '/api/cargapollo/recalcular') {
        $reporteController->recalcular();
        exit;
    }

    if ($method === 'POST' && $uri === '/api/cargapollo/importar-calendario') {
        $reporteController->importarCalendario();
        exit;
    }
};
