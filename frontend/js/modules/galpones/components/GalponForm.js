/**
 * Componente de Formulario de Galpón
 * Maneja la creación y edición de galpones
 */
class GalponForm extends Component {
    constructor(selector, options = {}) {
        super(selector);
        this.galpon = options.galpon || null;
        this.caracteristicas = options.caracteristicas || [];
        this.granjas = options.granjas || []; // Array de granjas disponibles
        this.galponesDisponibles = options.galponesDisponibles || []; // Galpones de la granja seleccionada
        this.onGranjaChange = options.onGranjaChange || null; // Callback al cambiar granja
        this.onSubmit = options.onSubmit || null;
        this.onCancel = options.onCancel || null;
        this.mode = options.mode || (this.galpon ? 'edit' : 'create');
        
        // Opciones locales para comboboxes
        this.opcionesCaracteristicas = {
            1: ['La Joya', 'Mollendo'], // Zona
            4: ['Automático - CASP', 'Automático - HUALI', 'Automático - Plasson', 'Convencional'], // Tipo de Comedero
            5: ['Campaña', 'Niple - HUALI', 'Niple - LUBBING', 'Niple - PLASSON', 'Niple - VALL'] // Tipo de Bebedero
        };
    }

    render() {
        if (!this.container) return;

        const title = this.mode === 'edit' ? 'Editar Galpón' : 'Nuevo Galpón';
        const submitText = this.mode === 'edit' ? 'Actualizar' : 'Crear';

        this.container.innerHTML = `
            <form id="galponForm">
                <!-- Información Básica -->
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    ${this.mode === 'edit' ? `
                    <div>
                        <label class="block text-sm font-bold text-gray-700 mb-1">
                            <i class="fas fa-hashtag text-purple-600 mr-2"></i>
                            ID Galpón
                        </label>
                        <input 
                            type="text" 
                            name="id_galpon" 
                            value="${this.galpon?.id || ''}"
                            readonly
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg bg-gray-100 text-gray-600"
                            placeholder="Auto-generado">
                    </div>
                    ` : ''}

                    <div>
                        <label class="block text-sm font-bold text-gray-700 mb-1">
                            <i class="fas fa-home text-purple-600 mr-2"></i>
                            Granja *
                        </label>
                        ${this.mode === 'edit' ? `
                        <input 
                            type="text" 
                            name="granja" 
                            value="${this.granjas.find(g => g.id == this.galpon?.granja)?.nombre || 'No especificada'}"
                            readonly
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg bg-gray-100 text-gray-600"
                            placeholder="Granja">
                        <input type="hidden" name="granja_id" value="${this.galpon?.granja || ''}">
                        ` : `
                        <select 
                            name="granja" 
                            required
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent transition-all">
                            <option value="">Seleccionar granja...</option>
                            ${(() => {
                                console.log('=== RENDERIZANDO GRANJAS EN FORMULARIO ===');
                                console.log('Total granjas para formulario:', this.granjas.length);
                                console.log('Primeras 3 granjas:', this.granjas.slice(0, 3));
                                return this.granjas.map(granja => {
                                    console.log('Granja:', granja.id, '-', granja.nombre);
                                    return `<option value="${granja.id}">${granja.nombre}</option>`;
                                }).join('');
                            })()}
                        </select>
                        `}
                    </div>

                    <div>
                        <label class="block text-sm font-bold text-gray-700 mb-1">
                            <i class="fas fa-warehouse text-purple-600 mr-2"></i>
                            Número de Galpón *
                        </label>
                        ${this.mode === 'edit' ? `
                        <input 
                            type="number" 
                            name="galpon" 
                            value="${this.galpon?.galpon || ''}"
                            required
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent transition-all"
                            placeholder="Ej: 1, 2, 3...">
                        ` : `
                        <select 
                            id="galponSelect"
                            name="galpon" 
                            required
                            ${this.galponesDisponibles.length === 0 ? 'disabled' : ''}
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent transition-all">
                            <option value="">Primero selecciona una granja...</option>
                            ${this.galponesDisponibles.map(g => `
                                <option value="${g.numero}">${g.numero}</option>
                            `).join('')}
                        </select>
                        `}
                    </div>

                    <!--<div class="md:col-span-3">
                        <label class="block text-sm font-bold text-gray-700 mb-1">
                            <i class="fas fa-building text-purple-600 mr-2"></i>
                            Nombre del Galpón *
                        </label>
                        <input 
                            type="text" 
                            name="nombre" 
                            value="${this.galpon?.nombre || ''}"
                            required
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent transition-all"
                            placeholder="Nombre descriptivo del galpón">
                    </div>-->
                </div>

                <!-- Características Dinámicas -->
                <div class="mt-6 border-t pt-6">
                    <h4 class="text-lg font-semibold text-gray-700 mb-4">
                        <i class="fas fa-list text-purple-600 mr-2"></i>
                        Características del Galpón
                    </h4>
                    <div id="caracteristicasContainer" class="grid grid-cols-1 md:grid-cols-1 lg:grid-cols-1 gap-5">
                        ${this.renderCaracteristicas()}
                    </div>
                </div>

                <!-- Botones -->
                <div class="flex justify-end gap-3 mt-6">
                    <button 
                        type="button" 
                        id="btnCancelar"
                        class="bg-gray-500 hover:bg-gray-600 text-white font-semibold py-2 px-6 rounded-lg transition">
                        Cancelar
                    </button>
                    <button 
                        type="submit"
                        class="bg-purple-500 hover:bg-purple-600 text-white font-semibold py-2 px-6 rounded-lg transition">
                        <i class="fas fa-save mr-2"></i>
                        ${submitText}
                    </button>
                </div>
            </form>
        `;

        this.attachEvents();
    }

