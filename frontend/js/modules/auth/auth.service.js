/**
 * Servicio de autenticación
 * Extiende la clase base Service para comunicación con la API
 */
class AuthService extends Service {
    constructor() {
        super(AppConfig.API.BASE_URL);
    }

    /**
     * Inicia sesión con credenciales de usuario
     */
    async login(usuario, password, ubicacion_gps) {
        try {
            const endpoint = AppConfig.API.ENDPOINTS.AUTH.LOGIN;
            console.log('🌐 [AUTH SERVICE] Endpoint:', endpoint);
            console.log('📤 [AUTH SERVICE] Enviando datos:', { username: usuario, password: '***' });

            const response = await this.post(endpoint, {
                username: usuario,  // La API espera 'username'
                password
            });

            console.log('📥 [AUTH SERVICE] Respuesta de API:', response);
            console.log('🔍 [AUTH SERVICE] response.success:', response?.success);
            console.log('📋 [AUTH SERVICE] response.data:', response?.data);
            console.log('💬 [AUTH SERVICE] response.message:', response?.message);

            if (response.success) {
                console.log('✅ [AUTH SERVICE] Login exitoso');
                // Guardar datos del usuario en sessionStorage
                sessionStorage.setItem('usuario', JSON.stringify(response.data));
                sessionStorage.setItem('isAuthenticated', 'true');
                
                // Guardar módulos (nuevo sistema) - compatible con IAM
                if (response.data.modulos) {
                    sessionStorage.setItem('modulos', JSON.stringify(response.data.modulos));
                }
                
                // Guardar roles (nuevo sistema multi-rol) - compatible con IAM
                if (response.data.roles && response.data.roles.length > 0) {
                    sessionStorage.setItem('roles', JSON.stringify(response.data.roles));
                    // Guardar rol principal (primer rol) para compatibilidad
                    sessionStorage.setItem('user_rol', response.data.roles[0].id_rol);
                    // Compatible con IAM: nom_rol o nombre
                    const rolNombre = response.data.roles[0].nom_rol || response.data.roles[0].nombre;
                    sessionStorage.setItem('user_rol_nombre', rolNombre);
                }
                
                // Actualizar store global
                AppStore.setState({
                    user: response.data,
                    isAuthenticated: true
                });
            }

            return response;
        } catch (error) {
            console.error('[AUTH SERVICE] Error en login:', error);
            console.error('[AUTH SERVICE] Error tipo:', error.type);
            console.error('[AUTH SERVICE] Error message:', error.message);
            return this.handleError(error);
        }
    }

    /**
     * Valida la sesión actual
     */
    async validarSesion() {
        try {
            const endpoint = AppConfig.API.ENDPOINTS.AUTH.VALIDAR;
            const response = await this.get(endpoint);

            if (response.success && response.data) {
                // Actualizar sessionStorage con datos frescos
                sessionStorage.setItem('usuario', JSON.stringify(response.data));
                sessionStorage.setItem('isAuthenticated', 'true');
                
                // Guardar módulos (nuevo sistema) - compatible con IAM
                if (response.data.modulos) {
                    sessionStorage.setItem('modulos', JSON.stringify(response.data.modulos));
                }
                
                // Guardar roles (nuevo sistema multi-rol) - compatible con IAM
                if (response.data.roles && response.data.roles.length > 0) {
                    sessionStorage.setItem('roles', JSON.stringify(response.data.roles));
                    sessionStorage.setItem('user_rol', response.data.roles[0].id_rol);
                    // Compatible con IAM: nom_rol o nombre
                    const rolNombre = response.data.roles[0].nom_rol || response.data.roles[0].nombre;
                    sessionStorage.setItem('user_rol_nombre', rolNombre);
                }
                
                // Actualizar store global
                AppStore.setState({
                    user: response.data,
                    isAuthenticated: true
                });
            } else {
                // Limpiar sesión si no es válida
                sessionStorage.removeItem('usuario');
                sessionStorage.removeItem('isAuthenticated');
                sessionStorage.removeItem('modulos');
                sessionStorage.removeItem('roles');
                sessionStorage.removeItem('user_rol');
                sessionStorage.removeItem('user_rol_nombre');
                
                AppStore.setState({
                    user: null,
                    isAuthenticated: false
                });
            }

            return response;
        } catch (error) {
            // Limpiar sesión en caso de error
            sessionStorage.removeItem('usuario');
            sessionStorage.removeItem('isAuthenticated');
            sessionStorage.removeItem('modulos');
            sessionStorage.removeItem('roles');
            sessionStorage.removeItem('user_rol');
            sessionStorage.removeItem('user_rol_nombre');
            
            AppStore.setState({
                user: null,
                isAuthenticated: false
            });
            return this.handleError(error);
        }
    }

