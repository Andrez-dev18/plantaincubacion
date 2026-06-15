/**
 * Servicio para gestión administrativa de usuarios
 * Maneja operaciones CRUD de usuarios y asignación de roles
 */
class UsuarioAdminService extends Service {
    constructor() {
        super(AppConfig.API.BASE_URL);
    }

    /**
     * Obtiene todos los usuarios
     */
    async listar() {
        try {
            return await this.get('/admin/usuarios');
        } catch (error) {
            return this.handleError(error);
        }
    }

    /**
     * Obtiene un usuario por ID
     */
    async obtenerPorId(idUsuario) {
        try {
            return await this.get(`/admin/usuarios/${idUsuario}`);
        } catch (error) {
            return this.handleError(error);
        }
    }

    /**
     * Crea un nuevo usuario
     */
    async crear(datos) {
        try {
            return await this.post('/admin/usuarios', datos);
        } catch (error) {
            return this.handleError(error);
        }
    }

    /**
     * Actualiza un usuario existente
     */
    async actualizar(idUsuario, datos) {
        try {
            return await this.put(`/admin/usuarios/${idUsuario}`, datos);
        } catch (error) {
            return this.handleError(error);
        }
    }

    /**
     * Cambia la contraseña de un usuario
     */
    async cambiarPassword(idUsuario, password) {
        try {
            return await this.post('/admin/usuarios/cambiar-password', {
                id_usuario: idUsuario,
                password: password
            });
        } catch (error) {
            return this.handleError(error);
        }
    }

    /**
     * Elimina un usuario
     */
    async eliminar(idUsuario) {
        try {
            return await this.delete(`/admin/usuarios/${idUsuario}`);
        } catch (error) {
            return this.handleError(error);
        }
    }

    /**
     * Asigna un rol a un usuario
     */
    async asignarRol(idUsuario, idRol) {
        try {
            return await this.put(`/admin/usuarios/${idUsuario}`, {
                id_rol: idRol
            });
        } catch (error) {
            return this.handleError(error);
        }
    }
}
