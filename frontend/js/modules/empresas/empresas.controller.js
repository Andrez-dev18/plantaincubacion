/**
 * EmpresasController — Orquestador del módulo de empresas en el frontend
 */
class EmpresasController {
    constructor() {
        this.service = new EmpresasService();
        this.dataTableGestion = null;
    }

    async init() {
        // Validar permiso de creación de forma unificada
        AppSecurity.aplicarPermisoCrear('btnNuevo');

        this.setupEventListeners();
        this.setupSelect2();
        await this.renderizarTablaEmpresas();
    }

    setupEventListeners() {
        // Botón "Nuevo Registro" (Disparador del Modal)
        document.getElementById('btnNuevo')?.addEventListener('click', () => this.abrirModalEmpresa());

        // Cancelar y cerrar Modal Empresa
        document.getElementById('btnCerrarModalEmpresa')?.addEventListener('click', () => this.cerrarModalEmpresa());
        document.getElementById('btnCancelarEmpresa')?.addEventListener('click', () => this.cerrarModalEmpresa());

        // Intercepción del Submit del Formulario Principal (Crear/Editar)
        document.getElementById('formEmpresa')?.addEventListener('submit', (e) => this.guardarFormEmpresa(e));
    }

    setupSelect2() {
        const selectCcte = $('#empresa_ccte');
        if (selectCcte.length) {
            selectCcte.select2({
                placeholder: 'Seleccione un proveedor...',
                minimumInputLength: 0,
                width: '100%',
                dropdownParent: $('#modalEmpresa'),
                ajax: {
                    delay: 250,
                    transport: (params, success, failure) => {
                        let aborted = false;
                        const q = params.data.q || '';
                        const page = params.data.page || 1;

                        this.service.listarCCTE(q, page, 20)
                            .then(res => {
                                if (aborted) return;
                                if (res.success) {
                                    success({
                                        results: res.data.map(item => ({
                                            id: item.codigo,
                                            text: `${item.codigo} - ${item.nombre}`,
                                            nombre: item.nombre
                                        })),
                                        pagination: {
                                            more: (page * 20) < res.total
                                        }
                                    });
                                } else {
                                    failure(new Error(res.message));
                                }
                            })
                            .catch(err => {
                                if (aborted) return;
                                failure(err);
                            });

                        return {
                            abort: () => {
                                aborted = true;
                            }
                        };
                    }
                }
            });

            // Auto-rellenar nombre al seleccionar proveedor
            selectCcte.on('select2:select', (e) => {
                const data = e.params.data;
                const inputNombre = document.getElementById('empresa_nombre');
                if (inputNombre && data.nombre) {
                    inputNombre.value = data.nombre;
                }
            });
        }
    }

