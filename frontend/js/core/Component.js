/**
 * Clase base para componentes reutilizables
 * Proporciona estructura común para todos los componentes de la aplicación
 */
class Component {
    constructor(selector) {
        this.container = typeof selector === 'string' 
            ? document.querySelector(selector) 
            : selector;
        
        if (!this.container) {
            console.warn(`Container not found for selector: ${selector}`);
        }
        
        this.state = {};
        this.listeners = [];
    }

    /**
     * Renderiza el componente
     * Debe ser implementado por las clases hijas
     */
    render() {
        throw new Error('El método render() debe ser implementado');
    }

    /**
     * Actualiza el estado del componente y re-renderiza
     */
    setState(newState) {
        this.state = { ...this.state, ...newState };
        this.render();
    }

    /**
     * Monta el componente en el DOM
     */
    mount() {
        if (this.container) {
            this.render();
            this.attachEvents();
        }
    }

    /**
     * Desmonta el componente y limpia event listeners
     */
    unmount() {
        this.removeEvents();
        if (this.container) {
            this.container.innerHTML = '';
        }
    }

    /**
     * Adjunta event listeners
     * Debe ser implementado por las clases hijas si necesitan eventos
     */
    attachEvents() {
        // Override in child classes
    }

    /**
     * Remueve event listeners
     */
    removeEvents() {
        this.listeners.forEach(({ element, event, handler }) => {
            element.removeEventListener(event, handler);
        });
        this.listeners = [];
    }

    /**
     * Agrega un event listener y lo registra para limpieza posterior
     */
    addEventListener(element, event, handler) {
        element.addEventListener(event, handler);
        this.listeners.push({ element, event, handler });
    }

    /**
     * Muestra el componente
     */
    show() {
        if (this.container) {
            this.container.classList.remove('hidden');
        }
    }

    /**
     * Oculta el componente
     */
    hide() {
        if (this.container) {
            this.container.classList.add('hidden');
        }
    }

    /**
     * Alterna la visibilidad del componente
     */
    toggle() {
        if (this.container) {
            this.container.classList.toggle('hidden');
        }
    }
}
