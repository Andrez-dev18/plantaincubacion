/**
 * Servicio de Cargapollo por Dia
 * Gestiona la comunicacion con la API de cargapollo
 */
class CargapolloPorDiaService extends Service {
    constructor() {
        super(AppConfig.API.BASE_URL);
    }

    /**
     * Lista todas las proyecciones disponibles
     */
    async listarProyecciones() {
        try {
            const endpoint = '/api/cargapollo/proyecciones';
            return await this.get(endpoint);
        } catch (error) {
            return this.handleError(error);
        }
    }

    /**
     * Obtiene los datos pivotados de una proyección
     */
    async obtenerDatos(proyeccion) {
        try {
            const endpoint = `/api/cargapollo/datos?proyeccion=${encodeURIComponent(proyeccion)}`;
            return await this.get(endpoint);
        } catch (error) {
            return this.handleError(error);
        }
    }

    /**
     * Obtiene el resumen de oferta/demanda
     */
    async obtenerResumen(proyeccion) {
        try {
            const endpoint = `/api/cargapollo/resumen?proyeccion=${encodeURIComponent(proyeccion)}`;
            return await this.get(endpoint);
        } catch (error) {
            return this.handleError(error);
        }
    }

    /**
     * Exporta a Excel (descarga directa)
     */
    exportarExcel(proyeccion) {
        const url = `${this.baseUrl}/api/cargapollo/excel?proyeccion=${encodeURIComponent(proyeccion)}`;
        window.open(url, '_blank');
    }

    /**
     * Genera Excel personalizado
     */
    async generarExcelPersonalizado(opciones) {
        try {
            const endpoint = '/api/cargapollo/excel-custom';
            return await this.post(endpoint, opciones);
        } catch (error) {
            return this.handleError(error);
        }
    }

    /**
     * Obtiene datos de fechaproy (calendario de fechas y cargas)
     */
    async obtenerFechaProy(proyeccion) {
        try {
            const endpoint = `/api/cargapollo/fechaproy?proyeccion=${encodeURIComponent(proyeccion)}`;
            return await this.get(endpoint);
        } catch (error) {
            return this.handleError(error);
        }
    }

    /**
     * Actualiza cargas de una fecha específica
     */
    async actualizarCargas(proyeccion, fecha, cargas) {
        try {
            const endpoint = '/api/cargapollo/actualizar-cargas';
            return await this.put(endpoint, { proyeccion, fecha, cargas });
        } catch (error) {
            return this.handleError(error);
        }
    }

    /**
     * Copia el calendario de una proyección origen a la proyección destino
     */
    async copiarCalendario(proyeccionDestino, proyeccionOrigen) {
        try {
            const endpoint = '/api/secuencia/copiar-calendario';
            return await this.post(endpoint, {
                proyeccion_destino: proyeccionDestino,
                proyeccion_origen: proyeccionOrigen
            });
        } catch (error) {
            return this.handleError(error);
        }
    }

    /**
     * Recalcula el calendario completo basado en secuencia y cargas
     */
    async recalcular(proyeccion, fechaDesde = null, fechaHasta = null) {
        try {
            const endpoint = '/api/cargapollo/recalcular';
            const payload = { proyeccion };
            if (fechaDesde) payload.fechaDesde = fechaDesde;
            if (fechaHasta) payload.fechaHasta = fechaHasta;
            return await this.post(endpoint, payload);
        } catch (error) {
            return this.handleError(error);
        }
    }
}
