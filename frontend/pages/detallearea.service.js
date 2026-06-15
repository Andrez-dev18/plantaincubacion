class DetalleAreaService {
    constructor() {
        this.baseURL = window.AppConfig.API.BASE_URL;
    }

    async getAll() {
        try {
            const url = `${this.baseURL}${window.AppConfig.API.ENDPOINTS.DETALLE_AREA.ALL}`;
            console.log('Fetching from:', url);

            const response = await fetch(url, {
                method: 'GET',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json'
                }
            });

            if (!response.ok) {
                const errorText = await response.text();
                throw new Error(`Error ${response.status}: ${errorText}`);
            }

            const data = await response.json();
            return Array.isArray(data) ? data : [];
        } catch (error) {
            console.error('Error en getAll:', error);
            throw new Error(`No se pudo cargar Detalle Área: ${error.message}`);
        }
    }

    async crear(data) {
        try {
            const url = `${this.baseURL}${window.AppConfig.API.ENDPOINTS.DETALLE_AREA.CREAR}`;
            
            const response = await fetch(url, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(data)
            });

            if (!response.ok) {
                const error = await response.json();
                throw new Error(error.message || 'Error al crear registro');
            }

            return await response.json();
        } catch (error) {
            console.error('Error en crear:', error);
            throw error;
        }
    }

    async actualizar(data) {
        try {
            const url = `${this.baseURL}${window.AppConfig.API.ENDPOINTS.DETALLE_AREA.EDITAR}`;

            let response = await fetch(url, {
                method: 'PUT',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(data)
            });

            const responseText = await response.text();

            if (!response.ok) {
                response = await fetch(url, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(data)
                });
                const fallbackText = await response.text();
                if (!response.ok) {
                    let errorMsg = 'Error al actualizar registro';
                    try {
                        const errorData = JSON.parse(fallbackText);
                        errorMsg = errorData.message || errorData.error || errorMsg;
                    } catch (e) {
                        errorMsg = fallbackText || errorMsg;
                    }
                    throw new Error(errorMsg);
                }
                return JSON.parse(fallbackText);
            }
            return JSON.parse(responseText);
        } catch (error) {
            console.error('Error en actualizar:', error);
            throw error;
        }
    }

    async eliminar(id) {
        try {
            const url = `${this.baseURL}${window.AppConfig.API.ENDPOINTS.DETALLE_AREA.BORRAR}/${id}`;

            let response = await fetch(url, { method: 'DELETE' });
            let responseText = await response.text();

            if (!response.ok) {
                const contentType = response.headers.get('content-type') || '';
                if (contentType.includes('text/html') || response.status === 405 || response.status === 404) {
                    response = await fetch(url, { method: 'POST' });
                    responseText = await response.text();
                }

                if (!response.ok) {
                    let errorMsg = 'Error al eliminar registro';
                    try {
                        const errorData = JSON.parse(responseText);
                        errorMsg = errorData.message || errorData.error || errorMsg;
                    } catch (e) {
                        errorMsg = responseText || errorMsg;
                    }
                    throw new Error(errorMsg);
                }
            }

            return responseText ? JSON.parse(responseText) : { success: true };
        } catch (error) {
            console.error('Error en eliminar:', error);
            throw error;
        }
    }

    async filtrar(filtros) {
        try {
            const params = new URLSearchParams();

            if (filtros.fechaInicio) params.append('fechaInicio', filtros.fechaInicio);
            if (filtros.fechaFin) params.append('fechaFin', filtros.fechaFin);
            if (filtros.provincia) params.append('provincia', filtros.provincia);
            if (filtros.area) params.append('area', filtros.area);

            const url = `${this.baseURL}${window.AppConfig.API.ENDPOINTS.DETALLE_AREA.FILTRO}${params}`;

            const response = await fetch(url);

            if (!response.ok) {
                const errorText = await response.text();
                throw new Error(`Error ${response.status}: ${errorText}`);
            }

            const result = await response.json();

            if (!result || typeof result !== 'object' || !Array.isArray(result.data)) {
                throw new Error('Formato de respuesta inválido');
            }

            return result;
        } catch (error) {
            console.error('Error en filtrar:', error);
            throw error;
        }
    }

    async exportarExcel() {
        try {
            window.open(`${this.baseURL}${window.AppConfig.API.ENDPOINTS.DETALLE_AREA.EXPORTAR}`, '_blank');
        } catch (error) {
            console.error('Error en exportar:', error);
            throw error;
        }
    }

    async getProvincias() {
        try {
            const response = await fetch(`${this.baseURL}/provincia/all`);
            if (!response.ok) throw new Error('Error al obtener provincias');
            const data = await response.json();
            return data.map(item => ({
                id: item.codigo,
                provincia: item.nombre
            }));
        } catch (error) {
            console.error('Error al obtener provincias:', error);
            return [];
        }
    }

    getAreas() {
        return Promise.resolve(DetalleAreaConfig.AREAS);
    }
}
