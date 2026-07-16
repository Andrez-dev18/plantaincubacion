/**
 * GestionUsuariosController — Orquestador Dinámico del Frontend (PIC)
 */
class GestionUsuariosController {
    constructor() {
        this.service = new GestionUsuariosService();
        this.dataTableGestion = null;
    }

    async init() {
        this.setupEventListeners();
        await this.renderizarTablaGestionUsuarios();
    }

    setupEventListeners() {
        // Botón "Nuevo Registro" (Disparador del Modal)
        document.getElementById('btnNuevo')?.addEventListener('click', () => this.abrirModalUsuario());

        // Cancelar y cerrar Modal Usuario
        document.getElementById('btnCerrarModalUsuario')?.addEventListener('click', () => this.cerrarModalUsuario());
        document.getElementById('btnCancelarUsuario')?.addEventListener('click', () => this.cerrarModalUsuario());

        // Intercepción del Submit del Formulario Principal (Crear/Editar)
        document.getElementById('formUsuario')?.addEventListener('submit', (e) => this.guardarFormUsuario(e));

        // Cancelar y cerrar Modal Reset Pass
        document.getElementById('btnCerrarModalPass')?.addEventListener('click', () => this.cerrarModalResetPass());
        document.getElementById('btnCancelarPass')?.addEventListener('click', () => this.cerrarModalResetPass());

        // Intercepción del Reset del Administrador para la Contraseña
        document.getElementById('formResetPass')?.addEventListener('submit', (e) => this.procesarResetPass(e));
    }

    // ─────────────────────────────────────────────────────────────────────
    // GRILLA PRINCIPAL (DataTables Server-Side con Roles PIC)
    // ─────────────────────────────────────────────────────────────────────
    async renderizarTablaGestionUsuarios() {
        const tableElement = $('#dataTableUser');
        if (!tableElement.length) return;

        if ($.fn.DataTable.isDataTable(tableElement)) {
            tableElement.DataTable().clear().destroy();
        }

        this.dataTableGestion = tableElement.DataTable({
            processing: true,
            serverSide: true,
            ajax: async (dataRequests, callback) => {
                try {
                    const response = await this.service.getGestionUsuarios(dataRequests);
                    
                    // Mostrar dinámicamente la cantidad total de usuarios en el badge superior
                    const total = response.recordsTotal || 0;
                    const badge = document.getElementById('lblTotal');
                    if (badge) {
                        badge.textContent = `${total} usuario${total !== 1 ? 's' : ''}`;
                    }

                    callback({
                        draw: dataRequests.draw,
                        recordsTotal: response.recordsTotal,
                        recordsFiltered: response.recordsFiltered,
                        data: response.data
                    });
                } catch (error) {
                    console.error("Error al cargar la tabla server-side:", error);
                    callback({ draw: dataRequests.draw, recordsTotal: 0, recordsFiltered: 0, data: [] });
                }
            },
            columns: [
                {
                    data: null,
                    orderable: false,
                    className: 'text-center',
                    render: (data, type, row, meta) => meta.row + meta.settings._iDisplayStart + 1
                },
                { data: 'codigo', className: 'text-center' },
                { data: 'nombre', className: 'text-left' },
                { 
                    data: 'nombres_roles', 
                    className: 'text-left',
                    render: (data) => {
                        if (!data || data === '') return '<span class="text-gray-400 text-xs italic">Sin roles</span>';
                        return data.split('||').map(rol => 
                            `<span class="inline-flex items-center bg-blue-50 text-blue-700 text-[10px] font-semibold px-2 py-0.5 rounded-md border border-blue-200 mr-1 my-0.5">${rol}</span>`
                        ).join('');
                    }
                },
                {
                    data: 'activo',
                    className: 'text-left',
                    render: (data) => parseInt(data) === 1
                        ? '<span class="bg-green-100 text-green-700 px-2 py-1 rounded text-xs font-bold border border-green-200">ACTIVO</span>'
                        : '<span class="bg-red-100 text-red-700 px-2 py-1 rounded text-xs font-bold border border-red-200">INACTIVO</span>'
                },
                {
                    data: null,
                    orderable: false,
                    className: 'text-center',
                    render: (data, type, row) => `
                        <div class="flex gap-2 justify-center">
                            <button type="button" class="btn-editar-user bg-blue-100 hover:bg-blue-200 text-blue-600 px-2 py-1.5 rounded transition-colors shadow-sm" title="Editar Nombre" data-codigo="${row.codigo}">
                                <i class="fas fa-edit"></i>
                            </button>
                            <button type="button" class="btn-reset-pass bg-yellow-100 hover:bg-yellow-200 text-yellow-700 px-2 py-1.5 rounded transition-colors shadow-sm" title="Cambiar Contraseña" data-codigo="${row.codigo}" data-nombre="${row.nombre}">
                                <i class="fas fa-key"></i>
                            </button>
                            <button type="button" class="btn-toggle-estado ${parseInt(row.activo) === 1 ? 'bg-red-100 hover:bg-red-200 text-red-600' : 'bg-green-100 hover:bg-green-200 text-green-600'} px-2 py-1.5 rounded transition-colors shadow-sm" title="${parseInt(row.activo) === 1 ? 'Desactivar' : 'Activar'} Usuario" data-codigo="${row.codigo}" data-estado="${row.activo}">
                                <i class="fas fa-power-off"></i>
                            </button>
                        </div>
                    `
                }
            ],
            order: [[1, 'asc']],
            scrollX: true,
            responsive: false,
            pageLength: 10,
            language: { url: 'https://cdn.datatables.net/plug-ins/2.0.8/i18n/es-ES.json' }
        });

        // Configuración de delegación de clicks mediante jQuery
        const self = this;
        tableElement.off('click', '.btn-editar-user').on('click', '.btn-editar-user', function () {
            self.abrirModalUsuario($(this).attr('data-codigo'));
        });
        tableElement.off('click', '.btn-reset-pass').on('click', '.btn-reset-pass', function () {
            self.abrirModalResetPass($(this).attr('data-codigo'), $(this).attr('data-nombre'));
        });
        tableElement.off('click', '.btn-toggle-estado').on('click', '.btn-toggle-estado', function () {
            self.toggleEstadoUsuario($(this).attr('data-codigo'), $(this).attr('data-estado'));
        });
    }

