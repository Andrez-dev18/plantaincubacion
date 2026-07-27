/**
 * ContribuyentesController — Orquestador del módulo de Contribuyentes
 */
class ContribuyentesController {
    constructor() {
        this.service = new ContribuyentesService();
        this.dataTableGestion = null;
    }

    async init() {
        // Validar permiso de creación
        AppSecurity.aplicarPermisoCrear('btnNuevo');

        this.setupEventListeners();
        this.setupFiltros();
        await this.renderizarTablaContribuyentes();
    }

    setupEventListeners() {
        // Botón "Nuevo Registro" (Disparador del Modal)
        document.getElementById('btnNuevo')?.addEventListener('click', () => this.abrirModalContribuyente());

        // Cancelar y cerrar Modal
        document.getElementById('btnCerrarModalContribuyente')?.addEventListener('click', () => this.cerrarModalContribuyente());
        document.getElementById('btnCancelarContribuyente')?.addEventListener('click', () => this.cerrarModalContribuyente());

        // Intercepción del Submit del Formulario Principal (Crear/Editar)
        document.getElementById('formContribuyente')?.addEventListener('submit', (e) => this.guardarFormContribuyente(e));
    }

    setupFiltros() {
        const filterInput = document.getElementById('filterCliente');
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
                    filterInput.removeAttribute('data-selected-codigo');
                    filterInput.removeAttribute('data-selected-nombre');
                    filterInput.removeAttribute('data-selected-ruc');
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
                filterInput.removeAttribute('data-selected-codigo');
                filterInput.removeAttribute('data-selected-nombre');
                filterInput.removeAttribute('data-selected-ruc');

                clearTimeout(searchTimeout);

                if (query.length < 1) {
                    if (dropdown) dropdown.classList.add('hidden');
                    return;
                }

                if (query.includes(' - ')) return;

                searchTimeout = setTimeout(() => {
                    this.service.getContribuyentes({ q: query, page: 1, pageSize: 15 })
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
            
            const rucSpan = item.ruc ? `<span class="text-xs bg-gray-100 text-gray-600 px-1.5 py-0.5 rounded font-mono ml-auto">RUC: ${item.ruc}</span>` : '';
            div.innerHTML = `
                <span class="text-blue-600 font-bold font-mono mr-2">${item.codigo || ''}</span>
                <span class="text-gray-400 mr-2">-</span>
                <span class="text-gray-700 font-semibold uppercase">${item.nombre || ''}</span>
                ${rucSpan}
            `;

            div.addEventListener('click', () => {
                const input = document.getElementById('filterCliente');
                if (input) {
                    input.value = `${item.codigo || ''} - ${item.nombre || ''}`;
                    input.setAttribute('data-selected-codigo', item.codigo || '');
                    input.setAttribute('data-selected-nombre', item.nombre || '');
                    input.setAttribute('data-selected-ruc', item.ruc || '');
                }
                dropdown.classList.add('hidden');
            });

            dropdown.appendChild(div);
        });

        dropdown.classList.remove('hidden');
    }

