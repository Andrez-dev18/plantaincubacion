<?php
/**
 * ===========================================
 * RUTAS DE ADMINISTRACIÓN - SISTEMA LEGACY
 * ===========================================
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
 * Tabla de equivalencias:
 * - /admin/roles → /iam/roles
 * - /admin/usuarios → /iam/usuarios
 * - /admin/usuario-rol → /iam/usuarios/{codigo}/roles
 * - /admin/navegacion → /iam/menu
 * - /admin/programas → /iam/programas
 * 
 * NUEVAS FUNCIONALIDADES DISPONIBLES EN IAM:
 * ✓ /iam/roles/{id}/accesos - Asignación de accesos rol-programa-módulo
 * ✓ /iam/permisos/validar - Validación de permisos en tiempo real
 * ✓ /iam/menu - Generación de menús dinámicos
 * ✓ /iam/usuarios/{codigo}/permisos - Gestión de permisos globales
 * 
 * NOTA DE ARQUITECTURA:
 * El nuevo módulo IAM está completamente desacoplado y soporta:
 * - Lógica de intersección multinivel
 * - Deny by default
 * - Multi-programa
 * - Auditoría automática
 * 
 * Define rutas relacionadas con administración del sistema:
 * - Gestión de roles (multi-programa)
 * - Gestión de asignación usuario-roles
 * - Gestión de navegación (módulos visibles por rol)
 * - Gestión de usuarios (CRUD)
 * - Gestión de programas
 */