    // ─────────────────────────────────────────────────────────────────────
    // GESTIÓN DEL MODAL PRINCIPAL
    // ─────────────────────────────────────────────────────────────────────
    async abrirModalUsuario(codigo = null) {
        const modal = document.getElementById('modalUsuario');
        const form = document.getElementById('formUsuario');
        const title = document.getElementById('modalUsuarioTitle');
        const passContainer = document.getElementById('passwordContainer');
        const inputIsEdit = document.getElementById('user_is_edit');
        const inputCodigo = document.getElementById('user_codigo');
        const inputPassword = document.getElementById('user_password');

        form.reset();

        if (codigo) {
            // MODO EDICIÓN
            inputIsEdit.value = 'true';
            title.innerHTML = '<i class="fas fa-user-edit text-blue-600 mr-2"></i> Editar Usuario';
            passContainer.style.display = 'none';
            inputCodigo.readOnly = true;
            inputCodigo.classList.add('bg-gray-100', 'cursor-not-allowed');
            inputPassword.removeAttribute('required');

            // Cargar datos
            const response = await this.service.obtenerUsuario(codigo);
            if (response.success && response.data) {
                inputCodigo.value = response.data.codigo || '';
                document.getElementById('user_nombre').value = response.data.nombre || '';
                document.getElementById('user_ruc').value = response.data.ruc || '';
            } else {
                Swal.fire('Error', 'No se pudieron cargar los datos del usuario.', 'error');
                return;
            }
        } else {
            // MODO CREACIÓN
            inputIsEdit.value = 'false';
            title.innerHTML = '<i class="fas fa-user-plus text-green-600 mr-2"></i> Nuevo Usuario';
            passContainer.style.display = 'block';
            inputCodigo.readOnly = false;
            inputCodigo.classList.remove('bg-gray-100', 'cursor-not-allowed');
            inputPassword.setAttribute('required', 'required');
            document.getElementById('user_ruc').value = '';
        }

        modal.classList.remove('hidden');
        modal.style.display = 'flex';
    }

    cerrarModalUsuario() {
        const modal = document.getElementById('modalUsuario');
        if (modal) {
            modal.classList.add('hidden');
            modal.style.display = 'none';
        }
        document.getElementById('formUsuario').reset();
    }