    renderCaracteristicas() {
        if (!this.caracteristicas.length) {
            return '<p class="text-gray-500 col-span-full">Cargando características...</p>';
        }

        // Agrupar características por categoría/tipo
        const grupos = {
            'Datos Generales': [],
            'Dimensiones': [],
            'Condiciones Ambientales': [],
            'Equipamiento': [],
            'Otras': []
        };

        this.caracteristicas.forEach(carac => {
            const nombre = carac.nombre.toLowerCase();
            
            // Clasificar por nombre de característica
            if (nombre.includes('zona') || nombre.includes('subzona') || nombre.includes('encargado') || 
                nombre.includes('veterinario') || nombre.includes('comedero') || nombre.includes('bebedero') || 
                nombre.includes('niveles')) {
                grupos['Datos Generales'].push(carac);
            } else if (nombre.includes('largo') || nombre.includes('ancho') || nombre.includes('area') || 
                       nombre.includes('altura') || nombre.includes('paño')) {
                grupos['Dimensiones'].push(carac);
            } else if (nombre.includes('temperatura') || nombre.includes('humedad') || nombre.includes('desnivel') || 
                       nombre.includes('piso') || nombre.includes('microclima')) {
                grupos['Condiciones Ambientales'].push(carac);
            } else if (nombre.includes('muro') || nombre.includes('precio') || nombre.includes('renzo') || 
                       nombre.includes('aaaa')) {
                grupos['Equipamiento'].push(carac);
            } else {
                grupos['Otras'].push(carac);
            }
        });

        // Generar HTML con pestañas
        let html = '<div class="col-span-full">';
        
        // Tabs navigation
        html += '<div class="border-b border-gray-200 mb-4">';
        html += '<nav class="-mb-px flex gap-2 overflow-x-auto">';
        
        let tabIndex = 0;
        for (const [categoria, items] of Object.entries(grupos)) {
            if (items.length > 0) {
                const isActive = tabIndex === 0;
                html += `
                    <button type="button" 
                        class="tab-button whitespace-nowrap py-2 px-4 border-b-2 font-medium text-sm transition ${
                            isActive 
                                ? 'border-purple-500 text-purple-600' 
                                : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'
                        }"
                        data-tab="${categoria}">
                        ${categoria} (${items.length})
                    </button>
                `;
                tabIndex++;
            }
        }
        
        html += '</nav></div>';
        
        // Tabs content
        html += '<div class="tab-content-container">';
        
        tabIndex = 0;
        for (const [categoria, items] of Object.entries(grupos)) {
            if (items.length > 0) {
                const isActive = tabIndex === 0;
                html += `
                    <div class="tab-content grid grid-cols-1 md:grid-cols-3 gap-4 ${isActive ? '' : 'hidden'}" 
                         data-content="${categoria}">
                `;
                
                items.forEach(carac => {
                    html += this.renderCampoCaracteristica(carac);
                });
                
                html += '</div>';
                tabIndex++;
            }
        }
        
        html += '</div></div>';
        
        return html;
    }

    renderCampoCaracteristica(carac) {
        const valor = this.galpon ? this.galpon[carac.id] || '' : '';
        
        // Si la característica tiene opciones locales definidas, renderizar como SELECT
        if (this.opcionesCaracteristicas[carac.id]) {
            const opciones = this.opcionesCaracteristicas[carac.id];
            
            return `
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">
                        ${carac.nombre}
                    </label>
                    <select 
                        name="caracteristica_${carac.id}" 
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent transition-all">
                        <option value="">Seleccionar...</option>
                        ${opciones.map(opcion => `
                            <option value="${opcion}" ${valor === opcion ? 'selected' : ''}>
                                ${opcion}
                            </option>
                        `).join('')}
                    </select>
                </div>
            `;
        }
        
        // Si es de tipo "Número", renderizar como INPUT number
        if (carac.tipo_dato === 'Número' || carac.tipo_dato === 'Decimal') {
            return `
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">
                        ${carac.nombre}
                    </label>
                    <input 
                        type="number" 
                        name="caracteristica_${carac.id}" 
                        value="${valor}"
                        step="0.01"
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent transition-all"
                        placeholder="${carac.nombre}">
                </div>
            `;
        }
        
        // Por defecto, renderizar como INPUT text
        return `
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">
                    ${carac.nombre}
                </label>
                <input 
                    type="text" 
                    name="caracteristica_${carac.id}" 
                    value="${valor}"
                    class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent transition-all"
                    placeholder="${carac.nombre}">
            </div>
        `;
    }

