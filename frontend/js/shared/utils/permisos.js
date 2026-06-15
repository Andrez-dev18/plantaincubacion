class PermisosManager {
    // Definición de roles del sistema
    static ROLES = {
        ADMIN: 'admin',
        ADMINISTRADOR: 'administrador', // Nuevo sistema RBAC
        USER: 'user',
        USUARIO: 'usuario',
        SUPERVISOR: 'supervisor de planta', // Nuevo sistema RBAC
        OPERARIO: 'operario', // Nuevo sistema RBAC
        VETERINARIO: 'veterinario senior' // Nuevo sistema RBAC
    };

    // Permisos por página
    static PERMISOS = {
        'dashboard.html': ['admin', 'administrador', 'administrador del sistema', 'user', 'usuario', 'supervisor de planta', 'gestor de planta', 'operario', 'veterinario senior'],
        'dashboard-control-objetivos.html': ['admin', 'administrador', 'administrador del sistema'],
        'dashboard-confirmacion-objetivos.html': ['admin', 'administrador', 'administrador del sistema', 'user', 'usuario', 'supervisor de planta', 'gestor de planta'],
        'index.html': ['admin', 'administrador', 'administrador del sistema', 'user', 'usuario', 'supervisor de planta', 'supervisor de operaciones', 'gestor de planta', 'operador', 'operario', 'veterinario senior', 'solo consulta']
    };

    // Permisos por acción
    static PERMISOS_ACCIONES = {
        'editar': ['admin', 'administrador', 'administrador del sistema', 'user', 'gestor de planta', 'supervisor de planta'],
        'validar': ['admin', 'administrador', 'administrador del sistema', 'user', 'usuario', 'gestor de planta', 'supervisor de planta', 'supervisor de operaciones', 'veterinario senior'],
        'eliminar': ['admin', 'administrador', 'administrador del sistema'],
        'crear': ['admin', 'administrador', 'administrador del sistema', 'user', 'gestor de planta', 'supervisor de planta']
    };

    /**
     * Obtiene el usuario actual desde sessionStorage
     */
    static getUsuarioActual() {
        try {
            const usuarioStr = sessionStorage.getItem('usuario');
            if (usuarioStr) {
                return JSON.parse(usuarioStr);
            }
        } catch (e) {
            console.error('Error al obtener usuario:', e);
        }
        return null;
    }

    /**
     * Obtiene el rol del usuario actual en minúsculas
     * Compatible con el nuevo sistema de roles RBAC multi-rol
     */
    static getRolUsuario() {
        const usuario = this.getUsuarioActual();
        if (usuario) {
            // Nuevo sistema RBAC multi-rol: usar roles array (primer rol)
            if (usuario.roles && Array.isArray(usuario.roles) && usuario.roles.length > 0) {
                // Compatible con IAM: nom_rol, nombre_rol o nombre
                const nombreRol = usuario.roles[0].nom_rol || usuario.roles[0].nombre_rol || usuario.roles[0].nombre;
                if (nombreRol) {
                    return nombreRol.toLowerCase();
                }
            }
            // Compatibilidad: nombre_rol directo
            if (usuario.nombre_rol) {
                return usuario.nombre_rol.toLowerCase();
            }
            // Sistema legacy: usar rol o rol_sanidad como fallback
            const rol = usuario.rol || usuario.rol_sanidad;
            if (rol) {
                return rol.toLowerCase();
            }
        }
        return null;
    }

    /**
     * Verifica si el usuario actual es administrador
     */
    static esAdmin() {
        const rol = this.getRolUsuario();
        // Verificar tanto "admin" como "administrador" para compatibilidad con IAM
        return rol === this.ROLES.ADMIN || rol === 'administrador' || rol === 'administrador del sistema';
    }

    /**
     * Verifica si el usuario actual es solo 'usuario' (sin privilegios)
     */
    static esSoloUsuario() {
        return this.getRolUsuario() === this.ROLES.USUARIO;
    }

    /**
     * Verifica si el usuario puede realizar una acción específica
     */
    static puedeRealizarAccion(accion) {
        const rol = this.getRolUsuario();
        if (!rol) return false;
        
        const rolesPermitidos = this.PERMISOS_ACCIONES[accion];
        if (!rolesPermitidos) {
            // Si la acción no está definida, permitir por defecto
            return true;
        }
        
        return rolesPermitidos.includes(rol);
    }

    /**
     * Verifica si el usuario puede editar
     */
    static puedeEditar() {
        return this.puedeRealizarAccion('editar');
    }

    /**
     * Verifica si el usuario puede validar
     */
    static puedeValidar() {
        return this.puedeRealizarAccion('validar');
    }

    /**
     * Verifica si el usuario puede eliminar
     */
    static puedeEliminar() {
        return this.puedeRealizarAccion('eliminar');
    }

    /**
     * Verifica si el usuario puede crear
     */
    static puedeCrear() {
        return this.puedeRealizarAccion('crear');
    }

    /**
     * Oculta botones según los permisos del usuario
     */
    static ocultarBotonesSegunPermisos() {
        // Ocultar botones de editar si no tiene permiso
        if (!this.puedeEditar()) {
            const botonesEditar = document.querySelectorAll('.btn-editar, [data-accion="editar"], .accion-editar');
            botonesEditar.forEach(btn => {
                btn.style.display = 'none';
            });
        }

        // Ocultar botones de validar si no tiene permiso
        if (!this.puedeValidar()) {
            const botonesValidar = document.querySelectorAll('.btn-validar, [data-accion="validar"], .accion-validar');
            botonesValidar.forEach(btn => {
                btn.style.display = 'none';
            });
        }

        // Ocultar botones de eliminar si no tiene permiso
        if (!this.puedeEliminar()) {
            const botonesEliminar = document.querySelectorAll('.btn-eliminar, [data-accion="eliminar"], .accion-eliminar');
            botonesEliminar.forEach(btn => {
                btn.style.display = 'none';
            });
        }

        // Ocultar botones de crear si no tiene permiso
        if (!this.puedeCrear()) {
            const botonesCrear = document.querySelectorAll('.btn-crear, [data-accion="crear"], .accion-crear');
            botonesCrear.forEach(btn => {
                btn.style.display = 'none';
            });
        }

        console.log('Permisos aplicados - Editar:', this.puedeEditar(), '| Validar:', this.puedeValidar());
    }

    /**
     * Verifica si el usuario tiene permiso para acceder a una página
     */
    static tienePermisoParaPagina(nombrePagina) {
        const rol = this.getRolUsuario();
        if (!rol) return false;

        const rolesPermitidos = this.PERMISOS[nombrePagina];
        if (!rolesPermitidos) {
            // Si la página no está en PERMISOS, permitir por defecto
            return true;
        }

        return rolesPermitidos.includes(rol);
    }

    /**
     * Verifica acceso a la página actual y redirige si es necesario
     */
    static verificarAccesoPaginaActual() {
        const paginaActual = window.location.pathname.split('/').pop() || 'index.html';
        const usuario = this.getUsuarioActual();

        // ✅ PERMITIR ACCESO LIBRE A PÁGINAS PRINCIPALES
        const paginasAbiertas = ['index.html', 'login.html'];
        if (paginasAbiertas.includes(paginaActual)) {
            return true; // Siempre permitir acceso a index y login
        }

        // Si no hay usuario, redirigir a login
        if (!usuario) {
            try {
                if (window.top && window.top !== window) {
                    window.top.location.replace('/plantaincubacion/login.html');
                } else {
                    window.location.replace('/plantaincubacion/login.html');
                }
            } catch (e) {
                window.location.replace('/plantaincubacion/login.html');
            }
            return false;
        }

        // Verificar permiso para la página
        if (!this.tienePermisoParaPagina(paginaActual)) {
            alert('No tienes permiso para acceder a esta página');
            window.location.href = 'index.html';
            return false;
        }

        return true;
    }

    /**
     * Filtra elementos del menú según permisos
     */
    static filtrarMenuPorPermisos() {
        const rol = this.getRolUsuario();
        
        // ✅ Si no hay rol, no filtrar (confiar en el IAMMenuService del backend)
        if (!rol) {
            console.log('Sin rol detectado - menú ya viene filtrado del backend IAM');
            return;
        }
        
        // Si es admin, mostrar todo (no filtrar nada)
        if (this.esAdmin()) {
            console.log('Usuario es admin - mostrando todo el menú');
            return;
        }
        
        const elementosConPermiso = document.querySelectorAll('[data-permiso]');

        elementosConPermiso.forEach(elemento => {
            const permisosRequeridos = elemento.getAttribute('data-permiso').split(',').map(p => p.trim().toLowerCase());
            
            if (rol && permisosRequeridos.includes(rol)) {
                elemento.style.display = '';
            } else {
                elemento.style.display = 'none';
            }
        });
    }

    /**
     * Aplica permisos a la interfaz de usuario
     */
    static aplicarPermisosUI() {
        const rol = this.getRolUsuario();

        // Ocultar elementos marcados como solo-admin
        if (!this.esAdmin()) {
            const elementosAdmin = document.querySelectorAll('.solo-admin');
            elementosAdmin.forEach(el => {
                el.style.display = 'none';
            });
        }

        // Ocultar elementos marcados como solo-usuario
        if (!this.esSoloUsuario()) {
            const elementosUsuario = document.querySelectorAll('.solo-usuario');
            elementosUsuario.forEach(el => {
                el.style.display = 'none';
            });
        }

        // Mostrar elementos que requieren edición solo si tiene permiso
        if (!this.puedeEditar()) {
            const elementosEditar = document.querySelectorAll('.requiere-editar');
            elementosEditar.forEach(el => {
                el.style.display = 'none';
            });
        }

        // Filtrar menú por permisos
        this.filtrarMenuPorPermisos();

        // Ocultar botones según permisos
        this.ocultarBotonesSegunPermisos();
    }

    /**
     * Inicializa el sistema de permisos
     */
    static init() {
        this.verificarAccesoPaginaActual();
        this.aplicarPermisosUI();

        const usuario = this.getUsuarioActual();
        console.log('Usuario:', usuario?.nombre || 'N/A');
        console.log('Rol:', this.getRolUsuario());
        console.log('Es Admin:', this.esAdmin());
    }
}