    async renderizarTablaContribuyentes() {
        const tableElement = $('#dataTableContribuyentes');
        if (!tableElement.length) return;

        if ($.fn.DataTable.isDataTable(tableElement)) {
            tableElement.DataTable().clear().destroy();
        }

        this.dataTableGestion = tableElement.DataTable({
            processing: true,
            serverSide: true, // Paginación desde el servidor
            ajax: (dataRequests, callback) => {
                const pageSize = dataRequests.length || 10;
                const page = Math.floor((dataRequests.start || 0) / pageSize) + 1;
                
                let q = '';
                let nombre = '';
                let ruc = '';

                const filterInput = document.getElementById('filterCliente');
                const selectedCodigo = filterInput?.getAttribute('data-selected-codigo') || '';
                const selectedNombre = filterInput?.getAttribute('data-selected-nombre') || '';
                const selectedRuc = filterInput?.getAttribute('data-selected-ruc') || '';
                const filterClienteVal = filterInput?.value?.trim() || '';

                if (filterClienteVal !== '') {
                    if (selectedCodigo !== '') {
                        // Si se seleccionó desde sugerencias, filtramos exactamente por ese registro
                        q = selectedCodigo;
                        nombre = selectedNombre;
                        ruc = selectedRuc;
                    } else {
                        // Si fue escrito a mano, mandamos como filtro general
                        q = filterClienteVal;
                    }
                } else {
                    // Fallback al buscador de DataTables
                    q = dataRequests.search?.value || '';
                }

                this.service.getContribuyentes({ q: q, nombre: nombre, ruc: ruc, page: page, pageSize: pageSize })
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
                        console.error("Error al cargar la tabla de contribuyentes:", error);
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
                { data: 'codigo', className: 'text-center font-semibold font-mono' },
                { data: 'nombre', className: 'text-left font-medium' },
                { data: 'ruc', className: 'text-center font-mono' },
                { data: 'direcc', className: 'text-left text-xs' },
                { data: 'clpr', className: 'text-center uppercase font-mono' },
                {
                    data: 'act_ivo',
                    className: 'text-center',
                    render: (data) => {
                        const activeStr = String(data || '').trim().toUpperCase();
                        if (activeStr === 'A' || activeStr === 'S' || activeStr === '') {
                            return `<span class="bg-green-100 text-green-700 px-2 py-1 rounded text-xs font-bold border border-green-200">Activo</span>`;
                        }
                        return `<span class="bg-red-100 text-red-800 px-2 py-1 rounded text-xs font-bold border border-red-200">Inactivo</span>`;
                    }
                },
                {
                    data: null,
                    orderable: false,
                    className: 'text-center',
                    render: (data, type, row) => {
                        const activeStr = String(row.act_ivo || '').trim().toUpperCase();
                        const isActivo = activeStr === 'A' || activeStr === 'S' || activeStr === '';
                        const toggleBtnClass = isActivo ? 'bg-orange-100 hover:bg-orange-200 text-orange-600' : 'bg-green-100 hover:bg-green-200 text-green-600';
                        const toggleBtnTitle = isActivo ? 'Desactivar Contribuyente' : 'Activar Contribuyente';
                        const toggleBtnIcon = isActivo ? 'fa-ban' : 'fa-check';

                        return AppSecurity.filtrarBotonesTabla(`
                            <div class="flex gap-2 justify-center">
                                <button type="button" data-perm="edit" class="btn-editar-contribuyente bg-blue-100 hover:bg-blue-200 text-blue-600 px-2 py-1.5 rounded transition-colors shadow-sm" title="Editar Contribuyente" data-codigo="${row.codigo}">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <button type="button" data-perm="edit" class="btn-toggle-contribuyente ${toggleBtnClass} px-2 py-1.5 rounded transition-colors shadow-sm" title="${toggleBtnTitle}" data-codigo="${row.codigo}">
                                    <i class="fas ${toggleBtnIcon}"></i>
                                </button>
                                <button type="button" data-perm="delete" class="btn-eliminar-contribuyente bg-red-100 hover:bg-red-200 text-red-600 px-2 py-1.5 rounded transition-colors shadow-sm" title="Eliminar Contribuyente" data-codigo="${row.codigo}">
                                    <i class="fas fa-trash-alt"></i>
                                </button>
                            </div>
                        `);
                    }
                }
            ],
            scrollX: true,
            responsive: false,
            pageLength: 10,
            language: { url: 'https://cdn.datatables.net/plug-ins/2.0.8/i18n/es-ES.json' }
        });

        // Configuración de delegación de clicks mediante jQuery
        const self = this;
        tableElement.off('click', '.btn-editar-contribuyente').on('click', '.btn-editar-contribuyente', function () {
            self.abrirModalContribuyente($(this).attr('data-codigo'));
        });
        tableElement.off('click', '.btn-toggle-contribuyente').on('click', '.btn-toggle-contribuyente', function () {
            self.toggleEstadoContribuyente($(this).attr('data-codigo'));
        });
        tableElement.off('click', '.btn-eliminar-contribuyente').on('click', '.btn-eliminar-contribuyente', function () {
            self.eliminarContribuyente($(this).attr('data-codigo'));
        });
    }

