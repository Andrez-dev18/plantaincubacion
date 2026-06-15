/**
 * Servicio de Características
 * Gestiona la comunicación con la API de características
 */
class CaracteristicasService extends Service {
    constructor() {
        super(AppConfig.API.BASE_URL);
    }

    /**
     * Lista todas las características
     */
    async listar() {
        try {
            return await this.get('/caracteristicas');
        } catch (error) {
            return this.handleError(error);
        }
    }

    /**
     * Obtiene una característica específica por ID
     */
    async obtener(id) {
        try {
            return await this.get(`/caracteristicas/${id}`);
        } catch (error) {
            return this.handleError(error);
        }
    }

    /**
     * Crea una nueva característica
     */
    async crear(data) {
        try {
            return await this.post('/caracteristicas', data);
        } catch (error) {
            return this.handleError(error);
        }
    }

    /**
     * Actualiza una característica existente
     */
    async actualizar(id, data) {
        try {
            return await this.put(`/caracteristicas/${id}`, data);
        } catch (error) {
            return this.handleError(error);
        }
    }

    /**
     * Elimina una característica
     */
    async eliminar(id, forzar = false) {
        try {
            const url = forzar ? `/caracteristicas/${id}?forzar=true` : `/caracteristicas/${id}`;
            return await this.delete(url);
        } catch (error) {
            return this.handleError(error);
        }
    }

    /**
     * Verifica si una característica está en uso
     */
    async verificarUso(id) {
        try {
            return await this.get(`/caracteristicas/${id}/verificar-uso`);
        } catch (error) {
            return this.handleError(error);
        }
    }

    /**
     * Busca características por término
     */
    async buscar(searchTerm) {
        try {
            return await this.get(`/caracteristicas/buscar/${searchTerm}`);
        } catch (error) {
            return this.handleError(error);
        }
    }

    /**
     * Obtiene los tipos de datos disponibles
     */
    async obtenerTiposDatos() {
        try {
            return await this.get('/caracteristicas/tipos-datos');
        } catch (error) {
            return this.handleError(error);
        }
    }
}