/**
 * Gestión de Paneles según el rol del usuario
 */
class PanelManager {
    static PANELES = {
        'dashboard': {
            nombre: 'Dashboard',
            href: 'dashboard.html',
            roles: ['admin', 'user', 'usuario']
        },
        'control-objetivos': {
            nombre: 'Gestión de Objetivos y Metas',
            href: 'dashboard-control-objetivos.html',
            roles: ['admin']
        },
        'confirmacion-objetivos': {
            nombre: 'Confirmación Objetivos y Metas',
            href: 'dashboard-confirmacion-objetivos.html',
            roles: ['admin', 'user', 'usuario']
        }
    };

    /**
     * Obtiene los paneles disponibles para el rol actual
     */
    static getPanelesDisponibles() {
        const rol = PermisosManager.getRolUsuario();
        const panelesDisponibles = {};

        for (const [key, panel] of Object.entries(this.PANELES)) {
            if (panel.roles.includes(rol)) {
                panelesDisponibles[key] = panel;
            }
        }

        return panelesDisponibles;
    }

    /**
     * Activa/desactiva paneles en la interfaz según permisos
     */
    static activarPaneles() {
        const panelesDisponibles = this.getPanelesDisponibles();
        const rol = PermisosManager.getRolUsuario();

        // Buscar todos los enlaces de navegación
        const enlaces = document.querySelectorAll('a[href]');

        enlaces.forEach(enlace => {
            const href = enlace.getAttribute('href');
            
            // Buscar si el href corresponde a algún panel
            for (const [key, panel] of Object.entries(this.PANELES)) {
                if (href && href.includes(panel.href)) {
                    if (panel.roles.includes(rol)) {
                        enlace.style.display = '';
                    } else {
                        enlace.style.display = 'none';
                    }
                    break;
                }
            }
        });

        console.log('Paneles disponibles para', rol + ':', Object.keys(panelesDisponibles).length);
    }

    /**
     * Inicializa el gestor de paneles
     */
    static init() {
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', () => {
                this.activarPaneles();
            });
        } else {
            this.activarPaneles();
        }
    }
}


// Exportar al objeto window para acceso global
window.PermisosManager = PermisosManager;
window.PanelManager = PanelManager;