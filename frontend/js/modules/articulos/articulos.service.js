/**
 * ArticulosService
 * Utiliza la clase base global 'Service' y el objeto helper 'Http'.
 */
class ArticulosService extends Service {
    constructor() {
        super();
        // Ruta base para los endpoints en PHP
        this.base = `${AppConfig.API.BASE_URL}/api/articulos`;
    }

    /**
     * Obtiene el listado de artículos
     * POST /api/articulos/listar
     */
    getArticulos(dtParams = {}) {
        return Http.post(`${this.base}/listar`, dtParams);
    }

    /**
     * Obtiene los datos de un artículo específico por su código
     * POST /api/articulos/obtener
     */
    obtenerArticulo(codigo) {
        return Http.post(`${this.base}/obtener`, { codigo: codigo });
    }

    /**
     * Inserta o edita un artículo
     * POST /api/articulos/guardar
     */
    guardarArticulo(data) {
        return Http.post(`${this.base}/guardar`, data);
    }

    /**
     * Elimina un artículo
     * POST /api/articulos/eliminar
     */
    eliminarArticulo(codigo) {
        return Http.post(`${this.base}/eliminar`, { codigo: codigo });
    }
}

// Exponer el servicio al entorno global window
window.ArticulosService = ArticulosService;
