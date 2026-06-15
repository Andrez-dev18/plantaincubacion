<?php
// SEGURIDAD DESACTIVADA TEMPORALMENTE
session_start();
// Liberar el lock del archivo de sesión inmediatamente.
// Así los 8+ requests paralelos del frontend no se bloquean entre sí.
// Los controladores que ESCRIBEN en $_SESSION (login/logout) llaman
// session_start() de nuevo justo antes de escribir.
session_write_close();

// Cabeceras CORS generales
header("Access-Control-Allow-Origin: https://granjarinconadadelsur.com");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Access-Control-Allow-Credentials: true");

// Responder preflight OPTIONS sin bloquear nada
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

/*
// VALIDACIÓN DE SESIÓN COMENTADA - NO SE USA POR AHORA
$path = $_SERVER['REQUEST_URI'] ?? '';

// Rutas abiertas que NO necesitan sesión
$openRoutes = [
    '/usuario/login',
    '/usuario/validarSesion',
    '/usuario/logout'
];

// Verificar si la ruta es abierta
$isOpenRoute = false;
foreach ($openRoutes as $route) {
    if (strpos($path, $route) !== false) {
        $isOpenRoute = true;
        break;
    }
}

// Validar sesión si no es ruta abierta
if (!$isOpenRoute) {
    if (!isset($_SESSION['usuario'])) {
        http_response_code(401);
        echo json_encode([
            "success" => false,
            "message" => "Sesion no valida o expirada"
        ]);
        exit;
    }
}
*/
