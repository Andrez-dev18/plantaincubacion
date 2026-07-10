/**
 * GestionUsuariosService — Adaptado al Módulo de Planta Incubación (Tablas _pic)
 * Utiliza la clase base global 'Service' y el objeto helper 'Http'.
 */
class GestionUsuariosService extends Service {
    constructor() {
        super();
        // Ruta unificada y limpia hacia tus nuevos endpoints en PHP
        this.base = `${AppConfig.API.BASE_URL}/api/usuario`;
    }

    /**
     * Obtiene el listado Server-Side mapeado para DataTables
     * Nota: Como DataTables hace una petición POST nativa con FormData, 
     * le pasamos los parámetros crudos y dejamos que Http.post los envíe.
     */
    getGestionUsuarios(dtParams = {}) {
        return Http.post(`${this.base}/listar`, dtParams);
    }

    /**
     * Obtiene los datos base de un usuario y los IDs de sus roles en Planta Incubación
     * POST /api/usuario/obtener
     */
    obtenerUsuario(codigo) {
        return Http.post(`${this.base}/obtener`, { codigo: codigo });
    }

    /**
     * Inserta o edita un usuario y guarda sus roles asociados de forma masiva
     * POST /api/usuario/guardar
     * @param {Object|FormData} data - Contiene codigo, nombre, password y roles[]
     */
    guardarUsuario(data) {
        return Http.post(`${this.base}/guardar`, data);
    }

    /**
     * Activa o desactiva un usuario (intercambia el estado entre 1 y 0)
     * POST /api/usuario/toggle
     */
    cambiarEstado(codigo) {
        return Http.post(`${this.base}/toggle`, { codigo: codigo });
    }

    /**
     * Resetea la contraseña de un usuario desde el panel del administrador
     * POST /api/usuario/reset-password
     */
    resetPassword(codigo, password) {
        return Http.post(`${this.base}/reset-password`, { codigo: codigo, password: password });
    }

    /**
     * Obtiene el catálogo de todos los roles activos vinculados al programa 1 (PIC)
     * GET /api/usuario/roles
     * Sirve para renderizar de manera dinámica los checkboxes del formulario
     */
    obtenerRolesActivos() {
        return Http.get(`${this.base}/roles`);
    }
}

// Exponer el servicio al entorno global window para que el controlador lo instancie
window.GestionUsuariosService = GestionUsuariosService;