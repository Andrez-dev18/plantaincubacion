<?php
/**
 * UsuarioSistemaController — Módulo Usuarios del Sistema
 *
 * Endpoints:
 *   GET  /api/usuario-sistema/listar     → listar()          — todos los usuarios
 *   POST /api/usuario-sistema/crear      → crear()           — nuevo usuario
 *   POST /api/usuario-sistema/actualizar → actualizar()      — editar nombre
 *   POST /api/usuario-sistema/toggle     → toggleActivo()    — activar / desactivar
 *   POST /api/usuario-sistema/password   → cambiarPassword() — cambiar contraseña
 *
 * @package Backend
 * @subpackage Controllers
 */
class UsuarioSistemaController {

    private $usuarioSistemaRepository;

    public function __construct($usuarioSistemaRepository) {
        $this->usuarioSistemaRepository = $usuarioSistemaRepository;
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

    // ─── GET /api/usuario-sistema/listar ─────────────────────────────────

    public function listar(): void {
        try {
            $usuarios = $this->usuarioSistemaRepository->obtenerTodosAdmin();
            $this->json(['success' => true, 'data' => $usuarios]);
        } catch (Exception $e) {
            error_log('UsuarioSistemaController::listar — ' . $e->getMessage());
            $this->json(['success' => false, 'message' => 'Error al obtener usuarios.'], 500);
        }
    }

    // ─── POST /api/usuario-sistema/crear ─────────────────────────────────
    // Body JSON: { "codigo": "...", "nombre": "...", "password": "..." }

    public function crear(): void {
        try {
            $data     = $this->input();
            $codigo   = trim($data['codigo'] ?? '');
            $nombre   = trim($data['nombre'] ?? '');
            $password = $data['password'] ?? '';

            if (empty($codigo)) {
                $this->json(['success' => false, 'message' => 'El código de usuario es obligatorio.'], 400);
                return;
            }
            if (empty($nombre)) {
                $this->json(['success' => false, 'message' => 'El nombre es obligatorio.'], 400);
                return;
            }
            if (strlen($password) < 4) {
                $this->json(['success' => false, 'message' => 'La contraseña debe tener al menos 4 caracteres.'], 400);
                return;
            }

            $id = $this->usuarioSistemaRepository->crear([
                'codigo'   => $codigo,
                'nombre'   => $nombre,
                'password' => $password,
            ]);

            $this->json([
                'success' => true,
                'message' => 'Usuario creado correctamente.',
                'codigo'  => $id,
            ], 201);

        } catch (Exception $e) {
            $code = (strpos($e->getMessage(), 'ya está registrado') !== false) ? 409 : 500;
            error_log('UsuarioSistemaController::crear — ' . $e->getMessage());
            $this->json(['success' => false, 'message' => $e->getMessage()], $code);
        }
    }

    // ─── POST /api/usuario-sistema/actualizar ────────────────────────────
    // Body JSON: { "codigo": "...", "nombre": "..." }

    public function actualizar(): void {
        try {
            $data   = $this->input();
            $codigo = trim($data['codigo'] ?? '');
            $nombre = trim($data['nombre'] ?? '');

            if (empty($codigo)) {
                $this->json(['success' => false, 'message' => 'El código es obligatorio.'], 400);
                return;
            }
            if (empty($nombre)) {
                $this->json(['success' => false, 'message' => 'El nombre es obligatorio.'], 400);
                return;
            }

            $this->usuarioSistemaRepository->actualizar($codigo, $nombre);
            $this->json(['success' => true, 'message' => 'Usuario actualizado correctamente.']);

        } catch (Exception $e) {
            error_log('UsuarioSistemaController::actualizar — ' . $e->getMessage());
            $this->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    // ─── POST /api/usuario-sistema/toggle ────────────────────────────────
    // Body JSON: { "codigo": "...", "activo": 1|0 }

    public function toggleActivo(): void {
        try {
            $data   = $this->input();
            $codigo = trim($data['codigo'] ?? '');
            $activo = (int)($data['activo'] ?? 0);

            if (empty($codigo)) {
                $this->json(['success' => false, 'message' => 'El código es obligatorio.'], 400);
                return;
            }

            $this->usuarioSistemaRepository->toggleActivo($codigo, $activo);
            $estado = $activo ? 'activado' : 'desactivado';
            $this->json(['success' => true, 'message' => "Usuario {$estado} correctamente."]);

        } catch (Exception $e) {
            error_log('UsuarioSistemaController::toggleActivo — ' . $e->getMessage());
            $this->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    // ─── POST /api/usuario-sistema/password ──────────────────────────────
    // Body JSON: { "codigo": "...", "password": "..." }

    public function cambiarPassword(): void {
        try {
            $data     = $this->input();
            $codigo   = trim($data['codigo'] ?? '');
            $password = $data['password'] ?? '';

            if (empty($codigo)) {
                $this->json(['success' => false, 'message' => 'El código es obligatorio.'], 400);
                return;
            }
            if (strlen($password) < 4) {
                $this->json(['success' => false, 'message' => 'La contraseña debe tener al menos 4 caracteres.'], 400);
                return;
            }

            $this->usuarioSistemaRepository->cambiarPassword($codigo, $password);
            $this->json(['success' => true, 'message' => 'Contraseña actualizada correctamente.']);

        } catch (Exception $e) {
            error_log('UsuarioSistemaController::cambiarPassword — ' . $e->getMessage());
            $this->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }
}
