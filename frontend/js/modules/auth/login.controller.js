/**
 * Controlador para la página de login
 * Maneja la autenticación de usuarios
 */
class LoginController {
    constructor() {
        this.authService = new AuthService();
        this.elements = {
            loginForm: document.getElementById('loginForm'),
            errorMessage: document.getElementById('errorMessage'),
            loading: document.getElementById('loading'),
            togglePassword: document.getElementById('togglePassword'),
            passwordInput: document.getElementById('password'),
            usuarioInput: document.getElementById('usuario')
        };

        this.init();
    }

    /**
     * Inicializa el controlador
     */
    init() {
        // Removido checkExistingSession() para evitar loops
        this.setupPasswordToggle();
        this.setupFormSubmit();
    }

    /**
     * Verifica si ya existe una sesión activa
     * NOTA: Deshabilitado para evitar loops de redirección
     */
    async checkExistingSession() {
        // Esta función ya no se llama en init()
        // La validación de sesión se hace solo en index.html
        return;
    }

    /**
     * Configura el toggle de mostrar/ocultar contraseña
     */
    setupPasswordToggle() {
        const { togglePassword, passwordInput } = this.elements;
        
        if (togglePassword && passwordInput) {
            togglePassword.addEventListener('click', () => {
                const type = passwordInput.type === 'password' ? 'text' : 'password';
                passwordInput.type = type;
                togglePassword.innerHTML = type === 'password'
                    ? '<i class="fas fa-eye"></i>'
                    : '<i class="fas fa-eye-slash"></i>';
            });
        }
    }

    /**
     * Configura el envío del formulario
     */
    setupFormSubmit() {
        const { loginForm } = this.elements;
        
        if (loginForm) {
            loginForm.addEventListener('submit', async (e) => {
                e.preventDefault();
                await this.handleLogin();
            });
        }
    }

    /**
     * Maneja el proceso de login
     */
    async handleLogin() {
        const { usuarioInput, passwordInput, errorMessage, loading } = this.elements;

        // Limpiar mensaje de error
        errorMessage?.classList.remove('show');

        const usuario = usuarioInput.value.trim();
        const password = passwordInput.value.trim();

        console.log('🔐 [LOGIN] Iniciando login con usuario:', usuario);

        if (!usuario || !password) {
            console.error('❌ [LOGIN] Campos vacíos');
            this.showError('Por favor complete todos los campos');
            return;
        }

        try {
            // Mostrar loading
            if (loading) loading.classList.add('show');

            console.log('📡 [LOGIN] Enviando credenciales al servidor...');

            // Obtener ubicación GPS
            const ubicacion = await this.obtenerUbicacion();

            // Realizar login
            const data = await this.authService.login(usuario, password, ubicacion);

            console.log('📊 [LOGIN] Respuesta del servidor:', data);

            if (data && data.success) {
                console.log('✅ [LOGIN] Autenticación exitosa');
                // Guardar en sessionStorage para compatibilidad
                sessionStorage.setItem('usuario', JSON.stringify(data.data));

                // Redirigir al dashboard
                console.log('🔄 [LOGIN] Redirigiendo a index.html');
                window.location.href = 'index.html';
            } else {
                const errorMsg = data.message || data.mensaje || 'Credenciales inválidas';
                console.error('❌ [LOGIN] Error de autenticación:', errorMsg);
                console.error('📋 [LOGIN] Respuesta completa:', data);
                this.showError(errorMsg);
            }
        } catch (error) {
            console.error('⚠️ [LOGIN] Error en catch:', error);
            console.error('🔍 [LOGIN] Stack:', error.stack);
            // Mostrar el mensaje del servidor si está disponible, no genérico
            const msg = error.message && !error.message.startsWith('HTTP Error')
                ? error.message
                : 'Usuario o contraseña incorrectos';
            this.showError(msg);
        } finally {
            if (loading) loading.classList.remove('show');
        }
    }

    /**
     * Muestra un mensaje de error
     */
    showError(mensaje) {
        const { errorMessage } = this.elements;

        if (errorMessage) {
            const span = errorMessage.querySelector('span');
            if (span) {
                span.textContent = mensaje;
            } else {
                errorMessage.innerHTML = `<i class="fas fa-exclamation-circle"></i><span>${mensaje}</span>`;
            }
            errorMessage.classList.add('show');
        }
    }

    /**
     * Obtiene la ubicación GPS del usuario
     */
    obtenerUbicacion() {
        return new Promise((resolve) => {
            if (!navigator.geolocation) {
                resolve(null);
                return;
            }

            navigator.geolocation.getCurrentPosition(
                pos => {
                    resolve(`${pos.coords.latitude},${pos.coords.longitude}`);
                },
                err => {
                    resolve(null); // Usuario negó permiso o error
                }
            );
        });
    }
}

// Inicializar el controlador cuando el DOM esté listo
document.addEventListener('DOMContentLoaded', () => {
    new LoginController();
});
