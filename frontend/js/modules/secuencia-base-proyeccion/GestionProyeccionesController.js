/**
 * Controlador de Gestión de Proyecciones
 * Pantalla para crear, listar y eliminar proyecciones
 */
class GestionProyeccionesController {
    constructor() {
        this.service = new SecuenciaBaseProyeccionService();
        this.proyecciones = [];
        this.proyeccionesOriginal = []; // Para filtros
        this.proyeccionSeleccionada = null;
        this.dataTableInstance = null; // Instancia de DataTable

        this.initElements();
        this.initEventListeners();
        this.cargarProyecciones();
    }

    /**
     * Inicializar referencias a elementos del DOM
     */
    initElements() {
        this.tbodyProyecciones = document.getElementById('tbodyProyecciones');
        this.emptyMessage = document.getElementById('emptyMessage');
        this.totalProyecciones = document.getElementById('totalProyecciones');
        this.loadingOverlay = document.getElementById('loadingOverlay');
    }

    /**
     * Configurar event listeners
     */
    initEventListeners() {
        // Validar permiso de creación
        AppSecurity.aplicarPermisoCrear('btnNueva');

        document.getElementById('btnNueva')?.addEventListener('click', () => this.nuevaProyeccion());
        document.getElementById('btnContinuarPaso2')?.addEventListener('click', () => this.continuarPaso2());
    }

    /**
     * Mostrar/ocultar loading
     */
    showLoading(show) {
        if (this.loadingOverlay) {
            this.loadingOverlay.style.display = show ? 'flex' : 'none';
        }
    }

    /**
     * Cargar lista de proyecciones
     */
    async cargarProyecciones() {
        try {
            this.showLoading(true);

            const response = await this.service.listarProyecciones();

            if (response.success && Array.isArray(response.data)) {
                this.proyecciones = response.data;
                this.proyeccionesOriginal = [...response.data]; // Guardar copia original
                this.renderProyecciones();
            } else {
                this.showError(response.message || 'Error al cargar proyecciones');
                this.proyecciones = [];
                this.mostrarMensajeVacio(true);
            }
        } catch (error) {
            console.error('Error al cargar proyecciones:', error);
            this.showError('Error de conexión al cargar proyecciones');
            this.proyecciones = [];
            this.mostrarMensajeVacio(true);
        } finally {
            this.showLoading(false);
        }
    }

    /**
     * Renderizar tabla de proyecciones
     */
    renderProyecciones() {
        if (!this.proyecciones || this.proyecciones.length === 0) {
            this.mostrarMensajeVacio(true);
            this.totalProyecciones.textContent = '0 proyecciones';
            return;
        }

        // Destruir DataTable existente si existe
        if (this.dataTableInstance) {
            this.dataTableInstance.destroy();
            this.dataTableInstance = null;
        }

        this.mostrarMensajeVacio(false);
        this.totalProyecciones.textContent = `${this.proyecciones.length} proyecciones`;

        // Limpiar tabla
        this.tbodyProyecciones.innerHTML = '';

        // Renderizar filas
        this.proyecciones.forEach((proyeccion, index) => {
            const tr = document.createElement('tr');
            tr.className = 'cursor-pointer';
            tr.dataset.nombre = proyeccion.nombre;

            // Construir nombre con semana
            const semanaTexto = proyeccion.semana ? ` (${proyeccion.semana} SEM)` : '';
            const nombreCompleto = proyeccion.nombre + semanaTexto;

            // Validar estado
            const esValida = String(proyeccion.indicador || '').trim().toUpperCase() === 'VALIDO';
            const badgeClass = esValida ? 'badge badge-valid' : 'badge badge-invalid';
            const badgeText = esValida ? '✓ VALIDO' : 'NO VALIDO';

            // Construir fila con innerHTML
            tr.innerHTML = `
                <td class="text-center font-semibold" style="color: #6b7280;">${index + 1}</td>
                <td class="font-medium">${nombreCompleto}</td>
                <td class="text-center">
                    <span class="${badgeClass}">${badgeText}</span>
                </td>
                <td class="text-center">
                    ${AppSecurity.filtrarBotonesTabla(`
                        <div class="flex justify-center gap-2 items-center">
                            <button class="btn-editar text-blue-600 hover:text-blue-800 transition-colors btn-table-action" data-perm="edit" title="Editar">
                                <i class="fas fa-edit"></i>
                            </button>
                            <button class="btn-eliminar text-red-600 hover:text-red-800 transition-colors btn-table-action" data-perm="delete" title="Eliminar">
                                <i class="fas fa-trash-alt"></i>
                            </button>
                        </div>
                    `)}
                </td>
            `;

            // Event listeners para botones
            const btnEditar = tr.querySelector('.btn-editar');
            const btnEliminar = tr.querySelector('.btn-eliminar');

            if (btnEditar) {
                btnEditar.addEventListener('click', (e) => {
                    e.stopPropagation();
                    this.proyeccionSeleccionada = proyeccion.nombre;
                    this.editarProyeccion();
                });
            }

            if (btnEliminar) {
                btnEliminar.addEventListener('click', (e) => {
                    e.stopPropagation();
                    this.proyeccionSeleccionada = proyeccion.nombre;
                    this.eliminarProyeccion();
                });
            }

            // Event listener para selección de fila
            tr.addEventListener('click', () => this.seleccionarProyeccion(tr));

            this.tbodyProyecciones.appendChild(tr);
        });

        // Inicializar DataTable
        this.initDataTable();
    }

