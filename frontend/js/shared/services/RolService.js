/**
 * Servicio para gestión de roles
 * Maneja operaciones CRUD de roles del sistema
 */
class RolService extends Service {
    constructor() {
        super(AppConfig.API.BASE_URL);
    }

    /**
     * Obtiene todos los roles
     */
    async listar() {
        try {
            return await this.get('/admin/roles');
        } catch (error) {
            return this.handleError(error);
        }
    }

    /**
     * Obtiene un rol por ID
     */
    async obtenerPorId(idRol) {
        try {
            return await this.get(`/admin/roles/${idRol}`);
        } catch (error) {
            return this.handleError(error);
        }
    }

    /**
     * Crea un nuevo rol
     */
    async crear(datos) {
        try {
            return await this.post('/admin/roles', datos);
        } catch (error) {
            return this.handleError(error);
        }
    }

    /**
     * Actualiza un rol existente
     */
    async actualizar(idRol, datos) {
        try {
            return await this.put(`/admin/roles/${idRol}`, datos);
        } catch (error) {
            return this.handleError(error);
        }
    }

    /**
     * Elimina un rol
     */
    async eliminar(idRol) {
        try {
            return await this.delete(`/admin/roles/${idRol}`);
        } catch (error) {
            return this.handleError(error);
        }
    }

    /**
     * Obtiene la lista de programas disponibles
     */
    async obtenerProgramas() {
        try {
            return await this.get('/admin/programas');
        } catch (error) {
            return this.handleError(error);
        }
    }

    /**
     * Obtiene los módulos de un programa
     */
    async obtenerModulosPorPrograma(idPrograma) {
        try {
            return await this.get(`/admin/programas/${idPrograma}/modulos`);
        } catch (error) {
            return this.handleError(error);
        }
    }

    /**
     * Obtiene los módulos asignados a un rol
     */
    async obtenerModulosAsignados(idRol) {
        try {
            return await this.get(`/admin/roles/${idRol}/modulos`);
        } catch (error) {
            return this.handleError(error);
        }
    }

    /**
     * Obtiene los módulos asignados a un rol agrupados por programa
     */
    async obtenerModulosAgrupadosPorPrograma(idRol) {
        try {
            return await this.get(`/admin/roles/${idRol}/modulos-agrupados`);
        } catch (error) {
            return this.handleError(error);
        }
    }
}
