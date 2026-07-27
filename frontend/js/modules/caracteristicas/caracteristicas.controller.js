/**
 * Controlador de Características
 * Gestiona la lógica del módulo de características
 */
class CaracteristicasController {
    constructor() {
        this.service = new CaracteristicasService();
        this.caracteristicas = [];
        this.tiposDatos = [];
        this.modal = null;
        this.dataTableInstance = null; // Instancia de DataTable
        
        this.init();
    }

    async init() {
        // Validar permiso de creación
        AppSecurity.aplicarPermisoCrear('btnNuevaCaracteristica');

        // Cargar tipos de datos
        await this.cargarTiposDatos();
        
        // Cargar características
        await this.cargarCaracteristicas();
    }

    async cargarTiposDatos() {
        try {
            console.log('🔍 [CARACTERISTICAS] Iniciando cargarTiposDatos...');
            const response = await this.service.obtenerTiposDatos();
            console.log('📡 [CARACTERISTICAS] Respuesta tipos de datos:', response);
            
            if (response && response.success && response.data) {
                this.tiposDatos = response.data;
                console.log('✅ [CARACTERISTICAS] Tipos de datos cargados:', this.tiposDatos);
                this.poblarSelectorTipos();
            } else {
                console.error('❌ [CARACTERISTICAS] Respuesta inválida:', response);
            }
        } catch (error) {
            console.error('💥 [CARACTERISTICAS] Error al cargar tipos de datos:', error);
        }
    }

    poblarSelectorTipos() {
        // Este método se puede utilizar si hay un selector de tipos en la página
        // Por ahora, los tipos se cargan en memoria para usar en los formularios
        console.log('✅ [CARACTERISTICAS] Tipos de datos listos para formularios');
    }

    async cargarCaracteristicas() {
        try {
            console.log('🔍 [CARACTERISTICAS] Iniciando cargarCaracteristicas...');
            const response = await this.service.listar();
            console.log('📡 [CARACTERISTICAS] Respuesta de listado:', response);
            
            if (response.success) {
                this.caracteristicas = response.data || [];
                console.log('✅ [CARACTERISTICAS] Características cargadas:', this.caracteristicas);
                console.log('📊 [CARACTERISTICAS] Total características:', this.caracteristicas.length);
                this.renderTabla();
            } else {
                console.error('❌ [CARACTERISTICAS] Error en respuesta:', response.message);
                Notification.error(response.message || 'Error al cargar características');
            }
        } catch (error) {
            console.error('💥 [CARACTERISTICAS] Error de conexión:', error);
            Notification.error('Error de conexión');
        }
    }