    async renderizarTablaEmpresas() {
        const tableElement = $('#dataTableEmpresas');
        if (!tableElement.length) return;

        if ($.fn.DataTable.isDataTable(tableElement)) {
            tableElement.DataTable().clear().destroy();
        }

        this.dataTableGestion = tableElement.DataTable({
            processing: true,
            serverSide: false, // Usamos client-side ya que listar devuelve la lista completa
            ajax: (dataRequests, callback) => {
                this.service.getEmpresas()
                    .then(response => {
                        if (response.success && response.data) {
                            callback({
                                data: response.data
                            });
                        } else {
                            callback({ data: [] });
                        }
                    })
                    .catch(error => {
                        console.error("Error al cargar la tabla:", error);
                        callback({ data: [] });
                    });
            },
            columns: [
                {
                    data: null,
                    orderable: false,
                    className: 'text-center',
                    render: (data, type, row, meta) => meta.row + meta.settings._iDisplayStart + 1
                },
                { data: 'ruc', className: 'text-center font-semibold' },
                { data: 'nombre', className: 'text-left' },
                { data: 'serie_cpe_ft', className: 'text-left font-mono' },
                { data: 'guifac_url', className: 'text-left text-xs break-all max-w-[200px]' },
                {
                    data: 'activo',
                    className: 'text-center',
                    render: (data) => parseInt(data) === 1
                        ? '<span class="bg-green-100 text-green-700 px-2 py-1 rounded text-xs font-bold border border-green-200">ACTIVO</span>'
                        : '<span class="bg-red-100 text-red-700 px-2 py-1 rounded text-xs font-bold border border-red-200">INACTIVO</span>'
                },
                {
                    data: null,
                    orderable: false,
                    className: 'text-center',
                    render: (data, type, row) => AppSecurity.filtrarBotonesTabla(`
                        <div class="flex gap-2 justify-center">
                            <button type="button" data-perm="edit" class="btn-editar-empresa bg-blue-100 hover:bg-blue-200 text-blue-600 px-2 py-1.5 rounded transition-colors shadow-sm" title="Editar Empresa" data-id="${row.id_proveedor}">
                                <i class="fas fa-edit"></i>
                            </button>
                            <button type="button" data-perm="edit" class="btn-toggle-estado ${parseInt(row.activo) === 1 ? 'bg-orange-100 hover:bg-orange-200 text-orange-600' : 'bg-green-100 hover:bg-green-200 text-green-600'} px-2 py-1.5 rounded transition-colors shadow-sm" title="${parseInt(row.activo) === 1 ? 'Desactivar' : 'Activar'} Empresa" data-id="${row.id_proveedor}" data-estado="${row.activo}">
                                <i class="fas fa-power-off"></i>
                            </button>
                            <button type="button" data-perm="delete" class="btn-eliminar-empresa bg-red-100 hover:bg-red-200 text-red-600 px-2 py-1.5 rounded transition-colors shadow-sm" title="Eliminar Empresa" data-id="${row.id_proveedor}">
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
        tableElement.off('click', '.btn-editar-empresa').on('click', '.btn-editar-empresa', function () {
            self.abrirModalEmpresa($(this).attr('data-id'));
        });
        tableElement.off('click', '.btn-toggle-estado').on('click', '.btn-toggle-estado', function () {
            self.toggleEstadoEmpresa($(this).attr('data-id'), $(this).attr('data-estado'));
        });
        tableElement.off('click', '.btn-eliminar-empresa').on('click', '.btn-eliminar-empresa', function () {
            self.eliminarEmpresa($(this).attr('data-id'));
        });
    }

    async abrirModalEmpresa(id = null) {
        const modal = document.getElementById('modalEmpresa');
        const form = document.getElementById('formEmpresa');
        const title = document.getElementById('modalEmpresaTitle');
        const inputIsEdit = document.getElementById('empresa_is_edit');
        const inputId = document.getElementById('empresa_id');
        const selectContainer = document.getElementById('ccteSelectContainer');
        const inputContainer = document.getElementById('ccteInputContainer');
        const inputReadonly = document.getElementById('empresa_ccte_readonly');
        const selectCcte = $('#empresa_ccte');

        form.reset();
        selectCcte.val(null).trigger('change');

        if (id) {
            // MODO EDICIÓN
            inputIsEdit.value = 'true';
            inputId.value = id;
            title.innerHTML = '<i class="fas fa-edit text-blue-600 mr-2"></i> Editar Empresa';
            
            // Ocultar Select2, mostrar input readonly
            selectContainer.classList.add('hidden');
            inputContainer.classList.remove('hidden');
            selectCcte.removeAttr('required');

            // Cargar datos
            const response = await this.service.obtenerEmpresa(id);
            if (response.success && response.data) {
                inputReadonly.value = response.data.ruc || '';
                document.getElementById('empresa_nombre').value = response.data.nombre || '';
                document.getElementById('empresa_serie').value = response.data.serie_cpe_ft || '';
                document.getElementById('empresa_url').value = response.data.guifac_url || '';
                document.getElementById('empresa_token').value = response.data.guifac_token || '';
                document.getElementById('empresa_activo').checked = parseInt(response.data.activo) === 1;
            } else {
                Swal.fire('Error', 'No se pudieron cargar los datos de la empresa.', 'error');
                return;
            }
        } else {
            // MODO CREACIÓN
            inputIsEdit.value = 'false';
            inputId.value = '';
            title.innerHTML = '<i class="fas fa-plus text-green-600 mr-2"></i> Nueva Empresa';
            
            // Mostrar Select2, ocultar input readonly
            selectContainer.classList.remove('hidden');
            inputContainer.classList.add('hidden');
            selectCcte.attr('required', 'required');
            inputReadonly.value = '';
            document.getElementById('empresa_activo').checked = true;
        }

        modal.classList.remove('hidden');
        modal.style.display = 'flex';
    }

    cerrarModalEmpresa() {
        const modal = document.getElementById('modalEmpresa');
        if (modal) {
            modal.classList.add('hidden');
            modal.style.display = 'none';
        }
        document.getElementById('formEmpresa').reset();
    }

    async guardarFormEmpresa(e) {
        e.preventDefault();

        const btnGuardar = document.getElementById('btnGuardarEmpresa');
        const textoOriginal = btnGuardar.innerHTML;
        btnGuardar.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Guardando...';
        btnGuardar.disabled = true;

        const isEdit = document.getElementById('empresa_is_edit').value === 'true';
        const id = document.getElementById('empresa_id').value;
        const ruc = isEdit ? document.getElementById('empresa_ccte_readonly').value : $('#empresa_ccte').val();

        const payload = {
            id: isEdit ? id : null,
            ruc: ruc,
            nombre: document.getElementById('empresa_nombre').value.trim(),
            serie_cpe_ft: document.getElementById('empresa_serie').value.trim().toUpperCase(),
            guifac_url: document.getElementById('empresa_url').value.trim(),
            guifac_token: document.getElementById('empresa_token').value.trim(),
            activo: document.getElementById('empresa_activo').checked ? 1 : 0
        };

        try {
            const response = await this.service.guardarEmpresa(payload);
            if (response.success) {
                Swal.fire({ icon: 'success', title: '¡Éxito!', text: response.message, timer: 1500, showConfirmButton: false });
                this.cerrarModalEmpresa();
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

    toggleEstadoEmpresa(id, estadoActual) {
        const estadoNum = parseInt(estadoActual);
        const accionText = estadoNum === 1 ? 'desactivar' : 'activar';
        const confirmColor = estadoNum === 1 ? '#ef4444' : '#10b981';

        Swal.fire({
            title: `¿Deseas ${accionText} esta empresa?`,
            text: `La empresa cambiará su estado de acceso/actividad.`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: confirmColor,
            cancelButtonColor: '#6b7280',
            confirmButtonText: `Sí, ${accionText}`,
            cancelButtonText: 'Cancelar'
        }).then(async (result) => {
            if (result.isConfirmed) {
                try {
                    const response = await this.service.cambiarEstado(id);
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

    eliminarEmpresa(id) {
        Swal.fire({
            title: `¿Deseas eliminar esta empresa?`,
            text: `Esta acción no se puede deshacer y eliminará las configuraciones de facturación asociadas.`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#ef4444',
            cancelButtonColor: '#6b7280',
            confirmButtonText: `Sí, eliminar`,
            cancelButtonText: 'Cancelar'
        }).then(async (result) => {
            if (result.isConfirmed) {
                try {
                    const response = await this.service.eliminarEmpresa(id);
                    if (response.success) {
                        Swal.fire({ icon: 'success', title: 'Empresa eliminada', timer: 1500, showConfirmButton: false });
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
window.empresasController = new EmpresasController();
document.addEventListener('DOMContentLoaded', () => {
    window.empresasController.init();
});
