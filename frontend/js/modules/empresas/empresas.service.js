/**
 * EmpresasService
 * Utiliza la clase base global 'Service' y el objeto helper 'Http'.
 */
class EmpresasService extends Service {
    constructor() {
        super();
        // Ruta unificada y limpia hacia tus nuevos endpoints en PHP
        this.base = `${AppConfig.API.BASE_URL}/api/empresa`;
    }

    /**
     * Obtiene el listado de empresas
     * POST /api/empresa/listar
     */
    getEmpresas(dtParams = {}) {
        return Http.post(`${this.base}/listar`, dtParams);
    }

    /**
     * Obtiene los datos de una empresa específica por su ID
     * POST /api/empresa/obtener
     */
    obtenerEmpresa(id) {
        return Http.post(`${this.base}/obtener`, { id: id });
    }

    /**
     * Inserta o edita una empresa
     * POST /api/empresa/guardar
     */
    guardarEmpresa(data) {
        return Http.post(`${this.base}/guardar`, data);
    }

    /**
     * Activa o desactiva una empresa
     * POST /api/empresa/toggle
     */
    cambiarEstado(id) {
        return Http.post(`${this.base}/toggle`, { id: id });
    }

    /**
     * Elimina una empresa
     * POST /api/empresa/eliminar
     */
    eliminarEmpresa(id) {
        return Http.post(`${this.base}/eliminar`, { id: id });
    }

    /**
     * Lista clientes/proveedores de la tabla ccte
     * POST /api/empresa/listarCCTE
     */
    listarCCTE(q = '', page = 1, pageSize = 20) {
        return Http.post(`${this.base}/listarCCTE`, { q: q, page: page, pageSize: pageSize });
    }
}

// Exponer el servicio al entorno global window
window.EmpresasService = EmpresasService;