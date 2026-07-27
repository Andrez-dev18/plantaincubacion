/**
 * ServiciosController — Orquestador del módulo de Servicios
 */
class ServiciosController {
    constructor() {
        this.service = new ServiciosService();
        this.dataTableGestion = null;
    }

    async init() {
        // Validar permiso de creación
        AppSecurity.aplicarPermisoCrear('btnNuevo');

        this.setupEventListeners();
        this.setupFiltros();
        await this.renderizarTablaServicios();
    }

    setupEventListeners() {
        // Botón "Nuevo Registro" (Disparador del Modal)
        document.getElementById('btnNuevo')?.addEventListener('click', () => this.abrirModalServicio());

        // Cancelar y cerrar Modal Servicio
        document.getElementById('btnCerrarModalServicio')?.addEventListener('click', () => this.cerrarModalServicio());
        document.getElementById('btnCancelarServicio')?.addEventListener('click', () => this.cerrarModalServicio());

        // Intercepción del Submit del Formulario Principal (Crear/Editar)
        document.getElementById('formServicio')?.addEventListener('submit', (e) => this.guardarFormServicio(e));
    }

    setupFiltros() {
        const filterInput = document.getElementById('filterServicio');
        const dropdown = document.getElementById('suggestionsDropdown');
        const btnToggle = document.getElementById('btnToggleFiltros');
        const filterContent = document.getElementById('filterContent');
        const btnLimpiar = document.getElementById('btnLimpiarFiltros');
        const btnAplicar = document.getElementById('btnAplicarFiltros');

        // Toggle filtros
        if (btnToggle && filterContent) {
            // Asegurar que inicie con overflow: visible si está expandido por defecto
            if (filterContent.classList.contains('show')) {
                filterContent.style.overflow = 'visible';
            } else {
                filterContent.style.overflow = 'hidden';
            }

            btnToggle.addEventListener('click', () => {
                const isOpen = filterContent.classList.contains('show');
                const chevron = document.getElementById('filterChevron');
                if (isOpen) {
                    filterContent.classList.remove('show');
                    filterContent.style.overflow = 'hidden';
                    if (chevron) {
                        chevron.classList.remove('fa-chevron-up');
                        chevron.classList.add('fa-chevron-down');
                    }
                } else {
                    filterContent.classList.add('show');
                    // Esperar a que termine la animación para hacer visible el overflow
                    setTimeout(() => {
                        if (filterContent.classList.contains('show')) {
                            filterContent.style.overflow = 'visible';
                        }
                    }, 400);
                    if (chevron) {
                        chevron.classList.remove('fa-chevron-down');
                        chevron.classList.add('fa-chevron-up');
                    }
                }
            });
        }

        // Limpiar filtros
        if (btnLimpiar) {
            btnLimpiar.addEventListener('click', () => {
                if (filterInput) {
                    filterInput.value = '';
                    filterInput.removeAttribute('data-selected-codi');
                }
                if (dropdown) {
                    dropdown.classList.add('hidden');
                }
                if (this.dataTableGestion) {
                    this.dataTableGestion.search('').draw();
                }
            });
        }

        // Aplicar filtros
        if (btnAplicar) {
            btnAplicar.addEventListener('click', () => {
                if (this.dataTableGestion) {
                    this.dataTableGestion.ajax.reload();
                }
            });
        }

        // Autocomplete/Suggestions
        let searchTimeout = null;
        if (filterInput) {
            filterInput.addEventListener('input', (e) => {
                const query = e.target.value.trim();
                
                // Clear attributes so manually typed searches don't carry previous metadata
                filterInput.removeAttribute('data-selected-codi');

                clearTimeout(searchTimeout);

                if (query.length < 1) {
                    if (dropdown) dropdown.classList.add('hidden');
                    return;
                }

                if (query.includes(' - ')) return;

                searchTimeout = setTimeout(() => {
                    this.service.getServicios({ q: query, page: 1, pageSize: 15 })
                        .then(response => {
                            if (response.success && response.data && response.data.length > 0) {
                                this.renderSuggestions(response.data);
                            } else {
                                if (dropdown) dropdown.classList.add('hidden');
                            }
                        })
                        .catch(err => {
                            console.error('Error fetching suggestions:', err);
                            if (dropdown) dropdown.classList.add('hidden');
                        });
                }, 300);
            });
        }

        // Cerrar dropdown al hacer click fuera
        document.addEventListener('click', (e) => {
            if (dropdown && !dropdown.contains(e.target) && e.target !== filterInput) {
                dropdown.classList.add('hidden');
            }
        });
    }

