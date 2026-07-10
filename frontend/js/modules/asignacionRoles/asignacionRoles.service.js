/**
 * AsignacionService — Servicio para la asignación de roles a usuarios (PIC)
 */
class AsignacionService extends Service {
    constructor() {
        super();
        this.base = `${AppConfig.API.BASE_URL}/api/asignacion`;
    }

    /**
     * Paginación Server-Side para la grilla de usuarios con sus roles
     */
    obtenerDatatable(dtParams) {
        return Http.post(`${this.base}/datatable`, dtParams);
    }

    /**
     * Obtiene roles disponibles de Planta de Incubación y marcados del usuario
     */
    obtenerRolesParaUsuario(codigo) {
        return Http.post(`${this.base}/obtener`, { codigo: codigo });
    }

    /**
     * Guarda la asignación de roles para el usuario
     */
    guardar(payload) {
        return Http.post(`${this.base}/guardar`, payload);
    }
}

window.AsignacionService = AsignacionService;
