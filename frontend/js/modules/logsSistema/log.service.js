/**
 * LogService
 * Utiliza la clase base global 'Service' y el objeto helper 'Http'.
 */
class LogService extends Service {
    constructor() {
        super();
        this.base = `${AppConfig.API.BASE_URL}/api/logs`;
    }

    /**
     * Obtiene los valores únicos de filtros para los desplegables de búsqueda
     * POST /api/logs/filtros
     */
    getFiltros() {
        return Http.post(`${this.base}/filtros`, {});
    }

    /**
     * Obtiene el listado de logs filtrados y paginados para DataTable
     * POST /api/logs/listar
     */
    getFiltered(dtParams = {}) {
        return Http.post(`${this.base}/listar`, dtParams);
    }

    /**
     * Inserta un nuevo log
     * POST /api/logs/guardar
     */
    guardarLog(data) {
        return Http.post(`${this.base}/guardar`, data);
    }
}

// Exponer el servicio al entorno global window como una instancia
window.LogService = new LogService();
