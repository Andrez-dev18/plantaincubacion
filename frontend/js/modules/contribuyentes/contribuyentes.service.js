/**
 * ContribuyentesService
 * Utiliza la clase base global 'Service' y el objeto helper 'Http'.
 */
class ContribuyentesService extends Service {
    constructor() {
        super();
        // Ruta base para los endpoints en PHP
        this.base = `${AppConfig.API.BASE_URL}/api/contribuyentes`;
    }

    /**
     * Obtiene el listado de contribuyentes
     * POST /api/contribuyentes/listar
     */
    getContribuyentes(dtParams = {}) {
        return Http.post(`${this.base}/listar`, dtParams);
    }

    /**
     * Obtiene los datos de un contribuyente específico por su código
     * POST /api/contribuyentes/obtener
     */
    obtenerContribuyente(codigo) {
        return Http.post(`${this.base}/obtener`, { codigo: codigo });
    }

    /**
     * Inserta o edita un contribuyente
     * POST /api/contribuyentes/guardar
     */
    guardarContribuyente(data) {
        return Http.post(`${this.base}/guardar`, data);
    }

    /**
     * Elimina un contribuyente
     * POST /api/contribuyentes/eliminar
     */
    eliminarContribuyente(codigo) {
        return Http.post(`${this.base}/eliminar`, { codigo: codigo });
    }

    /**
     * Alterna el estado activo/inactivo de un contribuyente
     * POST /api/contribuyentes/toggle
     */
    toggleActivoContribuyente(codigo) {
        return Http.post(`${this.base}/toggle`, { codigo: codigo });
    }
}

// Exponer el servicio al entorno global window
window.ContribuyentesService = ContribuyentesService;