    async guardarFormUsuario(e) {
        e.preventDefault();

        const btnGuardar = document.getElementById('btnGuardarUsuario');
        const textoOriginal = btnGuardar.innerHTML;
        btnGuardar.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Guardando...';
        btnGuardar.disabled = true;

        const isEdit = document.getElementById('user_is_edit').value === 'true';
        const payload = {
            is_edit: isEdit ? '1' : '0',
            codigo: document.getElementById('user_codigo').value.trim(),
            nombre: document.getElementById('user_nombre').value.trim(),
            ruc: document.getElementById('user_ruc').value.trim(),
            password: document.getElementById('user_password').value
        };

        try {
            const response = await this.service.guardarUsuario(payload);
            if (response.success) {
                Swal.fire({ icon: 'success', title: '¡Éxito!', text: response.message, timer: 1500, showConfirmButton: false });
                this.cerrarModalUsuario();
                if (this.dataTableGestion) this.dataTableGestion.ajax.reload(null, false);
            } else {
                Swal.fire('Error', response.message, 'error');
            }
        } catch (error) {
            Swal.fire('Error', 'Hubo un problema de conexión.', 'error');
        } finally {
            btnGuardar.innerHTML = textoOriginal;
            btnGuardar.disabled = false;
        }
    }

    // ─────────────────────────────────────────────────────────────────────
    // ESTADOS Y CONTRASEÑAS SECUNDARIAS
    // ─────────────────────────────────────────────────────────────────────
    toggleEstadoUsuario(codigo, estadoActual) {
        const estadoNum = parseInt(estadoActual);
        const accionText = estadoNum === 1 ? 'desactivar' : 'activar';
        const confirmColor = estadoNum === 1 ? '#ef4444' : '#10b981';

        Swal.fire({
            title: `¿Deseas ${accionText} este usuario?`,
            text: `El usuario ${codigo} cambiará su estado de acceso.`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: confirmColor,
            cancelButtonColor: '#6b7280',
            confirmButtonText: `Sí, ${accionText}`,
            cancelButtonText: 'Cancelar'
        }).then(async (result) => {
            if (result.isConfirmed) {
                try {
                    const response = await this.service.cambiarEstado(codigo);
                    if (response.success) {
                        Swal.fire({ icon: 'success', title: 'Estado actualizado', timer: 1500, showConfirmButton: false });
                        if (this.dataTableGestion) this.dataTableGestion.ajax.reload(null, false);
                    } else {
                        Swal.fire('Error', response.message, 'error');
                    }
                } catch (e) {
                    Swal.fire('Error', 'No se pudo procesar la solicitud.', 'error');
                }
            }
        });
    }

    abrirModalResetPass(codigo, nombre) {
        document.getElementById('formResetPass').reset();
        document.getElementById('reset_codigo').value = codigo;
        document.getElementById('reset_nombre_display').textContent = `${nombre} (${codigo})`;

        const modal = document.getElementById('modalResetPass');
        modal.classList.remove('hidden');
        modal.style.display = 'flex';
    }

    cerrarModalResetPass() {
        const modal = document.getElementById('modalResetPass');
        if (modal) {
            modal.classList.add('hidden');
            modal.style.display = 'none';
        }
    }

    async procesarResetPass(e) {
        e.preventDefault();

        const codigo = document.getElementById('reset_codigo').value;
        const newPassword = document.getElementById('reset_password').value;

        const btnGuardar = document.getElementById('btnGuardarPass');
        const textoOriginal = btnGuardar.innerHTML;
        btnGuardar.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Guardando...';
        btnGuardar.disabled = true;

        try {
            const response = await this.service.resetPassword(codigo, newPassword);
            if (response.success) {
                Swal.fire({ icon: 'success', title: 'Contraseña Actualizada', text: `Se cambió la contraseña de ${codigo}`, timer: 1500, showConfirmButton: false });
                this.cerrarModalResetPass();
            } else {
                Swal.fire('Error', response.message, 'error');
            }
        } catch (error) {
            Swal.fire('Error', 'Hubo un problema de conexión.', 'error');
        } finally {
            btnGuardar.innerHTML = textoOriginal;
            btnGuardar.disabled = false;
        }
    }
}

// Inicializar el controlador global
window.gestionUsuariosController = new GestionUsuariosController();
document.addEventListener('DOMContentLoaded', () => {
    window.gestionUsuariosController.init();
});