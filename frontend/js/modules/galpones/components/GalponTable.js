/**
 * Componente de Tabla de Galpones
 * Muestra el listado de galpones con acciones
 */
class GalponTable extends Component {
    constructor(selector, options = {}) {
        super(selector);
        this.table = document.getElementById('galponesTable');
        this.headerRow = document.getElementById('headerRow');
        this.tableBody = document.getElementById('tableBody');
        this.emptyMessage = document.getElementById('emptyMessage');
        
        this.galpones = options.galpones || [];
        this.caracteristicas = options.caracteristicas || [];
        this.onEdit = options.onEdit || null;
        this.onDelete = options.onDelete || null;
        this.onView = options.onView || null;
        
        this.headersRendered = false;
        this.dataTableInstance = null; // Instancia de DataTable
    }

    render() {
        // Reasignar elementos en caso de que el DOM cambió
        this.table = document.getElementById('galponesTable');
        this.tableBody = document.getElementById('tableBody');
        this.emptyMessage = document.getElementById('emptyMessage');
        this.headerRow = document.getElementById('headerRow');

        if (!this.table || !this.tableBody) {
            console.error('Elementos de tabla no encontrados');
            return;
        }

        // Destruir DataTable existente si existe
        if (this.dataTableInstance) {
            this.dataTableInstance.destroy();
            this.dataTableInstance = null;
        }

        console.log('GalponTable - Galpones:', this.galpones);
        console.log('GalponTable - Características:', this.caracteristicas);

        // Mostrar u ocultar mensaje vacío
        if (!this.galpones.length) {
            this.table.style.display = 'none';
            if (this.emptyMessage) {
                this.emptyMessage.style.display = 'block';
            }
            return;
        }

        this.table.style.display = 'table';
        if (this.emptyMessage) {
            this.emptyMessage.style.display = 'none';
        }

        // Renderizar headers de características si no se han renderizado
        if (!this.headersRendered && this.caracteristicas.length > 0) {
            this.renderCaracteristicasHeaders();
            this.headersRendered = true;
        }

        // Limpiar y llenar tabla
        this.tableBody.innerHTML = this.renderRows();
        this.attachEvents();

        // Inicializar DataTable
        this.initDataTable();
    }

    renderCaracteristicasHeaders() {
        if (!this.headerRow) return;
        
        console.log('🔧 RENDERIZANDO HEADERS:', this.caracteristicas.length, 'características');
        
        // Remover headers de características anteriores
        const existingHeaders = this.headerRow.querySelectorAll('.carac-header');
        existingHeaders.forEach(h => h.remove());
        
        // Insertar nuevos headers antes de "Opciones"
        const opcionesHeader = this.headerRow.querySelector('th:last-child');
        
        this.caracteristicas.forEach(carac => {
            const th = document.createElement('th');
            th.className = 'carac-header px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider';
            th.textContent = carac.nombre;
            this.headerRow.insertBefore(th, opcionesHeader);
        });
        
        console.log('✅ Headers renderizados:', this.headerRow.querySelectorAll('.carac-header').length);
    }

    renderRows() {
        return this.galpones.map((galpon, index) => {
            const galponId = galpon.id || galpon.id_galpon;
            return `
            <tr class="hover:bg-gray-50 transition">
                <td class="text-center text-sm px-4 py-3 text-gray-900 font-medium">
                    ${index + 1}
                </td>
                <td class="text-sm px-4 py-3 text-gray-900 font-medium">
                    Granja ${galpon.granja || '-'}
                </td>
                <td class="text-center text-sm px-4 py-3 text-gray-900 font-medium">
                    ${galpon.galpon || '-'}
                </td>
                <td class="text-sm px-4 py-3 text-gray-900 font-medium">
                    ${galpon.nombre || '-'}
                </td>
                ${this.renderCaracteristicasValues(galpon)}
                <td class="text-center px-4 py-3">
                    ${AppSecurity.filtrarBotonesTabla(`
                        <div class="flex gap-2 justify-center">
                            ${this.onView ? `
                                <button 
                                    class="btn-view bg-blue-500 hover:bg-blue-600 text-white px-3 py-1 rounded-lg text-sm transition btn-hover-scale"
                                    data-id="${galponId}"
                                    title="Ver detalles">
                                    <i class="fas fa-eye"></i>
                                </button>
                            ` : ''}
                            ${this.onEdit ? `
                                <button 
                                    class="btn-edit bg-yellow-500 hover:bg-yellow-600 text-white px-3 py-1 rounded-lg text-sm transition btn-hover-scale"
                                    data-id="${galponId}"
                                    data-perm="edit"
                                    title="Editar">
                                    <i class="fas fa-edit"></i>
                                </button>
                            ` : ''}
                            ${this.onDelete ? `
                                <button 
                                    class="btn-delete bg-red-500 hover:bg-red-600 text-white px-3 py-1 rounded-lg text-sm transition btn-hover-scale"
                                    data-id="${galponId}"
                                    data-perm="delete"
                                    title="Eliminar">
                                    <i class="fas fa-trash"></i>
                                </button>
                            ` : ''}
                        </div>
                    `)}
                </td>
            </tr>
        `;
        }).join('');
    }

