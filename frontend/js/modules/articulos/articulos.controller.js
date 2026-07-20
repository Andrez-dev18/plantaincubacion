/**
 * ArticulosController — Orquestador del módulo de Artículos
 */
class ArticulosController {
    constructor() {
        this.service = new ArticulosService();
        this.dataTableGestion = null;
    }

    async init() {
        this.setupEventListeners();
        await this.renderizarTablaArticulos();
    }

    setupEventListeners() {
        // Botón "Nuevo Registro" (Disparador del Modal)
        document.getElementById('btnNuevo')?.addEventListener('click', () => this.abrirModalArticulo());

        // Cancelar y cerrar Modal Artículo
        document.getElementById('btnCerrarModalArticulo')?.addEventListener('click', () => this.cerrarModalArticulo());
        document.getElementById('btnCancelarArticulo')?.addEventListener('click', () => this.cerrarModalArticulo());

        // Intercepción del Submit del Formulario Principal (Crear/Editar)
        document.getElementById('formArticulo')?.addEventListener('submit', (e) => this.guardarFormArticulo(e));
    }

    async renderizarTablaArticulos() {
        const tableElement = $('#dataTableArticulos');
        if (!tableElement.length) return;

        if ($.fn.DataTable.isDataTable(tableElement)) {
            tableElement.DataTable().clear().destroy();
        }

        this.dataTableGestion = tableElement.DataTable({
            processing: true,
            serverSide: true, // Paginación desde el servidor
            ajax: (dataRequests, callback) => {
                const pageSize = dataRequests.length || 25;
                const page = Math.floor((dataRequests.start || 0) / pageSize) + 1;
                const q = dataRequests.search?.value || '';

                this.service.getArticulos({ q: q, page: page, pageSize: pageSize })
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
                        console.error("Error al cargar la tabla de artículos:", error);
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
                { data: 'codigo', className: 'text-center font-semibold' },
                { data: 'descri', className: 'text-left font-medium' },
                { data: 'unidad', className: 'text-left font-semibold' },
                { data: 'lin', className: 'text-center' },
                {
                    data: 'preuni',
                    className: 'text-right',
                    render: (data) => parseFloat(data || 0).toFixed(3)
                },
                {
                    data: 'igv',
                    className: 'text-center',
                    render: (data) => `${parseFloat(data || 0).toFixed(2)}%`
                },
                {
                    data: 'estado',
                    className: 'text-center',
                    render: (data) => {
                        const estadoStr = String(data).trim().toUpperCase();
                        if (estadoStr === 'A') {
                            return `<span class="bg-green-100 text-green-700 px-2 py-1 rounded text-xs font-bold border border-green-200">Activo</span>`;
                        }
                        return `<span class="bg-red-100 text-red-800 px-2 py-1 rounded text-xs font-bold border border-red-200">Inactivo</span>`;
                    }
                },
                {
                    data: null,
                    orderable: false,
                    className: 'text-center',
                    render: (data, type, row) => `
                        <div class="flex gap-2 justify-center">
                            <button type="button" class="btn-editar-articulo bg-blue-100 hover:bg-blue-200 text-blue-600 px-2 py-1.5 rounded transition-colors shadow-sm" title="Editar Artículo" data-codigo="${row.codigo}">
                                <i class="fas fa-edit"></i>
                            </button>
                            <button type="button" class="btn-eliminar-articulo bg-orange-100 hover:bg-orange-200 text-orange-600 px-2 py-1.5 rounded transition-colors shadow-sm" title="Eliminar Artículo" data-codigo="${row.codigo}">
                                <i class="fas fa-trash-alt"></i>
                            </button>
                        </div>
                    `
                }
            ],
            scrollX: true,
            responsive: false,
            pageLength: 10,
            language: { url: 'https://cdn.datatables.net/plug-ins/2.0.8/i18n/es-ES.json' }
        });

        // Configuración de delegación de clicks mediante jQuery
        const self = this;
        tableElement.off('click', '.btn-editar-articulo').on('click', '.btn-editar-articulo', function () {
            self.abrirModalArticulo($(this).attr('data-codigo'));
        });
        tableElement.off('click', '.btn-eliminar-articulo').on('click', '.btn-eliminar-articulo', function () {
            self.eliminarArticulo($(this).attr('data-codigo'));
        });
    }

