/**
 * Archivo principal de la aplicación
 * Inicializa los componentes y configura el sistema
 */

// Inicializar el store de la aplicación
const appStore = new Store({
    user: null,
    isAuthenticated: false,
    loading: false,
    currentModule: null
});

// Hacer el store accesible globalmente
window.AppStore = appStore;

// Variables para control de sesión
let sessionCheckInterval = null;
let sessionWarningShown = false;
const SESSION_CHECK_INTERVAL = 5 * 60 * 1000; // Verificar cada 5 minutos
const SESSION_TIMEOUT = 30 * 60 * 1000; // Timeout de 30 minutos de inactividad
const SESSION_WARNING_TIME = 25 * 60 * 1000; // Advertir a los 25 minutos (5 minutos antes de expirar)

/**
 * Inicializa la aplicación
 */
async function initApp() {
    try {
        // Validar sesión si estamos en página protegida
        const isLoginPage = window.location.pathname.includes('login.html');
        
        if (!isLoginPage) {
            await checkAuthentication();
            // Iniciar monitoreo de sesión
            startSessionMonitoring();
        }

        // Inicializar sistema de notificaciones
        Notification.init();

        console.log('✓ Aplicación inicializada correctamente');
    } catch (error) {
        console.error('Error al inicializar la aplicación:', error);
    }
}

/**
 * Inicia el monitoreo periódico de sesión
 */
function startSessionMonitoring() {
    // Limpiar intervalo anterior si existe
    if (sessionCheckInterval) {
        clearInterval(sessionCheckInterval);
    }
    
    // Registrar actividad del usuario
    updateLastActivity();
    
    // Escuchar eventos de actividad del usuario
    ['mousedown', 'keydown', 'scroll', 'touchstart'].forEach(event => {
        document.addEventListener(event, updateLastActivity, { passive: true });
    });
    
    // Verificar sesión periódicamente
    sessionCheckInterval = setInterval(async () => {
        try {
            const lastActivity = localStorage.getItem('lastActivity');
            const now = Date.now();
            const inactiveTime = lastActivity ? (now - parseInt(lastActivity)) : 0;
            
            // Advertir si está cerca de expirar (25 minutos de inactividad)
            if (inactiveTime > SESSION_WARNING_TIME && inactiveTime < SESSION_TIMEOUT && !sessionWarningShown) {
                showSessionWarning();
            }
            
            // Si ha pasado mucho tiempo sin actividad, validar sesión
            if (inactiveTime > SESSION_TIMEOUT) {
                console.warn('⚠️ Detectado timeout de inactividad, validando sesión...');
                sessionWarningShown = false;
                await checkAuthentication();
            } else {
                // Validación silenciosa de sesión
                const authService = new AuthService();
                const response = await authService.validarSesion();
                
                if (!response.success) {
                    console.warn('⚠️ Sesión inválida detectada en verificación periódica');
                    sessionWarningShown = false;
                }
            }
        } catch (error) {
            console.error('Error en monitoreo de sesión:', error);
            sessionWarningShown = false;
        }
    }, SESSION_CHECK_INTERVAL);
    
    console.log('✓ Monitoreo de sesión iniciado');
}

/**
 * Actualiza el timestamp de última actividad
 */
function updateLastActivity() {
    localStorage.setItem('lastActivity', Date.now().toString());
    // Resetear flag de advertencia cuando hay actividad
    sessionWarningShown = false;
}

/**
 * Muestra advertencia de sesión por expirar
 */