    async abrirModalContribuyente(codigo = null) {
        const modal = document.getElementById('modalContribuyente');
        const form = document.getElementById('formContribuyente');
        const title = document.getElementById('modalContribuyenteTitle');
        const inputIsEdit = document.getElementById('contribuyente_is_edit');
        const inputCodigo = document.getElementById('fCodigo');

        form.reset();

        if (codigo) {
            // MODO EDICIÓN
            inputIsEdit.value = 'true';
            title.innerHTML = '<i class="fas fa-edit text-blue-600 mr-2"></i> Editar Contribuyente';
            inputCodigo.readOnly = true;
            inputCodigo.classList.add('bg-gray-100', 'cursor-not-allowed');

            // Cargar datos del contribuyente
            const response = await this.service.obtenerContribuyente(codigo);
            if (response.success && response.data) {
                inputCodigo.value = response.data.codigo || '';
                document.getElementById('fPersona').value = response.data.persona || response.data.tipo || '';
                document.getElementById('fTipodoc').value = response.data.tipodoc || '';
                document.getElementById('fTdocid').value = response.data.tdocid || '';
                document.getElementById('fFormulario').value = response.data.formulario || '';
                document.getElementById('fLe').value = response.data.le || '';
                document.getElementById('fNdocid').value = response.data.ndocid || '';
                document.getElementById('fNombre').value = response.data.nombre || '';
                document.getElementById('fDirecc').value = response.data.direcc || '';
                document.getElementById('fTelefo').value = response.data.telefo || '';
                document.getElementById('fUbigeo').value = response.data.ubigeo || '';
                document.getElementById('fDeparta').value = response.data.departa || '';
                document.getElementById('fProvincia').value = response.data.provincia || '';
                document.getElementById('fDistrito').value = response.data.distrito || '';
                document.getElementById('fCodmer').value = response.data.codmer || '';
                document.getElementById('fCodven').value = response.data.codven || '';
                document.getElementById('fFax').value = response.data.fax || '';
                document.getElementById('fPais').value = response.data.pais || '';
                document.getElementById('fContacto').value = response.data.contacto || '';
                document.getElementById('fRuc').value = response.data.ruc || '';
                document.getElementById('fZpos').value = response.data.zpos || '';
                document.getElementById('fClpr').value = response.data.clpr || '';
                document.getElementById('fEstadoRuc').value = response.data.estado_ruc || '';
                document.getElementById('fCondicionRuc').value = response.data.condicion_ruc || '';
                document.getElementById('contribuyente_act_ivo').value = response.data.act_ivo || 'A';

                // Campos de Clasificación y detracción
                document.getElementById('fRuta').value = response.data.ruta || '';
                document.getElementById('fEmail').value = response.data.email || '';
                document.getElementById('fCanal').value = response.data.canal || '';
                document.getElementById('fTipocli').value = response.data.tipocli || '';
                document.getElementById('fGiro').value = response.data.giro || '';
                document.getElementById('fNomrep').value = response.data.nomrep || '';
                document.getElementById('fCodrep').value = response.data.codrep || '';
                document.getElementById('fReten').value = response.data.reten || '';
                document.getElementById('fUbcarp').value = response.data.ubcarp || '';
                document.getElementById('fSecuencia').value = response.data.secuencia || '';
                document.getElementById('fBrevete').value = response.data.brevete || '';
                document.getElementById('fCtaDetra').value = response.data.ctactedetra || '';
                document.getElementById('fModelo').value = response.data.modelo || response.data.porc || '';
                document.getElementById('fCopa').value = response.data.copa || '';
                document.getElementById('fPlaca').value = response.data.placa || '';

                // Campos de Cuentas bancarias
                document.getElementById('fTipoprod').value = response.data.tipoprod || '';
                document.getElementById('fCtaAhorros').value = response.data.ctaahorros || '';
                document.getElementById('fCtaCorrientes').value = response.data.ctacorrientes || '';
                document.getElementById('fCtaAhorroD').value = response.data.ctaahrod || response.data.ctaahorrod || '';
                document.getElementById('fCtaCorrienteD').value = response.data.ctacorriented || '';
            } else {
                Swal.fire('Error', 'No se pudieron cargar los datos del contribuyente.', 'error');
                return;
            }
        } else {
            // MODO CREACIÓN
            inputIsEdit.value = 'false';
            title.innerHTML = '<i class="fas fa-plus text-green-600 mr-2"></i> Nuevo Contribuyente';
            inputCodigo.readOnly = false;
            inputCodigo.classList.remove('bg-gray-100', 'cursor-not-allowed');
            document.getElementById('contribuyente_act_ivo').value = 'A';
        }

        modal.classList.remove('hidden');
        modal.style.display = 'flex';
    }

    cerrarModalContribuyente() {
        const modal = document.getElementById('modalContribuyente');
        if (modal) {
            modal.classList.add('hidden');
            modal.style.display = 'none';
        }
        document.getElementById('formContribuyente').reset();
    }

