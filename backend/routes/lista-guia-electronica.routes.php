<?php
/**
 * Rutas del Módulo de Lista de Guías de Remisión Electrónica
 */
require_once __DIR__ . '/../controllers/ListaGuiaElectronicaController.php';

return function ($container) {
    $db = $container['db'];
    
    // Obtener el controlador desde el contenedor de dependencias
    $controller = $container['controllers']['listaGuiaElectronica'] ?? new ListaGuiaElectronicaController($db);

    $method = $_SERVER['REQUEST_METHOD'];
    if ($method === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true) ?? [];
        if (isset($input['_method'])) {
            $method = strtoupper($input['_method']);
        } elseif (isset($_SERVER['HTTP_X_HTTP_METHOD_OVERRIDE'])) {
            $method = strtoupper($_SERVER['HTTP_X_HTTP_METHOD_OVERRIDE']);
        }
    }

    $uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

    // Limpiar URI de prefijos locales
    $uri = str_replace(['/plantaincubacion/backend/index.php', '/plantaincubacion/backend', '/backend'], '', $uri);
    $uri = preg_replace('#/+#', '/', $uri);
    $uri = rtrim(trim($uri), '/');

    // Registrar el endpoint
    if ($method === 'GET' && ($uri === '/api/lista-guia-electronica' || $uri === '/api/guia-electronica/listar' || $uri === '/api/guia-electronica/listado')) {
        $controller->getListado();
        exit;
    }

    // Registrar el detalle
    if ($method === 'GET' && ($uri === '/api/lista-guia-electronica/detalle' || $uri === '/api/guia-electronica/detalle')) {
        $controller->getDetalle();
        exit;
    }

    // Registrar el PDF
    if ($method === 'GET' && ($uri === '/api/lista-guia-electronica/pdf' || $uri === '/api/guia-electronica/pdf')) {
        $controller->descargarPDF();
        exit;
    }

    // Registrar el borrado de la guía
    if ($method === 'DELETE' && ($uri === '/api/lista-guia-electronica/eliminar' || $uri === '/api/guia-electronica/eliminar')) {
        $controller->eliminarGuia();
        exit;
    }
};