    renderCaracteristicasValues(galpon) {
        return this.caracteristicas.map(carac => {
            // El backend pivotea las características usando el ID como clave
            // Intentar ambos tipos: número y string
            let valor = galpon[carac.id] || galpon[String(carac.id)] || '-';
            
            // Formatear valores específicos según el tipo de característica
            valor = this.formatearValor(carac.nombre, valor);
            
            return `
                <td class="text-sm px-4 py-3 text-gray-700">
                    ${valor}
                </td>
            `;
        }).join('');
    }
    
    /**
     * Formatea valores según el tipo de característica
     */
    formatearValor(nombreCaracteristica, valor) {
        if (!valor || valor === '-' || valor === '') {
            return '-';
        }
        
        const nombre = nombreCaracteristica.toLowerCase();
        
        // Campos de medidas (agregar unidades si no las tienen)
        if (nombre.includes('largo') || nombre.includes('ancho') || nombre.includes('altura')) {
            return isNaN(valor) ? valor : `${valor} m`;
        }
        
        if (nombre.includes('area')) {
            return isNaN(valor) ? valor : `${valor} m²`;
        }
        
        if (nombre.includes('desnivel') && nombre.includes('cm')) {
            return isNaN(valor) ? valor : `${valor} cm`;
        }
        
        if (nombre.includes('precio') || nombre.includes('mantenimiento')) {
            return isNaN(valor) ? valor : `S/ ${valor}`;
        }
        
        // Piso de cemento - mostrar valor decimal tal como está (sin unidades)
        if (nombre.includes('piso') && nombre.includes('cemento')) {
            return valor;
        }
        
        // Campos booleanos restantes (solo muro)
        if (nombre.includes('muro') || nombre.includes('perimetrico')) {
            if (valor === '1' || valor === 1 || valor === 'si' || valor === 'SI' || valor === 'Si') {
                return 'SÍ';
            } else if (valor === '0' || valor === 0 || valor === 'no' || valor === 'NO' || valor === 'No') {
                return 'NO';
            }
        }
        
        return valor;
    }

    attachEvents() {
        // Botones de ver
        if (this.onView) {
            const viewButtons = this.tableBody.querySelectorAll('.btn-view');
            console.log('Botones de ver encontrados:', viewButtons.length);
            viewButtons.forEach(btn => {
                btn.addEventListener('click', () => {
                    const id = btn.dataset.id;
                    console.log('Ver galpón con ID:', id);
                    this.onView(id);
                });
            });
        }

        // Botones de editar
        if (this.onEdit) {
            const editButtons = this.tableBody.querySelectorAll('.btn-edit');
            console.log('Botones de editar encontrados:', editButtons.length);
            editButtons.forEach(btn => {
                btn.addEventListener('click', () => {
                    const id = btn.dataset.id;
                    console.log('Editar galpón con ID:', id);
                    this.onEdit(id);
                });
            });
        }

        // Botones de eliminar
        if (this.onDelete) {
            const deleteButtons = this.tableBody.querySelectorAll('.btn-delete');
            console.log('Botones de eliminar encontrados:', deleteButtons.length);
            deleteButtons.forEach(btn => {
                btn.addEventListener('click', () => {
                    const id = btn.dataset.id;
                    console.log('Eliminar galpón con ID:', id);
                    this.onDelete(id);
                });
            });
        }
    }

    updateData(galpones, caracteristicas = null) {
        this.galpones = galpones;
        if (caracteristicas) {
            this.caracteristicas = caracteristicas;
        }
        this.render();
    }

    /**
     * Inicializa DataTable con configuración optimizada
     */
    initDataTable() {
        if (!window.jQuery) {
            console.warn('jQuery no disponible, no se puede inicializar DataTable');
            return;
        }

        try {
            this.dataTableInstance = $(this.table).DataTable({
                paging: true,
                pageLength: 25,
                lengthMenu: [[10, 25, 50, 100, -1], [10, 25, 50, 100, "Todos"]],
                searching: true,
                ordering: true,
                info: true,
                autoWidth: false,
                language: {
                    url: '//cdn.datatables.net/plug-ins/1.13.7/i18n/es-ES.json'
                },
                scrollY: 'calc(100vh - 450px)',
                scrollX: true,
                scrollCollapse: true,
                order: [[0, 'asc']],
                drawCallback: () => {
                    // Re-adjuntar eventos después de cada redibujado
                    this.attachEvents();
                }
            });
            console.log('✅ DataTable inicializado para galpones');
        } catch (error) {
            console.error('Error al inicializar DataTable:', error);
        }
    }
}