    renderTabla() {
        const tbody = document.getElementById('tableBody');
        console.log('🎨 [CARACTERISTICAS] renderTabla - tbody encontrado:', !!tbody);
        console.log('🎨 [CARACTERISTICAS] renderTabla - características:', this.caracteristicas.length);
        
        if (!tbody) {
            console.error('❌ [CARACTERISTICAS] Elemento tableBody no encontrado');
            return;
        }

        // Destruir DataTable existente si existe
        if (this.dataTableInstance) {
            this.dataTableInstance.destroy();
            this.dataTableInstance = null;
        }

        if (this.caracteristicas.length === 0) {
            const table = document.getElementById('caracteristicasTable');
            const emptyMsg = document.getElementById('emptyMessage');
            if (table) table.style.display = 'none';
            if (emptyMsg) emptyMsg.style.display = 'block';
            console.log('ℹ️ [CARACTERISTICAS] Sin datos - mostrando mensaje vacío');
            return;
        }

        const table = document.getElementById('caracteristicasTable');
        const emptyMsg = document.getElementById('emptyMessage');
        if (table) table.style.display = 'table';
        if (emptyMsg) emptyMsg.style.display = 'none';

        tbody.innerHTML = this.caracteristicas.map((carac, index) => `
            <tr class="hover:bg-gray-50 transition">
                <td class="text-center text-sm px-4 py-3 text-gray-900 font-medium">${index + 1}</td>
                <td class="text-sm px-4 py-3 text-gray-900 font-medium">${carac.nombre || '-'}</td>
                <td class="text-sm px-4 py-3 text-gray-700">${carac.tipo_dato || '-'}</td>
                <td class="text-sm px-4 py-3 text-gray-700">${carac.listado_opciones ? carac.listado_opciones.substring(0, 50) + '...' : '-'}</td>
                <td class="text-sm px-4 py-3 text-gray-600">${carac.usuario_crea || '-'}</td>
                <td class="text-center px-4 py-3">
                    ${AppSecurity.filtrarBotonesTabla(`
                        <div class="flex gap-2 justify-center">
                            <button 
                                class="btn-edit bg-yellow-500 hover:bg-yellow-600 text-white px-3 py-1 rounded-lg text-sm transition"
                                data-perm="edit"
                                data-id="${carac.id}"
                                title="Editar">
                                <i class="fas fa-edit"></i>
                            </button>
                            <button 
                                class="btn-delete bg-red-500 hover:bg-red-600 text-white px-3 py-1 rounded-lg text-sm transition"
                                data-perm="delete"
                                data-id="${carac.id}"
                                title="Eliminar">
                                <i class="fas fa-trash"></i>
                            </button>
                        </div>
                    `)}
                </td>
            </tr>
        `).join('');

        console.log('✅ [CARACTERISTICAS] Tabla renderizada con', this.caracteristicas.length, 'filas');
        this.attachEvents();
        this.initDataTable();
    }

    attachEvents() {
        // Botones de editar
        const editButtons = document.querySelectorAll('.btn-edit');
        editButtons.forEach(btn => {
            btn.addEventListener('click', () => {
                const id = btn.dataset.id;
                this.editarCaracteristica(id);
            });
        });

        // Botones de eliminar
        const deleteButtons = document.querySelectorAll('.btn-delete');
        deleteButtons.forEach(btn => {
            btn.addEventListener('click', () => {
                const id = btn.dataset.id;
                this.eliminarCaracteristica(id);
            });
        });
    }

    mostrarFormularioNuevo() {
        if (this.modal) {
            this.modal.destroy();
            this.modal = null;
        }

        const modalOverlays = document.querySelectorAll('.modal-overlay');
        modalOverlays.forEach(overlay => {
            if (overlay && overlay.parentElement) {
                overlay.remove();
            }
        });

        document.body.style.overflow = '';

        this.modal = new Modal({
            title: '<i class="fas fa-plus text-green-600 mr-2"></i>Nueva Característica',
            content: '<div id="formContainer"></div>',
            size: 'large',
            onClose: () => {
                this.modal = null;
            }
        });

        this.modal.open();

        setTimeout(() => {
            const container = this.modal?.container?.querySelector('#formContainer');
            if (container) {
                container.innerHTML = this.getFormHTML(null);
                this.attachFormEvents(null);
            }
        }, 0);
    }

    async editarCaracteristica(id) {
        try {
            const response = await this.service.obtener(id);
            
            if (!response.success) {
                Notification.error('Característica no encontrada');
                return;
            }

            const caracteristica = response.data;

            if (this.modal) {
                this.modal.destroy();
                this.modal = null;
            }

            const modalOverlays = document.querySelectorAll('.modal-overlay');
            modalOverlays.forEach(overlay => {
                if (overlay && overlay.parentElement) {
                    overlay.remove();
                }
            });

            document.body.style.overflow = '';

            this.modal = new Modal({
                title: `<i class="fas fa-edit text-yellow-600 mr-2"></i>Editar Característica: ${caracteristica.nombre}`,
                content: '<div id="formContainer"></div>',
                size: 'large',
                onClose: () => {
                    this.modal = null;
                }
            });

            this.modal.open();

            setTimeout(() => {
                const container = this.modal?.container?.querySelector('#formContainer');
                if (container) {
                    container.innerHTML = this.getFormHTML(caracteristica);
                    this.attachFormEvents(caracteristica.id);
                }
            }, 0);
        } catch (error) {
            console.error('Error:', error);
            Notification.error('Error al cargar característica');
        }
    }

