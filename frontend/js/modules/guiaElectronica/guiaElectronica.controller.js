// guiaElectronica.controller.js
class GuiaElectronicaController {
    constructor() {
        this.guiaService = new window.GuiaElectronicaService();
        this._handleEscapeModal = null;
        this.debounceTimer = null;

        // Lee la configuración externa
        this.busquedasConfig = window.GuiaElectronicaConfig || {};
        this.activeSearchConfig = null;
        this.activeSearchInputId = null;
    }

    async init() {
        this.setupEventListeners();
        await this.cargarZonas();
        await this.cargarTiposTransporte();
    }

    setupEventListeners() {
        const form = document.getElementById('formGuiaRemision');
        if (form) {
            form.addEventListener('submit', (e) => e.preventDefault());
        }

        // Configurar los triggers dinámicos de búsqueda
        Object.keys(this.busquedasConfig).forEach(inputId => {
            const inputElement = document.getElementById(inputId);
            if (inputElement) {
                inputElement.addEventListener('keydown', (e) => {
                    if (e.key === 'Enter') {
                        e.preventDefault();
                        this.abrirBuscadorDinamico(inputId);
                    }
                });
            }
        });

        // Botón de cerrar (X) en modal
        const btnCerrar = document.getElementById('btn-cerrar-modal-transportistas');
        if (btnCerrar) {
            btnCerrar.addEventListener('click', () => this.cerrarBuscadorDinamico());
        }

        // Botón de cancelar en modal
        const btnCancelar = document.getElementById('btn-cancelar-modal-transportistas');
        if (btnCancelar) {
            btnCancelar.addEventListener('click', () => this.cerrarBuscadorDinamico());
        }

        // Cerrar al hacer clic fuera del modal
        const modal = document.getElementById('modal-transportistas');
        if (modal) {
            modal.addEventListener('click', (e) => {
                if (e.target === modal) {
                    this.cerrarBuscadorDinamico();
                }
            });
        }

        // Input de búsqueda del modal
        const buscarInput = document.getElementById('buscar-transportista');
        if (buscarInput) {
            buscarInput.addEventListener('input', () => {
                const query = buscarInput.value;
                clearTimeout(this.debounceTimer);
                this.debounceTimer = setTimeout(() => {
                    this.cargarDataBuscador(query);
                }, 300);
            });
        }
    }

    async cargarZonas() {
        const selectOrigen = document.getElementById('zonaOrigen');
        const selectDestino = document.getElementById('zonaDestino');

        if (!selectOrigen && !selectDestino) return;

        try {
            const response = await this.guiaService.getZonas();

            if (response && response.success && Array.isArray(response.data)) {
                const fragmentOrigen = document.createDocumentFragment();
                const fragmentDestino = document.createDocumentFragment();

                if (selectOrigen) selectOrigen.innerHTML = '<option value="">-- Seleccione Zona Origen --</option>';
                if (selectDestino) selectDestino.innerHTML = '<option value="">-- Seleccione Zona Destino --</option>';

                response.data.forEach(item => {
                    const codigo = item.codigo || item.tzona;
                    const descripcion = item.descri || item.descripcion;

                    if (selectOrigen) {
                        const opt = document.createElement('option');
                        opt.value = codigo;
                        opt.textContent = `${codigo} | ${descripcion}`;
                        fragmentOrigen.appendChild(opt);
                    }

                    if (selectDestino) {
                        const opt = document.createElement('option');
                        opt.value = codigo;
                        opt.textContent = `${codigo} | ${descripcion}`;
                        fragmentDestino.appendChild(opt);
                    }
                });

                if (selectOrigen) selectOrigen.appendChild(fragmentOrigen);
                if (selectDestino) selectDestino.appendChild(fragmentDestino);
            }
        } catch (error) {
            console.error("Error al cargar zonas de origen/destino:", error);
        }
    }

    async cargarTiposTransporte() {
        const selectTransporte = document.getElementById('tipoTransporte');
        if (!selectTransporte) return;

        try {
            const response = await this.guiaService.getTransporte();

            if (response && response.success && Array.isArray(response.data)) {
                const fragment = document.createDocumentFragment();

                selectTransporte.innerHTML = '<option value="">-- Seleccione Tipo Transporte --</option>';

                response.data.forEach(item => {
                    const codigo = item.codigo || item.cod;
                    const descripcion = item.descripcion || item.nom;

                    const opt = document.createElement('option');
                    opt.value = codigo;
                    opt.textContent = `${codigo} | ${descripcion}`;
                    fragment.appendChild(opt);
                });

                selectTransporte.appendChild(fragment);
            }
        } catch (error) {
            console.error("Error al cargar tipos de transporte:", error);
        }
    }

    // ── LOGICA BUSCADOR DINAMICO REUSABLE ──────────────────────────────────