    /**
     * Cierra la sesión actual
     */
    async logout() {
        try {
            const endpoint = AppConfig.API.ENDPOINTS.AUTH.LOGOUT;
            const response = await this.post(endpoint, {});

            // Limpiar sessionStorage completamente
            sessionStorage.removeItem('usuario');
            sessionStorage.removeItem('isAuthenticated');
            sessionStorage.removeItem('modulos');
            sessionStorage.removeItem('roles');
            sessionStorage.removeItem('user_rol');
            sessionStorage.removeItem('user_rol_nombre');
            
            // Actualizar store global
            AppStore.setState({
                user: null,
                isAuthenticated: false
            });

            return response;
        } catch (error) {
            return this.handleError(error);
        }
    }

    /**
     * Verifica si hay una sesión activa
     * @returns {boolean}
     */
    isAuthenticated() {
        return sessionStorage.getItem('isAuthenticated') === 'true';
    }

    /**
     * Obtiene los datos del usuario actual
     * @returns {object|null}
     */
    getCurrentUser() {
        try {
            const userData = sessionStorage.getItem('usuario');
            return userData ? JSON.parse(userData) : null;
        } catch (error) {
            console.error('Error al obtener usuario:', error);
            return null;
        }
    }

    /**
     * Obtiene los módulos/permisos del usuario actual
     * @returns {array}
     */
    getPermisos() {
        try {
            // Nuevo sistema: usar módulos
            const modulos = sessionStorage.getItem('modulos');
            return modulos ? JSON.parse(modulos) : [];
        } catch (error) {
            console.error('Error al obtener módulos:', error);
            return [];
        }
    }

    /**
     * Verifica si el usuario tiene permiso para un módulo específico
     * @param {string} codMod - Código del módulo
     * @param {string} tipo - Tipo de permiso (leer, insertar, editar, eliminar, anular)
     * @returns {boolean}
     */
    tienePermiso(codMod, tipo = null) {
        const modulos = this.getPermisos();
        
        if (!modulos || modulos.length === 0) {
            return false;
        }

        const modulo = modulos.find(m => m.cod_mod === codMod);
        
        if (!modulo) {
            return false;
        }

        // Si no se especifica tipo, verificar p_leer (puede VER el módulo)
        if (!tipo) {
            return modulo.p_leer === 1;
        }

        // Verificar permiso específico
        const campoPermiso = `p_${tipo}`;
        return modulo[campoPermiso] === 1;
    }

    /**
     * Verifica si el usuario puede VER/LEER un módulo
     * @param {string} codMod - Código del módulo
     * @returns {boolean}
     */
    puedeLeer(codMod) {
        return this.tienePermiso(codMod, 'leer');
    }

    /**
     * Obtiene todos los módulos que el usuario puede ver
     * @returns {array}
     */
    getModulosVisibles() {
        const modulos = this.getPermisos();
        return modulos.filter(m => m.p_leer === 1);
    }

    /**
     * Obtiene información del rol actual
     * @returns {object}
     */
    getRolInfo() {
        return {
            id: sessionStorage.getItem('user_rol'),
            nombre: sessionStorage.getItem('user_rol_nombre')
        };
    }

    /**
     * Obtiene el menú jerárquico del usuario autenticado
     * @returns {Promise<object>}
     */
    async obtenerMenu() {
        try {
            const response = await this.get('/menu/obtener');
            return response;
        } catch (error) {
            console.error('Error en obtenerMenu:', error);
            return { success: false, message: error.message };
        }
    }
}

window.AuthService = AuthService;