    /**
     * Seleccionar una proyección
     */
    seleccionarProyeccion(tr) {
        // Quitar selección anterior
        const selectedRows = this.tbodyProyecciones.querySelectorAll('tr.selected');
        selectedRows.forEach(row => row.classList.remove('selected'));

        // Agregar selección nueva
        tr.classList.add('selected');
        this.proyeccionSeleccionada = tr.dataset.nombre;
    }

    /**
     * Mostrar/ocultar mensaje vacío
     */
    mostrarMensajeVacio(show) {
        if (this.emptyMessage) {
            this.emptyMessage.style.display = show ? 'flex' : 'none';
        }
        if (this.tbodyProyecciones.parentElement) {
            this.tbodyProyecciones.parentElement.parentElement.style.display = show ? 'none' : 'block';
        }
    }

    /**
     * Crear nueva proyección
     */
    async nuevaProyeccion() {
        const { value: formValues } = await Swal.fire({
            title: 'Nueva Proyección',
            html: `
                <div class="text-left" style="padding: 0 20px;">
                    <label for="swal-input-nombre" class="block text-sm font-semibold text-gray-700 mb-2">Nombre de la proyección:</label>
                    <input id="swal-input-nombre" class="w-full px-3 py-2 border border-gray-300 rounded-lg shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none text-sm transition-all" placeholder="Ej: CARGA 2026 (13.5 SEM) v1" style="margin: 0 0 15px 0;">
                    
                    <label for="swal-input-indicador" class="block text-sm font-semibold text-gray-700 mb-2">Estado:</label>
                    <select id="swal-input-indicador" class="w-full px-3 py-2 border border-gray-300 rounded-lg shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none text-sm transition-all" style="margin: 0;">
                        <option value="NO VALIDO">NO VALIDO</option>
                        <option value="VALIDO">VALIDO</option>
                    </select>
                </div>
            `,
            focusConfirm: false,
            showCancelButton: true,
            confirmButtonText: 'Crear',
            cancelButtonText: 'Cancelar',
            confirmButtonColor: '#2563eb',
            cancelButtonColor: '#6b7280',
            // ── PERSONALIZACIÓN DE BORDES Y SOMBRAS GENERALES ──
            customClass: {
                popup: 'rounded-2xl shadow-2xl border border-gray-100', // Redondeado suave y sombra profunda
                confirmButton: 'rounded-lg font-semibold px-5 py-2.5', // Botón de confirmación redondeado
                cancelButton: 'rounded-lg font-semibold px-5 py-2.5'   // Botón de cancelación redondeado
            },
            preConfirm: () => {
                const nombre = document.getElementById('swal-input-nombre').value;
                const indicador = document.getElementById('swal-input-indicador').value;

                if (!nombre || nombre.trim() === '') {
                    Swal.showValidationMessage('Debes ingresar un nombre');
                    return false;
                }
                if (nombre.length > 100) {
                    Swal.showValidationMessage('El nombre no puede tener más de 100 caracteres');
                    return false;
                }

                return { nombre: nombre.trim(), indicador: indicador };
            }
        });

        if (formValues) {
            // Si está intentando crear como VALIDO, verificar si ya existe otro
            if (formValues.indicador === 'VALIDO') {
                const validoActual = this.proyecciones.find(p =>
                    String(p.indicador).trim().toUpperCase() === 'VALIDO'
                );

                if (validoActual) {
                    // Preguntar y actualizar automáticamente si el usuario confirma
                    const permitirCambio = await this.preguntarCambioValido(validoActual.nombre);
                    if (!permitirCambio) {
                        // Usuario canceló, marcar esta nueva como NO VALIDO
                        formValues.indicador = 'NO VALIDO';
                    }
                }
            }

            try {
                this.showLoading(true);

                const response = await this.service.nuevaProyeccion(formValues);

                if (response.success) {
                    this.showSuccess('Proyección creada exitosamente');
                    await this.cargarProyecciones();
                } else {
                    this.showError(response.message || 'Error al crear proyección');
                }
            } catch (error) {
                console.error('Error:', error);
                this.showError('Error de conexión');
            } finally {
                this.showLoading(false);
            }
        }
    }

