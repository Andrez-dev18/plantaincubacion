<?php
/**
 * Front Controller - Punto de entrada único de la API
 * 
 * Este archivo es el punto de entrada principal de la aplicación.
 * Carga el middleware de seguridad, el contenedor de dependencias
 * y delega las peticiones a los archivos de rutas específicos.
 */

// Configurar manejo de errores para que siempre retorne JSON
set_error_handler(function($errno, $errstr, $errfile, $errline) {
    error_log("PHP Error [$errno]: $errstr in $errfile:$errline");
    throw new ErrorException($errstr, 0, $errno, $errfile, $errline);
});

set_exception_handler(function($exception) {
    error_log("Uncaught Exception: " . $exception->getMessage());
    http_response_code(500);
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode([
        'success' => false,
        'message' => 'Error interno del servidor: ' . $exception->getMessage()
    ]);
    exit;
});

// Cargar middleware de seguridad
require_once __DIR__ . '/../middleware/security.php';
// Manejar peticiones OPTIONS para CORS
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit; 
}

// Configurar headers
header('Content-Type: application/json; charset=UTF-8');

try {
    // Cargar el contenedor de dependencias (bootstrap)
    $container = require_once __DIR__ . '/../bootstrap.php';

    // ========================================
    // AUTENTICACIÓN
    // ========================================
    $authRoutes = require_once __DIR__ . '/../routes/auth.php';
    $authRoutes($container);

    $adminRoutes = require_once __DIR__ . '/../routes/admin.php';
    $adminRoutes($container);

    // ========================================
    // MÓDULO USUARIOS/ROLES/PERMISOS
    // ========================================
    $usuarioSistemaRoutes = require_once __DIR__ . '/../routes/usuario-sistema.php';
    $usuarioSistemaRoutes($container);

    $rolRoutes = require_once __DIR__ . '/../routes/rol.php';
    $rolRoutes($container);

    $usuarioRolRoutes = require_once __DIR__ . '/../routes/usuario-rol.php';
    $usuarioRolRoutes($container);

    // ========================================
    // MÓDULOS FUNCIONALES (NO TOCAR)
    // Galpones y Características siguen funcionando normalmente
    // ========================================
    $galponRoutes = require_once __DIR__ . '/../routes/galpon.php';
    $galponRoutes($container);

    $caracteristicasRoutes = require_once __DIR__ . '/../routes/caracteristica.php';
    $caracteristicasRoutes($container);

    $moduloRoutes = require_once __DIR__ . '/../routes/modulo.php';
    $moduloRoutes($container);

    $dashboardModuloRoutes = require_once __DIR__ . '/../routes/dashboard-modulo.php';
    $dashboardModuloRoutes($container);

    $movimientoAlmacenRoutes = require_once __DIR__ . '/../routes/movimiento-almacen.routes.php';
    $movimientoAlmacenRoutes($container);

    // ========================================
    // MÓDULOS ANTIGUOS (COMENTADOS - Usar los nuevos endpoints abajo)
    // ========================================
    // $reporteRoutes = require_once __DIR__ . '/../routes/reporte.php';
    // $reporteRoutes($container);
    // $proyeccionesCargasPollosRoutes = require_once __DIR__ . '/../routes/proyecciones-cargas-pollos.routes.php';
    // $proyeccionesCargasPollosRoutes($container);

    // ========================================
    // MÓDULO DE SECUENCIA BASE PROYECCIÓN
    // Endpoints separados: /api/secuencia/*
    // ========================================
    $secuenciaRoutes = require_once __DIR__ . '/../routes/secuencia.routes.php';
    $secuenciaRoutes($container);

    // ========================================
    // MÓDULO DE CARGAPOLLO POR DÍA
    // Endpoints separados: /api/cargapollo/*
    // ========================================
    $cargapolloRoutes = require_once __DIR__ . '/../routes/cargapollo.routes.php';
    $cargapolloRoutes($container);

    // ========================================
    // MÓDULO DE SIMULACIÓN DE ESCENARIOS DE CARGA
    // Endpoints separados: /api/simulacion-escenarios/*
    // ========================================
    $simulacionEscenariosRoutes = require_once __DIR__ . '/../routes/simulacion-escenarios.routes.php';
    $simulacionEscenariosRoutes($container);
    
    // ========================================
    // MÓDULO: REPORTE DE TRANSACCIONES
    // ========================================
    $reporteTransaccionesRoutes = require_once __DIR__ . '/../routes/reporteTransacciones.php';
    $reporteTransaccionesRoutes($container);
    
    // Aquí se cargarán más rutas conforme se agreguen módulos:
    // require_once __DIR__ . '/../routes/incubacion.php';
    // require_once __DIR__ . '/../routes/lotes.php';
    // require_once __DIR__ . '/../routes/vacunacion.php';

    // Si no se encontró ninguna ruta, devolver 404
    http_response_code(404);
    echo json_encode([
        'success' => false,
        'message' => 'Ruta no encontrada'
    ]);
} catch (Exception $e) {
    error_log("Error en router: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error del servidor: ' . $e->getMessage()
    ]);
}
?>