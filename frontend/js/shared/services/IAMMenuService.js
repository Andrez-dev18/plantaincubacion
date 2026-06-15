/**
 * Servicio para gestionar el menú dinámico del módulo IAM
 * Maneja la comunicación con el backend IAM para obtener módulos y menús
 */
class IAMMenuService extends Service {
    constructor() {
        super(AppConfig.API.BASE_URL);
    }

    /**
     * Obtiene el menú completo del usuario autenticado
     */
    async obtenerMenuCompleto() {
        try {
            const endpoint = AppConfig.API.ENDPOINTS.IAM.MENU;
            const response = await this.get(endpoint);
            
            if (!response.success) {
                throw new Error(response.message || 'Error al obtener menú');
            }

            return {
                success: true,
                data: response.data || []
            };
        } catch (error) {
            console.error('Error al obtener menú completo:', error);
            return {
                success: false,
                error: error.message,
                data: []
            };
        }
    }

    /**
     * Obtiene el menú de un programa específico
     */
    async obtenerMenuPorPrograma(idPrograma) {
        try {
            const endpoint = `${AppConfig.API.ENDPOINTS.IAM.MENU_PROGRAMA}/${idPrograma}`;
            const response = await this.get(endpoint);
            
            if (!response.success) {
                throw new Error(response.message || 'Error al obtener menú del programa');
            }

            return {
                success: true,
                data: response.data || []
            };
        } catch (error) {
            console.error(`Error al obtener menú del programa ${idPrograma}:`, error);
            return {
                success: false,
                error: error.message,
                data: []
            };
        }
    }

    /**
     * Obtiene los programas disponibles
     */
    async obtenerProgramas() {
        try {
            const endpoint = AppConfig.API.ENDPOINTS.IAM.PROGRAMAS;
            const response = await this.get(endpoint);
            
            if (!response.success) {
                throw new Error(response.message || 'Error al obtener programas');
            }

            return {
                success: true,
                data: response.data || []
            };
        } catch (error) {
            console.error('Error al obtener programas:', error);
            return {
                success: false,
                error: error.message,
                data: []
            };
        }
    }

    /**
     * Obtiene módulos de un programa específico
     */
    async obtenerModulosPorPrograma(idPrograma) {
        try {
            const endpoint = `${AppConfig.API.ENDPOINTS.IAM.MODULOS_PROGRAMA}/${idPrograma}/modulos`;
            const response = await this.get(endpoint);
            
            if (!response.success) {
                throw new Error(response.message || 'Error al obtener módulos del programa');
            }

            return {
                success: true,
                data: response.data || []
            };
        } catch (error) {
            console.error(`Error al obtener módulos del programa ${idPrograma}:`, error);
            return {
                success: false,
                error: error.message,
                data: []
            };
        }
    }

    /**
     * Convierte el formato de menú IAM al formato esperado por renderSidebarMenu
     */
    convertirMenuIAMaFormato(menuIAM) {
        // Convierte el árbol jerárquico del backend en un array plano para renderSidebarMenu
        if (!Array.isArray(menuIAM)) {
            console.warn('El menú IAM no es un array válido:', menuIAM);
            return [];
        }

        const items = [];

        // Función recursiva para aplanar el árbol
        function aplanarNodo(nodo, parentCod = null) {
            const item = {
                cod_mod: nodo.cod_mod,
                nom_mod: nodo.nom_mod || nodo.label_short || 'Módulo',
                icono: nodo.icono || 'fas fa-cog',
                tipo: nodo.tipo || 'item',
                orden: nodo.orden || 0,
                url: nodo.url || 'pages/trabajando.html',
                label_short: nodo.label_short || nodo.nom_mod?.substring(0, 5) || 'MOD',
                parent_cod: parentCod,
                permiso: nodo.permiso || null,
                tipo_param: nodo.tipo_param || 'modulo',
                titulo: nodo.nom_mod || nodo.label_short || 'Módulo'
            };
            
            items.push(item);

            // Si tiene hijos, aplanarlos también
            if (Array.isArray(nodo.children)) {
                nodo.children.forEach(hijo => aplanarNodo(hijo, nodo.cod_mod));
            }
        }

        // Procesar cada programa
        menuIAM.forEach((programa, programaIndex) => {
            const hasMenu = Array.isArray(programa.menu) && programa.menu.length > 0;
            if (!hasMenu) {
                return;
            }

            // Crear grupo principal para el programa
            const grupoPrograma = {
                cod_mod: `PROG_${programa.programa?.id_programa || programaIndex}`,
                nom_mod: programa.programa?.nombre || 'Programa',
                icono: 'fas fa-folder',
                tipo: 'group',
                orden: programaIndex + 1,
                label_short: programa.programa?.nombre?.substring(0, 4) || 'PROG',
                parent_cod: null,
                url: null,
                permiso: null,
                tipo_param: null,
                titulo: programa.programa?.nombre || 'Programa'
            };
            
            items.push(grupoPrograma);

            // Aplanar todos los nodos del menú del programa
            programa.menu.forEach(nodo => {
                aplanarNodo(nodo, grupoPrograma.cod_mod);
            });
        });

        return items;
    }
}

// Exportar para uso global
window.IAMMenuService = IAMMenuService;