    getFormHTML(caracteristica = null) {
        const isEdit = caracteristica !== null;
        
        // Si tiene opciones, es un Selector con tipo de listado definido
        const tieneOpciones = !!(caracteristica?.listado_opciones && caracteristica?.listado_opciones.trim());
        const tipoSelectorInicial = tieneOpciones ? 'Selector' : (caracteristica?.tipo_dato || '');
        const tipoListadoInicial = tieneOpciones ? (caracteristica?.tipo_dato || '') : '';

        return `
            <form id="caracteristicaForm" class="space-y-4">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-bold text-gray-700 mb-1">
                            <i class="fas fa-tag text-purple-600 mr-2"></i>Nombre *
                        </label>
                        <input 
                            type="text" 
                            name="nombre"
                            value="${caracteristica?.nombre || ''}"
                            required
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500"
                            placeholder="Ej: Tipo de Comedero">
                    </div>

                    <div>
                        <label class="block text-sm font-bold text-gray-700 mb-1">
                            <i class="fas fa-database text-purple-600 mr-2"></i>Tipo de Dato *
                        </label>
                        <select 
                            id="tipoDatoSelect"
                            name="tipo_dato"
                            required
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500">
                            <option value="">Seleccionar...</option>
                            <option value="Selector" ${tipoSelectorInicial === 'Selector' ? 'selected' : ''}>Selector</option>
                            ${this.tiposDatos
                                .filter(tipo => tipo !== 'Selector')
                                .map(tipo => `
                                    <option value="${tipo}" ${tipoSelectorInicial === tipo ? 'selected' : ''}>${tipo}</option>
                                `).join('')}
                        </select>
                    </div>
                </div>

                <!-- Opciones para Selector -->
                <div id="opcionesContainer" class="hidden space-y-3">
                    <div>
                        <label class="block text-sm font-bold text-gray-700 mb-1">
                            <i class="fas fa-sliders-h text-purple-600 mr-2"></i>Tipo de listado *
                        </label>
                        <select
                            id="tipoListadoSelect"
                            name="tipo_listado"
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500">
                            <option value="">Seleccionar...</option>
                            <option value="Texto" ${tipoListadoInicial === 'Texto' ? 'selected' : ''}>Texto</option>
                            <option value="Número" ${tipoListadoInicial === 'Número' ? 'selected' : ''}>Número</option>
                            <option value="Decimal" ${tipoListadoInicial === 'Decimal' ? 'selected' : ''}>Decimal</option>
                        </select>
                        <small class="text-gray-500">El tipo de dato se guardará como este tipo de listado.</small>
                    </div>
                    <label class="block text-sm font-bold text-gray-700 mb-1">
                        <i class="fas fa-list text-purple-600 mr-2"></i>Opciones (para Selector) *
                    </label>
                    <textarea 
                        id="listadoOpcionesField"
                        name="listado_opciones"
                        rows="3"
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500"
                        placeholder="Separar opciones con coma: Opción1, Opción2, Opción3"
                    >${caracteristica?.listado_opciones || ''}</textarea>
                    <small class="text-gray-500">Ejemplo: Automático - CASP, Automático - HUALI, Convencional</small>
                </div>

                <!-- Ayuda dinámica según tipo -->
                <div id="tipoAyuda" class="bg-blue-50 border-l-4 border-blue-500 p-3 rounded text-sm text-blue-700 hidden">
                    <i class="fas fa-info-circle mr-2"></i>
                    <span id="tipoAyudaTexto"></span>
                </div>

                <div class="flex gap-2 justify-end border-t pt-4">
                    <button 
                        type="button" 
                        onclick="window.caracteristicasController.modal.close()"
                        class="px-4 py-2 bg-gray-300 text-gray-700 rounded-lg hover:bg-gray-400">
                        Cancelar
                    </button>
                    <button 
                        type="submit"
                        class="px-4 py-2 bg-purple-500 text-white rounded-lg hover:bg-purple-600">
                        ${isEdit ? 'Actualizar' : 'Crear'} Característica
                    </button>
                </div>
            </form>
        `;
    }