function showSessionWarning() {
    sessionWarningShown = true;
    
    const timeRemaining = Math.floor((SESSION_TIMEOUT - SESSION_WARNING_TIME) / 60000); // minutos
    
    // Usar SweetAlert si está disponible, sino usar confirm nativo
    if (typeof Swal !== 'undefined') {
        Swal.fire({
            title: '⚠️ Sesión por Expirar',
            html: `Su sesión expirará en aproximadamente <strong>${timeRemaining} minutos</strong> por inactividad.<br><br>¿Desea mantener su sesión activa?`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Sí, mantener sesión',
            cancelButtonText: 'No, cerrar sesión',
            confirmButtonColor: '#3085d6',
            cancelButtonColor: '#d33',
            allowOutsideClick: false
        }).then((result) => {
            if (result.isConfirmed) {
                extendSession();
            } else {
                handleLogout();
            }
        });
    } else {
        // Fallback a confirm nativo
        const extend = confirm(
            `⚠️ ADVERTENCIA DE SESIÓN\n\n` +
            `Su sesión expirará en aproximadamente ${timeRemaining} minutos por inactividad.\n\n` +
            `¿Desea mantener su sesión activa?\n\n` +
            `Presione OK para mantener la sesión\n` +
            `Presione Cancelar para cerrar sesión`
        );
        
        if (extend) {
            extendSession();
        } else {
            handleLogout();
        }
    }
}

/**
 * Extiende la sesión del usuario
 */
async function extendSession() {
    try {
        console.log('🔄 Extendiendo sesión...');
        
        // Actualizar actividad
        updateLastActivity();
        
        // Hacer una petición para refrescar la sesión en el servidor
        const authService = new AuthService();
        const response = await authService.validarSesion();
        
        if (response.success) {
            if (window.Notification) {
                window.Notification.success('Sesión extendida exitosamente');
            }
            console.log('✅ Sesión extendida');
        } else {
            console.error('❌ No se pudo extender la sesión');
            if (window.Notification) {
                window.Notification.error('No se pudo extender la sesión. Por favor, inicie sesión nuevamente.');
            }
        }
    } catch (error) {
        console.error('Error al extender sesión:', error);
        if (window.Notification) {
            window.Notification.error('Error al extender la sesión');
        }
    }
}

/**
 * Detiene el monitoreo de sesión
 */
function stopSessionMonitoring() {
    if (sessionCheckInterval) {
        clearInterval(sessionCheckInterval);
        sessionCheckInterval = null;
    }
    
    // Remover listeners de actividad
    ['mousedown', 'keydown', 'scroll', 'touchstart'].forEach(event => {
        document.removeEventListener(event, updateLastActivity);
    });
}

/**
 * Verifica la autenticación del usuario
 */
async function checkAuthentication() {
    const authService = new AuthService();
    
    try {
        const response = await authService.validarSesion();
        
        if (!response.success) {
            // Redirigir al login si no hay sesión válida
            try {
                if (window.top && window.top !== window) {
                    window.top.location.replace('/plantaincubacion/login.html');
                } else {
                    window.location.replace('/plantaincubacion/login.html');
                }
            } catch (e) {
                window.location.replace('/plantaincubacion/login.html');
            }
        }
    } catch (error) {
        console.error('Error al validar sesión:', error);
        try {
            if (window.top && window.top !== window) {
                window.top.location.replace('/plantaincubacion/login.html');
            } else {
                window.location.replace('/plantaincubacion/login.html');
            }
        } catch (e) {
            window.location.replace('/plantaincubacion/login.html');
        }
    }
}

/**
 * Maneja el logout
 */
async function handleLogout() {
    const authService = new AuthService();
    
    try {
        AppStore.setState({ loading: true });
        
        const response = await authService.logout();
        
        if (response.success) {
            try {
                if (window.top && window.top !== window) {
                    window.top.location.replace('/plantaincubacion/login.html');
                } else {
                    window.location.replace('/plantaincubacion/login.html');
                }
            } catch (e) {
                window.location.replace('/plantaincubacion/login.html');
            }
        } else {
            Notification.error('Error al cerrar sesión');
        }
    } catch (error) {
        console.error('Error en logout:', error);
        Notification.error('Error al cerrar sesión');
    } finally {
        AppStore.setState({ loading: false });
    }
}

// Inicializar cuando el DOM esté listo
document.addEventListener('DOMContentLoaded', initApp);

// Limpiar monitoreo cuando se cierre la ventana
window.addEventListener('beforeunload', stopSessionMonitoring);

// Exportar funciones útiles globalmente
window.handleLogout = handleLogout;
window.checkAuthentication = checkAuthentication;
window.startSessionMonitoring = startSessionMonitoring;
window.stopSessionMonitoring = stopSessionMonitoring;
window.extendSession = extendSession;
