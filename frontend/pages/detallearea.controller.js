class DetalleAreaController {
    constructor() {
        this.service = new DetalleAreaService();
        this.registroSeleccionado = null;
        this.datos = [];
        this.catalogos = {};
        this.dataTable = null;
        this.columnasVisibles = {};
    }

    async init() {
        await this.cargarCatalogos();
        this.setupEventListeners();
        this.setupColumnToggle();
        this.setupToggleFiltros();
        this.establecerFiltrosIniciales();
    }

    establecerFiltrosIniciales() {
        // Establecer fecha actual
        const now = new Date();
        const offset = now.getTimezoneOffset() * 60000;
        const localDate = new Date(now.getTime() - offset);
        const hoy = localDate.toISOString().split('T')[0];
        
        // Configurar período a "POR_FECHA" con fecha actual
        const periodoTipo = document.getElementById('periodoTipo');
        if (periodoTipo) {
            periodoTipo.value = 'POR_FECHA';
            periodoTipo.dispatchEvent(new Event('change'));
        }
        
        const fechaUnica = document.getElementById('fechaUnica');
        if (fechaUnica) {
            fechaUnica.value = hoy;
        }
        
        // Resetear filtros de Provincia y Área
        document.getElementById('filterProvincia').value = '';
        document.getElementById('filterArea').value = '';
        this.limpiarTodosSelects();
    }

    setupColumnToggle() {
        const btnToggle = document.getElementById('btnToggleColumns');
        const dropdown = document.getElementById('columnDropdown');
        const btnClose = document.getElementById('btnCloseDropdown');
        const checkboxes = document.querySelectorAll('.column-checkbox');

        checkboxes.forEach(checkbox => {
            const columnIndex = parseInt(checkbox.dataset.column);
            this.columnasVisibles[columnIndex] = checkbox.checked;
        });

        btnToggle?.addEventListener('click', (e) => {
            e.preventDefault();
            e.stopPropagation();
            dropdown.classList.toggle('show');
        });

        btnClose?.addEventListener('click', (e) => {
            e.preventDefault();
            e.stopPropagation();
            dropdown.classList.remove('show');
        });

        document.addEventListener('click', (e) => {
            if (!e.target.closest('.dropdown-columns')) {
                dropdown.classList.remove('show');
            }
        });

        checkboxes.forEach(checkbox => {
            checkbox.addEventListener('change', (e) => {
                e.stopPropagation();
                const columnIndex = parseInt(e.target.dataset.column);
                this.columnasVisibles[columnIndex] = e.target.checked;
                
                if (this.dataTable) {
                    const column = this.dataTable.column(columnIndex);
                    if (column) {
                        column.visible(e.target.checked);
                    }
                }
            });
        });
    }

    setupToggleFiltros() {
        const btnToggle = document.getElementById('btnToggleFiltros');
        const filterContent = document.getElementById('filterContent');

        if (!btnToggle || !filterContent) return;

        filterContent.classList.add('show');
        
        btnToggle.addEventListener('click', () => {
            const isOpen = filterContent.classList.contains('show');
            const icon = btnToggle.querySelector('i');
            
            if (isOpen) {
                filterContent.classList.remove('show');
                icon.classList.remove('fa-chevron-up');
                icon.classList.add('fa-chevron-down');
            } else {
                filterContent.classList.add('show');
                icon.classList.remove('fa-chevron-down');
                icon.classList.add('fa-chevron-up');
            }
        });
    }

    async cargarCatalogos() {
        try {
            const [provincias, areas] = await Promise.all([
                this.service.getProvincias(),
                this.service.getAreas()
            ]);

            this.catalogos = { provincias, areas };
            this.poblarSelects();
        } catch (error) {
            console.error('Error al cargar catálogos:', error);
            this.mostrarNotificacion('Error al cargar catálogos', 'error');
        }
    }

    poblarSelects() {
        this.poblarSelect('filterProvincia', this.catalogos.provincias, 'id', 'provincia');
        this.poblarSelect('filterArea', this.catalogos.areas, 'id', 'nombre');
        this.poblarSelect('modalProvincia', this.catalogos.provincias, 'id', 'provincia');
        this.poblarSelect('modalArea', this.catalogos.areas, 'id', 'nombre');
    }

    poblarSelect(selectId, datos, valueField, textField) {
        const select = document.getElementById(selectId);
        if (!select) return;

        const opciones = datos.map(item =>
            `<option value="${item[valueField]}">${item[textField]}</option>`
        ).join('');

        if (selectId.startsWith('filter')) {
            select.innerHTML = `<option value="">Todos</option>${opciones}`;
        } else {
            select.innerHTML = `<option value="">Seleccionar...</option>${opciones}`;
        }
    }

    setupEventListeners() {
        document.getElementById('btnNuevo')?.addEventListener('click', () => this.mostrarModalNuevo());
        document.getElementById('btnLimpiarFiltros')?.addEventListener('click', () => this.limpiarFiltros());
        document.getElementById('btnAplicarFiltros')?.addEventListener('click', () => this.aplicarFiltros());
        document.getElementById('btnGuardar')?.addEventListener('click', () => this.guardarRegistro());
        document.getElementById('btnCancelar')?.addEventListener('click', () => this.cerrarModal());
        document.getElementById('btnExportar')?.addEventListener('click', () => this.exportarExcel());
    }

    async cargarDatos() {
        try {
            this.mostrarCargando(true);
            this.datos = await this.service.getAll();
            this.renderizarTabla();
            this.mostrarNotificacion(`${this.datos.length} registros cargados`, 'success');
        } catch (error) {
            console.error('Error:', error);
            this.mostrarNotificacion('Error al cargar datos: ' + error.message, 'error');
            this.datos = [];
            this.renderizarTabla();
        } finally {
            this.mostrarCargando(false);
        }
    }

    async aplicarFiltros() {
        try {
            this.mostrarCargando(true);

            const filtros = {};
            const fechas = window.obtenerFechasPorPeriodo ? window.obtenerFechasPorPeriodo() : {};
            const provincia = document.getElementById('filterProvincia').value;
            const area = document.getElementById('filterArea').value;

            if (fechas.fechaInicio) filtros.fechaInicio = fechas.fechaInicio;
            if (fechas.fechaFin) filtros.fechaFin = fechas.fechaFin;
            if (provincia) filtros.provincia = provincia;
            if (area) filtros.area = area;

            if (!fechas.fechaInicio && !fechas.fechaFin && !provincia && !area) {
                await this.cargarDatos();
                return;
            }

            const resultado = await this.service.filtrar(filtros);
            
            if (Array.isArray(resultado)) {
                this.datos = resultado;
            } else if (resultado && Array.isArray(resultado.data)) {
                this.datos = resultado.data;
            } else {
                this.datos = [];
            }

            this.renderizarTabla();
            this.mostrarNotificacion(`Filtrados: ${this.datos.length} registros`, 'success');
            
        } catch (error) {
            console.error('Error al filtrar:', error);
            this.mostrarNotificacion('Error al filtrar: ' + error.message, 'error');
            await this.cargarDatos();
        } finally {
            this.mostrarCargando(false);
        }
    }

    limpiarFiltros() {
        // Establecer fecha actual sin limpiar todo el período
        const now = new Date();
        const offset = now.getTimezoneOffset() * 60000;
        const localDate = new Date(now.getTime() - offset);
        const hoy = localDate.toISOString().split('T')[0];
        
        // Configurar período a "POR_FECHA" con fecha actual
        const periodoTipo = document.getElementById('periodoTipo');
        if (periodoTipo) {
            periodoTipo.value = 'POR_FECHA';
            periodoTipo.dispatchEvent(new Event('change'));
        }
        
        const fechaUnica = document.getElementById('fechaUnica');
        if (fechaUnica) {
            fechaUnica.value = hoy;
        }
        
        // Limpiar filtros de Provincia y Área
        document.getElementById('filterProvincia').value = '';
        document.getElementById('filterArea').value = '';
        this.limpiarTodosSelects();
        
        // Aplicar filtro con fecha actual
        this.aplicarFiltros();
    }

    // Función para limpiar TODOS los selects con Select2
    limpiarTodosSelects() {
        // Selecciona todos los selects que tienen Select2 (por clase o por tag)
        $('.select2-basic').each(function () {
            $(this).val(null).trigger('change'); // Resetea a placeholder
        });

    }

    renderizarTabla() {
        const tbody = document.getElementById('tableBody');
        if (!tbody) return;

        const table = $('.min-w-full');
        
        if ($.fn.DataTable.isDataTable(table)) {
            table.DataTable().destroy();
        }

        tbody.innerHTML = '';
        
        if (this.datos.length === 0) {
            tbody.innerHTML = '<tr><td colspan="14" class="text-center py-8 text-gray-500">No hay registros para mostrar</td></tr>';
            return;
        }

        this.datos.forEach((registro, i) => {
            const tr = document.createElement('tr');
            tr.className = 'hover:bg-blue-50 transition-colors';
            
            const fecha = registro.fecha ? new Date(registro.fecha + 'T00:00:00').toLocaleDateString('es-PE') : '-';
            
            tr.innerHTML = `
                <td class="px-2 py-1 border-b text-sm text-center">${i + 1}</td>
                <td class="px-2 py-1 border-b text-sm text-center">${fecha}</td>
                <td class="px-2 py-1 border-b text-sm">${registro.provincia || '-'}</td>
                <td class="px-2 py-1 border-b text-sm">${registro.area || '-'}</td>
                <td class="px-2 py-1 border-b text-sm text-center">${registro.cod_mot || registro.id || '-'}</td>
                <td class="px-2 py-1 border-b text-sm" style="max-width: 300px; white-space: normal;">${registro.motivo || '-'}</td>
                <td class="px-2 py-1 border-b text-sm text-center">${registro.usuarioReporte || '-'}</td>
                <td class="px-2 py-1 border-b text-sm text-center">${registro.usuarioRegistro || '-'}</td>
                <td class="px-2 py-1 border-b text-sm text-center">${registro.fechaHoraRegistro || '-'}</td>
                <td class="px-2 py-1 border-b text-sm text-center">${registro.usuarioTransferencia || '-'}</td>
                <td class="px-2 py-1 border-b text-sm text-center">${registro.fechaHoraTransferencia || '-'}</td>
                <td class="px-2 py-1 border-b text-sm text-center">${registro.usuarioModificacion || '-'}</td>
                <td class="px-2 py-1 border-b text-sm text-center">${registro.fechaHoraModificacion || '-'}</td>
                <td class="px-2 py-1 border-b text-sm text-center space-x-2">
                    <button class="bg-yellow-400 hover:bg-yellow-500 text-white px-2 py-1 rounded edit-btn" title="Editar">
                        <i class="fas fa-edit"></i>
                    </button>
                    <button class="bg-red-500 hover:bg-red-600 text-white px-2 py-1 rounded delete-btn" title="Eliminar">
                        <i class="fas fa-trash"></i>
                    </button>
                </td>
            `;
            
            const editBtn = tr.querySelector('.edit-btn');
            const deleteBtn = tr.querySelector('.delete-btn');
            
            editBtn.onclick = () => {
                this.registroSeleccionado = this.datos[i];
                this.modificarSeleccionado();
            };
            
            deleteBtn.onclick = async () => {
                this.registroSeleccionado = this.datos[i];
                await this.eliminarSeleccionado();
            };
            
            tbody.appendChild(tr);
        });
        
        setTimeout(() => {
            try {
                this.dataTable = $('.min-w-full').DataTable({
                    pageLength: 10,
                    language: {
                        url: 'https://cdn.datatables.net/plug-ins/2.0.8/i18n/es-ES.json'
                    },
                    responsive: true,
                    order: [[0, 'desc']],
                    scrollX: true,
                    columnDefs: [
                        { targets: [7, 8, 9, 10, 11, 12], visible: false }
                    ],
                    drawCallback: () => {
                        Object.keys(this.columnasVisibles).forEach(columnIndex => {
                            const column = this.dataTable.column(parseInt(columnIndex));
                            if (column) {
                                column.visible(this.columnasVisibles[columnIndex]);
                            }
                        });
                    }
                });

                // Aplicar visibilidad inicial
                Object.keys(this.columnasVisibles).forEach(columnIndex => {
                    const column = this.dataTable.column(parseInt(columnIndex));
                    if (column) {
                        column.visible(this.columnasVisibles[columnIndex]);
                    }
                });
            } catch (error) {
                console.error('Error al inicializar DataTable:', error);
            }
        }, 100);
    }

    mostrarModalNuevo() {
        this.registroSeleccionado = null;
        document.getElementById('modalTitle').textContent = 'Nuevo Registro - Detalle por Área';
        this.limpiarFormulario();
        const modal = document.getElementById('modal');
        modal.classList.remove('hidden');
        modal.style.display = 'flex';
    }

    modificarSeleccionado() {
        if (!this.registroSeleccionado) return;

        document.getElementById('modalTitle').textContent = 'Modificar Registro - Detalle por Área';
        this.cargarDatosEnFormulario();
        const modal = document.getElementById('modal');
        modal.classList.remove('hidden');
        modal.style.display = 'flex';
    }

    async eliminarSeleccionado() {
        if (!this.registroSeleccionado) return;
        if (!confirm('¿Estás seguro de eliminar este registro?')) return;

        try {
            this.mostrarCargando(true);
            await this.service.eliminar(this.registroSeleccionado.id);
            this.mostrarNotificacion('Registro eliminado exitosamente', 'success');
            await this.cargarDatos();
        } catch (error) {
            console.error('Error:', error);
            this.mostrarNotificacion('Error al eliminar: ' + error.message, 'error');
        } finally {
            this.mostrarCargando(false);
        }
    }

    cargarDatosEnFormulario() {
    const r = this.registroSeleccionado;
    
    if (r.fecha) {
        document.getElementById('modalFecha').value = r.fecha;
    }
    
    // Buscar la provincia por código o por nombre
    let provinciaObj = this.catalogos.provincias.find(p => p.id == r.provincia);
    if (!provinciaObj) {
        provinciaObj = this.catalogos.provincias.find(p => p.provincia === r.provincia);
    }
    document.getElementById('modalProvincia').value = provinciaObj ? provinciaObj.id : '';
    
    document.getElementById('modalArea').value = r.area || '';
    document.getElementById('modalMotivo').value = r.motivo || '';
    document.getElementById('modalUsuarioReporte').value = r.usuarioReporte || '';
}


    limpiarFormulario() {
        document.querySelectorAll('#modalForm input, #modalForm select, #modalForm textarea').forEach(input => {
            if (input.type === 'date') {
                input.value = new Date().toISOString().split('T')[0];
            } else {
                input.value = '';
            }
        });
    }

    async guardarRegistro() {
        const data = this.obtenerDatosFormulario();

        if (!this.validarFormulario(data)) return;

        try {
            this.mostrarCargando(true);

            if (this.registroSeleccionado) {
                data.id = this.registroSeleccionado.id;
                await this.service.actualizar(data);
                this.mostrarNotificacion('Registro actualizado exitosamente', 'success');
            } else {
                await this.service.crear(data);
                this.mostrarNotificacion('Registro creado exitosamente', 'success');
            }

            this.cerrarModal();
            await this.cargarDatos();
        } catch (error) {
            console.error('Error al guardar:', error);
            this.mostrarNotificacion('Error al guardar: ' + error.message, 'error');
        } finally {
            this.mostrarCargando(false);
        }
    }

    obtenerDatosFormulario() {
        const provinciaId = document.getElementById('modalProvincia').value || null;

        return {
            fecha: document.getElementById('modalFecha').value,
            provincia: provinciaId,
            area: document.getElementById('modalArea').value,
            motivo: document.getElementById('modalMotivo').value,
            usuarioReporte: document.getElementById('modalUsuarioReporte').value
        };
    }

    validarFormulario(data) {
        if (!data.fecha) {
            this.mostrarNotificacion('La fecha es obligatoria', 'warning');
            return false;
        }
        if (!data.provincia) {
            this.mostrarNotificacion('La provincia es obligatoria', 'warning');
            return false;
        }
        if (!data.area) {
            this.mostrarNotificacion('El área es obligatoria', 'warning');
            return false;
        }
        if (!data.motivo) {
            this.mostrarNotificacion('El motivo es obligatorio', 'warning');
            return false;
        }
        if (!data.usuarioReporte) {
            this.mostrarNotificacion('El usuario que reporta es obligatorio', 'warning');
            return false;
        }
        return true;
    }

    cerrarModal() {
        const modal = document.getElementById('modal');
        modal.classList.add('hidden');
        modal.style.display = 'none';
        this.limpiarTodosSelects();
        this.registroSeleccionado = null;
    }

    mostrarCargando(mostrar) {
        const loading = document.getElementById('loading');
        if (loading) {
            loading.style.display = mostrar ? 'flex' : 'none';
        }
    }

    mostrarNotificacion(mensaje, tipo = 'info') {
        let container = document.getElementById('notificaciones-container');
        if (!container) {
            container = document.createElement('div');
            container.id = 'notificaciones-container';
            container.style.cssText = 'position: fixed; top: 20px; right: 20px; z-index: 9999; max-width: 400px;';
            document.body.appendChild(container);
        }

        const notif = document.createElement('div');
        const colores = {
            success: 'bg-green-500',
            error: 'bg-red-500',
            warning: 'bg-yellow-500',
            info: 'bg-blue-500'
        };
        const iconos = {
            success: '✅',
            error: '❌',
            warning: '⚠️',
            info: 'ℹ️'
        };

        notif.className = `${colores[tipo]} text-white px-6 py-4 rounded-lg shadow-lg mb-2 flex items-center gap-3`;
        notif.style.animation = 'slideInRight 0.3s ease';
        notif.innerHTML = `
            <span style="font-size: 20px;">${iconos[tipo]}</span>
            <span>${mensaje}</span>
        `;
        container.appendChild(notif);

        setTimeout(() => {
            notif.style.animation = 'slideOutRight 0.3s ease';
            setTimeout(() => notif.remove(), 300);
        }, 4000);
    }

    exportarExcel() {
        try {
            this.service.exportarExcel();
            this.mostrarNotificacion('Iniciando descarga de Excel...', 'success');
        } catch (error) {
            this.mostrarNotificacion('Error al exportar: ' + error.message, 'error');
        }
    }
}

window.detalleAreaController = new DetalleAreaController();
