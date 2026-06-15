/**
 * Clase base para servicios
 * Proporciona métodos comunes para comunicación con la API
 */
class Service {
    constructor(baseUrl = '') {
        this.baseUrl = baseUrl;
        // Flag para evitar múltiples redirecciones
        this.redirecting = false;
    }

    /**
     * Realiza una petición HTTP
     */
    async request(endpoint, options = {}) {
        const url = `${this.baseUrl}${endpoint}`;
        
        const defaultOptions = {
            credentials: 'include',
            headers: {
                'Content-Type': 'application/json',
                ...options.headers
            }
        };

        const config = { ...defaultOptions, ...options };

        try {
            const response = await fetch(url, config);
            
            if (!response.ok) {
                // Manejar error 401 (Unauthorized) - Sesión expirada en ruta protegida
                if (response.status === 401) {
                    // Marcar como redirigiendo inmediatamente para evitar logs de otras llamadas
                    this.redirecting = true;
                    this.handleSessionExpired();
                    // Redirigir directamente sin lanzar error
                    return { success: false, mensaje: 'Sesión expirada', redirecting: true };
                }
                
                // Para 400 y otros errores, devolver el JSON con el mensaje real
                // (ej. credenciales inválidas en el login)
                const errorBody = await response.json().catch(() => ({}));
                throw new Error(errorBody.message || errorBody.mensaje || `HTTP Error: ${response.status}`);
            }

            return await response.json();
        } catch (error) {
            // Solo loguear si no es un error de sesión expirada
            if (!this.redirecting) {
                console.error('Service request error:', error);
            }
            
            // Si el error es por sesión expirada, manejarlo
            if (error.message && (error.message.includes('sesión ha expirado') || error.message.includes('Unauthorized'))) {
                this.handleSessionExpired();
                return { success: false, mensaje: 'Sesión expirada', redirecting: true };
            }
            
            throw error;
        }
    }

    /**
     * Maneja la expiración de sesión
     */
    handleSessionExpired() {
        // Evitar múltiples redirecciones
        if (this.redirecting) {
            return;
        }
        
        this.redirecting = true;
        
        // Limpiar todos los datos de sesión silenciosamente
        sessionStorage.clear();
        localStorage.removeItem('lastActivity');
        
        // Notificar al window principal (index.html) que la sesión expiró
        try {
            if (window.parent && window.parent !== window) {
                window.parent.postMessage({ type: 'SESSION_EXPIRED' }, '*');
            }
        } catch (e) {
            // Ignorar errores de cross-origin
        }
        
        // Redirigir al login inmediatamente sin mostrar notificaciones
        // Usar window.top para salir completamente del iframe si existe
        setTimeout(() => {
            try {
                // Intentar redirigir desde la ventana superior (salir del iframe)
                if (window.top && window.top !== window) {
                    window.top.location.replace('/plantaincubacion/login.html');
                } else {
                    window.location.replace('/plantaincubacion/login.html');
                }
            } catch (e) {
                // Si hay error de cross-origin, intentar con window.parent
                try {
                    window.parent.location.replace('/plantaincubacion/login.html');
                } catch (e2) {
                    // Último recurso: redirigir solo esta ventana
                    window.location.replace('/plantaincubacion/login.html');
                }
            }
        }, 100);
    }

    /**
     * GET request
     */
    async get(endpoint, params = {}) {
        const queryString = new URLSearchParams(params).toString();
        let url = endpoint;
        
        if (queryString) {
            // Si el endpoint ya tiene query string, usar &, sino usar ?
            const separator = endpoint.includes('?') ? '&' : '?';
            url = `${endpoint}${separator}${queryString}`;
        }
        
        return this.request(url, {
            method: 'GET'
        });
    }

    /**
     * POST request
     */
    async post(endpoint, data = {}) {
        return this.request(endpoint, {
            method: 'POST',
            body: JSON.stringify(data)
        });
    }

    /**
     * PUT request
     */
    async put(endpoint, data = {}) {
        return this.request(endpoint, {
            method: 'PUT',
            body: JSON.stringify(data)
        });
    }

    /**
     * DELETE request
     */
    async delete(endpoint) {
        return this.request(endpoint, {
            method: 'DELETE'
        });
    }

    /**
     * Maneja errores de manera uniforme
     */
    handleError(error) {
        console.error('Service Error:', error);
        
        if (error.message.includes('Failed to fetch')) {
            return {
                success: false,
                mensaje: 'Error de conexión con el servidor'
            };
        }

        return {
            success: false,
            mensaje: error.message || 'Error desconocido'
        };
    }
}
