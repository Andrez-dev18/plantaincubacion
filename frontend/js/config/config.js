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
