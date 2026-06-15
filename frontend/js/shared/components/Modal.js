/**
 * Componente Modal reutilizable
 * Crea modales personalizados con contenido dinámico
 */
class Modal extends Component {
    constructor(options = {}) {
        super(null);
        
        this.options = {
            title: options.title || '',
            content: options.content || '',
            size: options.size || 'medium', // small, medium, large
            showCloseButton: options.showCloseButton !== false,
            onClose: options.onClose || null,
            ...options
        };

        this.createModal();
    }

    /**
     * Crea la estructura del modal
     */
    createModal() {
        const modal = document.createElement('div');
        modal.className = 'modal-overlay fixed inset-0 bg-black bg-opacity-50 hidden z-50 flex items-center justify-center';
        modal.innerHTML = this.getTemplate();
        
        document.body.appendChild(modal);
        this.container = modal;
    }

    /**
     * Template del modal
     */
    getTemplate() {
        const sizeClasses = {
            small: 'max-w-md',
            medium: 'max-w-2xl',
            large: 'max-w-4xl',
            xlarge: 'max-w-6xl'
        };

        return `
            <div class="modal-content bg-white rounded-2xl shadow-2xl ${sizeClasses[this.options.size]} w-full mx-4 my-8">
                <div class="modal-header bg-gradient-to-r from-purple-500 to-indigo-600 p-6 rounded-t-2xl flex justify-between items-center">
                    <h3 class="text-2xl font-bold text-white">${this.options.title}</h3>
                    ${this.options.showCloseButton ? `
                        <button class="modal-close text-white hover:text-gray-200 transition">
                            <i class="fas fa-times text-xl"></i>
                        </button>
                    ` : ''}
                </div>
                <div class="modal-body p-6" style="max-height: calc(100vh - 200px); overflow-y: auto;">
                    ${this.options.content}
                </div>
                ${this.options.footer ? `
                    <div class="modal-footer p-6 border-t">
                        ${this.options.footer}
                    </div>
                ` : ''}
            </div>
        `;
    }

    /**
     * Renderiza el modal
     */
    render() {
        if (!this.container) return;
        
        const body = this.container.querySelector('.modal-body');
        if (body && this.options.content) {
            body.innerHTML = this.options.content;
        }
    }

    /**
     * Adjunta eventos
     */
    attachEvents() {
        const closeButton = this.container.querySelector('.modal-close');
        const overlay = this.container;

        if (closeButton) {
            this.addEventListener(closeButton, 'click', () => this.close());
        }

        // Cerrar al hacer click en el overlay
        this.addEventListener(overlay, 'click', (e) => {
            if (e.target === overlay) {
                this.close();
            }
        });

        // Cerrar con ESC
        const escHandler = (e) => {
            if (e.key === 'Escape') {
                this.close();
            }
        };
        document.addEventListener('keydown', escHandler);
        this.listeners.push({ element: document, event: 'keydown', handler: escHandler });
    }

    /**
     * Abre el modal
     */
    open() {
        if (!this.container) return;
        
        this.container.classList.remove('hidden');
        document.body.style.overflow = 'hidden';
        this.attachEvents();
    }

    /**
     * Cierra el modal
     */
    close() {
        if (!this.container) return;
        
        this.container.classList.add('hidden');
        document.body.style.overflow = '';
        this.removeEvents();
        
        if (this.options.onClose) {
            this.options.onClose();
        }
    }

    /**
     * Actualiza el contenido del modal
     */
    updateContent(content) {
        this.options.content = content;
        this.render();
    }

    /**
     * Actualiza el título del modal
     */
    updateTitle(title) {
        const header = this.container?.querySelector('.modal-header h3');
        if (header) {
            header.innerHTML = title;
        }
    }

    /**
     * Destruye el modal completamente
     */
    destroy() {
        if (!this.container) return;
        
        // Ocultar modal
        this.container.classList.add('hidden');
        document.body.style.overflow = '';
        
        // Remover eventos
        this.removeEvents();
        
        // Remover del DOM
        this.container.remove();
        this.container = null;
    }
}
