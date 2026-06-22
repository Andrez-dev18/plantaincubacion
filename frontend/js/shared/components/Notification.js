/**
 * Sistema de notificaciones impulsado por SweetAlert2 (Toasts)
 * Mantiene la compatibilidad con los llamados antiguos pero usa alertas modernas.
 */
class Notification {
    
    /**
     * Motor interno que configura y dispara el SweetAlert en modo Toast
     */
    static _fireToast(message, type, duration = 3000) {
        // Detectamos si el sistema está en modo oscuro para pintar el toast
        const isDarkMode = document.body.classList.contains('dark-mode');

        const Toast = Swal.mixin({
            toast: true,
            position: 'top-end',
            showConfirmButton: false,
            timer: duration,
            timerProgressBar: true,
            // Pausa el tiempo si el usuario pasa el mouse por encima
            didOpen: (toast) => {
                toast.addEventListener('mouseenter', Swal.stopTimer)
                toast.addEventListener('mouseleave', Swal.resumeTimer)
            },
            // Estilos dinámicos para modo claro/oscuro
            background: isDarkMode ? '#1e293b' : '#ffffff',
            color: isDarkMode ? '#f8fafc' : '#1e293b',
            iconColor: isDarkMode ? undefined : undefined, // Usa los colores nativos de Swal
            customClass: {
                popup: isDarkMode ? 'border border-slate-700' : 'border border-slate-100 shadow-xl'
            }
        });

        Toast.fire({
            icon: type,
            title: message
        });
    }

    /**
     * Muestra una notificación general
     */
    static show(message, type = 'info', duration = 3000) {
        this._fireToast(message, type, duration);
    }

    /**
     * Muestra notificación de éxito (Check verde)
     */
    static success(message, duration) {
        this._fireToast(message, 'success', duration);
    }

    /**
     * Muestra notificación de error (X roja)
     */
    static error(message, duration) {
        this._fireToast(message, 'error', duration);
    }

    /**
     * Muestra notificación de advertencia (Triángulo amarillo)
     */
    static warning(message, duration) {
        this._fireToast(message, 'warning', duration);
    }

    /**
     * Muestra notificación de información (i azul)
     */
    static info(message, duration) {
        this._fireToast(message, 'info', duration);
    }

    /**
     * Limpia todas las notificaciones en pantalla
     */
    static clearAll() {
        Swal.close();
    }
}

// Mantener compatibilidad absoluta con el código antiguo
window.Notification = Notification;