<?php
/**
 * ========================================
 * RUTAS DE AUTENTICACIÓN - SISTEMA LEGACY
 * ========================================
 * 
 * @deprecated Desde 2026-02-10
 * @see /iam/routes/iam-routes.php para el nuevo sistema
 * @see /iam/IAM_API_REFERENCE.md para documentación completa
 * 
 * ESTADO: MANTENIMIENTO PASIVO
 * Estas rutas se mantienen para compatibilidad con sistemas existentes.
 * NO se deben agregar nuevas funcionalidades aquí.
 * 
 * MIGRACIÓN RECOMENDADA:
 * Migrar a las nuevas rutas IAM:
 * - /usuario/login → /iam/auth/login
 * - /usuario/validarSesion → /iam/auth/validate
 * - /usuario/logout → /iam/auth/logout
 * 
 * VENTAJAS DEL NUEVO SISTEMA IAM:
 * ✓ Validación de permisos multinivel (usuario → rol → programa → módulo)
 * ✓ Soporte multi-programa
 * ✓ Menús dinámicos basados en permisos
 * ✓ Auditoría completa
 * ✓ API RESTful estandarizada
 * 
 * Define rutas relacionadas con autenticación de usuarios:
 * - Login
 * - Logout
 * - Validación de sesión
 */

return function($container) {
    $request = $_SERVER['REQUEST_METHOD'];
    
    // Extraer path - soporta tanto /ruta como index.php?/ruta
    $path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    $query = parse_url($_SERVER['REQUEST_URI'], PHP_URL_QUERY);
    
    // Si la ruta viene en query string (index.php?/auth/login), usarla
    if ($query && strpos($query, '/') === 0) {
        $path = $query;
    }

    // POST /usuario/login - Iniciar sesión
    if ($request === 'POST' && strpos($path, '/usuario/login') !== false) {
        $data = json_decode(file_get_contents('php://input'), true);
        $container['controllers']['usuario']->login($data);
        exit;
    }

    // GET /usuario/validarSesion - Validar sesión activa
    if ($request === 'GET' && strpos($path, '/usuario/validarSesion') !== false) {
        if (isset($_SESSION['usuario'])) {
            echo json_encode([
                'success' => true, 
                'data' => [
                    'codigo' => $_SESSION['usuario'], 
                    'nombre' => $_SESSION['nombre']
                ]
            ]);
        } else {
            echo json_encode(['success' => false]);
        }
        exit;
    }

    // GET /usuario/logout - Cerrar sesión
    if ($request === 'GET' && strpos($path, '/usuario/logout') !== false) {
        session_destroy();
        echo json_encode(['success' => true]);
        exit;
    }

    // ========== RUTAS DEL NUEVO SISTEMA CON ROLES ==========

    // POST /auth/login - Login con sistema de roles
    if ($request === 'POST' && strpos($path, '/auth/login') !== false) {
        $data = json_decode(file_get_contents('php://input'), true);
        $container['controllers']['usuario']->loginConRol($data);
        exit;
    }

    // GET /auth/validar-sesion - Validar sesión con roles
    if ($request === 'GET' && strpos($path, '/auth/validar-sesion') !== false) {
        $container['controllers']['usuario']->validarSesionConRol();
        exit;
    }

    // GET /auth/validar - Alias de /auth/validar-sesion (compatibilidad frontend)
    if ($request === 'GET' && strpos($path, '/auth/validar') !== false) {
        $container['controllers']['usuario']->validarSesionConRol();
        exit;
    }

    // POST /auth/logout - Cerrar sesión con sistema de roles
    if ($request === 'POST' && strpos($path, '/auth/logout') !== false) {
        $container['controllers']['usuario']->logoutConRol();
        exit;
    }

    // GET /auth/menu - Obtener menú del usuario autenticado
    if ($request === 'GET' && strpos($path, '/auth/menu') !== false) {
        $container['controllers']['usuario']->obtenerMenu();
        exit;
    }
};
