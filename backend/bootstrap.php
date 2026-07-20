<?php
/**
 * Bootstrap - Grafo de Dependencias (DI Container)
 * Limpio y Optimizado para el nuevo módulo de Usuarios
 */

try {
    require_once __DIR__ . '/config/database.php';
    
    // Repositories
    require_once __DIR__ . '/repositories/UsuarioRepository.php';
    require_once __DIR__ . '/repositories/AsignacionRepository.php';
    require_once __DIR__ . '/repositories/NavegacionRepository.php';
    require_once __DIR__ . '/repositories/GalponRepository.php';
    require_once __DIR__ . '/repositories/CaracteristicaRepository.php';
    require_once __DIR__ . '/repositories/ModuloRepository.php';
    require_once __DIR__ . '/repositories/DashboardModuloRepository.php';
    require_once __DIR__ . '/repositories/RolRepository.php';
    require_once __DIR__ . '/repositories/PermisoRepository.php';
    require_once __DIR__ . '/repositories/SimulacionEscenariosRepository.php';
    require_once __DIR__ . '/repositories/MovimientoAlmacenRepository.php';
    require_once __DIR__ . '/repositories/ReporteTransaccionesRepository.php';
    require_once __DIR__ . '/repositories/ReporteStockRepository.php';
    require_once __DIR__ . '/repositories/ReporteKardexRepository.php';
    require_once __DIR__ . '/repositories/GuiaElectronicaRepository.php';
    require_once __DIR__ . '/repositories/ListaGuiaElectronicaRepository.php';
    require_once __DIR__ . '/repositories/ConfigApiRepository.php';
    require_once __DIR__ . '/repositories/BorradorRepository.php';
    require_once __DIR__ . '/repositories/EmpresaRepository.php';
    require_once __DIR__ . '/repositories/ServiciosRepository.php';
    require_once __DIR__ . '/repositories/ArticulosRepository.php';
    require_once __DIR__ . '/repositories/ContribuyentesRepository.php';

    // Services
    require_once __DIR__ . '/services/UsuarioService.php'; // Nuestro único servicio unificado
    require_once __DIR__ . '/services/AsignacionService.php';
    require_once __DIR__ . '/services/UsuarioAdminService.php';
    require_once __DIR__ . '/services/NavegacionService.php';
    require_once __DIR__ . '/services/GalponService.php';
    require_once __DIR__ . '/services/CaracteristicaService.php';
    require_once __DIR__ . '/services/ModuloService.php';
    require_once __DIR__ . '/services/DashboardModuloService.php';
    require_once __DIR__ . '/services/RolService.php';
    require_once __DIR__ . '/services/PermisoService.php';
    require_once __DIR__ . '/services/SimulacionEscenariosService.php';
    require_once __DIR__ . '/services/MovimientoAlmacenService.php';
    require_once __DIR__ . '/services/ReporteTransaccionService.php';
    require_once __DIR__ . '/services/ReporteStockService.php';
    require_once __DIR__ . '/services/ReporteKardexService.php';
    require_once __DIR__ . '/services/GuiaElectronicaService.php';
    require_once __DIR__ . '/services/ListaGuiaElectronicaService.php';
    require_once __DIR__ . '/services/ConfigApiService.php';
    require_once __DIR__ . '/services/BorradorService.php';
    require_once __DIR__ . '/services/EmpresaService.php';
    require_once __DIR__ . '/services/ServiciosService.php';
    require_once __DIR__ . '/services/ArticulosService.php';
    require_once __DIR__ . '/services/ContribuyentesService.php';

    // Controllers
    require_once __DIR__ . '/controllers/UsuarioController.php'; // Nuestro único controlador unificado
    require_once __DIR__ . '/controllers/AsignacionController.php';
    require_once __DIR__ . '/controllers/UsuarioAdminController.php';
    require_once __DIR__ . '/controllers/NavegacionController.php';
    require_once __DIR__ . '/controllers/GalponController.php';
    require_once __DIR__ . '/controllers/CaracteristicaController.php';
    require_once __DIR__ . '/controllers/ModuloController.php';
    require_once __DIR__ . '/controllers/DashboardModuloController.php';
    require_once __DIR__ . '/controllers/RolController.php';
    require_once __DIR__ . '/controllers/PermisoController.php';
    require_once __DIR__ . '/controllers/SimulacionEscenariosController.php';
    require_once __DIR__ . '/controllers/MovimientoAlmacenController.php';
    require_once __DIR__ . '/controllers/ReporteTransaccionController.php';
    require_once __DIR__ . '/controllers/ReporteStockController.php';
    require_once __DIR__ . '/controllers/ReporteKardexController.php';
    require_once __DIR__ . '/controllers/GuiaElectronicaController.php';
    require_once __DIR__ . '/controllers/ListaGuiaElectronicaController.php';
    require_once __DIR__ . '/controllers/ConfigApiController.php';
    require_once __DIR__ . '/controllers/BorradorController.php';
    require_once __DIR__ . '/controllers/EmpresaController.php';
    require_once __DIR__ . '/controllers/ServiciosController.php';
    require_once __DIR__ . '/controllers/ArticulosController.php';
    require_once __DIR__ . '/controllers/ContribuyentesController.php';

    // 1. Crear conexión
    $db = Database::getInstance()->getConnection();

    // 2. Crear repositorios
    $usuarioRepository = new UsuarioRepository($db);
    $asignacionRepository = new AsignacionRepository($db);
    $navegacionRepository = new NavegacionRepository($db);
    $galponRepository = new GalponRepository($db);
    $caracteristicaRepository = new CaracteristicaRepository($db);
    $moduloRepository = new ModuloRepository($db);
    $dashboardModuloRepository = new DashboardModuloRepository($db);
    $rolRepository = new RolRepository($db);
    $permisoRepository = new PermisoRepository($db);
    $movimientoAlmacenRepository = new MovimientoAlmacenRepository($db);
    $guiaElectronicaRepository = new GuiaElectronicaRepository($db);
    $listaGuiaElectronicaRepository = new ListaGuiaElectronicaRepository($db);
    $configApiRepository = new ConfigApiRepository($db);
    $borradorRepository = new BorradorRepository($db);
    $empresaRepository = new EmpresaRepository($db);
    $serviciosRepository = new ServiciosRepository($db);
    $articulosRepository = new ArticulosRepository($db);
    $contribuyentesRepository = new ContribuyentesRepository($db);

    // 3. Crear servicios
    $usuarioService = new UsuarioService($db);
    $asignacionService = new AsignacionService($db);
    $usuarioAdminService = new UsuarioAdminService($usuarioRepository, $rolRepository);
    $rolService = new RolService($db);
    $navegacionService = new NavegacionService($db);
    $galponService = new GalponService($galponRepository);
    $caracteristicaService = new CaracteristicaService($caracteristicaRepository);
    $moduloService = new ModuloService($moduloRepository);
    $dashboardModuloService = new DashboardModuloService($dashboardModuloRepository);
    $permisoService = new PermisoService($permisoRepository, $dashboardModuloRepository);
    $movimientoAlmacenService = new MovimientoAlmacenService($db);
    $reporteTransaccionService = new ReporteTransaccionService($db);
    $reporteStockService = new ReporteStockService($db);
    $reporteKardexService = new ReporteKardexService($db);
    $guiaElectronicaService = new GuiaElectronicaService($db);
    $listaGuiaElectronicaService = new ListaGuiaElectronicaService($db);
    $configApiService = new ConfigApiService($db);
    $borradorService = new BorradorService($db);
    $empresaService = new EmpresaService($db);
    $serviciosService = new ServiciosService($db);
    $articulosService = new ArticulosService($db);
    $contribuyentesService = new ContribuyentesService($db);

    // 4. Crear controladores
    $usuarioController = new UsuarioController($db); // Inyectamos $db como lo definimos
    $asignacionController = new AsignacionController();
    $usuarioAdminController = new UsuarioAdminController($usuarioAdminService);
    $rolController = new RolController($rolRepository);
    $navegacionController = new NavegacionController();
    $galponController = new GalponController($galponService);
    $caracteristicaController = new CaracteristicaController($caracteristicaService);
    $moduloController = new ModuloController($moduloService);
    $dashboardModuloController = new DashboardModuloController($dashboardModuloService);
    $permisoController = new PermisoController($permisoService);
    $movimientoAlmacenController = new MovimientoAlmacenController($movimientoAlmacenService);
    $reporteTransaccionController = new ReporteTransaccionController($db);
    $reporteStockController = new ReporteStockController($db);
    $reporteKardexController = new ReporteKardexController($db);
    $guiaElectronicaController = new GuiaElectronicaController($db);
    $listaGuiaElectronicaController = new ListaGuiaElectronicaController($db);
    $configApiController = new ConfigApiController($db);
    $borradorController = new BorradorController($db);
    $empresaController = new EmpresaController($empresaRepository);
    $serviciosController = new ServiciosController($serviciosRepository);
    $articulosController = new ArticulosController($articulosRepository);
    $contribuyentesController = new ContribuyentesController($contribuyentesRepository);

    // Contenedor de dependencias (accesible para las rutas)
    return [
        'db' => $db,
        'controllers' => [
            'usuario' => $usuarioController,
            'usuarioRol' => $asignacionController,
            'asignacion' => $asignacionController,
            'usuarioAdmin' => $usuarioAdminController,
            'navegacion' => $navegacionController,
            'galpon' => $galponController,
            'caracteristica' => $caracteristicaController,
            'modulo' => $moduloController,
            'dashboard_modulo' => $dashboardModuloController,
            'rol' => $rolController,
            'permiso' => $permisoController,
            'movimientoAlmacen' => $movimientoAlmacenController,
            'reporteTransaccion' => $reporteTransaccionController,
            'reporteStock' => $reporteStockController,
            'reporteKardex' => $reporteKardexController,
            'guiaElectronica' => $guiaElectronicaController,
            'listaGuiaElectronica' => $listaGuiaElectronicaController,
            'configApi' => $configApiController,
            'borrador' => $borradorController,
            'empresa' => $empresaController,
            'servicios' => $serviciosController,
            'articulos' => $articulosController,
            'contribuyentes' => $contribuyentesController,
        ],
        'services' => [
            'usuario' => $usuarioService,
            'usuarioRol' => $asignacionService,
            'asignacion' => $asignacionService,
            'usuarioAdmin' => $usuarioAdminService,
            'navegacion' => $navegacionService,
            'galpon' => $galponService,
            'caracteristica' => $caracteristicaService,
            'modulo' => $moduloService,
            'dashboard_modulo' => $dashboardModuloService,
            'rol' => $rolService,
            'permiso' => $permisoService,
            'movimientoAlmacen' => $movimientoAlmacenService,
            'guiaElectronica' => $guiaElectronicaService,
            'listaGuiaElectronica' => $listaGuiaElectronicaService,
            'configApi' => $configApiService,
            'borrador' => $borradorService,
            'empresa' => $empresaService,
            'servicios' => $serviciosService,
            'articulos' => $articulosService,
            'contribuyentes' => $contribuyentesService,
        ],
        'repositories' => [
            'usuario' => $usuarioRepository,
            'usuarioRol' => $asignacionRepository,
            'asignacion' => $asignacionRepository,
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
            'reporteKardex' => new ReporteKardexRepository($db),
            'guiaElectronica' => $guiaElectronicaRepository,
            'listaGuiaElectronica' => $listaGuiaElectronicaRepository,
            'configApi' => $configApiRepository,
            'borrador' => $borradorRepository,
            'empresa' => $empresaRepository,
            'servicios' => $serviciosRepository,
            'articulos' => $articulosRepository,
            'contribuyentes' => $contribuyentesRepository
        ]
    ];
} catch (Exception $e) {
    error_log("Error en bootstrap: " . $e->getMessage());
    throw $e;
}