/**
 * Servicio de Galpones
 * Gestiona la comunicación con la API de galpones
 */
class GalponesService extends Service {
    constructor() {
        super(AppConfig.API.BASE_URL);
    }

    /**
     * Lista todos los galpones
     */
    async listar() {
        try {
            const endpoint = AppConfig.API.ENDPOINTS.GALPONES.LISTAR;
            return await this.get(endpoint);
        } catch (error) {
            return this.handleError(error);
        }
    }

    /**
     * Obtiene un galpón específico por ID
     */
    async obtener(id, idGranja) {
        try {
            const endpoint = `${AppConfig.API.ENDPOINTS.GALPONES.OBTENER}/${id}?id_granja=${idGranja}`;
            return await this.get(endpoint);
        } catch (error) {
            return this.handleError(error);
        }
    }

    /**
     * Crea un nuevo galpón
     */
    async crear(data) {
        try {
            const endpoint = AppConfig.API.ENDPOINTS.GALPONES.CREAR;
            return await this.post(endpoint, data);
        } catch (error) {
            return this.handleError(error);
        }
    }

    /**
     * Actualiza un galpón existente
     */
    async actualizar(id, idGranja, data) {
        try {
            const endpoint = `${AppConfig.API.ENDPOINTS.GALPONES.ACTUALIZAR}/${id}?id_granja=${idGranja}`;
            return await this.put(endpoint, data);
        } catch (error) {
            return this.handleError(error);
        }
    }

    /**
     * Elimina un galpón 
     */
    async eliminar(id, idGranja) {
        try {
            const endpoint = `${AppConfig.API.ENDPOINTS.GALPONES.ELIMINAR}/${id}?id_granja=${idGranja}`;
            return await this.delete(endpoint);
        } catch (error) {
            return this.handleError(error);
        }
    }

    /**
     * Obtiene todas las características disponibles
     */
    async obtenerCaracteristicas() {
        try {
            const endpoint = AppConfig.API.ENDPOINTS.GALPONES.CARACTERISTICAS;
            return await this.get(endpoint);
        } catch (error) {
            return this.handleError(error);
        }
    }

    /**
     * Obtiene la lista de granjas disponibles
     */
    async obtenerGranjas() {
        try {
            const endpoint = AppConfig.API.ENDPOINTS.GALPONES.GRANJAS;
            return await this.get(endpoint);
        } catch (error) {
            return this.handleError(error);
        }
    }

    /**
     * Obtiene los galpones de una granja específica
     */
    async obtenerGalponesPorGranja(idGranja) {
        try {
            const endpoint = `${AppConfig.API.ENDPOINTS.GALPONES.LISTAR}/galpones-por-granja/${idGranja}`;
            return await this.get(endpoint);
        } catch (error) {
            return this.handleError(error);
        }
    }

    /**
     * Exporta galpones a PDF con filtros opcionales
     */
    exportarPDF(filtros = {}) {
        const params = new URLSearchParams();
        
        if (filtros.id_granja) {
            params.append('id_granja', filtros.id_granja);
        }
        if (filtros.fecha_desde) {
            params.append('fecha_desde', filtros.fecha_desde);
        }
        if (filtros.fecha_hasta) {
            params.append('fecha_hasta', filtros.fecha_hasta);
        }
        
        const endpoint = `${AppConfig.API.ENDPOINTS.GALPONES.LISTAR}/exportar-pdf${params.toString() ? '?' + params.toString() : ''}`;
        const url = `${this.baseUrl}${endpoint}`;
        
        // Abrir en nueva ventana para descargar
        window.open(url, '_blank');
    }

    exportarExcel(filtros = {}) {
        const params = new URLSearchParams();
        
        if (filtros.id_granja) {
            params.append('id_granja', filtros.id_granja);
        }
        if (filtros.fecha_desde) {
            params.append('fecha_desde', filtros.fecha_desde);
        }
        if (filtros.fecha_hasta) {
            params.append('fecha_hasta', filtros.fecha_hasta);
        }
        
        const url = `${this.baseUrl}${AppConfig.API.ENDPOINTS.GALPONES.EXPORTAR_EXCEL}${params.toString() ? '?' + params.toString() : ''}`;
        
        // Abrir en nueva ventana para descargar
        window.open(url, '_blank');
    }
}
