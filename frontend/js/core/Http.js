/**
 * Wrapper para fetch con manejo de errores mejorado
 * Funcionalidad adicional para requests HTTP
 */
class Http {
    static defaultConfig = {
        credentials: 'include',
        headers: {
            'Content-Type': 'application/json'
        }
    };

    /**
     * Realiza una petición HTTP configurada
     */
    static async request(url, options = {}) {
        const config = {
            ...Http.defaultConfig,
            ...options,
            headers: {
                ...Http.defaultConfig.headers,
                ...options.headers
            }
        };

        try {
            const response = await fetch(url, config);
            
            // Intenta parsear la respuesta como JSON
            let data;
            const contentType = response.headers.get('content-type');
            
            if (contentType && contentType.includes('application/json')) {
                data = await response.json();
            } else {
                data = await response.text();
            }

            if (!response.ok) {
                const backendMessage =
                    (data && (data.error || data.message || data.mensaje)) ||
                    `HTTP ${response.status}: ${response.statusText}`;

                throw new HttpError(
                    backendMessage,
                    response.status,
                    data
                );
            }

            return data;
        } catch (error) {
            if (error instanceof HttpError) {
                throw error;
            }

            // Error de red o de otro tipo
            throw new HttpError(
                error.message || 'Error de conexión con el servidor',
                0,
                null
            );
        }
    }

    /**
     * GET request
     */
    static async get(url, params = {}) {
        const queryString = new URLSearchParams(params).toString();
        const fullUrl = queryString ? `${url}?${queryString}` : url;
        
        return Http.request(fullUrl, { method: 'GET' });
    }

    /**
     * POST request
     */
    static async post(url, data = {}) {
        return Http.request(url, {
            method: 'POST',
            body: JSON.stringify(data)
        });
    }

    /**
     * PUT request
     */
    static async put(url, data = {}) {
        // Usar POST con _method override para compatibilidad con servidores
        return Http.request(url, {
            method: 'POST',
            headers: {
                'X-HTTP-Method-Override': 'PUT'
            },
            body: JSON.stringify({ ...data, _method: 'PUT' })
        });
    }

    /**
     * DELETE request
     */
    static async delete(url, data = {}) {
        // Usar POST con _method override para compatibilidad con servidores
        return Http.request(url, {
            method: 'POST',
            headers: {
                'X-HTTP-Method-Override': 'DELETE'
            },
            body: JSON.stringify({ ...data, _method: 'DELETE' })
        });
    }

    /**
     * PATCH request
     */
    static async patch(url, data = {}) {
        return Http.request(url, {
            method: 'PATCH',
            body: JSON.stringify(data)
        });
    }

    /**
     * Configura headers por defecto
     */
    static setDefaultHeader(key, value) {
        Http.defaultConfig.headers[key] = value;
    }

    /**
     * Remueve un header por defecto
     */
    static removeDefaultHeader(key) {
        delete Http.defaultConfig.headers[key];
    }
}

/**
 * Clase de error personalizada para HTTP
 */
class HttpError extends Error {
    constructor(message, status, data) {
        super(message);
        this.name = 'HttpError';
        this.status = status;
        this.data = data;
    }

    isClientError() {
        return this.status >= 400 && this.status < 500;
    }

    isServerError() {
        return this.status >= 500;
    }

    isNetworkError() {
        return this.status === 0;
    }
}