    async abrirModalArticulo(codigo = null) {
        const modal = document.getElementById('modalArticulo');
        const form = document.getElementById('formArticulo');
        const title = document.getElementById('modalArticuloTitle');
        const inputIsEdit = document.getElementById('articulo_is_edit');
        const inputCodigo = document.getElementById('aCodigo');

        form.reset();

        if (codigo) {
            // MODO EDICIÓN
            inputIsEdit.value = 'true';
            title.innerHTML = '<i class="fas fa-edit text-blue-600 mr-2"></i> Editar Artículo';
            inputCodigo.readOnly = true;
            inputCodigo.classList.add('bg-gray-100', 'cursor-not-allowed');

            // Cargar datos del artículo
            const response = await this.service.obtenerArticulo(codigo);
            if (response.success && response.data) {
                inputCodigo.value = response.data.codigo || '';
                document.getElementById('aDescri').value = response.data.descri || '';
                document.getElementById('aUnidad').value = response.data.unidad || '';
                document.getElementById('aPeso').value = response.data.peso || '';
                document.getElementById('aLin').value = response.data.lin || '';
                document.getElementById('aCuenta').value = response.data.cuenta || '';
                document.getElementById('aCtacos').value = response.data.ctacos || '';
                document.getElementById('aCtacar').value = response.data.ctacar || '';
                document.getElementById('aCtaabo').value = response.data.ctaabo || '';
                document.getElementById('aCventa').value = response.data.c_venta || '';
                document.getElementById('aCtanue').value = response.data.ctanue || '';
                document.getElementById('aPreuni').value = response.data.preuni || '';
                document.getElementById('aPreven').value = response.data.preven || '';
                document.getElementById('aPredol').value = response.data.predol || '';
                document.getElementById('aIgv').value = response.data.igv || '18';
                document.getElementById('aSafestk').value = response.data.safestk || '';
                document.getElementById('aHibri').value = response.data.hibri || '';
                document.getElementById('aTipo').value = response.data.tipo || '';
                document.getElementById('aFchpre').value = response.data.fchpre || '';
                document.getElementById('aFchvcto').value = response.data.fchvcto || '';
                document.getElementById('aEstado').value = response.data.estado || 'A';
                document.getElementById('aTactivo').value = response.data.tactivo || 'S';
            } else {
                Swal.fire('Error', 'No se pudieron cargar los datos del artículo.', 'error');
                return;
            }
        } else {
            // MODO CREACIÓN
            inputIsEdit.value = 'false';
            title.innerHTML = '<i class="fas fa-plus text-green-600 mr-2"></i> Nuevo Artículo';
            inputCodigo.readOnly = false;
            inputCodigo.classList.remove('bg-gray-100', 'cursor-not-allowed');
            document.getElementById('aIgv').value = '18';
            document.getElementById('aEstado').value = 'A';
            document.getElementById('aTactivo').value = 'S';
        }

        modal.classList.remove('hidden');
        modal.style.display = 'flex';
    }

    cerrarModalArticulo() {
        const modal = document.getElementById('modalArticulo');
        if (modal) {
            modal.classList.add('hidden');
            modal.style.display = 'none';
        }
        document.getElementById('formArticulo').reset();
    }

    async guardarFormArticulo(e) {
        e.preventDefault();

        const btnGuardar = document.getElementById('btnGuardarArticulo');
        const textoOriginal = btnGuardar.innerHTML;
        btnGuardar.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Guardando...';
        btnGuardar.disabled = true;

        const isEdit = document.getElementById('articulo_is_edit').value === 'true';

        const payload = {
            is_edit: isEdit,
            codigo: document.getElementById('aCodigo').value.trim(),
            descri: document.getElementById('aDescri').value.trim(),
            unidad: document.getElementById('aUnidad').value.trim(),
            peso: document.getElementById('aPeso').value.trim(),
            lin: document.getElementById('aLin').value.trim(),
            cuenta: document.getElementById('aCuenta').value.trim(),
            ctacos: document.getElementById('aCtacos').value.trim(),
            ctacar: document.getElementById('aCtacar').value.trim(),
            ctaabo: document.getElementById('aCtaabo').value.trim(),
            c_venta: document.getElementById('aCventa').value.trim(),
            ctanue: document.getElementById('aCtanue').value.trim(),
            preuni: parseFloat(document.getElementById('aPreuni').value) || 0,
            preven: parseFloat(document.getElementById('aPreven').value) || 0,
            predol: parseFloat(document.getElementById('aPredol').value) || 0,
            igv: parseFloat(document.getElementById('aIgv').value) || 0,
            safestk: parseFloat(document.getElementById('aSafestk').value) || 0,
            hibri: document.getElementById('aHibri').value.trim(),
            tipo: document.getElementById('aTipo').value.trim(),
            fchpre: document.getElementById('aFchpre').value,
            fchvcto: document.getElementById('aFchvcto').value,
            estado: document.getElementById('aEstado').value,
            tactivo: document.getElementById('aTactivo').value
        };

        try {
            const response = await this.service.guardarArticulo(payload);
            if (response.success) {
                Swal.fire({ icon: 'success', title: '¡Éxito!', text: response.message, timer: 1500, showConfirmButton: false });
                this.cerrarModalArticulo();
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

    eliminarArticulo(codigo) {
        Swal.fire({
            title: `¿Deseas eliminar este artículo?`,
            text: `Esta acción no se puede deshacer y eliminará el artículo ${codigo} permanentemente.`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#ef4444',
            cancelButtonColor: '#6b7280',
            confirmButtonText: `Sí, eliminar`,
            cancelButtonText: 'Cancelar'
        }).then(async (result) => {
            if (result.isConfirmed) {
                try {
                    const response = await this.service.eliminarArticulo(codigo);
                    if (response.success) {
                        Swal.fire({ icon: 'success', title: 'Artículo eliminado', timer: 1500, showConfirmButton: false });
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
window.articulosController = new ArticulosController();
document.addEventListener('DOMContentLoaded', () => {
    window.articulosController.init();
});