    attachTabEvents() {
        const tabButtons = this.container?.querySelectorAll('.tab-button');
        const tabContents = this.container?.querySelectorAll('.tab-content');
        
        if (!tabButtons || !tabContents) return;
        
        tabButtons.forEach(button => {
            const handler = () => {
                const targetTab = button.dataset.tab;
                
                // Actualizar botones
                tabButtons.forEach(btn => {
                    if (btn.dataset.tab === targetTab) {
                        btn.classList.remove('border-transparent', 'text-gray-500');
                        btn.classList.add('border-purple-500', 'text-purple-600');
                    } else {
                        btn.classList.remove('border-purple-500', 'text-purple-600');
                        btn.classList.add('border-transparent', 'text-gray-500');
                    }
                });
                
                // Actualizar contenidos
                tabContents.forEach(content => {
                    if (content.dataset.content === targetTab) {
                        content.classList.remove('hidden');
                    } else {
                        content.classList.add('hidden');
                    }
                });
            };
            
            // Usar this.addEventListener para registrar el listener para limpieza
            this.addEventListener(button, 'click', handler);
        });
    }

    attachEvents() {
        // Adjuntar eventos de las tabs primero
        this.attachTabEvents();
        
        const form = this.container.querySelector('#galponForm');
        const btnCancelar = this.container.querySelector('#btnCancelar');
        const selectGranja = this.container.querySelector('select[name="granja"]');
        const selectGalpon = this.container.querySelector('#galponSelect');

        if (form) {
            this.addEventListener(form, 'submit', (e) => {
                e.preventDefault();
                this.handleSubmit(form);
            });
        }

        if (btnCancelar && this.onCancel) {
            this.addEventListener(btnCancelar, 'click', () => {
                this.onCancel();
            });
        }

        // Evento cuando cambia la granja seleccionada
        if (selectGranja && this.onGranjaChange) {
            this.addEventListener(selectGranja, 'change', (e) => {
                const idGranja = e.target.value;
                if (idGranja) {
                    this.onGranjaChange(idGranja);
                } else {
                    // Limpiar el combo de galpones si no hay granja seleccionada
                    this.galponesDisponibles = [];
                    this.actualizarComboGalpones();
                }
            });
        }
    }

    /**
     * Actualiza el combobox de galpones con los datos cargados
     */
    actualizarComboGalpones() {
        const selectGalpon = this.container?.querySelector('#galponSelect');
        if (!selectGalpon) return;

        // Limpiar opciones actuales
        selectGalpon.innerHTML = '<option value="">Seleccione un galpón...</option>';

        // Si hay galpones disponibles, agregarlos
        if (this.galponesDisponibles && this.galponesDisponibles.length > 0) {
            this.galponesDisponibles.forEach(g => {
                const option = document.createElement('option');
                option.value = g.numero;
                option.textContent = g.numero;
                selectGalpon.appendChild(option);
            });
            selectGalpon.disabled = false;
        } else {
            // Si no hay galpones, mostrar mensaje y deshabilitar
            selectGalpon.innerHTML = '<option value="">No hay galpones disponibles</option>';
            selectGalpon.disabled = true;
        }
    }

    handleSubmit(form) {
        const formData = new FormData(form);
        const data = {
            granja: parseInt(formData.get('granja')),
            galpon: parseInt(formData.get('galpon')),
            nombre: formData.get('nombre'),
            caracteristicas: []
        };

        // Recoger características
        this.caracteristicas.forEach(carac => {
            const valor = formData.get(`caracteristica_${carac.id}`);
            if (valor && valor.trim()) {
                data.caracteristicas.push({
                    id_caracteristica: carac.id,
                    valor: valor.trim()
                });
            }
        });

        if (this.onSubmit) {
            this.onSubmit(data);
        }
    }

    updateCaracteristicas(caracteristicas) {
        this.caracteristicas = caracteristicas;
        const container = this.container?.querySelector('#caracteristicasContainer');
        if (container) {
            container.innerHTML = this.renderCaracteristicas();
        }
    }
}