    abrirBuscadorDinamico(inputId) {
        const config = this.busquedasConfig[inputId];
        if (!config) return;

        this.activeSearchConfig = config;
        this.activeSearchInputId = inputId;

        const modal = document.getElementById('modal-transportistas');
        if (!modal) return;

        // Actualizar título de cabecera e icono
        const titleEl = modal.querySelector('.modal-header h3');
        if (titleEl) titleEl.textContent = config.title;

        const iconEl = modal.querySelector('.modal-header i');
        if (iconEl) {
            iconEl.className = config.iconClass;
        }

        // Actualizar placeholder del input
        const buscarInput = document.getElementById('buscar-transportista');
        if (buscarInput) {
            buscarInput.placeholder = config.placeholder;
            buscarInput.value = '';
        }

        // Regenerar cabeceras de la tabla
        const thead = modal.querySelector('table thead');
        if (thead) {
            thead.innerHTML = `
                <tr>
                    ${config.headers.map((h, idx) => {
                        let widthClass = '';
                        let alignClass = '';
                        let borderClass = idx < config.headers.length - 1 ? 'border-r border-slate-100' : '';
                        if (idx === 0) {
                            widthClass = 'w-12';
                            alignClass = 'text-center';
                        } else if (h === 'Estado' || h === 'DEL') {
                            alignClass = 'text-center';
                        }
                        return `<th class="px-4 py-3 ${alignClass} ${widthClass} ${borderClass}">${h}</th>`;
                    }).join('')}
                </tr>
            `;
        }

        // Mostrar modal
        modal.style.display = 'flex';
        setTimeout(() => modal.classList.add('show'), 10);
        if (buscarInput) buscarInput.focus();

        // Cargar datos iniciales
        this.cargarDataBuscador('');

        // Registrar tecla Escape
        this._handleEscapeModal = (e) => {
            if (e.key === 'Escape') {
                this.cerrarBuscadorDinamico();
            }
        };
        document.addEventListener('keydown', this._handleEscapeModal);
    }

    cerrarBuscadorDinamico() {
        const modal = document.getElementById('modal-transportistas');
        if (!modal) return;

        modal.classList.remove('show');
        setTimeout(() => {
            modal.style.display = 'none';
        }, 200);

        if (this._handleEscapeModal) {
            document.removeEventListener('keydown', this._handleEscapeModal);
            this._handleEscapeModal = null;
        }

        // Retornar el foco al input disparador
        const triggerInput = document.getElementById(this.activeSearchInputId);
        if (triggerInput) triggerInput.focus();
    }

    async cargarDataBuscador(query = '') {
        const tbody = document.getElementById('tabla-body-transportistas');
        if (!tbody || !this.activeSearchConfig) return;

        tbody.innerHTML = `
            <tr>
                <td colspan="${this.activeSearchConfig.headers.length}" class="text-center py-6 text-slate-400">
                    <i class="fas fa-spinner fa-spin mr-1.5 text-blue-500"></i> Cargando...
                </td>
            </tr>
        `;

        try {
            // Pasamos la instancia del servicio al fetchData de la configuración externa
            const response = await this.activeSearchConfig.fetchData(this.guiaService, query);

            if (response && response.success && Array.isArray(response.data)) {
                tbody.innerHTML = '';
                const items = response.data;

                if (items.length === 0) {
                    tbody.innerHTML = `
                        <tr>
                            <td colspan="${this.activeSearchConfig.headers.length}" class="text-center py-8 text-slate-400">
                                No se encontraron registros
                            </td>
                        </tr>
                    `;
                    return;
                }

                const fragment = document.createDocumentFragment();
                items.forEach((item, index) => {
                    const tr = document.createElement('tr');
                    tr.className = 'hover:bg-slate-50 cursor-pointer transition-colors';
                    tr.addEventListener('click', () => {
                        this.activeSearchConfig.onSelect(item);
                        this.cerrarBuscadorDinamico();
                    });

                    tr.innerHTML = this.activeSearchConfig.renderRow(item, index);
                    fragment.appendChild(tr);
                });

                tbody.appendChild(fragment);
            } else {
                tbody.innerHTML = `
                    <tr>
                        <td colspan="${this.activeSearchConfig.headers.length}" class="text-center py-8 text-rose-500">
                            Error al cargar los datos
                        </td>
                    </tr>
                `;
            }
        } catch (error) {
            console.error("Error al buscar en el modal dinámico:", error);
            tbody.innerHTML = `
                <tr>
                    <td colspan="${this.activeSearchConfig.headers.length}" class="text-center py-8 text-rose-500">
                        Error de conexión con el servidor
                    </td>
                </tr>
            `;
        }
    }
}

window.GuiaElectronicaController = GuiaElectronicaController;
