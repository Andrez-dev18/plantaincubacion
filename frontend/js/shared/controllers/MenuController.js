/**
 * Controlador para el menú lateral dinámico
 * Se encarga de cargar y mostrar el menú usando el módulo IAM
 */
class MenuController {
    constructor() {
        this.menuService = new IAMMenuService();
        this.menuActual = [];
        
        // Bind methods
        this.cargarMenu = this.cargarMenu.bind(this);
        this.onMenuChange = this.onMenuChange.bind(this);
    }

    /**
     * Inicializa el controlador del menú
     */
    async init() {
        try {
            console.log('🔄 Inicializando controlador de menú...');
            await this.cargarMenu();
            this.setupEventListeners();
            console.log('✅ Controlador de menú inicializado');
        } catch (error) {
            console.error('❌ Error inicializando controlador de menú:', error);
            this.mostrarMenuFallback();
        }
    }

    /**
     * Carga el menú desde el módulo IAM
     */
    async cargarMenu() {
        try {
            console.log('📥 Cargando menú desde IAM...');
            
            const response = await this.menuService.obtenerMenuCompleto();
            
            if (response.success && response.data) {
                console.log('📊 Datos de menú recibidos:', response.data);
                
                // Convertir formato IAM al formato esperado por el frontend
                const menuItems = this.menuService.convertirMenuIAMaFormato(response.data);
                
                console.log('🔄 Items de menú convertidos:', menuItems);
                
                // Guardar menú actual
                this.menuActual = menuItems;
                
                // Renderizar menú si existe la función
                if (typeof window.renderSidebarMenu === 'function') {
                    window.renderSidebarMenu(menuItems);
                    console.log('✅ Menú renderizado correctamente');
                } else {
                    console.warn('⚠️ Función renderSidebarMenu no encontrada');
                }
                
                return true;
            } else {
                // ✅ Si no hay datos válidos, mostrar menú de fallback
                console.warn('⚠️ No se recibieron datos de menú válidos, usando fallback');
                this.mostrarMenuFallback();
                return false;
            }
        } catch (error) {
            console.error('❌ Error cargando menú:', error);
            throw error;
        }
    }

    /**
     * Muestra un menú básico en caso de error 
     */
    mostrarMenuFallback() {
        if (window.enableMenuFallback !== true) {
            console.warn('⚠️ Menú de fallback deshabilitado; evitando datos hardcodeados');
            if (typeof window.renderSidebarMenu === 'function') {
                window.renderSidebarMenu([]);
            }
            return;
        }

        console.log('🆘 Mostrando menú de fallback...');

        const menuFallback = [];

        if (typeof window.renderSidebarMenu === 'function') {
            window.renderSidebarMenu(menuFallback);
            console.log('✅ Menú de fallback renderizado');
        }
    }

    /**
     * Configura event listeners
     */
    setupEventListeners() {
        // Listener para recargar menú
        document.addEventListener('menuReload', this.onMenuChange);
        
        // Listener para cambios de permisos
        document.addEventListener('permissionsChanged', this.onMenuChange);
    }

    /**
     * Maneja cambios en el menú
     */
    async onMenuChange(event) {
        console.log('🔄 Cambio detectado en menú:', event.type);
        await this.cargarMenu();
    }

    /**
     * Recarga el menú manualmente
     */
    async recargarMenu() {
        console.log('🔄 Recargando menú manualmente...');
        return await this.cargarMenu();
    }

    /**
     * Obtiene el menú actual
     */
    getMenuActual() {
        return this.menuActual;
    }
}

// Crear instancia global del controlador
window.MenuController = MenuController;

// Auto-inicialización cuando el DOM esté listo
document.addEventListener('DOMContentLoaded', async function() {
    // Esperar a que se carguen todos los servicios
    setTimeout(async () => {
        try {
            console.log('🚀 Iniciando carga del menú IAM...');

            if (window.disableIAMMenu || window.menuLoadedFromDashboard) {
                console.log('⏭️ Menú IAM deshabilitado por configuración del dashboard');
                return;
            }
            
            // Verificar que todas las dependencias estén disponibles
            if (typeof IAMMenuService === 'undefined') {
                console.warn('⚠️ IAMMenuService no disponible');
                return;
            }
            
            if (typeof window.renderSidebarMenu !== 'function') {
                console.warn('⚠️ renderSidebarMenu no disponible');
                return;
            }

            // Crear e inicializar el controlador del menú
            window.menuController = new MenuController();
            await window.menuController.init();
            
            console.log('✅ Menú IAM completamente cargado');
        } catch (error) {
            console.error('❌ Error inicializando MenuController:', error);
            console.log('🆘 Usando menú de respaldo...');
            
            // Intentar cargar menú de respaldo
            if (window.menuController) {
                window.menuController.mostrarMenuFallback();
            }
        }
    }, 2000); // Aumentar tiempo para asegurar que todo se cargue
});