<?php
/**
 * Bootstrap - Grafo de Dependencias (DI Container)
 * 
 * Este archivo construye todas las dependencias del sistema en el orden correcto:
 * 1. Conexión a base de datos
 * 2. Repositorios
 * 3. Servicios
 * 4. Controladores
 */

try {
    require_once __DIR__ . '/config/database.php';
    
    // Repositories
    require_once __DIR__ . '/repositories/UsuarioRepository.php';
    require_once __DIR__ . '/repositories/UsuarioRolRepository.php';
    require_once __DIR__ . '/repositories/NavegacionRepository.php';
    require_once __DIR__ . '/repositories/GalponRepository.php';
    require_once __DIR__ . '/repositories/CaracteristicaRepository.php';
    require_once __DIR__ . '/repositories/ModuloRepository.php';
    require_once __DIR__ . '/repositories/DashboardModuloRepository.php';
    require_once __DIR__ . '/repositories/RolRepository.php';
    require_once __DIR__ . '/repositories/PermisoRepository.php';
    require_once __DIR__ . '/repositories/UsuarioSistemaRepository.php';
    require_once __DIR__ . '/repositories/SimulacionEscenariosRepository.php';
    require_once __DIR__ . '/repositories/MovimientoAlmacenRepository.php';
    require_once __DIR__ . '/repositories/ReporteTransaccionesRepository.php';
    require_once __DIR__ . '/repositories/ReporteStockRepository.php';
    require_once __DIR__ . '/repositories/ReporteKardexRepository.php';

    // Services
    require_once __DIR__ . '/services/UsuarioService.php';
    require_once __DIR__ . '/services/UsuarioRolService.php';
    require_once __DIR__ . '/services/NavegacionService.php';
    require_once __DIR__ . '/services/GalponService.php';
    require_once __DIR__ . '/services/CaracteristicaService.php';
    require_once __DIR__ . '/services/ModuloService.php';
    require_once __DIR__ . '/services/DashboardModuloService.php';
    require_once __DIR__ . '/services/RolService.php';
    require_once __DIR__ . '/services/PermisoService.php';
    require_once __DIR__ . '/services/UsuarioAdminService.php';
    require_once __DIR__ . '/services/SimulacionEscenariosService.php';
    require_once __DIR__ . '/services/MovimientoAlmacenService.php';
    require_once __DIR__ . '/services/ReporteTransaccionService.php';
    require_once __DIR__ . '/services/ReporteStockService.php';
    require_once __DIR__ . '/services/ReporteKardexService.php';

    // Controllers
    require_once __DIR__ . '/controllers/UsuarioController.php';
    require_once __DIR__ . '/controllers/UsuarioRolController.php';
    require_once __DIR__ . '/controllers/NavegacionController.php';
    require_once __DIR__ . '/controllers/GalponController.php';
    require_once __DIR__ . '/controllers/CaracteristicaController.php';
    require_once __DIR__ . '/controllers/ModuloController.php';
    require_once __DIR__ . '/controllers/DashboardModuloController.php';
    require_once __DIR__ . '/controllers/RolController.php';
    require_once __DIR__ . '/controllers/PermisoController.php';
    require_once __DIR__ . '/controllers/UsuarioSistemaController.php';
    require_once __DIR__ . '/controllers/SimulacionEscenariosController.php';
    require_once __DIR__ . '/controllers/UsuarioAdminController.php';
    require_once __DIR__ . '/controllers/MovimientoAlmacenController.php';
    require_once __DIR__ . '/controllers/ReporteTransaccionController.php';
    require_once __DIR__ . '/controllers/ReporteStockController.php';
    require_once __DIR__ . '/controllers/ReporteKardexController.php';

    // 1. Crear conexión (raíz del grafo) - Patrón Singleton
    $db = Database::getInstance()->getConnection();

    // 2. Crear repositorios (nivel más interno - dependen solo de $db)
    $usuarioRepository = new UsuarioRepository($db);
    $usuarioRolRepository = new UsuarioRolRepository($db);
    $navegacionRepository = new NavegacionRepository($db);
    $galponRepository = new GalponRepository($db);
    $caracteristicaRepository = new CaracteristicaRepository($db);
    $moduloRepository = new ModuloRepository($db);
    $dashboardModuloRepository = new DashboardModuloRepository($db);
    $rolRepository = new RolRepository($db);
    $permisoRepository = new PermisoRepository($db);
    $usuarioSistemaRepository = new UsuarioSistemaRepository($db);
    $movimientoAlmacenRepository = new MovimientoAlmacenRepository($db);

    // 3. Crear servicios (nivel medio - dependen de repositorios)
    // NOTA: Los nuevos servicios (UsuarioService, RolService, UsuarioRolService, NavegacionService)
    // ahora crean sus propias dependencias internamente usando $db
    $usuarioService = new UsuarioService($db);
    $rolService = new RolService($db);
    $usuarioRolService = new UsuarioRolService($db);
    $navegacionService = new NavegacionService($db);
    $galponService = new GalponService($galponRepository);
    $caracteristicaService = new CaracteristicaService($caracteristicaRepository);
    $moduloService = new ModuloService($moduloRepository);
    $dashboardModuloService = new DashboardModuloService($dashboardModuloRepository);
    $permisoService = new PermisoService($permisoRepository, $dashboardModuloRepository);
    $usuarioAdminService = new UsuarioAdminService($usuarioRepository, $rolRepository);
    $movimientoAlmacenService = new MovimientoAlmacenService($db);
    $reporteTransaccionService = new ReporteTransaccionService($db);
    $reporteStockService = new ReporteStockService($db);
    $reporteKardexService = new ReporteKardexService($db);

    // 4. Crear controladores (nivel externo - dependen de servicios o $db)
    // NOTA: Los nuevos controladores (UsuarioController, RolController, UsuarioRolController, NavegacionController)
    // no reciben dependencias, las crean internamente
    $usuarioController = new UsuarioController();
    $rolController = new RolController($rolRepository);
    $usuarioRolController = new UsuarioRolController($usuarioRolRepository);
    $usuarioSistemaController = new UsuarioSistemaController($usuarioSistemaRepository);
    $navegacionController = new NavegacionController();
    $galponController = new GalponController($galponService);
    $caracteristicaController = new CaracteristicaController($caracteristicaService);
    $moduloController = new ModuloController($moduloService);
    $dashboardModuloController = new DashboardModuloController($dashboardModuloService);
    $permisoController = new PermisoController($permisoService);
    $usuarioAdminController = new UsuarioAdminController($usuarioAdminService);
    $movimientoAlmacenController = new MovimientoAlmacenController($movimientoAlmacenService);
    $reporteTransaccionController = new ReporteTransaccionController($db);
    $reporteStockController = new ReporteStockController($db);
    $reporteKardexController = new ReporteKardexController($db);

    // Contenedor de dependencias (accesible para las rutas)
    return [
        'db' => $db,
        'controllers' => [
            'usuario' => $usuarioController,
            'usuarioRol' => $usuarioRolController,
            'navegacion' => $navegacionController,
            'galpon' => $galponController,
            'caracteristica' => $caracteristicaController,
            'modulo' => $moduloController,
            'dashboard_modulo' => $dashboardModuloController,
            'rol' => $rolController,
            'permiso' => $permisoController,
            'usuarioAdmin' => $usuarioAdminController,
            'movimientoAlmacen' => $movimientoAlmacenController,
            'usuarioSistema' => $usuarioSistemaController,
            'reporteTransaccion' => $reporteTransaccionController,
            'reporteStock' => $reporteStockController,
            'reporteKardex' => $reporteKardexController,
        ],
        'services' => [
            'usuario' => $usuarioService,
            'usuarioRol' => $usuarioRolService,
            'navegacion' => $navegacionService,
            'galpon' => $galponService,
            'caracteristica' => $caracteristicaService,
            'modulo' => $moduloService,
            'dashboard_modulo' => $dashboardModuloService,
            'rol' => $rolService,
            'permiso' => $permisoService,
            'usuarioAdmin' => $usuarioAdminService,
            'movimientoAlmacen' => $movimientoAlmacenService,
        ],
        'repositories' => [
            'usuario' => $usuarioRepository,
            'usuarioRol' => $usuarioRolRepository,
            'navegacion' => $navegacionRepository,
            'galpon' => $galponRepository,
            'caracteristica' => $caracteristicaRepository,
            'modulo' => $moduloRepository,
            'dashboard_modulo' => $dashboardModuloRepository,
            'rol' => $rolRepository,
            'permiso' => $permisoRepository,
            'movimientoAlmacen' => $movimientoAlmacenRepository,
            'reporteTransacciones' => new ReporteTransaccionesRepository($db),
            'reporteStock' => new ReporteStockRepository($db),
            'reporteKardex' => new ReporteKardexRepository($db)
        ]
    ];
} catch (Exception $e) {
    error_log("Error en bootstrap: " . $e->getMessage());
    throw $e;
}
