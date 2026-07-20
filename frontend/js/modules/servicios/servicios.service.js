/**
 * ServiciosService
 * Utiliza la clase base global 'Service' y el objeto helper 'Http'.
 */
class ServiciosService extends Service {
    constructor() {
        super();
        // Ruta unificada hacia los endpoints en PHP
        this.base = `${AppConfig.API.BASE_URL}/api/servicios`;
    }

    /**
     * Obtiene el listado de servicios
     * POST /api/servicios/listar
     */
    getServicios(dtParams = {}) {
        return Http.post(`${this.base}/listar`, dtParams);
    }

    /**
     * Obtiene los datos de un servicio específico por su código
     * POST /api/servicios/obtener
     */
    obtenerServicio(codi) {
        return Http.post(`${this.base}/obtener`, { codi: codi });
    }

    /**
     * Inserta o edita un servicio
     * POST /api/servicios/guardar
     */
    guardarServicio(data) {
        return Http.post(`${this.base}/guardar`, data);
    }

    /**
     * Elimina un servicio
     * POST /api/servicios/eliminar
     */
    eliminarServicio(codi) {
        return Http.post(`${this.base}/eliminar`, { codi: codi });
    }
}

// Exponer el servicio al entorno global window
window.ServiciosService = ServiciosService;
