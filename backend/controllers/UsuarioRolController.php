<?php
/**
 * UsuarioRolController — Asignación de roles a usuarios (IAM)
 *
 * Endpoints:
 *   GET  /api/usuario-rol/usuarios-con-roles  → usuariosConRoles()
 *   GET  /api/usuario-rol/roles-activos       → rolesActivos()
 *   GET  /api/usuario-rol/roles-dni           → rolesDni()
 *   POST /api/usuario-rol/guardar             → guardar()
 *
 * @package Backend
 * @subpackage Controllers
 */
class UsuarioRolController {

    private $usuarioRolRepository;

    public function __construct($usuarioRolRepository) {
        $this->usuarioRolRepository = $usuarioRolRepository;
    }

    // ─── helpers ──────────────────────────────────────────────────────────

    private function json($data, int $status = 200): void {
        http_response_code($status);
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE);
    }

    private function input(): array {
        return json_decode(file_get_contents('php://input'), true) ?? [];
    }

    // ─── GET /api/usuario-rol/usuarios-con-roles ──────────────────────────

    public function usuariosConRoles(): void {
        try {
            $data = $this->usuarioRolRepository->obtenerUsuariosConRoles();
            $this->json(['success' => true, 'data' => $data]);
        } catch (Exception $e) {
            error_log('UsuarioRolController::usuariosConRoles — ' . $e->getMessage());
            $this->json(['success' => false, 'message' => 'Error al obtener usuarios.'], 500);
        }
    }

    // ─── GET /api/usuario-rol/roles-activos ───────────────────────────────

    public function rolesActivos(): void {
        try {
            $roles = $this->usuarioRolRepository->obtenerRolesActivos();
            $this->json(['success' => true, 'data' => $roles]);
        } catch (Exception $e) {
            error_log('UsuarioRolController::rolesActivos — ' . $e->getMessage());
            $this->json(['success' => false, 'message' => 'Error al obtener roles.'], 500);
        }
    }

    // ─── GET /api/usuario-rol/roles-codigo?codigo=CODIGO ────────────────────

    public function rolesCodigo(): void {
        $codigo = trim($_GET['codigo'] ?? '');
        if ($codigo === '') {
            $this->json(['success' => false, 'message' => 'Parámetro codigo requerido.'], 400);
            return;
        }
        try {
            $ids = $this->usuarioRolRepository->obtenerRolesCodigo($codigo);
            $this->json(['success' => true, 'data' => $ids]);
        } catch (Exception $e) {
            error_log('UsuarioRolController::rolesCodigo — ' . $e->getMessage());
            $this->json(['success' => false, 'message' => 'Error al obtener roles.'], 500);
        }
    }

    // ─── GET /api/usuario-rol/roles-dni?dni=CODIGO (alias retrocompatibilidad) ─

    public function rolesDni(): void {
        $_GET['codigo'] = $_GET['codigo'] ?? $_GET['dni'] ?? '';
        $this->rolesCodigo();
    }

    // ─── POST /api/usuario-rol/guardar ────────────────────────────────────

    public function guardar(): void {
        $body = $this->input();

        // Acepta 'codigo' (nuevo sistema) o 'dni' (alias legacy)
        $dni   = trim($body['codigo'] ?? $body['dni'] ?? '');
        $roles = $body['ids_rol'] ?? [];

        if ($dni === '') {
            $this->json(['success' => false, 'message' => 'El campo dni es requerido.'], 400);
            return;
        }
        if (!is_array($roles)) {
            $this->json(['success' => false, 'message' => 'ids_rol debe ser un arreglo.'], 400);
            return;
        }

        try {
            $this->usuarioRolRepository->guardarRolesUsuario($dni, $roles);
            $this->json([
                'success' => true,
                'message' => 'Roles del usuario actualizados correctamente.',
            ]);
        } catch (Exception $e) {
            error_log('UsuarioRolController::guardar — ' . $e->getMessage());
            $this->json(['success' => false, 'message' => 'Error al guardar roles: ' . $e->getMessage()], 500);
        }
    }
}