    renderSuggestions(data) {
        const dropdown = document.getElementById('suggestionsDropdown');
        if (!dropdown) return;

        dropdown.innerHTML = '';

        data.forEach(item => {
            const div = document.createElement('div');
            div.className = 'px-4 py-2.5 hover:bg-gray-50 cursor-pointer text-sm flex items-center border-b border-gray-100 last:border-b-0 transition-colors';
            
            div.innerHTML = `
                <span class="text-blue-600 font-bold font-mono mr-2">${item.codi || ''}</span>
                <span class="text-gray-400 mr-2">-</span>
                <span class="text-gray-700 font-semibold uppercase">${item.descri || ''}</span>
            `;

            div.addEventListener('click', () => {
                const input = document.getElementById('filterServicio');
                if (input) {
                    input.value = `${item.codi || ''} - ${item.descri || ''}`;
                    input.setAttribute('data-selected-codi', item.codi || '');
                }
                dropdown.classList.add('hidden');
            });

            dropdown.appendChild(div);
        });

        dropdown.classList.remove('hidden');
    }

    async renderizarTablaServicios() {
        const tableElement = $('#dataTableServicios');
        if (!tableElement.length) return;

        if ($.fn.DataTable.isDataTable(tableElement)) {
            tableElement.DataTable().clear().destroy();
        }

        this.dataTableGestion = tableElement.DataTable({
            processing: true,
            serverSide: true, // Habilitar paginación del servidor
            ajax: (dataRequests, callback) => {
                const pageSize = dataRequests.length || 25;
                const page = Math.floor((dataRequests.start || 0) / pageSize) + 1;
                
                let q = '';
                let codi = '';
                const filterInput = document.getElementById('filterServicio');
                const selectedCodi = filterInput?.getAttribute('data-selected-codi') || '';
                const filterServicioVal = filterInput?.value?.trim() || '';

                if (filterServicioVal !== '') {
                    if (selectedCodi !== '') {
                        // Si se seleccionó desde sugerencias, filtramos exactamente por ese código
                        codi = selectedCodi;
                    } else {
                        // Si fue escrito a mano, mandamos como búsqueda general
                        q = filterServicioVal;
                    }
                } else {
                    // Fallback al buscador de DataTables
                    q = dataRequests.search?.value || '';
                }

                this.service.getServicios({ q: q, codi: codi, page: page, pageSize: pageSize })
                    .then(response => {
                        if (response.success && response.data) {
                            callback({
                                draw: dataRequests.draw,
                                recordsTotal: response.total || 0,
                                recordsFiltered: response.total || 0,
                                data: response.data
                            });
                        } else {
                            callback({ draw: dataRequests.draw, recordsTotal: 0, recordsFiltered: 0, data: [] });
                        }
                    })
                    .catch(error => {
                        console.error("Error al cargar la tabla:", error);
                        callback({ draw: dataRequests.draw, recordsTotal: 0, recordsFiltered: 0, data: [] });
                    });
            },
            columns: [
                {
                    data: null,
                    orderable: false,
                    className: 'text-center',
                    render: (data, type, row, meta) => meta.row + meta.settings._iDisplayStart + 1
                },
                { data: 'codi', className: 'text-center font-semibold font-mono' },
                { data: 'descri', className: 'text-left' },
                { data: 'funcio', className: 'text-left font-mono' },
                { data: 'natu_1', className: 'text-left font-mono' },
                { data: 'natu_2', className: 'text-center font-mono' },
                { data: 'grupo', className: 'text-center' },
                {
                    data: null,
                    orderable: false,
                    className: 'text-center',
                    render: (data, type, row) => AppSecurity.filtrarBotonesTabla(`
                        <div class="flex gap-2 justify-center">
                            <button type="button" data-perm="edit" class="btn-editar-servicio bg-blue-100 hover:bg-blue-200 text-blue-600 px-2 py-1.5 rounded transition-colors shadow-sm" title="Editar Servicio" data-codi="${row.codi}">
                                <i class="fas fa-edit"></i>
                            </button>
                            <button type="button" data-perm="delete" class="btn-eliminar-servicio bg-orange-100 hover:bg-orange-200 text-orange-600 px-2 py-1.5 rounded transition-colors shadow-sm" title="Eliminar Servicio" data-codi="${row.codi}">
                                <i class="fas fa-trash-alt"></i>
                            </button>
                        </div>
                    `)
                }
            ],
            scrollX: true,
            responsive: false,
            pageLength: 10,
            language: { url: 'https://cdn.datatables.net/plug-ins/2.0.8/i18n/es-ES.json' }
        });

        // Configuración de delegación de clicks mediante jQuery
        const self = this;
        tableElement.off('click', '.btn-editar-servicio').on('click', '.btn-editar-servicio', function () {
            self.abrirModalServicio($(this).attr('data-codi'));
        });
        tableElement.off('click', '.btn-eliminar-servicio').on('click', '.btn-eliminar-servicio', function () {
            self.eliminarServicio($(this).attr('data-codi'));
        });
    }

