/**
 * ConfigApisService — Servicio para la gestión de configuración de APIs
 */
class ConfigApisService extends Service {
    constructor() {
        super();
        this.base = `${AppConfig.API.BASE_URL}/api/config-api`;
    }

    /**
     * Obtiene el listado completo de APIs configuradas
     */
    listar() {
        return Http.post(`${this.base}/listar`);
    }

    /**
     * Obtiene los datos de una API por su ID
     */
    obtener(id) {
        return Http.post(`${this.base}/obtener`, { id: id });
    }

    /**
     * Guarda (crea o edita) una API
     */
    guardar(data) {
        return Http.post(`${this.base}/guardar`, data);
    }

    /**
     * Actualiza exclusivamente el token de una API
     */
    actualizarToken(id, token) {
        return Http.post(`${this.base}/actualizar-token`, { id: id, token: token });
    }

    /**
     * Elimina una API por su ID
     */
    eliminar(id) {
        return Http.post(`${this.base}/eliminar`, { id: id });
    }
}

window.ConfigApisService = ConfigApisService;