    /**
     * Eliminar proyección seleccionada
     */
    /**
     * Eliminar proyección seleccionada con ventana estilizada
     */
    async eliminarProyeccion() {
        if (!this.proyeccionSeleccionada) {
            this.showWarning('Selecciona una proyección primero');
            return;
        }

        const result = await Swal.fire({
            title: '¿Eliminar proyección completa?',
            html: `
                <div class="text-left px-4">
                    <p class="text-gray-700 text-sm leading-relaxed">Se eliminará <strong class="text-red-600 font-bold">${this.proyeccionSeleccionada}</strong> y todos sus datos asociados de forma permanente:</p>
                    <ul class="text-left mt-3 text-xs space-y-1.5 text-gray-500 bg-gray-50 p-3 rounded-xl border border-gray-100 font-mono">
                        <li>• (secuencia base)</li>
                        <li>• (secuencia generada)</li>
                        <li>• (calendario)</li>
                        <li>• (resumen semanal)</li>
                        <li>• (cargas pollos por día)</li>
                    </ul>
                    <p class="text-red-600 font-semibold text-xs mt-3 flex items-center gap-1.5">
                        <i class="fas fa-exclamation-triangle"></i> Esta acción no se puede deshacer
                    </p>
                </div>
            `,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#ef4444',
            cancelButtonColor: '#6b7280',
            confirmButtonText: 'Sí, eliminar todo',
            cancelButtonText: 'Cancelar',
            input: 'checkbox',
            inputValue: 0,
            inputPlaceholder: 'Confirmo que quiero eliminar esta proyección',
            // ── DISEÑO COMPACTO Y REDONDEADO ──
            customClass: {
                popup: 'rounded-2xl shadow-2xl border border-gray-100',
                confirmButton: 'rounded-lg font-semibold px-5 py-2.5 text-sm',
                cancelButton: 'rounded-lg font-semibold px-5 py-2.5 text-sm',
                input: 'font-sans text-sm rounded' // Estiliza el checkbox nativo
            },
            inputValidator: (result) => {
                if (!result) {
                    return 'Debes confirmar la eliminación';
                }
            }
        });

        if (result.isConfirmed && result.value === 1) {
            try {
                this.showLoading(true);

                const response = await this.service.eliminarProyeccion({ proyeccion: this.proyeccionSeleccionada });

                if (response.success) {
                    this.showSuccess('Proyección eliminada completamente');
                    this.proyeccionSeleccionada = null;
                    await this.cargarProyecciones();
                } else {
                    this.showError(response.message || 'Error al eliminar proyección');
                }
            } catch (error) {
                console.error('Error:', error);
                this.showError('Error de conexión');
            } finally {
                this.showLoading(false);
            }
        }
    }

