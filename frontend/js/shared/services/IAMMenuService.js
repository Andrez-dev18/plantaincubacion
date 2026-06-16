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
    /**
     * Convierte el formato de menú IAM al formato esperado por renderSidebarMenu
     * CON LIMPIEZA DE TEXTOS REPETIDOS DE DESARROLLO
     */
    convertirMenuIAMaFormato(menuIAM) {
        if (!Array.isArray(menuIAM)) {
            console.warn('El menú IAM no es un array válido:', menuIAM);
            return [];
        }

        const items = [];

        // Función auxiliar para separar el texto descriptivo del identificador técnico pegado
        // Ej: "Programa de CargaGest. Objetivos" -> "Programa de Carga"
        function limpiarTextoModulo(texto) {
            if (!texto) return '';
            let textoLimpio = texto.replace(/([a-z])([A-Z])/g, '$1 $2');

            // Eliminar colas sólo si están al final estricto del string
            textoLimpio = textoLimpio.replace(/\s*Gest\.\s*Objetivos$/i, '');
            textoLimpio = textoLimpio.replace(/\s*Config\s*Roles$/i, '');
            textoLimpio = textoLimpio.replace(/\s*Carga\s*Pollo$/i, '');

            return textoLimpio.trim();
        }

        // Función auxiliar para extraer un label corto limpio y amigable para el modo colapsado
        function extraerLabelCorto(nodo) {
            if (nodo.label_short && !nodo.label_short.includes('Gest.')) {
                return nodo.label_short;
            }
            // Si no hay un label corto limpio, procesamos el nombre original para sacar algo coherente
            const textoOriginal = nodo.nom_mod || '';
            if (textoOriginal.includes('Carga')) return 'Carga';
            if (textoOriginal.includes('Procedencia')) return 'Origen';
            if (textoOriginal.includes('Transporte')) return 'Transp.';
            if (textoOriginal.includes('Almacén')) return 'Almac.';
            if (textoOriginal.includes('Incubación')) return 'Incub.';
            if (textoOriginal.includes('Vacunación')) return 'Vacuna';
            if (textoOriginal.includes('Granja')) return 'Granja';
            if (textoOriginal.includes('Roles') || textoOriginal.includes('Config')) return 'Roles';

            return limpiarTextoModulo(textoOriginal).substring(0, 7);
        }

        // Función recursiva para aplanar el árbol
        function aplanarNodo(nodo, parentCod = null) {
            const nombreLimpio = limpiarTextoModulo(nodo.nom_mod || nodo.label_short || 'Módulo');

            const item = {
                cod_mod: nodo.cod_mod,
                nom_mod: nombreLimpio,
                icono: nodo.icono || 'fas fa-cog',
                tipo: nodo.tipo || 'item',
                orden: nodo.orden || 0,
                url: nodo.url || 'pages/trabajando.html',
                label_short: null, // CORRECCIÓN: Ya no necesitamos arrastrar datos cortos
                parent_cod: parentCod,
                permiso: nodo.permiso || null,
                tipo_param: nodo.tipo_param || 'modulo',
                titulo: nombreLimpio
            };

            items.push(item);

            if (Array.isArray(nodo.children)) {
                nodo.children.forEach(hijo => aplanarNodo(hijo, nodo.cod_mod));
            }
        }

        // Procesar cada programa
        menuIAM.forEach((programa, programaIndex) => {
            const hasMenu = Array.isArray(programa.menu) && programa.menu.length > 0;
            if (!hasMenu) return;

            const nombreProgramaLimpio = limpiarTextoModulo(programa.programa?.nombre || 'Programa');

            // Crear grupo principal para el programa (Nivel 1)
            const grupoPrograma = {
                cod_mod: `PROG_${programa.programa?.id_programa || programaIndex}`,
                nom_mod: nombreProgramaLimpio,
                icono: 'fas fa-folder',
                tipo: 'group',
                orden: programaIndex + 1,
                label_short: null, // Eliminado
                parent_cod: null,
                url: null,
                permiso: null,
                tipo_param: null,
                titulo: nombreProgramaLimpio
            };

            items.push(grupoPrograma);

            programa.menu.forEach(nodo => {
                aplanarNodo(nodo, grupoPrograma.cod_mod);
            });
        });

        return items;
    }
}

// Exportar para uso global
window.IAMMenuService = IAMMenuService;