return function($container) {
    $request = $_SERVER['REQUEST_METHOD'];
    
    // Extraer path - soporta tanto /ruta como index.php?/ruta
    $path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    $query = parse_url($_SERVER['REQUEST_URI'], PHP_URL_QUERY);
    
    // Si la ruta viene en query string (index.php?/admin/roles), usarla
    if ($query && strpos($query, '/') === 0) {
        $path = $query;
    }

    // ========== RUTAS DE PROGRAMAS ==========
    
    // GET /admin/programas - Listar programas
    if ($request === 'GET' && strpos($path, '/admin/programas') !== false && !preg_match('/\/modulos/', $path)) {
        $container['controllers']['rol']->obtenerProgramas();
        exit;
    }

    // GET /admin/programas/{id}/modulos - Obtener módulos de un programa
    if ($request === 'GET' && preg_match('/\/admin\/programas\/(\d+)\/modulos$/', $path, $matches)) {
        $container['controllers']['rol']->obtenerModulosPorPrograma($matches[1]);
        exit;
    }

    // ========== RUTAS DE ROLES ==========
    
    // GET /admin/roles/{id}/modulos-agrupados - Obtener módulos asignados agrupados por programa
    if ($request === 'GET' && preg_match('/\/admin\/roles\/(\d+)\/modulos-agrupados$/', $path, $matches)) {
        $container['controllers']['rol']->obtenerModulosAgrupadosPorPrograma($matches[1]);
        exit;
    }

    // GET /admin/roles/{id}/modulos - Obtener módulos asignados a un rol (DEBE IR ANTES de /admin/roles/{id})
    if ($request === 'GET' && preg_match('/\/admin\/roles\/(\d+)\/modulos$/', $path, $matches)) {
        $container['controllers']['rol']->obtenerModulosAsignados($matches[1]);
        exit;
    }
    
    // GET /admin/roles/programa/{programa} - Listar roles de un programa (debe ir antes de /admin/roles/{id})
    if ($request === 'GET' && preg_match('/\/admin\/roles\/programa\/(.+)$/', $path, $matches)) {
        $programa = urldecode($matches[1]);
        $container['controllers']['rol']->listarPorPrograma($programa);
        exit;
    }

    // GET /admin/roles/{id} - Obtener rol por ID
    if ($request === 'GET' && preg_match('/\/admin\/roles\/(\d+)$/', $path, $matches)) {
        $container['controllers']['rol']->obtener($matches[1]);
        exit;
    }

    // GET /admin/roles - Listar todos los roles
    if ($request === 'GET' && strpos($path, '/admin/roles') !== false) {
        $container['controllers']['rol']->listar();
        exit;
    }

    // POST /admin/roles - Crear rol
    if ($request === 'POST' && strpos($path, '/admin/roles') !== false) {
        $container['controllers']['rol']->crear();
        exit;
    }

    // PUT /admin/roles/{id} - Actualizar rol
    if ($request === 'PUT' && preg_match('/\/admin\/roles\/(\d+)$/', $path, $matches)) {
        $container['controllers']['rol']->actualizar($matches[1]);
        exit;
    }

    // DELETE /admin/roles/{id} - Eliminar rol
    if ($request === 'DELETE' && preg_match('/\/admin\/roles\/(\d+)$/', $path, $matches)) {
        $container['controllers']['rol']->eliminar($matches[1]);
        exit;
    }

    // ========== RUTAS DE USUARIO-ROLES (Asignación Multi-Rol) ==========
    
    // GET /admin/usuarios/{id}/roles/programa/{programa} - Roles de usuario por programa
    if ($request === 'GET' && preg_match('/\/admin\/usuarios\/([^\/]+)\/roles\/programa\/(.+)$/', $path, $matches)) {
        $idUsuario = $matches[1];
        $programa = urldecode($matches[2]);
        $container['controllers']['usuarioRol']->obtenerRolesPorPrograma($idUsuario, $programa);
        exit;
    }

    // GET /admin/usuarios/{id}/permisos/programa/{programa} - Permisos CRUD de usuario por programa
    if ($request === 'GET' && preg_match('/\/admin\/usuarios\/([^\/]+)\/permisos\/programa\/(.+)$/', $path, $matches)) {
        $idUsuario = $matches[1];
        $programa = urldecode($matches[2]);
        $container['controllers']['usuarioRol']->obtenerPermisosCRUD($idUsuario, $programa);
        exit;
    }

    // GET /admin/usuarios/{id}/roles - Obtener roles de un usuario
    if ($request === 'GET' && preg_match('/\/admin\/usuarios\/([^\/]+)\/roles$/', $path, $matches)) {
        $container['controllers']['usuarioRol']->obtenerRolesPorUsuario($matches[1]);
        exit;
    }

    // POST /admin/usuarios/{id}/roles - Asignar un rol a un usuario
    if ($request === 'POST' && preg_match('/\/admin\/usuarios\/([^\/]+)\/roles$/', $path, $matches)) {
        $container['controllers']['usuarioRol']->asignarRol($matches[1]);
        exit;
    }

    // PUT /admin/usuarios/{id}/roles - Asignar múltiples roles (reemplaza existentes)
    if ($request === 'PUT' && preg_match('/\/admin\/usuarios\/([^\/]+)\/roles$/', $path, $matches)) {
        $container['controllers']['usuarioRol']->asignarRolesLote($matches[1]);
        exit;
    }

    // DELETE /admin/usuarios/{id}/roles/{idRol} - Remover un rol de un usuario
    if ($request === 'DELETE' && preg_match('/\/admin\/usuarios\/([^\/]+)\/roles\/(\d+)$/', $path, $matches)) {
        $container['controllers']['usuarioRol']->removerRol($matches[1], $matches[2]);
        exit;
    }

    // GET /admin/roles/{id}/usuarios - Obtener usuarios de un rol
    if ($request === 'GET' && preg_match('/\/admin\/roles\/(\d+)\/usuarios$/', $path, $matches)) {
        $container['controllers']['usuarioRol']->obtenerUsuariosPorRol($matches[1]);
        exit;
    }

    // ========== RUTAS DE NAVEGACIÓN (Control Visual de Módulos) ==========
    
    // GET /admin/roles/{id}/navegacion/asignacion - Módulos para interfaz de asignación
    if ($request === 'GET' && preg_match('/\/admin\/roles\/(\d+)\/navegacion\/asignacion$/', $path, $matches)) {
        $container['controllers']['navegacion']->obtenerParaAsignacion($matches[1]);
        exit;
    }

    // GET /admin/roles/{id}/navegacion - Obtener módulos visibles de un rol
    if ($request === 'GET' && preg_match('/\/admin\/roles\/(\d+)\/navegacion$/', $path, $matches)) {
        $container['controllers']['navegacion']->obtenerModulosPorRol($matches[1]);
        exit;
    }

    // POST /admin/roles/{id}/navegacion - Asignar un módulo a un rol
    if ($request === 'POST' && preg_match('/\/admin\/roles\/(\d+)\/navegacion$/', $path, $matches)) {
        $container['controllers']['navegacion']->asignarModulo($matches[1]);
        exit;
    }

    // PUT /admin/roles/{id}/navegacion - Asignar múltiples módulos (reemplaza existentes)
    if ($request === 'PUT' && preg_match('/\/admin\/roles\/(\d+)\/navegacion$/', $path, $matches)) {
        $container['controllers']['navegacion']->asignarModulosLote($matches[1]);
        exit;
    }

    // DELETE /admin/roles/{id}/navegacion/{codMod} - Remover módulo de un rol
    if ($request === 'DELETE' && preg_match('/\/admin\/roles\/(\d+)\/navegacion\/([^\/]+)$/', $path, $matches)) {
        $container['controllers']['navegacion']->removerModulo($matches[1], urldecode($matches[2]));
        exit;
    }

    // GET /admin/usuarios/{id}/navegacion - Obtener módulos visibles de un usuario
    if ($request === 'GET' && preg_match('/\/admin\/usuarios\/([^\/]+)\/navegacion$/', $path, $matches)) {
        $container['controllers']['navegacion']->obtenerModulosPorUsuario($matches[1]);
        exit;
    }

    // GET /admin/usuarios/{id}/menu - Obtener menú jerárquico de un usuario
    if ($request === 'GET' && preg_match('/\/admin\/usuarios\/([^\/]+)\/menu$/', $path, $matches)) {
        $container['controllers']['navegacion']->obtenerMenuJerarquico($matches[1]);
        exit;
    }

    // GET /admin/usuarios/{id}/acceso/{codMod} - Verificar acceso a módulo
    if ($request === 'GET' && preg_match('/\/admin\/usuarios\/([^\/]+)\/acceso\/([^\/]+)$/', $path, $matches)) {
        $container['controllers']['navegacion']->verificarAcceso($matches[1], urldecode($matches[2]));
        exit;
    }

    // GET /admin/modulos/programa/{programa} - Módulos disponibles de un programa
    if ($request === 'GET' && preg_match('/\/admin\/modulos\/programa\/(.+)$/', $path, $matches)) {
        $programa = urldecode($matches[1]);
        $container['controllers']['navegacion']->obtenerModulosDisponibles($programa);
        exit;
    }

    // ========== RUTAS DE USUARIOS (CRUD) ==========
    
    // GET /admin/usuarios/{id}/menu - Obtener menú del usuario (puede estar duplicado arriba)
    if ($request === 'GET' && preg_match('/\/admin\/usuarios\/([^\/]+)\/menu$/', $path, $matches)) {
        $container['controllers']['usuario']->obtenerMenu($matches[1]);
        exit;
    }

    // PUT /admin/usuarios/{id}/password - Cambiar contraseña
    if ($request === 'PUT' && preg_match('/\/admin\/usuarios\/([^\/]+)\/password$/', $path, $matches)) {
        $container['controllers']['usuario']->cambiarPassword($matches[1]);
        exit;
    }

    // GET /admin/usuarios/{id} - Obtener usuario por ID
    if ($request === 'GET' && preg_match('/\/admin\/usuarios\/([^\/]+)$/', $path, $matches)) {
        $container['controllers']['usuarioAdmin']->obtener(['id_usuario' => $matches[1]]);
        exit;
    }

    // GET /admin/usuarios - Listar usuarios
    if ($request === 'GET' && strpos($path, '/admin/usuarios') !== false) {
        $container['controllers']['usuarioAdmin']->listar();
        exit;
    }

    // POST /admin/usuarios - Crear usuario
    if ($request === 'POST' && strpos($path, '/admin/usuarios') !== false) {
        $data = json_decode(file_get_contents('php://input'), true) ?: [];
        $container['controllers']['usuarioAdmin']->crear($data);
        exit;
    }

    // PUT /admin/usuarios/{id} - Actualizar usuario
    if ($request === 'PUT' && preg_match('/\/admin\/usuarios\/([^\/]+)$/', $path, $matches)) {
        $data = json_decode(file_get_contents('php://input'), true) ?: [];
        $data['id_usuario'] = $matches[1];
        $container['controllers']['usuarioAdmin']->actualizar($data);
        exit;
    }

    // DELETE /admin/usuarios/{id} - Eliminar usuario
    if ($request === 'DELETE' && preg_match('/\/admin\/usuarios\/([^\/]+)$/', $path, $matches)) {
        $container['controllers']['usuarioAdmin']->eliminar(['id_usuario' => $matches[1]]);
        exit;
    }
};
