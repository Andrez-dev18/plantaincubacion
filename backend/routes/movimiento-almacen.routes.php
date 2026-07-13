<?php
/**
 * Rutas - Movimiento de Almacén
 * Adaptado al sistema de enrutamiento existente.
 */
return function($container) {
    /** @var MovimientoAlmacenController $ctrl */
    $ctrl = $container['controllers']['movimientoAlmacen'];

    // Obtener método y ruta
    $method = $_SERVER['REQUEST_METHOD'];

    // Soporte para method override (para PUT y DELETE)
    if ($method === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true) ?? [];
        if (isset($input['_method'])) {
            $method = strtoupper($input['_method']);
        } elseif (isset($_SERVER['HTTP_X_HTTP_METHOD_OVERRIDE'])) {
            $method = strtoupper($_SERVER['HTTP_X_HTTP_METHOD_OVERRIDE']);
        }
    }

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
    if (strpos($path, '/api/movimiento-almacen') !== 0) {
        return;
    }

    // Extraer la ruta relativa después del prefijo
    $route = substr($path, strlen('/api/movimiento-almacen'));
    $route = trim($route, '/');
    $segments = $route ? explode('/', $route) : [];


    // GET /api/movimiento-almacen/almacenes
    if ($route === 'almacenes' && $method === 'GET') {
        $ctrl->getAlmacenes();
    }
    // GET /api/movimiento-almacen/transacciones
    if ($route === 'transacciones' && $method === 'GET') {
        $ctrl->getTransacciones();
    }
    // GET /api/movimiento-almacen/tipos-documento
    if ($route === 'tipos-documento' && $method === 'GET') {
        $ctrl->getTiposDocumento();
    }
    // GET /api/movimiento-almacen/centros-costo
    if ($route === 'centros-costo' && $method === 'GET') {
        $ctrl->getCentrosCosto();
    }
    // GET /api/movimiento-almacen/clientes-proveedores
    if ($route === 'clientes-proveedores' && $method === 'GET') {
        $ctrl->getClientesProveedores();
    }
    // GET /api/movimiento-almacen/lineas
    if ($route === 'lineas' && $method === 'GET') {
        $ctrl->getLineas();
    }
    // GET /api/movimiento-almacen/tipo-cambio?fecha=
    if ($route === 'tipo-cambio' && $method === 'GET') {
        $ctrl->getTipoCambio();
    }
    // GET /api/movimiento-almacen/correlativo?tdoc=...&tserie=...
    if ($route === 'correlativo' && $method === 'GET') {
        $ctrl->getCorrelativo();
    }
    // GET /api/movimiento-almacen/productos?q=...
    if ($route === 'productos' && $method === 'GET') {
        $ctrl->buscarProductos();
    }
    // GET /api/movimiento-almacen/lotes?alma=...&codigo=...&fecha=...
    if ($route === 'lotes' && $method === 'GET') {
        $ctrl->getLotes();
    }
    // GET /api/movimiento-almacen/abc/{tipo}
    if (!empty($segments) && $segments[0] === 'abc' && isset($segments[1]) && $method === 'GET') {
        $ctrl->getAbc(['tipo' => $segments[1]]);
    }
    // GET /api/movimiento-almacen/verificar-fecha?fecha=
    if ($route === 'verificar-fecha' && $method === 'GET') {
        $ctrl->verificarFecha();
    }
    // GET /api/movimiento-almacen/verificar-mes?fecha=
    if ($route === 'verificar-mes' && $method === 'GET') {
        $ctrl->verificarMes();
    }
    // GET /api/movimiento-almacen/nuevo-reg
    if ($route === 'nuevo-reg' && $method === 'GET') {
        $ctrl->getNuevoReg();
    }
    // GET /api/movimiento-almacen/dashboard-lista
    if ($route === 'dashboard-lista' && $method === 'GET') {
        $ctrl->listarMovimientosDashboard();
    }
    // GET/POST /api/movimiento-almacen/cabecera
    if ($route === 'cabecera') {
        if ($method === 'GET')  { $ctrl->listarMovimientos(); }
        if ($method === 'POST') { $ctrl->crearMovimiento(); }
    }
    // GET/DELETE /api/movimiento-almacen/cabecera/{treg}
    if (!empty($segments) && $segments[0] === 'cabecera' && isset($segments[1])) {
        $p = ['treg' => $segments[1]];
        if ($method === 'GET')    { $ctrl->getMovimiento($p); }
        if ($method === 'PUT')    { $ctrl->actualizarMovimiento($p); }
        if ($method === 'DELETE') { $ctrl->eliminarMovimiento($p); }
    }
    // GET /api/movimiento-almacen/kardex/{codigo}/{lote}/{alma}
    if (!empty($segments) && $segments[0] === 'kardex' && isset($segments[1], $segments[2], $segments[3])) {
        $ctrl->getKardex([
            'codigo' => $segments[1],
            'lote'   => $segments[2],
            'alma'   => $segments[3]
        ]);
    }
    // GET /api/movimiento-almacen/stock/{alma}
    if (!empty($segments) && $segments[0] === 'stock' && isset($segments[1]) && $method === 'GET') {
        $ctrl->getStockAlmacen(['alma' => $segments[1]]);
    }

    // GET /api/movimiento-almacen/reporte-kardex
    if ($route === 'reporte-kardex' && $method === 'GET') {
        $ctrl->getReporteKardex();
    }

    // GET /api/movimiento-almacen/salida-rapida/productos?alma=...&q=...
    if (!empty($segments) && $segments[0] === 'salida-rapida' && isset($segments[1]) && $method === 'GET') {
        if ($segments[1] === 'productos')  { $ctrl->getProductosStockSalida(); }
        if ($segments[1] === 'mis-salidas') { $ctrl->getMisSalidas(); }
    }

    // GET /api/movimiento-almacen/reporte-kardex-pdf
    if ($route === 'reporte-kardex-pdf' && $method === 'GET') {
        $ctrl->getReporteKardexPdf();
    }

    // GET /api/movimiento-almacen/comprobante-pdf/{treg}
    if (!empty($segments) && $segments[0] === 'comprobante-pdf' && isset($segments[1]) && $method === 'GET') {
        $ctrl->getComprobantePdf(['treg' => $segments[1]]);
    }
};
