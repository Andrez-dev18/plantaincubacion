/**
 * Servicio de Secuencia Base de Proyeccion
 * Gestiona la comunicacion con la API para secuencia base
 */
class SecuenciaBaseProyeccionService extends Service {
    constructor() {
        super(AppConfig.API.BASE_URL);
    }

    /**
     * Lista todas las proyecciones disponibles
     */
    async listarProyecciones() {
        try {
            const endpoint = '/api/secuencia/proyecciones';
            return await this.get(endpoint);
        } catch (error) {
            return this.handleError(error);
        }
    }

    /**
     * Obtiene los datos de ccosbase para una proyección con paginación
     */
    async obtenerDatosBase(proyeccion, page = 1, limit = 200) {
        try {
            const endpoint = `/api/secuencia/base?proyeccion=${encodeURIComponent(proyeccion)}&page=${page}&limit=${limit}`;
            return await this.get(endpoint);
        } catch (error) {
            return this.handleError(error);
        }
    }

    /**
     * Obtiene TODOS los datos de ccosbase cargando todas las páginas
     */
    async obtenerDatosBaseCompletos(proyeccion) {
        try {
            let allData = [];
            let page = 1;
            let hasMore = true;

            while (hasMore) {
                const response = await this.obtenerDatosBase(proyeccion, page, 200);
                if (!response.success || !response.data) {
                    throw new Error(response.message || 'Error al obtener datos');
                }

                allData = allData.concat(response.data);
                hasMore = response.pagination.has_next;
                page++;
            }

            return {
                success: true,
                data: allData,
                total: allData.length
            };
        } catch (error) {
            return this.handleError(error);
        }
    }

    /**
     * Obtiene los datos de ccosproy para una proyección con paginación
     */
    async obtenerDatosProyeccion(proyeccion, page = 1, limit = 200) {
        try {
            const endpoint = `/api/secuencia/proyeccion?proyeccion=${encodeURIComponent(proyeccion)}&page=${page}&limit=${limit}`;
            return await this.get(endpoint);
        } catch (error) {
            return this.handleError(error);
        }
    }

    /**
     * Obtiene TODOS los datos de ccosproy cargando todas las páginas
     */
    async obtenerDatosProyeccionCompletos(proyeccion) {
        try {
            let allData = [];
            let page = 1;
            let hasMore = true;

            while (hasMore) {
                const response = await this.obtenerDatosProyeccion(proyeccion, page, 200);
                if (!response.success || !response.data) {
                    throw new Error(response.message || 'Error al obtener datos');
                }

                allData = allData.concat(response.data);
                hasMore = response.pagination.has_next;
                page++;
            }

            return {
                success: true,
                data: allData,
                total: allData.length
            };
        } catch (error) {
            return this.handleError(error);
        }
    }

    /**
     * Copia secuencia de otra proyección
     */
    async copiarSecuencia(proyeccionDestino, proyeccionOrigen) {
        try {
            const endpoint = '/api/secuencia/copiar-secuencia';
            return await this.post(endpoint, {
                proyeccion_destino: proyeccionDestino,
                proyeccion_origen: proyeccionOrigen
            });
        } catch (error) {
            return this.handleError(error);
        }
    }

    /**
     * Crea la secuencia (PASO 3 del VBA)
     */
    async crearSecuencia(proyeccion, hastaCiclo) {
        try {
            const endpoint = '/api/secuencia/crear-secuencia';
            return await this.post(endpoint, {
                proyeccion: proyeccion,
                hasta_ciclo: hastaCiclo
            });
        } catch (error) {
            return this.handleError(error);
        }
    }

    /**
     * Crea el calendario (PASO 4 del VBA)
     */
    async crearCalendario(proyeccion) {
        try {
            const endpoint = '/api/secuencia/crear-calendario';
            return await this.post(endpoint, {
                proyeccion: proyeccion
            });
        } catch (error) {
            return this.handleError(error);
        }
    }

    /**
     * Copia el calendario de otra proyección
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
     * Guarda o actualiza un registro de base
     */
    async guardarBase(proyeccion, datos) {
        try {
            const endpoint = '/api/secuencia/guardar-base';
            return await this.post(endpoint, {
                proyeccion: proyeccion,
                ...datos
            });
        } catch (error) {
            return this.handleError(error);
        }
    }

 /**
     * Guarda o actualiza un registro de base (CORREGIDO)
     */
    async guardarBase(datos) {
        try {
            const endpoint = '/api/secuencia/guardar-base';
            return await this.post(endpoint, datos);
        } catch (error) {
            return this.handleError(error);
        }
    }

