/**
 * ConfigApisController — Orquestador de la gestión de APIs
 */
class ConfigApisController {
    constructor() {
        this.service = new ConfigApisService();
        this.tableBody = document.getElementById('tableBodyApis');
        this.modal = document.getElementById('modalApi');
        this.modalToken = document.getElementById('modalToken');
        this.form = document.getElementById('formApi');
        this.formToken = document.getElementById('formToken');
        this.modalTitle = document.getElementById('modalApiTitle');
        this.dataTable = null;
    }

    async init() {
        this.vincularEventos();
        await this.cargarTabla();
    }

    vincularEventos() {
        // Botón nuevo registro
        document.getElementById('btnNuevoApi')?.addEventListener('click', () => this.abrirModalCrear());
        
        // Cerrar y Cancelar Modal Principal
        document.getElementById('btnCerrarModalApi')?.addEventListener('click', () => this.cerrarModal());
        document.getElementById('btnCancelarApi')?.addEventListener('click', () => this.cerrarModal());
        
        // Cerrar y Cancelar Modal Token
        document.getElementById('btnCerrarModalToken')?.addEventListener('click', () => this.cerrarModalToken());
        document.getElementById('btnCancelarToken')?.addEventListener('click', () => this.cerrarModalToken());

        // Eventos Submit
        this.form.addEventListener('submit', (e) => this.procesarGuardado(e));
        this.formToken.addEventListener('submit', (e) => this.procesarActualizarToken(e));

        // Delegación de eventos en la tabla
        this.tableBody.addEventListener('click', (e) => {
            const btnEditar = e.target.closest('.btn-editar');
            const btnToken = e.target.closest('.btn-token');
            const btnEliminar = e.target.closest('.btn-eliminar');

            if (btnEditar) this.abrirModalEditar(btnEditar.dataset.id);
            if (btnToken) this.abrirModalToken(btnToken.dataset.id, btnToken.dataset.nombre);
            if (btnEliminar) this.eliminarApi(btnEliminar.dataset.id);
        });
    }

    async cargarTabla() {
        const tableElement = $('#dataTableApis');
        if (!tableElement.length) return;

        // Si ya existe la instancia de DataTable, la destruimos
        if ($.fn.DataTable.isDataTable(tableElement)) {
            tableElement.DataTable().destroy();
        }

        this.tableBody.innerHTML = `
            <tr>
                <td colspan="4" class="text-center py-8 text-gray-500">
                    <i class="fas fa-spinner fa-spin mr-2 text-purple-600 text-lg"></i>Cargando APIs...
                </td>
            </tr>
        `;

        const response = await this.service.listar();
        if (response.success) {
            const apis = response.data || [];
            if (apis.length === 0) {
                this.tableBody.innerHTML = `
                    <tr>
                        <td colspan="4" class="text-center py-8 text-gray-500 italic">
                            No hay APIs registradas en el sistema.
                        </td>
                    </tr>
                `;
                return;
            }

            this.tableBody.innerHTML = apis.map((api, index) => `
                <tr class="hover:bg-purple-50 transition-colors">
                    <td class="px-4 py-3 text-center text-gray-500 font-medium">${index + 1}</td>
                    <td class="px-4 py-3 font-semibold text-gray-800">${api.nom}</td>
                    <td class="px-4 py-3 text-gray-600 font-mono text-xs break-all">${api.ruta}</td>
                    <td class="px-4 py-3 text-center">
                        <div class="flex gap-2 justify-center">
                            <button type="button" class="btn-editar bg-blue-100 hover:bg-blue-200 text-blue-600 px-2 py-1.5 rounded-lg transition-colors shadow-sm btn-hover-scale" title="Editar Nombre y Ruta" data-id="${api.id}">
                                <i class="fas fa-edit text-sm"></i>
                            </button>
                            <button type="button" class="btn-token bg-yellow-100 hover:bg-yellow-200 text-yellow-700 px-2 py-1.5 rounded-lg transition-colors shadow-sm btn-hover-scale" title="Actualizar Token" data-id="${api.id}" data-nombre="${api.nom}">
                                <i class="fas fa-key text-sm"></i>
                            </button>
                            <button type="button" class="btn-eliminar bg-red-100 hover:bg-red-200 text-red-600 px-2 py-1.5 rounded-lg transition-colors shadow-sm btn-hover-scale" title="Eliminar API" data-id="${api.id}">
                                <i class="fas fa-trash-alt text-sm"></i>
                            </button>
                        </div>
                    </td>
                </tr>
            `).join('');

            // Reinicializar DataTables
            tableElement.DataTable({
                pageLength: 10,
                language: { url: 'https://cdn.datatables.net/plug-ins/2.0.8/i18n/es-ES.json' },
                scrollX: true,
                order: [[0, 'asc']],
                autoWidth: false
            });
        } else {
            this.tableBody.innerHTML = `
                <tr>
                    <td colspan="4" class="text-center py-8 text-red-500 font-semibold">
                        Error al cargar las APIs: ${response.message}
                    </td>
                </tr>
            `;
            Swal.fire('Error', response.message, 'error');
        }
    }