    async guardarFormContribuyente(e) {
        e.preventDefault();

        const btnGuardar = document.getElementById('btnGuardarContribuyente');
        const textoOriginal = btnGuardar.innerHTML;
        btnGuardar.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Guardando...';
        btnGuardar.disabled = true;

        const isEdit = document.getElementById('contribuyente_is_edit').value === 'true';

        const payload = {
            is_edit: isEdit,
            codigo: document.getElementById('fCodigo').value.trim(),
            tipo: document.getElementById('fPersona').value,
            persona: document.getElementById('fPersona').value,
            tipodoc: document.getElementById('fTipodoc').value.trim(),
            tdocid: document.getElementById('fTdocid').value.trim(),
            formulario: parseFloat(document.getElementById('fFormulario').value) || 0,
            le: document.getElementById('fLe').value.trim(),
            ndocid: document.getElementById('fNdocid').value.trim(),
            nombre: document.getElementById('fNombre').value.trim(),
            direcc: document.getElementById('fDirecc').value.trim(),
            telefo: document.getElementById('fTelefo').value.trim(),
            ubigeo: document.getElementById('fUbigeo').value.trim(),
            departa: document.getElementById('fDeparta').value.trim(),
            provincia: document.getElementById('fProvincia').value.trim(),
            distrito: document.getElementById('fDistrito').value.trim(),
            codmer: document.getElementById('fCodmer').value.trim(),
            codven: document.getElementById('fCodven').value.trim(),
            fax: document.getElementById('fFax').value.trim(),
            pais: document.getElementById('fPais').value.trim(),
            contacto: document.getElementById('fContacto').value.trim(),
            ruc: document.getElementById('fRuc').value.trim(),
            zpos: document.getElementById('fZpos').value.trim(),
            clpr: document.getElementById('fClpr').value.trim(),
            estado_ruc: document.getElementById('fEstadoRuc').value.trim(),
            condicion_ruc: document.getElementById('fCondicionRuc').value.trim(),
            act_ivo: document.getElementById('contribuyente_act_ivo').value.trim(),

            // Campos de Clasificación y detracción
            ruta: document.getElementById('fRuta').value.trim(),
            email: document.getElementById('fEmail').value.trim(),
            canal: document.getElementById('fCanal').value.trim(),
            tipocli: document.getElementById('fTipocli').value.trim(),
            giro: document.getElementById('fGiro').value.trim(),
            nomrep: document.getElementById('fNomrep').value.trim(),
            codrep: document.getElementById('fCodrep').value.trim(),
            reten: document.getElementById('fReten').value,
            ubcarp: document.getElementById('fUbcarp').value.trim(),
            secuencia: parseInt(document.getElementById('fSecuencia').value) || 0,
            brevete: document.getElementById('fBrevete').value.trim(),
            ctactedetra: document.getElementById('fCtaDetra').value.trim(),
            modelo: document.getElementById('fModelo').value.trim(),
            porc: document.getElementById('fModelo').value.trim(),
            copa: document.getElementById('fCopa').value.trim(),
            placa: document.getElementById('fPlaca').value.trim(),

            // Campos de Cuentas bancarias
            tipoprod: document.getElementById('fTipoprod').value,
            ctaahorros: document.getElementById('fCtaAhorros').value.trim(),
            ctacorrientes: document.getElementById('fCtaCorrientes').value.trim(),
            ctaahorrod: document.getElementById('fCtaAhorroD').value.trim(),
            ctacorriented: document.getElementById('fCtaCorrienteD').value.trim()
        };

        try {
            const response = await this.service.guardarContribuyente(payload);
            if (response.success) {
                Swal.fire({ icon: 'success', title: '¡Éxito!', text: response.message, timer: 1500, showConfirmButton: false });
                this.cerrarModalContribuyente();
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

    eliminarContribuyente(codigo) {
        Swal.fire({
            title: `¿Deseas eliminar este contribuyente?`,
            text: `Esta acción no se puede deshacer y eliminará el registro ${codigo} permanentemente.`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#ef4444',
            cancelButtonColor: '#6b7280',
            confirmButtonText: `Sí, eliminar`,
            cancelButtonText: 'Cancelar'
        }).then(async (result) => {
            if (result.isConfirmed) {
                try {
                    const response = await this.service.eliminarContribuyente(codigo);
                    if (response.success) {
                        Swal.fire({ icon: 'success', title: 'Contribuyente eliminado', timer: 1500, showConfirmButton: false });
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

    toggleEstadoContribuyente(codigo) {
        Swal.fire({
            title: `¿Deseas cambiar el estado de este contribuyente?`,
            text: `Se alternará la condición de activo/inactivo para el contribuyente con código ${codigo}.`,
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#3b82f6',
            cancelButtonColor: '#6b7280',
            confirmButtonText: `Sí, cambiar`,
            cancelButtonText: 'Cancelar'
        }).then(async (result) => {
            if (result.isConfirmed) {
                try {
                    const response = await this.service.toggleActivoContribuyente(codigo);
                    if (response.success) {
                        Swal.fire({ icon: 'success', title: 'Estado actualizado', timer: 1500, showConfirmButton: false });
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
window.contribuyentesController = new ContribuyentesController();
document.addEventListener('DOMContentLoaded', () => {
    window.contribuyentesController.init();
});