    attachFormEvents(caracteristicaId = null) {
        const form = document.getElementById('caracteristicaForm');
        const tipoDatoSelect = document.getElementById('tipoDatoSelect');
        const opcionesContainer = document.getElementById('opcionesContainer');
        const listadoOpcionesField = document.getElementById('listadoOpcionesField');
        const tipoListadoSelect = document.getElementById('tipoListadoSelect');
        const tipoAyuda = document.getElementById('tipoAyuda');
        const tipoAyudaTexto = document.getElementById('tipoAyudaTexto');

        // Mensajes de ayuda según tipo
        const mensajesAyuda = {
            'Texto': '✏️ Puedes dejar las opciones vacías para este tipo',
            'Número': '🔢 Ingresa números enteros (sin decimales)',
            'Decimal': '📊 Ingresa números con decimales (ej: 3.5)',
            'Fecha': '📅 Selecciona una fecha válida',
            'Selector': '⚠️ Las opciones son REQUERIDAS y el tipo se define por el listado'
        };

        // Función para actualizar UI según tipo de dato
        const actualizarPorTipo = () => {
            const tipoDato = tipoDatoSelect.value;
            
            // Mostrar/ocultar opciones
            if (tipoDato === 'Selector') {
                opcionesContainer.classList.remove('hidden');
                listadoOpcionesField.required = true;
                tipoListadoSelect.required = true;
            } else {
                opcionesContainer.classList.add('hidden');
                listadoOpcionesField.required = false;
                tipoListadoSelect.required = false;
                listadoOpcionesField.value = '';
                tipoListadoSelect.value = '';
            }

            // Mostrar ayuda
            if (tipoDato && mensajesAyuda[tipoDato]) {
                tipoAyudaTexto.textContent = mensajesAyuda[tipoDato];
                tipoAyuda.classList.remove('hidden');
            } else {
                tipoAyuda.classList.add('hidden');
            }
        };

        // Event listener para cambios en tipo de dato
        tipoDatoSelect.addEventListener('change', actualizarPorTipo);

        // Inicializar en caso de edición
        actualizarPorTipo();

        // Event listener para submit
        form.addEventListener('submit', async (e) => {
            e.preventDefault();
            
            const formData = new FormData(form);
            const tipoDato = formData.get('tipo_dato');
            const nombre = formData.get('nombre');
            const listadoOpciones = formData.get('listado_opciones');
            const tipoListado = formData.get('tipo_listado');

            // Validación por tipo de dato
            if (!nombre.trim()) {
                Notification.error('El nombre es requerido');
                return;
            }

            if (!tipoDato) {
                Notification.error('Debes seleccionar un tipo de dato');
                return;
            }

            // Validación específica por tipo
            if (tipoDato === 'Selector') {
                if (!tipoListado) {
                    Notification.error('Debes seleccionar el tipo de listado');
                    return;
                }
                if (!listadoOpciones?.trim()) {
                    Notification.error('Las opciones son requeridas para tipo Selector');
                    return;
                }
            }

            if (tipoDato === 'Número' && nombre && isNaN(parseInt(nombre))) {
                // Solo validar si hay contenido - para crear es el nombre el que se valida
                // En realidad, el nombre es la característica, no el valor
            }

            const data = {
                nombre: nombre,
                tipo_dato: tipoDato === 'Selector' ? tipoListado : tipoDato,
                listado_opciones: tipoDato === 'Selector' ? listadoOpciones : null
            };

            console.log('📤 [CARACTERISTICAS] Enviando datos:', data);
            console.log('📤 [CARACTERISTICAS] Operación:', caracteristicaId ? 'Actualizar' : 'Crear');

            try {
                let response;
                if (caracteristicaId) {
                    response = await this.service.actualizar(caracteristicaId, data);
                } else {
                    response = await this.service.crear(data);
                }

                console.log('📥 [CARACTERISTICAS] Respuesta del servidor:', response);

                if (response.success) {
                    const message = caracteristicaId 
                        ? 'Característica actualizada correctamente' 
                        : 'Característica creada correctamente';
                    Notification.success(message);
                    
                    if (this.modal) {
                        this.modal.destroy();
                        this.modal = null;
                    }
                    
                    await this.cargarCaracteristicas();
                } else {
                    Notification.error(response.message || 'Error al guardar');
                }
            } catch (error) {
                console.error('💥 [CARACTERISTICAS] Error:', error);
                Notification.error('Error de conexión');
            }
        });
    }

