/**
 * ====================================================================
 * GESTOR GLOBAL DE FILTROS Y COLUMNAS
 * ====================================================================
 * Archivo centralizado para manejar la funcionalidad de:
 * - Toggle de filtros (mostrar/ocultar)
 * - Toggle de columnas
 * - Visibilidad de columnas
 */

class FiltrosManager {
    constructor(tableId = 'tablaTareas') {
        this.tableId = tableId;
        this.init();
    }

    /**
     * Inicializa todos los event listeners
     */
    init() {
        this.setupToggleFiltros();
        this.setupColumnToggle();
    }

    /**
     * Configura el toggle de filtros (mostrar/ocultar con animación de flecha)
     */
    setupToggleFiltros() {
        const btnToggle = document.getElementById('btnToggleFiltros');
        const filterContent = document.getElementById('filterContent');
        
        if (!btnToggle || !filterContent) {
            console.warn('⚠️ FiltrosManager: btnToggleFiltros o filterContent no encontrados');
            return;
        }

        console.log('✅ FiltrosManager: setupToggleFiltros inicializado');

        btnToggle.addEventListener('click', () => {
            console.log('🔄 Click en btnToggleFiltros');
            
            // Toggle de la clase 'show'
            const isOpen = filterContent.classList.toggle('show');
            const icon = btnToggle.querySelector('i');
            
            if (!icon) return;
            
            // Cambiar el icono de flecha
            if (isOpen) {
                icon.classList.remove('fa-chevron-down');
                icon.classList.add('fa-chevron-up');
                console.log('📂 Filtros abiertos');
            } else {
                icon.classList.remove('fa-chevron-up');
                icon.classList.add('fa-chevron-down');
                console.log('📁 Filtros cerrados');
            }
        });
    }

    /**
     * Configura el toggle de columnas y su visibilidad
     */
    setupColumnToggle() {
        const btnToggle = document.getElementById('btnToggleColumns');
        const dropdown = document.getElementById('columnDropdown');
        const tableSelector = `#${this.tableId}`;

        if (!btnToggle || !dropdown) {
            console.warn('⚠️ FiltrosManager: btnToggleColumns o columnDropdown no encontrados');
            return;
        }

        console.log('✅ FiltrosManager: setupColumnToggle inicializado');

        // Toggle del dropdown de columnas
        btnToggle.addEventListener('click', (e) => {
            e.stopPropagation();
            console.log('🔄 Click en btnToggleColumns');
            dropdown.classList.toggle('show');
        });

        // Cerrar dropdown al hacer clic fuera
        document.addEventListener('click', (e) => {
            if (!e.target.closest('.dropdown-columns')) {
                dropdown.classList.remove('show');
            }
        });

        // Cambiar visibilidad de columnas
        document.querySelectorAll('.column-checkbox').forEach(checkbox => {
            checkbox.addEventListener('change', (e) => {
                const columnIndex = parseInt(e.target.dataset.column);
                
                // Selector CSS para la columna específica (1-based)
                const cellSelector = `${tableSelector} th:nth-child(${columnIndex + 1}), ${tableSelector} td:nth-child(${columnIndex + 1})`;
                const cells = document.querySelectorAll(cellSelector);
                
                cells.forEach(cell => {
                    // Usar display:none para ocultar o vaciar para mostrar
                    cell.style.display = e.target.checked ? '' : 'none';
                });
                
                console.log(`📊 Columna ${columnIndex + 1}: ${e.target.checked ? 'Visible' : 'Oculta'}`);
            });
        });
    }
}

// Inicializar cuando el DOM esté listo
document.addEventListener('DOMContentLoaded', function() {
    console.log('🚀 Inicializando FiltrosManager...');
    
    // Detectar cuál tabla está presente y usarla
    let tableId = 'tablaTareas';
    if (document.getElementById('tablaObjetivos')) {
        tableId = 'tablaObjetivos';
    } else if (document.getElementById('tablaConfirmacion')) {
        tableId = 'tablaConfirmacion';
    }
    
    window.filtrosManager = new FiltrosManager(tableId);
    console.log(`✅ FiltrosManager iniciado para tabla: ${tableId}`);
});

console.log('✅ filtros-manager.js cargado completamente');