    /**
     * Editar proyección seleccionada con ventana estilizada
     */
    async editarProyeccion() {
        if (!this.proyeccionSeleccionada) {
            this.showWarning('Selecciona una proyección primero');
            return;
        }

        // Obtener datos actuales de la proyección
        const proyeccionActual = this.proyecciones.find(p => p.nombre === this.proyeccionSeleccionada);
        const estadoActual = String(proyeccionActual?.indicador || '').trim().toUpperCase() === 'VALIDO' ? 'VALIDO' : 'NO VALIDO';

        const { value: formValues } = await Swal.fire({
            title: 'Editar Proyección',
            html: `
                <div class="text-left px-4">
                    <label for="swal-input-nombre" class="block text-sm font-semibold text-gray-700 mb-2">Nombre de la proyección:</label>
                    <input id="swal-input-nombre" class="w-full px-3 py-2 border border-gray-300 rounded-lg shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none text-sm transition-all" value="${this.proyeccionSeleccionada}" style="margin: 0 0 15px 0;">
                    
                    <label for="swal-input-indicador" class="block text-sm font-semibold text-gray-700 mb-2">Estado:</label>
                    <select id="swal-input-indicador" class="w-full px-3 py-2 border border-gray-300 rounded-lg shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none text-sm transition-all" style="margin: 0;">
                        <option value="NO VALIDO" ${estadoActual === 'NO VALIDO' ? 'selected' : ''}>NO VALIDO</option>
                        <option value="VALIDO" ${estadoActual === 'VALIDO' ? 'selected' : ''}>VALIDO</option>
                    </select>
                </div>
            `,
            focusConfirm: false,
            showCancelButton: true,
            confirmButtonText: 'Guardar',
            cancelButtonText: 'Cancelar',
            confirmButtonColor: '#2563eb',
            cancelButtonColor: '#6b7280',
            // ── DISEÑO COMPACTO Y REDONDEADO ──
            customClass: {
                popup: 'rounded-2xl shadow-2xl border border-gray-100',
                confirmButton: 'rounded-lg font-semibold px-5 py-2.5 text-sm',
                cancelButton: 'rounded-lg font-semibold px-5 py-2.5 text-sm'
            },
            preConfirm: () => {
                const nombre = document.getElementById('swal-input-nombre').value;
                const indicador = document.getElementById('swal-input-indicador').value;

                if (!nombre || nombre.trim() === '') {
                    Swal.showValidationMessage('Debes ingresar un nombre');
                    return false;
                }
                if (nombre.length > 100) {
                    Swal.showValidationMessage('El nombre no puede tener más de 100 caracteres');
                    return false;
                }

                return { nombre: nombre.trim(), indicador: indicador };
            }
        });

        if (formValues) {
            // Si está intentando poner VALIDO, verificar si ya existe otro
            if (formValues.indicador === 'VALIDO' && estadoActual !== 'VALIDO') {
                const validoActual = this.proyecciones.find(p =>
                    String(p.indicador).trim().toUpperCase() === 'VALIDO' && p.nombre !== this.proyeccionSeleccionada
                );

                if (validoActual) {
                    // Preguntar y actualizar automáticamente si el usuario confirma
                    const permitirCambio = await this.preguntarCambioValido(validoActual.nombre);
                    if (!permitirCambio) {
                        // Usuario canceló, mantener esta como NO VALIDO
                        formValues.indicador = 'NO VALIDO';
                    }
                }
            }

            try {
                this.showLoading(true);

                // Actualizar nombre e indicador en una sola llamada
                const response = await this.service.editarProyeccion({
                    proyeccionActual: this.proyeccionSeleccionada,
                    proyeccionNueva: formValues.nombre,
                    indicador: formValues.indicador
                });

                if (response.success) {
                    this.showSuccess('Proyección actualizada exitosamente');
                    this.proyeccionSeleccionada = formValues.nombre;
                    await this.cargarProyecciones();
                } else {
                    this.showError(response.message || 'Error al actualizar proyección');
                }
            } catch (error) {
                console.error('Error:', error);
                this.showError('Error de conexión');
            } finally {
                this.showLoading(false);
            }
        }
    }

    /**
     * Aplicar filtros de búsqueda y estado
     */
    aplicarFiltros(searchTerm = '', estado = '') {
        let proyeccionesFiltradas = [...this.proyeccionesOriginal];

        // Filtrar por búsqueda
        if (searchTerm && searchTerm.trim() !== '') {
            const termino = searchTerm.toLowerCase().trim();
            proyeccionesFiltradas = proyeccionesFiltradas.filter(p =>
                p.nombre.toLowerCase().includes(termino)
            );
        }

        // Filtrar por estado
        if (estado && estado.trim() !== '') {
            proyeccionesFiltradas = proyeccionesFiltradas.filter(p => {
                const esValida = String(p.indicador).trim().toUpperCase() === 'VALIDO';
                if (estado === 'valid') return esValida;
                if (estado === 'invalid') return !esValida;
                return true;
            });
        }

        this.proyecciones = proyeccionesFiltradas;
        this.renderProyecciones();
    }

    /**
     * Continuar al paso 2 (dashboard de cargas)
     */
    continuarPaso2() {
        window.location.href = 'dashboard-proyecciones-de-cargas-pollos.html';
    }

    /**
     * Helpers para notificaciones
     */
    showSuccess(message) {
        Swal.fire({
            icon: 'success',
            title: 'Éxito',
            text: message,
            timer: 2000,
            showConfirmButton: false
        });
    }

    showError(message) {
        Swal.fire({
            icon: 'error',
            title: 'Error',
            text: message
        });
    }