    async eliminarCaracteristica(id) {
        try {
            // Primero verificar si está en uso
            const verificarResponse = await this.service.verificarUso(id);
            
            if (!verificarResponse.success) {
                window.SwalHelpers.showError(verificarResponse.message || 'Error al verificar uso de característica');
                return;
            }

            const { en_uso, count } = verificarResponse.data;
            
            if (en_uso) {
                // Modal especial con advertencia y botón para eliminar forzadamente
                const result = await Swal.fire({
                    icon: 'warning',
                    title: '⚠️ Característica en Uso',
                    html: `
                        <div class="text-left">
                            <p class="mb-3">Esta característica está siendo utilizada en <strong>${count}</strong> ${count === 1 ? 'galpón' : 'galpones'}.</p>
                            <div class="bg-red-50 border border-red-200 rounded-lg p-3 mb-3">
                                <p class="text-red-700 font-semibold mb-2">⚠️ ADVERTENCIA:</p>
                                <p class="text-red-600 text-sm">Si eliminas esta característica, se perderán TODOS los datos asociados de los galpones que la utilizan.</p>
                            </div>
                            <p class="text-gray-700 text-sm">¿Qué deseas hacer?</p>
                        </div>
                    `,
                    showDenyButton: true,
                    confirmButtonText: '<i class="fas fa-trash"></i> Eliminar Todo',
                    confirmButtonColor: '#dc2626',
                    denyButtonText: '<i class="fas fa-times"></i> Cancelar',
                    denyButtonColor: '#6b7280',
                    reverseButtons: true,
                    customClass: {
                        popup: 'text-left'
                    }
                });
                
                if (result.isDenied) {
                    // Usuario canceló
                    return;
                }
                
                if (result.isConfirmed) {
                    // Usuario confirmó eliminación forzada
                    const confirmarForzado = await window.SwalHelpers.showConfirm(
                        '⚠️ Confirmación Final',
                        `¿Estás COMPLETAMENTE SEGURO? Se eliminarán ${count} ${count === 1 ? 'registro' : 'registros'} de datos.`
                    );
                    
                    if (!confirmarForzado) return;
                    
                    // Eliminar forzadamente
                    const deleteResponse = await this.service.eliminar(id, true);
                    
                    if (deleteResponse.success) {
                        window.SwalHelpers.showSuccess('Característica y sus datos eliminados correctamente');
                        await this.cargarCaracteristicas();
                    } else {
                        window.SwalHelpers.showError(deleteResponse.message || 'Error al eliminar característica');
                    }
                }
            } else {
                // No está en uso, eliminación normal
                const confirmado = await window.SwalHelpers.showConfirm(
                    '¿Eliminar esta característica?',
                    'Esta acción no se puede deshacer.'
                );

                if (!confirmado) return;

                const response = await this.service.eliminar(id, false);
                
                if (response.success) {
                    window.SwalHelpers.showSuccess('Característica eliminada correctamente');
                    await this.cargarCaracteristicas();
                } else {
                    window.SwalHelpers.showError(response.message || 'No se pudo eliminar esta característica');
                }
            }
        } catch (error) {
            console.error('Error:', error);
            window.SwalHelpers.showError('Error de conexión al eliminar característica');
        }
    }

