// Session Controller - Gestión de sesión y autenticación
document.addEventListener('DOMContentLoaded', async function() {
    const authService = new AuthService();
    
    // Validar sesión al cargar
    try {
        console.log('🔍 Validando sesión...');
        
        // Primero verificar sessionStorage (más rápido y confiable)
        const isAuth = authService.isAuthenticated();
        console.log('📋 Autenticado en sessionStorage:', isAuth);
        
        if (!isAuth) {
            console.warn('❌ No hay sesión en sessionStorage, redirigiendo a login...');
            try {
                if (window.top && window.top !== window) {
                    window.top.location.replace('/plantaincubacion/login.html');
                } else {
                    window.location.replace('/plantaincubacion/login.html');
                }
            } catch (e) {
                window.location.replace('/plantaincubacion/login.html');
            }
            return;
        }
        
        // Obtener datos del usuario desde sessionStorage
        const usuario = authService.getCurrentUser();
        console.log('✅ Sesión válida:', usuario);
        
        if (usuario) {
            const userNameElem = document.getElementById('userName');
            const rolUserElem = document.getElementById('rolUser');
            
            // Compatible con IAM y sistema antiguo
            if (userNameElem) userNameElem.textContent = usuario.nombre || usuario.nombre_completo || 'Usuario';
            if (rolUserElem) {
                const rolNombre = sessionStorage.getItem('username') || usuario.username || 'Usuario';
                rolUserElem.textContent = rolNombre;
            }
        }
    } catch (error) {
        console.error('💥 Error validando sesión:', error);
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

    // Event listener para cerrar sesión
    const btnCerrarSesion = document.getElementById('btnCerrarSesion');
    if (btnCerrarSesion) {
        btnCerrarSesion.addEventListener('click', async function(e) {
            e.preventDefault();

            const confirmarCierre = async () => {
                if (window.SwalHelpers?.showConfirm) {
                    return await window.SwalHelpers.showConfirm(
                        'Confirmar cierre de sesión',
                        '¿Está seguro que desea cerrar sesión?'
                    );
                }
                return confirm('¿Está seguro que desea cerrar sesión?');
            };

            if (await confirmarCierre()) {
                try {
                    await authService.logout();
                    sessionStorage.clear();
                    localStorage.clear();
                    try {
                        if (window.top && window.top !== window) {
                            window.top.location.replace('/plantaincubacion/login.html');
                        } else {
                            window.location.replace('/plantaincubacion/login.html');
                        }
                    } catch (e) {
                        window.location.replace('/plantaincubacion/login.html');
                    }
                } catch (error) {
                    console.error('Error al cerrar sesión:', error);

                    if (window.SwalHelpers?.showError) {
                        await window.SwalHelpers.showError('Error al cerrar sesión. Intente nuevamente.');
                    } else {
                        alert('Error al cerrar sesión. Intente nuevamente.');
                    }
                }
            }
        });
    }
});