    showWarning(message) {
        Swal.fire({
            icon: 'warning',
            title: 'Advertencia',
            text: message
        });
    }

    /**
     * Pregunta al usuario si desea cambiar la proyección válida actual
     * @param {string} nombreExistente - Nombre de la proyección actualmente válida
     * @returns {Promise<boolean>} - true si el usuario confirma el cambio, false si cancela
     */
    async preguntarCambioValido(nombreExistente) {
        const result = await Swal.fire({
            title: '¿Cambiar proyección válida?',
            html: `
                <p>Actualmente <strong>"${nombreExistente}"</strong> es la proyección válida.</p>
                <p>¿Deseas marcar esta proyección como válida en su lugar?</p>
                <p class="text-muted">Si confirmas, <strong>"${nombreExistente}"</strong> se marcará como "NO VALIDO".</p>
            `,
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: 'Sí, cambiar a VALIDO',
            cancelButtonText: 'No, mantener como NO VALIDO',
            confirmButtonColor: '#28a745',
            cancelButtonColor: '#6c757d'
        });

        // Si el usuario confirma, necesitamos invalidar la proyección existente
        if (result.isConfirmed) {
            try {
                await this.actualizarIndicador(nombreExistente, 'NO VALIDO');
                this.showSuccess(`"${nombreExistente}" ahora es NO VALIDO`);
                return true;
            } catch (error) {
                this.showError(`Error al actualizar "${nombreExistente}": ${error.message}`);
                return false;
            }
        }

        return false;
    }

    /**
     * Actualiza el indicador de una proyección específica
     * @param {string} nombreProyeccion - Nombre de la proyección a actualizar
     * @param {string} nuevoIndicador - Nuevo valor del indicador (VALIDO/NO VALIDO)
     * @returns {Promise<void>}
     */
    async actualizarIndicador(nombreProyeccion, nuevoIndicador) {
        const response = await this.service.editarProyeccion({
            proyeccionActual: nombreProyeccion,
            proyeccionNueva: nombreProyeccion, // No cambia el nombre, solo el indicador
            indicador: nuevoIndicador
        });

        if (!response.success) {
            throw new Error(response.message || 'Error al actualizar el indicador');
        }

        // Actualizar en el array local
        const proyeccion = this.proyecciones.find(p => p.nombre === nombreProyeccion);
        if (proyeccion) {
            proyeccion.indicador = nuevoIndicador;
        }
    }

    /**
     * Inicializa DataTable con configuración optimizada
     */
    initDataTable() {
        if (!window.jQuery) {
            console.warn('jQuery no disponible, no se puede inicializar DataTable');
            return;
        }

        const table = this.tbodyProyecciones.closest('table');
        if (!table) return;

        try {
            // Configuración minimalista para evitar conflictos
            this.dataTableInstance = $(table).DataTable({
                paging: true,
                pageLength: 25,
                lengthMenu: [[10, 25, 50, 100, -1], [10, 25, 50, 100, "Todos"]],
                searching: false, // Deshabilitar búsqueda de DataTable
                ordering: true,
                info: true,
                autoWidth: false,
                dom: 'frtip', // Layout simple
                // language: { url: '//cdn.datatables.net/plug-ins/1.13.7/i18n/es-ES.json' }, // Deshabilitado por error CORS
                order: [[0, 'asc']],
                retrieve: true, // Permite reutilizar la instancia existente
                deferRender: true, // Mejora el rendimiento
                language: {
                    processing: "Procesando...",
                    search: "Buscar:",
                    lengthMenu: "Mostrar _MENU_ registros",
                    info: "Mostrando registros del _START_ al _END_ de un total de _TOTAL_ registros",
                    infoEmpty: "Mostrando registros del 0 al 0 de un total de 0 registros",
                    infoFiltered: "(filtrado de un total de _MAX_ registros)",
                    infoPostFix: "",
                    loadingRecords: "Cargando...",
                    zeroRecords: "No se encontraron resultados",
                    emptyTable: "Ningún dato disponible en esta tabla",
                    paginate: {
                        first: "Primero",
                        previous: "Anterior",
                        next: "Siguiente",
                        last: "Último"
                    },
                    aria: {
                        sortAscending: ": Activar para ordenar la columna de manera ascendente",
                        sortDescending: ": Activar para ordenar la columna de manera descendente"
                    }
                }
            });
            console.log('✅ DataTable inicializado para proyecciones');
        } catch (error) {
            console.error('Error al inicializar DataTable:', error);
        }
    }
}
