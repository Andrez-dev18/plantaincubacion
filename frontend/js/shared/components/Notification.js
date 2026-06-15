/**
 * Sistema de notificaciones
 * Muestra mensajes toast al usuario
 */
class Notification {
    static instances = [];
    static container = null;

    /**
     * Inicializa el contenedor de notificaciones
     */
    static init() {
        if (!Notification.container) {
            const container = document.createElement('div');
            container.id = 'notifications-container';
            container.className = 'fixed top-4 right-4 z-50 space-y-2';
            document.body.appendChild(container);
            Notification.container = container;
        }
    }

    /**
     * Muestra una notificación
     */
    static show(message, type = 'info', duration = 3000) {
        Notification.init();

        const notification = new NotificationInstance(message, type, duration);
        Notification.instances.push(notification);
        
        return notification;
    }

    /**
     * Muestra notificación de éxito
     */
    static success(message, duration) {
        return Notification.show(message, 'success', duration);
    }

    /**
     * Muestra notificación de error
     */
    static error(message, duration) {
        return Notification.show(message, 'error', duration);
    }

    /**
     * Muestra notificación de advertencia
     */
    static warning(message, duration) {
        return Notification.show(message, 'warning', duration);
    }

    /**
     * Muestra notificación de información
     */
    static info(message, duration) {
        return Notification.show(message, 'info', duration);
    }

    /**
     * Limpia todas las notificaciones
     */
    static clearAll() {
        Notification.instances.forEach(instance => instance.remove());
        Notification.instances = [];
    }
}

/**
 * Instancia individual de notificación
 */
class NotificationInstance {
    constructor(message, type, duration) {
        this.message = message;
        this.type = type;
        this.duration = duration;
        this.element = null;
        this.timeout = null;

        this.create();
        this.show();
    }

    /**
     * Obtiene el icono según el tipo
     */
    getIcon() {
        const icons = {
            success: 'fa-check-circle',
            error: 'fa-exclamation-circle',
            warning: 'fa-exclamation-triangle',
            info: 'fa-info-circle'
        };
        return icons[this.type] || icons.info;
    }

    /**
     * Obtiene los colores según el tipo
     */
    getColors() {
        const colors = {
            success: 'bg-green-50 border-green-200 text-green-800',
            error: 'bg-red-50 border-red-200 text-red-800',
            warning: 'bg-yellow-50 border-yellow-200 text-yellow-800',
            info: 'bg-blue-50 border-blue-200 text-blue-800'
        };
        return colors[this.type] || colors.info;
    }

    /**
     * Crea el elemento de notificación
     */
    create() {
        const div = document.createElement('div');
        div.className = `notification flex items-center p-4 rounded-lg border shadow-lg ${this.getColors()} transition-all duration-300 transform translate-x-full`;
        
        div.innerHTML = `
            <i class="fas ${this.getIcon()} text-xl mr-3"></i>
            <span class="flex-1">${this.message}</span>
            <button class="ml-3 text-gray-400 hover:text-gray-600">
                <i class="fas fa-times"></i>
            </button>
        `;

        const closeButton = div.querySelector('button');
        closeButton.addEventListener('click', () => this.remove());

        this.element = div;
        Notification.container.appendChild(div);
    }

    /**
     * Muestra la notificación con animación
     */
    show() {
        setTimeout(() => {
            this.element.classList.remove('translate-x-full');
        }, 10);

        if (this.duration > 0) {
            this.timeout = setTimeout(() => {
                this.remove();
            }, this.duration);
        }
    }

    /**
     * Remueve la notificación
     */
    remove() {
        if (this.timeout) {
            clearTimeout(this.timeout);
        }

        this.element.classList.add('translate-x-full', 'opacity-0');
        
        setTimeout(() => {
            this.element.remove();
            const index = Notification.instances.indexOf(this);
            if (index > -1) {
                Notification.instances.splice(index, 1);
            }
        }, 300);
    }
}

// Mantener compatibilidad con código antiguo
window.Notification = Notification;
