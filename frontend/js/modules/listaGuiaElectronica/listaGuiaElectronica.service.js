class ListaGuiaElectronicaService extends Service {
    constructor() {
        super();
        this.base = '/plantaincubacion/backend/index.php/api/guia-electronica';
    }

    /**
     * Obtiene el listado de almacenes/zonas
     */
    getAlmacenes() {
        return Http.get(`${this.base}/zonas`);
    }

    /**
     * Obtiene el listado de guías de remisión filtrado
     */
    listarGuias(filtros = {}) {
        return Http.get(`${this.base}/listar`, filtros);
    }

    /**
     * Obtiene el detalle de una guía de remisión específica
     */
    getGuiaDetalle(params = {}) {
        return Http.get(`${this.base}/detalle`, params);
    }

    /**
     * Obtiene el detalle de ítems de una guía mediante el endpoint de listado
     */
    getDetalleGuia(params = {}) {
        return Http.get('/plantaincubacion/backend/index.php/api/lista-guia-electronica/detalle', params);
    }

    /**
     * Retorna la URL del backend encargada de generar el PDF de la guía
     */
    getImprimirUrl(serie, numero) {
        return `${this.base}/pdf?serie=${encodeURIComponent(serie)}&numero=${encodeURIComponent(numero)}`;
    }

    /**
     * Retorna la URL del backend para generar el reporte PDF usando el identificador treg
     */
    getImprimirPdfUrl(treg) {
        return `/plantaincubacion/backend/index.php/api/lista-guia-electronica/pdf?treg=${encodeURIComponent(treg)}`;
    }

    /**
     * Retorna la URL del proxy para cargar PDFs externos (ej. NubeFact) evitando bloqueos de X-Frame-Options
     */
    getProxyPdfUrl(url) {
        return `/plantaincubacion/backend/index.php/api/lista-guia-electronica/pdf-externo?url=${encodeURIComponent(url)}`;
    }

    /**
     * Elimina una guía de remisión por su treg
     */
    deleteGuia(treg) {
        return Http.delete('/plantaincubacion/backend/index.php/api/lista-guia-electronica/eliminar', { treg });
    }

    /**
     * Consulta el estado de una guía de remisión electrónica en SUNAT/NubeFact
     */
    consultarGuia(serie, numero, treg) {
        return Http.get(`${this.base}/consultar`, { serie, numero, treg });
    }
}

window.ListaGuiaElectronicaService = ListaGuiaElectronicaService;
