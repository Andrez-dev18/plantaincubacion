/**
 * GestionRolesService — Servicio para la gestión de roles en Planta de Incubación
 */
class GestionRolesService extends Service {
    constructor() {
        super();
        this.base = `${AppConfig.API.BASE_URL}/api/rol`;
    }

    /**
     * Obtiene el listado completo de roles
     */
    listarRoles() {
        return Http.post(`${this.base}/listar`);
    }

    /**
     * Obtiene el árbol completo de módulos permitidos
     */
    obtenerModulos() {
        return Http.post(`${this.base}/arbol`);
    }

    /**
     * Obtiene los datos de un rol por su ID
     */
    obtenerPorId(id) {
        return Http.post(`${this.base}/obtener`, { id: id });
    }

    /**
     * Guarda (crea o edita) un rol
     */
    guardar(data) {
        return Http.post(`${this.base}/guardar`, data);
    }

    /**
     * Elimina un rol
     */
    eliminar(id) {
        return Http.post(`${this.base}/eliminar`, { id: id });
    }

    /**
     * Activa o desactiva un rol
     */
    cambiarEstado(id, estado) {
        return Http.post(`${this.base}/toggle`, { id: id, estado: estado });
    }
}

window.GestionRolesService = GestionRolesService;