    abrirModalCrear() {
        this.form.reset();
        document.getElementById('api_id').value = '';
        document.getElementById('api_is_edit').value = 'false';
        
        // Mostrar contenedor de token y hacerlo requerido
        const tokenContainer = document.getElementById('tokenContainer');
        const inputToken = document.getElementById('api_token');
        if (tokenContainer) tokenContainer.style.display = 'block';
        if (inputToken) {
            inputToken.setAttribute('required', 'required');
            inputToken.disabled = false;
        }

        this.modalTitle.innerHTML = '<i class="fas fa-plus-circle mr-2"></i> Nueva API';
        this.modal.style.display = 'flex';
    }

    async abrirModalEditar(id) {
        this.form.reset();
        const response = await this.service.obtener(id);
        if (response.success && response.data) {
            const api = response.data;
            document.getElementById('api_id').value = api.id;
            document.getElementById('api_is_edit').value = 'true';
            document.getElementById('api_nombre').value = api.nom || '';
            document.getElementById('api_ruta').value = api.ruta || '';

            // Ocultar contenedor de token en edición y quitar requerimiento
            const tokenContainer = document.getElementById('tokenContainer');
            const inputToken = document.getElementById('api_token');
            if (tokenContainer) tokenContainer.style.display = 'none';
            if (inputToken) {
                inputToken.removeAttribute('required');
                inputToken.disabled = true;
            }

            this.modalTitle.innerHTML = '<i class="fas fa-edit mr-2"></i> Editar API';
            this.modal.style.display = 'flex';
        } else {
            Swal.fire('Error', 'No se pudieron cargar los datos de la API.', 'error');
        }
    }

    cerrarModal() {
        this.modal.style.display = 'none';
    }

    abrirModalToken(id, nombre) {
        this.formToken.reset();
        document.getElementById('token_api_id').value = id;
        document.getElementById('token_nombre_display').textContent = nombre;
        this.modalToken.style.display = 'flex';
    }

    cerrarModalToken() {
        this.modalToken.style.display = 'none';
    }

    async procesarGuardado(e) {
        e.preventDefault();

        const payload = {
            id: document.getElementById('api_id').value,
            is_edit: document.getElementById('api_is_edit').value === 'true',
            nom: document.getElementById('api_nombre').value,
            ruta: document.getElementById('api_ruta').value
        };

        // Si es registro nuevo, agregar el token
        if (payload.is_edit === false) {
            payload.token = document.getElementById('api_token').value;
        }

        const response = await this.service.guardar(payload);
        if (response.success) {
            Swal.fire({
                icon: 'success',
                title: '¡Guardado!',
                text: response.message,
                timer: 2000,
                showConfirmButton: false
            });
            this.cerrarModal();
            this.cargarTabla();
        } else {
            Swal.fire('Error', response.message, 'error');
        }
    }

    async procesarActualizarToken(e) {
        e.preventDefault();

        const id = document.getElementById('token_api_id').value;
        const token = document.getElementById('api_nuevo_token').value;

        const response = await this.service.actualizarToken(id, token);
        if (response.success) {
            Swal.fire({
                icon: 'success',
                title: '¡Actualizado!',
                text: response.message,
                timer: 2000,
                showConfirmButton: false
            });
            this.cerrarModalToken();
        } else {
            Swal.fire('Error', response.message, 'error');
        }
    }

    async eliminarApi(id) {
        const result = await Swal.fire({
            title: '¿Estás seguro?',
            text: 'Esta acción no se puede deshacer y eliminará permanentemente la configuración de esta API.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#ef4444',
            cancelButtonColor: '#6b7280',
            confirmButtonText: 'Sí, eliminar',
            cancelButtonText: 'Cancelar'
        });

        if (result.isConfirmed) {
            const response = await this.service.eliminar(id);
            if (response.success) {
                Swal.fire({
                    icon: 'success',
                    title: 'Eliminado',
                    text: response.message,
                    timer: 1500,
                    showConfirmButton: false
                });
                this.cargarTabla();
            } else {
                Swal.fire('Error', response.message, 'error');
            }
        }
    }
}

// Inicializar el controlador
window.configApisController = new ConfigApisController();
document.addEventListener('DOMContentLoaded', () => {
    window.configApisController.init();
});