    /**
     * Elimina un registro de base
     */
    async eliminarBase(datos) {
        try {
            const endpoint = '/api/secuencia/eliminar-base';
            return await this.post(endpoint, datos);
        } catch (error) {
            return this.handleError(error);
        }
    }

    /**
     * Mueve un registro hacia arriba o abajo
     */
    async moverBase(datos) {
        try {
            const endpoint = '/api/secuencia/mover-base';
            return await this.post(endpoint, datos);
        } catch (error) {
            return this.handleError(error);
        }
    }

    /**
     * Mueve una secuencia a una posición específica (para drag & drop)
     */
    async moverSecuenciaA(proyeccion, secuenciaOrigen, nuevaPosicion) {
        try {
            const endpoint = '/api/secuencia/mover-secuencia-a';
            return await this.post(endpoint, {
                proyeccion: proyeccion,
                secuencia_origen: secuenciaOrigen,
                nueva_posicion: nuevaPosicion
            });
        } catch (error) {
            return this.handleError(error);
        }
    }

    /**
     * Mueve una entrada en ccosproy intercambiando posiciones (para drag & drop en el modal)
     */
    async moverSecuenciaProyeccion(proyeccion, secuenciaOrigen, secuenciaDestino, posicion = 'despues') {
        try {
            const endpoint = '/api/secuencia/mover-proyeccion';
            return await this.post(endpoint, {
                proyeccion: proyeccion,
                secuencia_origen: secuenciaOrigen,
                secuencia_destino: secuenciaDestino,
                posicion: posicion // 'antes' o 'despues'
            });
        } catch (error) {
            return this.handleError(error);
        }
    }

    /**
     * Crea una nueva proyección base
     */
    async nuevaProyeccion(datos) {
        try {
            const endpoint = '/api/secuencia/nueva-proyeccion';
            return await this.post(endpoint, datos);
        } catch (error) {
            return this.handleError(error);
        }
    }

    /**
     * Edita el nombre de una proyección
     */
    async editarProyeccion(datos) {
        try {
            const endpoint = '/api/secuencia/editar-proyeccion';
            return await this.post(endpoint, datos);
        } catch (error) {
            return this.handleError(error);
        }
    }

    /**
     * Elimina una proyección completa
     */
    async eliminarProyeccion(datos) {
        try {
            const endpoint = '/api/secuencia/eliminar-proyeccion';
            return await this.post(endpoint, datos);
        } catch (error) {
            return this.handleError(error);
        }
    }

    /**
     * Obtiene lista de galpones/granjas
     */
    async obtenerGalpones() {
        try {
            const endpoint = '/api/secuencia/galpones';
            return await this.get(endpoint);
        } catch (error) {
            return this.handleError(error);
        }
    }

    /**
     * Obtiene lista de granjas (desde módulo de galpones)
     */
    async obtenerGranjas() {
        try {
            const endpoint = '/galpones/granjas';
            return await this.get(endpoint);
        } catch (error) {
            return this.handleError(error);
        }
    }

    /**
     * Obtiene galpones de una granja específica
     */
    async obtenerGalponesPorGranja(codigoGranja) {
        try {
            const endpoint = `/galpones/galpones-por-granja/${codigoGranja}`;
            return await this.get(endpoint);
        } catch (error) {
            return this.handleError(error);
        }
    }

    /**
     * Obtiene datos de un galpón específico
     */
    async obtenerDatosGalpon(idGalpon) {
        try {
            const endpoint = `/galpones/${idGalpon}`;
            return await this.get(endpoint);
        } catch (error) {
            return this.handleError(error);
        }
    }

    /**
     * Obtiene el último registro de una combinación granja-galpón
     */
    async obtenerUltimoRegistroGranjaGalpon(codigo, galpon) {
        try {
            const endpoint = `/api/secuencia/ultimo-registro-granja-galpon?codigo=${encodeURIComponent(codigo)}&galpon=${encodeURIComponent(galpon)}`;
            return await this.get(endpoint);
        } catch (error) {
            return this.handleError(error);
        }
    }
    
        /**
     * Obtiene calendario (fechaproy)
     */
    async obtenerCalendario(proyeccion, page = null, limit = null) {
        try {
            let endpoint = `/api/secuencia/calendario?proyeccion=${encodeURIComponent(proyeccion)}`;
            
            // Si se especifican page y limit, agregar parámetros de paginación
            if (page !== null && limit !== null) {
                endpoint += `&page=${page}&limit=${limit}`;
            }
            
            return await this.get(endpoint);
        } catch (error) {
            return this.handleError(error);
        }
    }

}

