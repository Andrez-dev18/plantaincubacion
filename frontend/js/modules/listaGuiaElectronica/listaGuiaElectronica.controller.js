// listaGuiaElectronica.controller.js
class ListaGuiaElectronicaController {
    constructor() {
        this.service = new window.ListaGuiaElectronicaService();
        this.rows = [];

        // Cache de elementos del DOM
        this.el = {
            searchInput: document.getElementById('searchInput'),
            filterAlmacenOrigen: document.getElementById('filterAlmacenOrigen'),
            filterFechaDesde: document.getElementById('filterFechaDesde'),
            filterFechaHasta: document.getElementById('filterFechaHasta'),
            filterSerie: document.getElementById('filterSerie'),
            filterNumero: document.getElementById('filterNumero'),
            
            btnToggleFiltros: document.getElementById('btnToggleFiltros'),
            btnAplicarFiltros: document.getElementById('btnAplicarFiltros'),
            btnLimpiarFiltros: document.getElementById('btnLimpiarFiltros'),
            filterContent: document.getElementById('filterContent'),

            tableBodyGuias: document.getElementById('tableBodyGuias'),
            emptyMessage: null,
            loadingMessage: document.getElementById('loadingMessage'),

            // Modal Detalle Nativo
            modalDetalle: document.getElementById('modalDetalleGuia'),
            lblDetalleDoc: document.getElementById('lblDetalleDoc'),
            lblDetalleFecha: document.getElementById('lblDetalleFecha'),
            lblDetalleAlmacen: document.getElementById('lblDetalleAlmacen'),
            lblDetalleCliente: document.getElementById('lblDetalleCliente'),
            lblDetalleBultos: document.getElementById('lblDetalleBultos'),
            lblDetallePeso: document.getElementById('lblDetallePeso'),
            tbodyDetalleGuia: document.getElementById('tbodyDetalleGuia'),
            btnCerrarModalTop: document.getElementById('btnCerrarModalTop'),
            btnCerrarModalBottom: document.getElementById('btnCerrarModalBottom'),
            lblDetalleMotivo: document.getElementById('lblDetalleMotivo'),
            lblDetalleTransportista: document.getElementById('lblDetalleTransportista'),
            lblDetalleVehiculo: document.getElementById('lblDetalleVehiculo'),
            lblDetalleConductor: document.getElementById('lblDetalleConductor'),
            lblDetallePartida: document.getElementById('lblDetallePartida'),
            lblDetalleLlegada: document.getElementById('lblDetalleLlegada'),
            lblDetalleModalidad: document.getElementById('lblDetalleModalidad'),
            lblDetalleTipoTransp: document.getElementById('lblDetalleTipoTransp')
        };

        this.currentDetail = {
            treg: null,
            serie: null,
            numero: null
        };

        this._debouncedSearch = this._debounce(() => {
            this.listarGuias();
        }, 350);
    }

    async init() {
        this._setDefaultDates();
        this._bindEvents();

        await this._cargarAlmacenes();
        await this.listarGuias();
    }

