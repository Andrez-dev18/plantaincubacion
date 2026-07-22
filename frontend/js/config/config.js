// ---------------------------------------------------------------------------
// BASE_URL se calcula automáticamente desde la URL actual del navegador.
// Funciona en desarrollo (localhost) y en producción sin cambiar nada.
//
//   Desarrollo : http://localhost/plantaincubacion/frontend/... 
//                → BASE_URL = http://localhost/plantaincubacion/backend
//
//   Producción : http://servidor/plantaincubacion/frontend/...
//                → BASE_URL = http://servidor/plantaincubacion/backend
//
// Si necesitas forzar una URL específica comenta las dos líneas y
// descomenta: const _BASE_URL = 'http://...';
// ---------------------------------------------------------------------------
const _pathSegment = '/' + window.location.pathname.split('/').filter(Boolean)[0]; // /plantaincubacion
const _BASE_URL    = window.location.origin + _pathSegment + '/backend';

const AppConfig = {
    API: {
        BASE_URL: _BASE_URL,
        ENDPOINTS: {
            AUTH: {
                LOGIN: '/auth/login',
                VALIDAR: '/auth/validar',
                LOGOUT: '/auth/logout',
                PERMISOS: '/auth/permisos'
            },
            GALPONES: {
                LISTAR: '/galpones',
                OBTENER: '/galpones', // + /{id}
                CREAR: '/galpones',
                ACTUALIZAR: '/galpones', // + /{id}
                ELIMINAR: '/galpones', // + /{id}
                CARACTERISTICAS: '/galpones/caracteristicas',
                GRANJAS: '/galpones/granjas',
                EXPORTAR_PDF: '/galpones/exportar-pdf',
                EXPORTAR_EXCEL: '/galpones/exportar-excel'
            },
            CARACTERISTICAS: {
                LISTAR: '/caracteristicas',
                OBTENER: '/caracteristicas', // + /{id}
                CREAR: '/caracteristicas',
                ACTUALIZAR: '/caracteristicas', // + /{id}
                ELIMINAR: '/caracteristicas', // + /{id}
                BUSCAR: '/caracteristicas/buscar', // + ?search=
                TIPOS_DATOS: '/caracteristicas/tiposDatos'
            },
            MODULOS: {
                LISTAR: '/modulos',
                PROGRAMAS: '/modulos/programas'
            },
            DASHBOARD_MODULOS: {
                LISTAR: '/dashboard-modulos',
                CREAR: '/dashboard-modulos',
                ACTUALIZAR: '/dashboard-modulos',
                ELIMINAR: '/dashboard-modulos',
                SEED: '/dashboard-modulos/seed',
                ORDENAR: '/dashboard-modulos/orden',
                SYNC: '/dashboard-modulos/sync'
            },
            IAM: {
                MENU: '/auth/menu',
                PROGRAMAS: '/iam/programas',
                MENU_PROGRAMA: '/iam/menu',
                MODULOS_PROGRAMA: '/iam/programas',
                REORDENAR: '/iam/programas',
                ESTRUCTURA: '/iam/programas'
            },
            USUARIO_SISTEMA: {
                LISTAR:     '/api/usuario-sistema/listar',
                CREAR:      '/api/usuario-sistema/crear',
                ACTUALIZAR: '/api/usuario-sistema/actualizar',
                TOGGLE:     '/api/usuario-sistema/toggle',
                PASSWORD:   '/api/usuario-sistema/password'
            },
            ROL: {
                LISTAR:            '/api/rol/listar',
                PROGRAMAS:         '/api/rol/programas',
                MENUS_DISPONIBLES: '/api/rol/menus-disponibles',
                MODULOS:           '/api/rol/modulos',
                CREAR:             '/api/rol/crear',
                ACTUALIZAR:        '/api/rol/actualizar',
                TOGGLE:            '/api/rol/toggle',
                GUARDAR_MODULOS:   '/api/rol/guardar-modulos',
                ELIMINAR:          '/api/rol/eliminar'
            },
            USUARIO_ROL: {
                USUARIOS_CON_ROLES: '/api/usuario-rol/usuarios-con-roles',
                ROLES_ACTIVOS:      '/api/usuario-rol/roles-activos',
                ROLES_CODIGO:       '/api/usuario-rol/roles-codigo',
                GUARDAR:            '/api/usuario-rol/guardar'
            }
        }
    }
};

window.AppConfig = AppConfig;
window.apiBaseUrl = AppConfig.API.BASE_URL;


// MÓDULO DE SEGURIDAD GLOBAL
const AppSecurity = {
    /**
     * Obtiene los datos del usuario logueado desde la sesión.
     */
    getUserData: function() {
        try {
            // Asegúrate de guardar la respuesta del login en sessionStorage con esta clave
            const userData = sessionStorage.getItem("usuario"); 
            return userData ? JSON.parse(userData) : null;          
        } catch (e) {
            console.error("Error leyendo datos de sesión", e);
            return null;
        }
    },

    /**
     * Verifica el campo 'crea' de la base de datos (1 = Permitido, 0 = Denegado)
     */
    puedeCrear: function() {
        const user = this.getUserData();
        return user ? parseInt(user.crea) === 1 : false;
    },

    /**
     * Verifica el campo 'modifica' de la base de datos
     */
    puedeEditar: function() {
        const user = this.getUserData();
        return user ? parseInt(user.modifica) === 1 : false;
    },

    /**
     * Verifica el campo 'elimina' de la base de datos
     */
    puedeEliminar: function() {
        const user = this.getUserData();
        return user ? parseInt(user.elimina) === 1 : false;
    },

    /**
     * Oculta o muestra un botón de creación en base al permiso puedeCrear().
     * @param {string} btnId ID del botón (por defecto 'btnNuevo')
     */
    aplicarPermisoCrear: function(btnId = 'btnNuevo') {
        const btn = document.getElementById(btnId);
        if (btn) {
            btn.style.display = this.puedeCrear() ? '' : 'none';
        }
    },

    /**
     * Filtra una cadena de texto HTML de botones de acción eliminando aquellos
     * que requieran permisos que el usuario no posea (marcados con data-perm="edit" o data-perm="delete").
     * Si no queda ningún botón con permisos, retorna un indicador de "Sin permisos".
     * @param {string} htmlString Cadena HTML con el contenedor y los botones de acción
     * @returns {string} HTML filtrado
     */
    filtrarBotonesTabla: function(htmlString) {
        try {
            const parser = new DOMParser();
            const doc = parser.parseFromString(htmlString.trim(), 'text/html');
            const wrapper = doc.body.firstElementChild || doc.body;

            // Filtrar botones de edición
            if (!this.puedeEditar()) {
                doc.querySelectorAll('[data-perm="edit"]').forEach(el => el.remove());
            }

            // Filtrar botones de eliminación
            if (!this.puedeEliminar()) {
                doc.querySelectorAll('[data-perm="delete"]').forEach(el => el.remove());
            }

            // Validar si quedan botones interactivos restantes dentro del contenedor
            const botonesRestantes = wrapper.querySelectorAll('button, a');
            if (botonesRestantes.length === 0) {
                return '<span class="text-gray-400 text-xs italic">Sin permisos</span>';
            }

            return wrapper.outerHTML || doc.body.innerHTML;
        } catch (e) {
            console.error("Error al filtrar botones de tabla:", e);
            return htmlString;
        }
    }
};

window.AppSecurity = AppSecurity;