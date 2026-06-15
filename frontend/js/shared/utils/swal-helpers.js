const Swal = window.Swal;

const getThemeSwalOptions = () => {
    const isDark = document.body?.classList.contains('dark-mode');

    if (!isDark) {
        return {
            background: '#ffffff',
            color: '#1f2937',
            confirmButtonColor: '#3085d6',
            cancelButtonColor: '#d33'
        };
    }

    return {
        background: '#111827',
        color: '#e5e7eb',
        confirmButtonColor: '#2563eb',
        cancelButtonColor: '#ef4444'
    };
};

const firePopup = (options = {}) => {
    if (!Swal?.fire) return Promise.resolve(null);

    const themed = getThemeSwalOptions();
    return Swal.fire({
        ...themed,
        allowOutsideClick: false,
        allowEscapeKey: true,
        heightAuto: false,
        ...options
    });
};

const showSuccess = (message, title = 'Operacion completada') => {
    return firePopup({
        icon: 'success',
        title,
        text: message,
        confirmButtonText: 'Aceptar'
    });
};

const showError = (message, title = 'Error') => {
    return firePopup({
        icon: 'error',
        title,
        text: message,
        confirmButtonText: 'Aceptar'
    });
};

const showWarning = (message, title = 'Advertencia') => {
    return firePopup({
        icon: 'warning',
        title,
        text: message,
        confirmButtonText: 'Aceptar'
    });
};

const showInfo = (message, title = 'Informacion', icon = 'info') => {
    return firePopup({
        icon,
        title,
        text: message,
        confirmButtonText: 'Aceptar'
    });
};

const showConfirm = async (title, text) => {
    try {
        const result = await firePopup({
            title: title,
            text: text,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Sí, confirmar',
            cancelButtonText: 'Cancelar',
            allowOutsideClick: false,
            allowEscapeKey: false,
            reverseButtons: false,
            focusCancel: false,
            backdrop: true
        });

        // Solo retorna true si explícitamente presionó "Sí, confirmar"
        return result.isConfirmed === true;
    } catch (error) {
        console.error('Error en SweetAlert confirmacion:', error);
        return false;
    }
};

window.SwalHelpers = { showSuccess, showError, showWarning, showInfo, showConfirm };
