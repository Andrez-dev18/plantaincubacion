/**
 * Store simple para manejo de estado global
 * Patrón Observer para notificar cambios
 */
class Store {
    constructor(initialState = {}) {
        this.state = initialState;
        this.listeners = new Map();
    }

    /**
     * Obtiene el estado actual
     */
    getState() {
        return { ...this.state };
    }

    /**
     * Obtiene un valor específico del estado
     */
    get(key) {
        return this.state[key];
    }

    /**
     * Actualiza el estado y notifica a los listeners
     */
    setState(updates) {
        const prevState = { ...this.state };
        this.state = { ...this.state, ...updates };

        // Notificar a listeners específicos
        Object.keys(updates).forEach(key => {
            if (this.listeners.has(key)) {
                this.listeners.get(key).forEach(callback => {
                    callback(this.state[key], prevState[key]);
                });
            }
        });

        // Notificar a listeners globales
        if (this.listeners.has('*')) {
            this.listeners.get('*').forEach(callback => {
                callback(this.state, prevState);
            });
        }
    }

    /**
     * Suscribe un listener a un cambio de estado específico
     */
    subscribe(key, callback) {
        if (!this.listeners.has(key)) {
            this.listeners.set(key, []);
        }
        this.listeners.get(key).push(callback);

        // Retorna función para desuscribirse
        return () => {
            const callbacks = this.listeners.get(key);
            const index = callbacks.indexOf(callback);
            if (index > -1) {
                callbacks.splice(index, 1);
            }
        };
    }

    /**
     * Limpia el estado
     */
    clear() {
        this.state = {};
        this.listeners.clear();
    }

    /**
     * Remueve una propiedad del estado
     */
    remove(key) {
        delete this.state[key];
        
        if (this.listeners.has(key)) {
            this.listeners.get(key).forEach(callback => {
                callback(undefined, this.state[key]);
            });
        }
    }
}

// Store global de la aplicación
const AppStore = new Store({
    user: null,
    isAuthenticated: false,
    loading: false
});