    _bindEvents() {
        // Toggle de sección colapsable de filtros
        this.el.btnToggleFiltros?.addEventListener('click', () => {
            const isOpen = this.el.filterContent.classList.contains('show');
            const icon = this.el.btnToggleFiltros.querySelector('i');

            this.el.filterContent.classList.toggle('show', !isOpen);
            if (icon) {
                icon.classList.toggle('fa-chevron-down', isOpen);
                icon.classList.toggle('fa-chevron-up', !isOpen);
            }
        });

        // Botón Aplicar filtros
        this.el.btnAplicarFiltros?.addEventListener('click', () => {
            this.listarGuias();
        });

        // Botón Limpiar filtros
        this.el.btnLimpiarFiltros?.addEventListener('click', () => {
            this._setDefaultDates(true);
            if (this.el.searchInput) this.el.searchInput.value = '';
            if (this.el.filterAlmacenOrigen) this.el.filterAlmacenOrigen.value = '';
            if (this.el.filterSerie) this.el.filterSerie.value = '';
            if (this.el.filterNumero) this.el.filterNumero.value = '';
            this.listarGuias();
        });

        // Buscador general (RUC o Razón Social) con debounce
        this.el.searchInput?.addEventListener('input', () => this._debouncedSearch());

        // Cambio rápido de almacén
        this.el.filterAlmacenOrigen?.addEventListener('change', () => {
            this.listarGuias();
        });

        // Acciones en la tabla (delegación de eventos)
        this.el.tableBodyGuias?.addEventListener('click', (event) => {
            const actionButton = event.target.closest('button[data-action]');
            if (!actionButton) return;

            const treg = actionButton.getAttribute('data-treg');
            const serie = actionButton.getAttribute('data-serie');
            const numero = actionButton.getAttribute('data-numero');

            const action = actionButton.getAttribute('data-action');
            if (action === 'ver') {
                this._abrirModalDetalle(treg, serie, numero);
            } else if (action === 'imprimir') {
                this._imprimirGuia(treg);
            }
        });

        // Cerrar modal de detalle nativo
        this.el.btnCerrarModalTop?.addEventListener('click', () => this._cerrarModalDetalle());
        this.el.btnCerrarModalBottom?.addEventListener('click', () => this._cerrarModalDetalle());
        this.el.modalDetalle?.addEventListener('click', (event) => {
            if (event.target === this.el.modalDetalle) {
                this._cerrarModalDetalle();
            }
        });

        // Tecla escape para cerrar modal
        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape' && this.el.modalDetalle && !this.el.modalDetalle.classList.contains('hidden')) {
                this._cerrarModalDetalle();
            }
        });
    }

    _setDefaultDates(force = false) {
        const now = new Date();
        const toLocalIso = (date) => {
            const clone = new Date(date);
            clone.setMinutes(clone.getMinutes() - clone.getTimezoneOffset());
            return clone.toISOString().slice(0, 10);
        };

        const hoy = toLocalIso(now);

        if (this.el.filterFechaDesde && (force || !this.el.filterFechaDesde.value)) {
            this.el.filterFechaDesde.value = hoy;
        }

        if (this.el.filterFechaHasta && (force || !this.el.filterFechaHasta.value)) {
            this.el.filterFechaHasta.value = hoy;
        }
    }

    async _cargarAlmacenes() {
        try {
            const response = await this.service.getAlmacenes();
            const data = Array.isArray(response?.data) ? response.data : [];

            if (!this.el.filterAlmacenOrigen) return;

            this.el.filterAlmacenOrigen.innerHTML = '<option value="">Todos los almacenes</option>' +
                data.map((item) => `
                    <option value="${this._escapeHtml(item.codigo)}">
                        ${this._escapeHtml(item.codigo)} - ${this._escapeHtml(item.descripcion || '')}
                    </option>
                `).join('');
        } catch (error) {
            console.error('Error cargando almacenes:', error);
        }
    }

    _buildFiltros() {
        return {
            q: this.el.searchInput?.value?.trim() || '',
            talm: this.el.filterAlmacenOrigen?.value || '',
            fecini: this.el.filterFechaDesde?.value || '',
            fecfin: this.el.filterFechaHasta?.value || '',
            serie: this.el.filterSerie?.value?.trim() || '',
            numero: this.el.filterNumero?.value?.trim() || ''
        };
    }

    async listarGuias() {
        this._setLoading(true);

        try {
            const response = await this.service.listarGuias(this._buildFiltros());
            if (!response?.success) {
                throw new Error(response?.error || 'No se pudo obtener el listado de guías.');
            }

            const payload = response.data || {};
            this.rows = Array.isArray(payload.rows) ? payload.rows : [];

            this._renderTabla();
        } catch (error) {
            console.error('Error al cargar guías:', error);
            this.rows = [];
            this._renderTabla();
            window.SwalHelpers?.showError(error.message || 'No se pudo cargar el listado de guías de remisión.');
        } finally {
            this._setLoading(false);
        }
    }

    _setLoading(isLoading) {
        if (this.el.loadingMessage) {
            this.el.loadingMessage.style.display = isLoading ? 'flex' : 'none';
        }

        if (this.el.tableBodyGuias && isLoading) {
            this.el.tableBodyGuias.innerHTML = `
                <tr>
                    <td colspan="11" class="text-center py-8 text-gray-500">
                        <i class="fas fa-spinner fa-spin mr-2"></i>Cargando guías de remisión...
                    </td>
                </tr>
            `;
        }
    }

    _renderTabla() {
        if (!this.el.tableBodyGuias) return;

        if (!this.rows.length) {
            this.el.tableBodyGuias.innerHTML = `
                <tr>
                    <td colspan="11" class="text-center py-8 text-slate-550 font-medium">
                        <i class="fas fa-info-circle mr-2 text-slate-400"></i>No hay datos para mostrar
                    </td>
                </tr>
            `;
            return;
        }

        this.el.tableBodyGuias.innerHTML = this.rows.map((row, idx) => {
            const fecha = this._formatFecha(row.fecha_emision || row.tfectra || row.tfecha);
            const tipoDoc = row.tipo_doc || row.tdoc || '09';
            const serie = row.serie || row.tserie || '';
            const numero = row.numero || row.tnumfac || '';
            const clienteRuc = row.cliente_ruc || row.tprocli || '';
            const razonSocial = row.cliente_razon_social || row.nombre || row.nom_cliente || '';
            const origen = row.almacen_origen || row.talm || '';
            
            const bultos = this._formatEntero(row.bultos ?? row.tnum_bultos ?? row.tcanttot ?? 0);
            const pesoTotal = this._formatDecimal(row.peso_total ?? row.tpeso_bruto ?? row.tpesotot ?? 0);

            const actionSerie = row.serie || row.tserie || '';
            const actionNumero = row.numero || row.tnumfac || '';

            return `
                <tr class="hover:bg-slate-50 border-b border-slate-100 transition-colors text-xs text-slate-700">
                    <td class="px-4 py-3 text-center text-gray-500">${idx + 1}</td>
                    <td class="px-4 py-3 text-left font-mono">${this._escapeHtml(fecha)}</td>
                    <td class="px-4 py-3 text-center font-mono">${this._escapeHtml(tipoDoc)}</td>
                    <td class="px-4 py-3 text-center font-mono font-semibold text-gray-800">${this._escapeHtml(serie)}</td>
                    <td class="px-4 py-3 text-center font-mono font-semibold text-gray-800">${this._escapeHtml(numero)}</td>
                    <td class="px-4 py-3 text-left font-mono text-gray-600">${this._escapeHtml(clienteRuc)}</td>
                    <td class="px-4 py-3 text-left max-w-[200px] truncate" title="${this._escapeHtml(razonSocial)}">${this._escapeHtml(razonSocial)}</td>
                    <td class="px-4 py-3 text-left">
                        <span class="font-mono text-gray-800 font-semibold">${this._escapeHtml(origen)}</span>
                        ${row.nom_almacen ? `<span class="text-gray-400 text-[10px]"> - ${this._escapeHtml(row.nom_almacen)}</span>` : ''}
                    </td>
                    <td class="px-4 py-3 text-right font-mono">${bultos}</td>
                    <td class="px-4 py-3 text-right font-bold font-mono">${pesoTotal}</td>
                    <td class="px-4 py-3">
                        <div class="flex gap-2 justify-center">
                            <button class="action-btn action-view" data-action="ver" 
                                data-treg="${this._escapeHtml(row.treg)}" 
                                data-serie="${this._escapeHtml(actionSerie)}" 
                                data-numero="${this._escapeHtml(actionNumero)}" 
                                title="Ver detalle">
                                <i class="fas fa-eye"></i>
                            </button>
                            <button class="action-btn action-pdf" data-action="imprimir" 
                                data-treg="${this._escapeHtml(row.treg)}" 
                                title="Imprimir Guía">
                                <i class="fas fa-print"></i>
                            </button>
                        </div>
                    </td>
                </tr>
            `;
        }).join('');
    }

    _imprimirGuia(treg) {
        if (!treg) {
            window.SwalHelpers?.showWarning('Registro no válido para la impresión.');
            return;
        }

        const url = this.service.getImprimirPdfUrl(treg);
        window.open(url, '_blank', 'noopener');
    }

    async _abrirModalDetalle(treg, serie, numero) {
        if (!treg) {
            window.SwalHelpers?.showWarning('Identificador de registro de guía no válido para abrir el detalle.');
            return;
        }

        try {
            // Limpiar la tabla de detalles antes de cargar
            if (this.el.tbodyDetalleGuia) {
                this.el.tbodyDetalleGuia.innerHTML = `
                    <tr>
                        <td colspan="9" class="text-center py-6 text-slate-400 font-sans">
                            <i class="fas fa-spinner fa-spin mr-1.5"></i>Cargando ítems...
                        </td>
                    </tr>
                `;
            }

            // Mostrar el modal
            if (this.el.modalDetalle) {
                this.el.modalDetalle.classList.remove('hidden');
            }

            // Llamar al servicio
            const response = await this.service.getDetalleGuia({ treg });

            if (!response?.success) {
                throw new Error(response?.message || 'No se pudo cargar el detalle de la guía.');
            }

            const cab = response.data?.cabecera || {};
            const items = Array.isArray(response.data?.detalle) ? response.data.detalle : [];

            // 1. Llenar Spans de las Tarjetas de Cabecera (Cards)
            this.el.lblDetalleDoc.textContent = (cab.tipo_doc || '') + ' - ' + (cab.serie || '') + ' - ' + (cab.numero || '');
            this.el.lblDetalleFecha.textContent = this._formatFecha(cab.fecha_emision);
            this.el.lblDetalleAlmacen.textContent = cab.almacen_origen || '';
            this.el.lblDetalleCliente.textContent = (cab.cliente_ruc || '') + ' - ' + (cab.cliente_razon_social || '');
            this.el.lblDetalleBultos.textContent = cab.bultos || '0';
            this.el.lblDetallePeso.textContent = this._formatDecimal(cab.peso_total || 0);

            this.el.lblDetalleMotivo.textContent = cab.motivo || '-';
            this.el.lblDetalleModalidad.textContent = cab.modalidad_transporte || '-';
            this.el.lblDetalleTipoTransp.textContent = (cab.tipo_transporte === '01') ? 'PÚBLICO' : ((cab.tipo_transporte === '02') ? 'PRIVADO' : '-');
            this.el.lblDetalleTransportista.textContent = (cab.transp_ruc || '') + ' - ' + (cab.transp_nombre || '-');
            this.el.lblDetalleVehiculo.textContent = 'Placa: ' + (cab.vehiculo_placa || '') + ' - Marca: ' + (cab.vehiculo_marca || '') + ' - NTM: ' + (cab.vehiculo_ntm || '');
            this.el.lblDetalleConductor.textContent = 'DNI: ' + (cab.cond_dni || '') + ' - Licencia: ' + (cab.cond_licencia || '') + ' - ' + (cab.cond_nombre || '-');
            this.el.lblDetallePartida.textContent = cab.punto_partida || '-';
            this.el.lblDetalleLlegada.textContent = cab.punto_llegada || '-';

            // 3. Renderizar Grilla de Ítems
            if (this.el.tbodyDetalleGuia) {
                this.el.tbodyDetalleGuia.innerHTML = '';

                if (items.length === 0) {
                    this.el.tbodyDetalleGuia.innerHTML = `
                        <tr>
                            <td colspan="9" class="text-center py-6 text-slate-400 font-sans">
                                No se encontraron ítems para esta guía.
                            </td>
                        </tr>
                    `;
                } else {
                    items.forEach((item, index) => {
                        const cant = this._formatDecimal(item.cantidad ?? 0);
                        const peso = this._formatDecimal(item.peso ?? 0);
                        const tr = document.createElement('tr');
                        tr.className = 'hover:bg-slate-50 border-b border-slate-100 transition-colors text-xs text-slate-600';
                        tr.innerHTML = `
                            <td class="px-4 py-3 text-center text-slate-400">${index + 1}</td>
                            <td class="px-4 py-3 font-semibold text-slate-800 font-mono">${this._escapeHtml(item.codigo || '')}</td>
                            <td class="px-4 py-3 font-sans text-left">${this._escapeHtml(item.descripcion || '')}</td>
                            <td class="px-4 py-3 text-center font-mono">${this._escapeHtml(item.lote || '')}</td>
                            <td class="px-4 py-3 text-right font-bold text-slate-900 font-mono">${cant}</td>
                            <td class="px-4 py-3 text-right font-bold text-slate-900 font-mono">${peso}</td>
                            <td class="px-4 py-3 text-center font-mono">${this._escapeHtml(item.cencos || '')}</td>
                            <td class="px-4 py-3 text-center font-mono">${this._escapeHtml(item.galpon || '')}</td>
                            <td class="px-4 py-3 text-left font-sans text-slate-500 italic max-w-[150px] truncate" title="${this._escapeHtml(item.observacion || '')}">
                                ${this._escapeHtml(item.observacion || '')}
                            </td>
                        `;
                        this.el.tbodyDetalleGuia.appendChild(tr);
                    });
                }
            }

            this.currentDetail = { treg, serie, numero };

        } catch (error) {
            console.error('Error al cargar el detalle de la guía:', error);
            window.SwalHelpers?.showError(error.message || 'No se pudo cargar la información de detalle.');
            this._cerrarModalDetalle();
        }
    }

    _cerrarModalDetalle() {
        if (!this.el.modalDetalle) return;
        this.el.modalDetalle.classList.add('hidden');
        this.currentDetail = { treg: null, serie: null, numero: null };
    }

    // ── Helper Utilities ────────────────────────────────────────────────────

    _formatFecha(fechaIso) {
        const text = String(fechaIso || '').trim();
        if (!text) return '-';

        const datePart = text.split(' ')[0];
        const parts = datePart.split('-');
        if (parts.length === 3) {
            return `${parts[2]}/${parts[1]}/${parts[0]}`;
        }
        return text;
    }

    _formatDecimal(value) {
        const num = Number(value || 0);
        return new Intl.NumberFormat('es-PE', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        }).format(num);
    }

    _formatEntero(value) {
        const num = Number(value || 0);
        return new Intl.NumberFormat('es-PE', {
            maximumFractionDigits: 0
        }).format(num);
    }

    _escapeHtml(value) {
        return String(value ?? '')
            .replaceAll('&', '&amp;')
            .replaceAll('<', '&lt;')
            .replaceAll('>', '&gt;')
            .replaceAll('"', '&quot;')
            .replaceAll("'", '&#39;');
    }

    _debounce(fn, ms) {
        let timer = null;
        return (...args) => {
            clearTimeout(timer);
            timer = setTimeout(() => fn(...args), ms);
        };
    }
}

window.ListaGuiaElectronicaController = ListaGuiaElectronicaController;