    async abrirModalServicio(codi = null) {
        const modal = document.getElementById('modalServicio');
        const form = document.getElementById('formServicio');
        const title = document.getElementById('modalServicioTitle');
        const inputIsEdit = document.getElementById('servicio_is_edit');
        const inputCodi = document.getElementById('sCodi');

        form.reset();

        if (codi) {
            // MODO EDICIÓN
            inputIsEdit.value = 'true';
            title.innerHTML = '<i class="fas fa-edit text-blue-600 mr-2"></i> Editar Servicio';
            inputCodi.readOnly = true;
            inputCodi.classList.add('bg-gray-100', 'cursor-not-allowed');

            // Cargar datos del servicio
            const response = await this.service.obtenerServicio(codi);
            if (response.success && response.data) {
                inputCodi.value = response.data.codi || '';
                document.getElementById('sDescri').value = response.data.descri || '';
                document.getElementById('sFuncio').value = response.data.funcio || '';
                document.getElementById('sNatu1').value = response.data.natu_1 || '';
                document.getElementById('sNatu2').value = response.data.natu_2 || '';
                document.getElementById('sCtanue').value = response.data.ctanue || '';
                document.getElementById('sGrupo').value = response.data.grupo || '';
                document.getElementById('sTipo').value = response.data.tipo || '';
                document.getElementById('sBien').value = response.data.bien || '';
                document.getElementById('sPorc').value = response.data.porc || '';
                document.getElementById('sMonto').value = response.data.monto || '';
                document.getElementById('sIgv').value = response.data.igv || '';
            } else {
                Swal.fire('Error', 'No se pudieron cargar los datos del servicio.', 'error');
                return;
            }
        } else {
            // MODO CREACIÓN
            inputIsEdit.value = 'false';
            title.innerHTML = '<i class="fas fa-plus text-green-600 mr-2"></i> Nuevo Servicio';
            inputCodi.readOnly = false;
            inputCodi.classList.remove('bg-gray-100', 'cursor-not-allowed');
        }

        modal.classList.remove('hidden');
        modal.style.display = 'flex';
    }

    cerrarModalServicio() {
        const modal = document.getElementById('modalServicio');
        if (modal) {
            modal.classList.add('hidden');
            modal.style.display = 'none';
        }
        document.getElementById('formServicio').reset();
    }

    async guardarFormServicio(e) {
        e.preventDefault();

        const btnGuardar = document.getElementById('btnGuardarServicio');
        const textoOriginal = btnGuardar.innerHTML;
        btnGuardar.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Guardando...';
        btnGuardar.disabled = true;

        const isEdit = document.getElementById('servicio_is_edit').value === 'true';

        const payload = {
            is_edit: isEdit,
            codi: document.getElementById('sCodi').value.trim(),
            descri: document.getElementById('sDescri').value.trim(),
            funcio: document.getElementById('sFuncio').value.trim(),
            natu_1: document.getElementById('sNatu1').value.trim(),
            natu_2: document.getElementById('sNatu2').value.trim(),
            ctanue: document.getElementById('sCtanue').value.trim(),
            grupo: document.getElementById('sGrupo').value.trim(),
            tipo: document.getElementById('sTipo').value.trim(),
            bien: document.getElementById('sBien').value.trim(),
            porc: parseFloat(document.getElementById('sPorc').value) || 0,
            monto: parseFloat(document.getElementById('sMonto').value) || 0,
            igv: parseFloat(document.getElementById('sIgv').value) || 0
        };

        try {
            const response = await this.service.guardarServicio(payload);
            if (response.success) {
                Swal.fire({ icon: 'success', title: '¡Éxito!', text: response.message, timer: 1500, showConfirmButton: false });
                this.cerrarModalServicio();
                if (this.dataTableGestion) this.dataTableGestion.ajax.reload(null, false);
            } else {
                Swal.fire('Error', response.message, 'error');
            }
        } catch (error) {
            Swal.fire('Error', error.message || 'Hubo un problema de conexión.', 'error');
        } finally {
            btnGuardar.innerHTML = textoOriginal;
            btnGuardar.disabled = false;
        }
    }

    eliminarServicio(codi) {
        Swal.fire({
            title: `¿Deseas eliminar este servicio?`,
            text: `Esta acción no se puede deshacer y eliminará el servicio ${codi} permanentemente.`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#ef4444',
            cancelButtonColor: '#6b7280',
            confirmButtonText: `Sí, eliminar`,
            cancelButtonText: 'Cancelar'
        }).then(async (result) => {
            if (result.isConfirmed) {
                try {
                    const response = await this.service.eliminarServicio(codi);
                    if (response.success) {
                        Swal.fire({ icon: 'success', title: 'Servicio eliminado', timer: 1500, showConfirmButton: false });
                        if (this.dataTableGestion) this.dataTableGestion.ajax.reload(null, false);
                    } else {
                        Swal.fire('Error', response.message, 'error');
                    }
                } catch (e) {
                    Swal.fire('Error', e.message || 'No se pudo procesar la solicitud.', 'error');
                }
            }
        });
    }
}

// Inicializar el controlador global
window.serviciosController = new ServiciosController();
document.addEventListener('DOMContentLoaded', () => {
    window.serviciosController.init();
});
