<?php
/**
 * RolController — Módulo Roles y Permisos
 *
 * Endpoints:
 *   GET  /api/rol/listar              → listar()
 *   GET  /api/rol/programas           → listarProgramas()
 *   GET  /api/rol/menus-disponibles   → menusDisponibles()
 *   GET  /api/rol/modulos             → obtenerModulos()
 *   POST /api/rol/crear               → crear()
 *   POST /api/rol/actualizar          → actualizar()
 *   POST /api/rol/toggle              → toggleActivo()
 *   POST /api/rol/guardar-modulos     → guardarModulos()
 *   POST /api/rol/eliminar            → eliminar()
 *
 * @package Backend
 * @subpackage Controllers
 */
class RolController {

    private $rolRepository;

    public function __construct($rolRepository) {
        $this->rolRepository = $rolRepository;
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

    // ─── GET /api/rol/listar ──────────────────────────────────────────────

    public function listar(): void {
        try {
            $roles = $this->rolRepository->obtenerTodos();
            $this->json(['success' => true, 'data' => $roles]);
        } catch (Exception $e) {
            error_log('RolController::listar — ' . $e->getMessage());
            $this->json(['success' => false, 'message' => 'Error al obtener roles.'], 500);
        }
    }

    // ─── GET /api/rol/programas ───────────────────────────────────────────

    public function listarProgramas(): void {
        try {
            $programas = $this->rolRepository->obtenerProgramas();
            $this->json(['success' => true, 'data' => $programas]);
        } catch (Exception $e) {
            error_log('RolController::listarProgramas — ' . $e->getMessage());
            $this->json(['success' => false, 'message' => 'Error al obtener programas.'], 500);
        }
    }

    // ─── GET /api/rol/menus-disponibles?id_programa=X ────────────────────

    public function menusDisponibles(): void {
        try {
            $idPrograma = trim($_GET['id_programa'] ?? '');
            if ($idPrograma === '') {
                $this->json(['success' => true, 'data' => []]);
                return;
            }
            $menus = $this->rolRepository->obtenerMenusDisponibles($idPrograma);
            $this->json(['success' => true, 'data' => $menus]);
        } catch (Exception $e) {
            error_log('RolController::menusDisponibles — ' . $e->getMessage());
            $this->json(['success' => false, 'message' => 'Error al obtener menús.'], 500);
        }
    }

    // ─── GET /api/rol/modulos?id_rol=X&id_programa=Y ─────────────────────

    public function obtenerModulos(): void {
        $idRol      = (int)($_GET['id_rol'] ?? 0);
        $idPrograma = trim($_GET['id_programa'] ?? '');
        if ($idRol <= 0) {
            $this->json(['success' => false, 'message' => 'id_rol inválido.'], 400);
            return;
        }
        try {
            $modulos = $this->rolRepository->obtenerModulosDeRol($idRol, $idPrograma);
            $this->json(['success' => true, 'data' => $modulos]);
        } catch (Exception $e) {
            error_log('RolController::obtenerModulos — ' . $e->getMessage());
            $this->json(['success' => false, 'message' => 'Error al obtener módulos del rol.'], 500);
        }
    }

    // ─── POST /api/rol/crear ──────────────────────────────────────────────

    public function crear(): void {
        try {
            $data   = $this->input();
            $codRol = trim($data['cod_rol'] ?? '');
            $nomRol = trim($data['nom_rol'] ?? '');

            if (empty($codRol)) {
                $this->json(['success' => false, 'message' => 'El código del rol es obligatorio.'], 400);
                return;
            }
            if (empty($nomRol)) {
                $this->json(['success' => false, 'message' => 'El nombre del rol es obligatorio.'], 400);
                return;
            }

            $id = $this->rolRepository->crear($data);
            $this->json(['success' => true, 'message' => 'Rol creado correctamente.', 'id' => $id]);
        } catch (Exception $e) {
            error_log('RolController::crear — ' . $e->getMessage());
            $this->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }

    // ─── POST /api/rol/actualizar ─────────────────────────────────────────

    public function actualizar(): void {
        try {
            $data = $this->input();
            $id   = (int)($data['id'] ?? 0);
            $nom  = trim($data['nom_rol'] ?? '');
            $desc = trim($data['descripcion'] ?? '');

            if ($id <= 0)   { $this->json(['success' => false, 'message' => 'ID inválido.'], 400); return; }
            if (empty($nom)){ $this->json(['success' => false, 'message' => 'El nombre es obligatorio.'], 400); return; }

            $this->rolRepository->actualizar($id, $nom, $desc);
            $this->json(['success' => true, 'message' => 'Rol actualizado correctamente.']);
        } catch (Exception $e) {
            error_log('RolController::actualizar — ' . $e->getMessage());
            $this->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    // ─── POST /api/rol/toggle ─────────────────────────────────────────────

    public function toggleActivo(): void {
        try {
            $data   = $this->input();
            $id     = (int)($data['id'] ?? 0);
            $activo = (int)($data['activo'] ?? 0);

            if ($id <= 0) { $this->json(['success' => false, 'message' => 'ID inválido.'], 400); return; }

            $this->rolRepository->toggleActivo($id, $activo);
            $estado = $activo ? 'activado' : 'desactivado';
            $this->json(['success' => true, 'message' => "Rol {$estado} correctamente."]);
        } catch (Exception $e) {
            error_log('RolController::toggleActivo — ' . $e->getMessage());
            $this->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    // ─── POST /api/rol/guardar-modulos ────────────────────────────────────
    // Body JSON: { "id_rol": X, "id_programa": "Y", "cod_mods": ["MOD1", ...] }

    public function guardarModulos(): void {
        try {
            $data       = $this->input();
            $idRol      = (int)($data['id_rol'] ?? 0);
            $idPrograma = trim($data['id_programa'] ?? '');
            $codMods    = $data['cod_mods'] ?? [];

            if ($idRol <= 0)       { $this->json(['success' => false, 'message' => 'id_rol inválido.'], 400); return; }
            if ($idPrograma === '') { $this->json(['success' => false, 'message' => 'id_programa requerido.'], 400); return; }
            if (!is_array($codMods)){ $this->json(['success' => false, 'message' => 'cod_mods debe ser un arreglo.'], 400); return; }

            $this->rolRepository->guardarModulos($idRol, $idPrograma, $codMods);
            $this->json([
                'success' => true,
                'message' => count($codMods) . ' módulo(s) asignados correctamente.',
            ]);
        } catch (Exception $e) {
            error_log('RolController::guardarModulos — ' . $e->getMessage());
            $this->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    // ─── POST /api/rol/eliminar ───────────────────────────────────────────

    public function eliminar(): void {
        try {
            $data = $this->input();
            $id   = (int)($data['id'] ?? 0);

            if ($id <= 0) { $this->json(['success' => false, 'message' => 'ID inválido.'], 400); return; }

            $this->rolRepository->eliminar($id);
            $this->json(['success' => true, 'message' => 'Rol eliminado correctamente.']);
        } catch (Exception $e) {
            error_log('RolController::eliminar — ' . $e->getMessage());
            $this->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }
}