    aplicarFiltros(searchTerm = '', tipo = '') {
        if (!searchTerm && !tipo) {
            this.renderTabla();
            return;
        }

        const filtered = this.caracteristicas.filter(carac => {
            const matchNombre = !searchTerm || 
                carac.nombre.toLowerCase().includes(searchTerm.toLowerCase()) ||
                carac.tipo_dato.toLowerCase().includes(searchTerm.toLowerCase());
            
            const matchTipo = !tipo || carac.tipo_dato === tipo;
            
            return matchNombre && matchTipo;
        });

        const tbody = document.getElementById('tableBody');
        if (!tbody) return;

        if (filtered.length === 0) {
            const table = document.getElementById('caracteristicasTable');
            const emptyMsg = document.getElementById('emptyMessage');
            if (table) table.style.display = 'none';
            if (emptyMsg) {
                emptyMsg.style.display = 'block';
                emptyMsg.innerHTML = `
                    <i class="fas fa-cogs text-6xl text-gray-300 mb-4"></i>
                    <p class="text-gray-500 text-lg">No hay características que coincidan con los filtros</p>
                `;
            }
            return;
        }

        const table = document.getElementById('caracteristicasTable');
        const emptyMsg = document.getElementById('emptyMessage');
        if (table) table.style.display = 'table';
        if (emptyMsg) emptyMsg.style.display = 'none';

        tbody.innerHTML = filtered.map((carac, index) => `
            <tr class="hover:bg-gray-50 transition">
                <td class="text-center text-sm px-4 py-3 text-gray-900 font-medium">${index + 1}</td>
                <td class="text-sm px-4 py-3 text-gray-900 font-medium">${carac.nombre || '-'}</td>
                <td class="text-sm px-4 py-3 text-gray-700">${carac.tipo_dato || '-'}</td>
                <td class="text-sm px-4 py-3 text-gray-700">${carac.listado_opciones ? carac.listado_opciones.substring(0, 50) + '...' : '-'}</td>
                <td class="text-sm px-4 py-3 text-gray-600">${carac.usuario_crea || '-'}</td>
                <td class="text-center px-4 py-3">
                    ${AppSecurity.filtrarBotonesTabla(`
                        <div class="flex gap-2 justify-center">
                            <button 
                                class="btn-edit bg-yellow-500 hover:bg-yellow-600 text-white px-3 py-1 rounded-lg text-sm transition"
                                data-perm="edit"
                                data-id="${carac.id}"
                                title="Editar">
                                <i class="fas fa-edit"></i>
                            </button>
                            <button 
                                class="btn-delete bg-red-500 hover:bg-red-600 text-white px-3 py-1 rounded-lg text-sm transition"
                                data-perm="delete"
                                data-id="${carac.id}"
                                title="Eliminar">
                                <i class="fas fa-trash"></i>
                            </button>
                        </div>
                    `)}
                </td>
            </tr>
        `).join('');

        this.attachEvents();
    }

    filtrar(searchTerm) {
        this.aplicarFiltros(searchTerm, '');
    }

    /**
     * Inicializa DataTable con configuración optimizada
     */
    initDataTable() {
        if (!window.jQuery) {
            console.warn('jQuery no disponible, no se puede inicializar DataTable');
            return;
        }

        const table = document.getElementById('caracteristicasTable');
        if (!table) return;

        try {
            this.dataTableInstance = $(table).DataTable({
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
            console.log('✅ DataTable inicializado para características');
        } catch (error) {
            console.error('Error al inicializar DataTable:', error);
        }
    }
}
