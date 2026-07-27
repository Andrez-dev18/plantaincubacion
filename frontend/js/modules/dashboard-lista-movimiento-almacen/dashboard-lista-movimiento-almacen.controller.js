class DashboardListaMovimientoAlmacenController {
    constructor() {
        this.service = new DashboardListaMovimientoAlmacenService();
        this.rows = [];
        this.currentDetailTreg = null;
        this.previewPdf = {
            treg: null,
            formato: 'a4'
        };
        this.state = {
            page: 1,
            perPage: 25,
            total: 0,
            totalPages: 1
        };

        this.el = {
            searchInput: document.getElementById('searchInput'),
            filterAlmacen: document.getElementById('filterAlmacen'),
            filterTransaccion: document.getElementById('filterTransaccion'),
            filterFechaInicio: document.getElementById('filterFechaInicio'),
            filterFechaFin: document.getElementById('filterFechaFin'),
            filterContent: document.getElementById('filterContent'),
            btnToggleFiltros: document.getElementById('btnToggleFiltros'),
            btnAplicarFiltros: document.getElementById('btnAplicarFiltros'),
            btnLimpiarFiltros: document.getElementById('btnLimpiarFiltros'),
            btnActualizar: document.getElementById('btnActualizarLista'),
            btnNuevoMovimiento: document.getElementById('btnNuevoMovimiento'),
            pageSizeSelect: document.getElementById('pageSizeSelect'),
            tableBody: document.getElementById('tableBodyMovimientos'),
            emptyMessage: document.getElementById('emptyMessage'),
            loadingMessage: document.getElementById('loadingMessage'),
            lblRegistros: document.getElementById('lblRegistros'),
            lblPaginacion: document.getElementById('lblPaginacion'),
            btnPrev: document.getElementById('btnPrevPage'),
            btnNext: document.getElementById('btnNextPage'),
            modalDetalle: document.getElementById('modalDetalleMovimiento'),
            modalCabecera: document.getElementById('detalleCabeceraCampos'),
            modalDetalleBody: document.getElementById('detalleItemsBody'),
            btnCerrarDetalle: document.getElementById('btnCerrarDetalle'),
            btnCerrarDetalleFooter: document.getElementById('btnCerrarDetalleFooter'),
            btnPdfA4: document.getElementById('btnDescargarPdfA4'),
            btnPdf80: document.getElementById('btnDescargarPdf80'),
            modalPreviewPdf: document.getElementById('modalPreviewPdfMovimiento'),
            iframePreviewPdf: document.getElementById('iframePreviewPdfMovimiento'),
            previewPdfTitle: document.getElementById('previewPdfTitleMovimiento'),
            btnPreviewDescargar: document.getElementById('btnPreviewDescargarPdf'),
            btnPreviewImprimir: document.getElementById('btnPreviewImprimirPdf'),
            btnPreviewCerrar: document.getElementById('btnPreviewCerrarPdf'),
            // Modal editar
            modalEditar: document.getElementById('modalEditarMovimiento'),
            meTraeg: document.getElementById('me-lm-treg'),
            meInfoBar: document.getElementById('me-lm-info'),
            meRuc: document.getElementById('me-lm-ruc'),
            meNombre: document.getElementById('me-lm-nombre'),
            meGlosa: document.getElementById('me-lm-glosa'),
            meDetalleTbody: document.getElementById('me-lm-tbody'),
            meBtnGuardar: document.getElementById('me-lm-btn-guardar'),
            meErrorMsg: document.getElementById('me-lm-error')
        };

        this._editarData = null;

        this._debouncedSearch = this._debounce(() => {
            this.state.page = 1;
            this.cargarMovimientos();
        }, 350);
    }

    async init() {
        // Validar permiso de creación
        AppSecurity.aplicarPermisoCrear('btnNuevoMovimiento');

        this._setDefaultDates();
        this._bindEvents();

        await Promise.all([
            this._cargarAlmacenes(),
            this._cargarTransacciones()
        ]);

        await this.cargarMovimientos();
    }

    _bindEvents() {
        this.el.btnToggleFiltros?.addEventListener('click', () => {
            const isOpen = this.el.filterContent.classList.contains('show');
            const icon = this.el.btnToggleFiltros.querySelector('i');

            this.el.filterContent.classList.toggle('show', !isOpen);
            if (icon) {
                icon.classList.toggle('fa-chevron-down', isOpen);
                icon.classList.toggle('fa-chevron-up', !isOpen);
            }
        });

        this.el.btnAplicarFiltros?.addEventListener('click', () => {
            this.state.page = 1;
            this.cargarMovimientos();
        });

        this.el.btnLimpiarFiltros?.addEventListener('click', () => {
            this._setDefaultDates(true);
            if (this.el.searchInput) this.el.searchInput.value = '';
            if (this.el.filterAlmacen) this.el.filterAlmacen.value = '';
            if (this.el.filterTransaccion) this.el.filterTransaccion.value = '';
            this.state.page = 1;
            this.cargarMovimientos();
        });

        this.el.btnActualizar?.addEventListener('click', () => this.cargarMovimientos());

        this.el.btnNuevoMovimiento?.addEventListener('click', () => {
            window.location.href = './dashboard-movimiento-almacen.html';
        });

        this.el.searchInput?.addEventListener('input', () => this._debouncedSearch());

        this.el.filterAlmacen?.addEventListener('change', () => {
            this.state.page = 1;
            this.cargarMovimientos();
        });

        this.el.filterTransaccion?.addEventListener('change', () => {
            this.state.page = 1;
            this.cargarMovimientos();
        });

        this.el.pageSizeSelect?.addEventListener('change', (event) => {
            const value = Number(event.target.value || 25);
            this.state.perPage = Number.isFinite(value) ? value : 25;
            this.state.page = 1;
            this.cargarMovimientos();
        });

        this.el.btnPrev?.addEventListener('click', () => {
            if (this.state.page <= 1) return;
            this.state.page -= 1;
            this.cargarMovimientos();
        });

        this.el.btnNext?.addEventListener('click', () => {
            if (this.state.page >= this.state.totalPages) return;
            this.state.page += 1;
            this.cargarMovimientos();
        });

        this.el.tableBody?.addEventListener('click', (event) => {
            const actionButton = event.target.closest('button[data-action]');
            if (!actionButton) return;

            const treg = actionButton.getAttribute('data-treg');
            if (!treg) return;

            const rowElement = actionButton.closest('tr');

            // 🛡️ CORRECCIÓN: Leemos los datos directamente de la fila pulsada (rowElement)
            const tcodtra = rowElement ? rowElement.querySelector('.chip')?.nextElementSibling?.textContent?.split(' - ')[0]?.trim() : null;
            // El almacén está en la celda 5 (índice 4)
            const talm = rowElement ? rowElement.cells[4]?.textContent?.split(' - ')[0]?.trim() : null;

            const action = actionButton.getAttribute('data-action');
            if (action === 'ver') {
                // Pasamos tanto tcodtra como talm
                this._abrirModalDetalle(treg, tcodtra, talm);
            }
            if (action === 'preview-pdf') {
                this._abrirModalPreviewPdf(treg, 'a4', tcodtra, talm);
            }
            if (action === 'editar') {
                this._abrirModalEditar(treg);
            }
        });

        this.el.btnCerrarDetalle?.addEventListener('click', () => this._cerrarModalDetalle());
        this.el.btnCerrarDetalleFooter?.addEventListener('click', () => this._cerrarModalDetalle());

        // Modal editar
        document.getElementById('me-lm-btn-cancelar')?.addEventListener('click', () => this._cerrarModalEditar());
        document.getElementById('me-lm-btn-guardar')?.addEventListener('click', () => this._guardarEdicion());
        document.getElementById('modalEditarMovimiento')?.addEventListener('click', (e) => {
            if (e.target === e.currentTarget) this._cerrarModalEditar();
        });
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && document.getElementById('modalEditarMovimiento')?.style.display === 'flex') {
                this._cerrarModalEditar();
            }
        });

        document.getElementById('btnAbrirPreviewModal')?.addEventListener('click', () => {
            if (this.currentDetailTreg) {
                this._abrirModalPreviewPdf(this.currentDetailTreg, 'a4', this.currentDetailTcodtra, this.currentDetailTalm);
            }
        });

        this.el.modalDetalle?.addEventListener('click', (event) => {
            if (event.target === this.el.modalDetalle) {
                this._cerrarModalDetalle();
            }
        });

        this.el.btnPreviewDescargar?.addEventListener('click', () => {
            this._descargarPreviewPdf();
        });

        this.el.btnPreviewImprimir?.addEventListener('click', () => {
            this._imprimirPreviewPdf();
        });

        this.el.btnPreviewCerrar?.addEventListener('click', () => {
            this._cerrarModalPreviewPdf();
        });

        this.el.modalPreviewPdf?.addEventListener('click', (event) => {
            if (event.target === this.el.modalPreviewPdf) {
                this._cerrarModalPreviewPdf();
            }
        });

        document.addEventListener('keydown', (event) => {
            if (event.key !== 'Escape') return;
            if (this.el.modalPreviewPdf?.style.display === 'flex') {
                this._cerrarModalPreviewPdf();
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

        const fechaFin = toLocalIso(now);
        const fechaInicioDate = new Date(now);
        fechaInicioDate.setDate(fechaInicioDate.getDate() - 30);
        const fechaInicio = toLocalIso(fechaInicioDate);

        if (this.el.filterFechaInicio && (force || !this.el.filterFechaInicio.value)) {
            this.el.filterFechaInicio.value = fechaInicio;
        }

        if (this.el.filterFechaFin && (force || !this.el.filterFechaFin.value)) {
            this.el.filterFechaFin.value = fechaFin;
        }
    }

    async _cargarAlmacenes() {
        try {
            const response = await this.service.getAlmacenes();
            const data = Array.isArray(response?.data) ? response.data : [];

            if (!this.el.filterAlmacen) return;

            this.el.filterAlmacen.innerHTML = '<option value="">Todos los almacenes</option>' +
                data.map((item) => `<option value="${this._escapeHtml(item.codalm)}">${this._escapeHtml(item.codalm)} - ${this._escapeHtml(item.descri || '')}</option>`).join('');
        } catch (error) {
            console.error('Error cargando almacenes:', error);
        }
    }

    async _cargarTransacciones() {
        try {
            const response = await this.service.getTransacciones();
            const data = Array.isArray(response?.data) ? response.data : [];

            if (!this.el.filterTransaccion) return;

            this.el.filterTransaccion.innerHTML = '<option value="">Todas las transacciones</option>' +
                data.map((item) => `<option value="${this._escapeHtml(item.codtra)}">${this._escapeHtml(item.codtra)} - ${this._escapeHtml(item.descri || '')}</option>`).join('');
        } catch (error) {
            console.error('Error cargando transacciones:', error);
        }
    }

    _buildFiltros() {
        return {
            q: this.el.searchInput?.value?.trim() || '',
            talm: this.el.filterAlmacen?.value || '',
            tcodtra: this.el.filterTransaccion?.value || '',
            fecini: this.el.filterFechaInicio?.value || '',
            fecfin: this.el.filterFechaFin?.value || '',
            page: this.state.page,
            per_page: this.state.perPage
        };
    }

    async cargarMovimientos() {
        this._setLoading(true);

        try {
            const response = await this.service.listarMovimientos(this._buildFiltros());
            if (!response?.success) {
                throw new Error(response?.error || 'No se pudo obtener el listado.');
            }

            const payload = response.data || {};
            const meta = payload.meta || {};

            this.rows = Array.isArray(payload.rows) ? payload.rows : [];
            this.state.total = Number(meta.total || 0);
            this.state.totalPages = Math.max(1, Number(meta.total_pages || 1));
            this.state.page = Math.min(Math.max(1, Number(meta.page || 1)), this.state.totalPages);

            this._renderTabla();
            this._renderPaginacion();
        } catch (error) {
            console.error('Error al cargar movimientos:', error);
            this.rows = [];
            this.state.total = 0;
            this.state.totalPages = 1;
            this._renderTabla();
            this._renderPaginacion();
            window.SwalHelpers?.showError(error.message || 'No se pudo cargar el listado de movimientos.');
        } finally {
            this._setLoading(false);
        }
    }

    _setLoading(isLoading) {
        if (this.el.loadingMessage) {
            this.el.loadingMessage.style.display = isLoading ? 'flex' : 'none';
        }

        if (this.el.tableBody && isLoading) {
            this.el.tableBody.innerHTML = '<tr><td colspan="11" class="text-center py-8 text-gray-500">Cargando movimientos...</td></tr>';
        }
    }

    _renderTabla() {
        if (!this.el.tableBody) return;

        if (!this.rows.length) {
            this.el.tableBody.innerHTML = '';
            if (this.el.emptyMessage) {
                this.el.emptyMessage.style.display = 'block';
            }
            return;
        }

        if (this.el.emptyMessage) {
            this.el.emptyMessage.style.display = 'none';
        }

        const startIndex = ((this.state.page - 1) * this.state.perPage) + 1;

        this.el.tableBody.innerHTML = this.rows.map((row, idx) => {
            const correlativo = startIndex + idx;
            const tipo = this._resolverTipoMovimiento(row.tcodtra);
            const doc = this._buildDocumento(row);

            return `
                <tr class="table-row-hover">
                    <td class="px-3 py-3 text-center">${correlativo}</td>
                    <td class="px-3 py-3 font-semibold">${this._escapeHtml(row.treg)}</td>
                    <td class="px-3 py-3">
                        <div>${this._formatFecha(row.tfectra)}</div>
                        ${row.ttime ? `<div class="text-xs text-gray-400 mt-0.5">⏱ ${this._formatHora(row.ttime)}</div>` : ''}
                    </td>
                    <td class="px-3 py-3">
                        <span class="chip ${tipo.className}">${tipo.label}</span>
                        <div class="text-xs text-gray-500 mt-1">${this._escapeHtml(row.tcodtra || '-')} - ${this._escapeHtml(row.nom_transaccion || '-')}</div>
                    </td>
                    <td class="px-3 py-3">${this._escapeHtml(row.talm || '-')} - ${this._escapeHtml(row.nom_almacen || '-')}</td>
                    <td class="px-3 py-3">${this._escapeHtml(row.talr || '-')}</td>
                    <td class="px-3 py-3">${this._escapeHtml(row.tprocli || '-')}</td>
                    <td class="px-3 py-3">${this._escapeHtml(doc)}</td>
                    <td class="px-3 py-3 text-right font-semibold">${this._formatMoneda(row.timport || 0)}</td>
                    <td class="px-3 py-3">${this._escapeHtml(row.tuser || '-')}</td>
                    <td class="px-3 py-3">
                        ${AppSecurity.filtrarBotonesTabla(`
                            <div class="flex gap-2 justify-center">
                                <button class="action-btn action-view" data-action="ver" data-treg="${this._escapeHtml(row.treg)}" title="Ver detalle">
                                    <i class="fas fa-eye"></i>
                                </button>
                                <button class="action-btn action-pdf" data-action="preview-pdf" data-treg="${this._escapeHtml(row.treg)}" title="Vista previa Comprobante">
                                    <i class="fas fa-file-invoice"></i>
                                </button>
                                <button class="action-btn action-edit" data-perm="edit" data-action="editar" data-treg="${this._escapeHtml(row.treg)}" title="Editar movimiento">
                                    <i class="fas fa-pen"></i>
                                </button>
                            </div>
                        `)}
                    </td>
                </tr>
            `;
        }).join('');
    }

    _renderPaginacion() {
        if (this.el.lblRegistros) {
            this.el.lblRegistros.textContent = `${this._formatEntero(this.state.total)} registros`;
        }

        if (this.el.lblPaginacion) {
            this.el.lblPaginacion.textContent = `Pagina ${this.state.page} de ${this.state.totalPages}`;
        }

        if (this.el.btnPrev) {
            this.el.btnPrev.disabled = this.state.page <= 1;
        }

        if (this.el.btnNext) {
            this.el.btnNext.disabled = this.state.page >= this.state.totalPages;
        }
    }

    async _abrirModalDetalle(treg, tcodtraFilter = null, talmFilter = null) {
        try {
            // 1. Construir los parámetros opcionales limpios
            const queryParams = [];
            if (tcodtraFilter) queryParams.push(`tcodtra=${encodeURIComponent(tcodtraFilter)}`);
            if (talmFilter) queryParams.push(`talm=${encodeURIComponent(talmFilter)}`);
            const queryString = queryParams.length ? `?${queryParams.join('&')}` : '';

            // 2. Enviar treg y queryString por separado al método corregido
            const response = await this.service.getMovimiento(treg, queryString);

            if (!response?.success) {
                throw new Error(response?.error || 'No se pudo cargar el movimiento.');
            }

            const cabecera = response.data?.cabecera || {};
            const detalle = Array.isArray(response.data?.detalle) ? response.data.detalle : [];

            this.currentDetailTreg = treg;
            this.currentDetailTcodtra = tcodtraFilter;
            this.currentDetailTalm = talmFilter;

            this._renderCabeceraDetalle(cabecera);
            this._renderItemsDetalle(detalle);

            if (this.el.modalDetalle) {
                this.el.modalDetalle.style.display = 'flex';
                setTimeout(() => this.el.modalDetalle.classList.add('show'), 10);
            }
        } catch (error) {
            console.error('Error al abrir detalle:', error);
            window.SwalHelpers?.showError(error.message || 'No se pudo abrir el detalle del movimiento.');
        }
    }

    _cerrarModalDetalle() {
        if (!this.el.modalDetalle) return;

        this.el.modalDetalle.classList.remove('show');
        setTimeout(() => {
            this.el.modalDetalle.style.display = 'none';
        }, 150);
    }

    _renderCabeceraDetalle(cabecera) {
        if (!this.el.modalCabecera) return;

        const doc = this._buildDocumento(cabecera);

        // Arreglo de campos con iconos y colores de Tailwind para darle vida
        const campos = [
            { label: 'Registro', value: cabecera.treg || '-', icon: 'fa-hashtag', color: 'text-blue-500 dark:text-blue-400' },
            { label: 'Fecha', value: this._formatFecha(cabecera.tfectra), icon: 'fa-calendar-day', color: 'text-emerald-500 dark:text-emerald-400' },
            { label: 'Hora', value: this._formatHora(cabecera.ttime) || '-', icon: 'fa-clock', color: 'text-amber-500 dark:text-amber-400' },
            { label: 'Transacción', value: `${cabecera.tcodtra || '-'} - ${cabecera.nom_transaccion || '-'}`, icon: 'fa-right-left', color: 'text-indigo-500 dark:text-indigo-400' },
            { label: 'Almacén', value: `${cabecera.talm || '-'} - ${cabecera.nom_almacen || '-'}`, icon: 'fa-warehouse', color: 'text-purple-500 dark:text-purple-400' },
            { label: 'Cliente / Prov', value: cabecera.tprocli || '-', icon: 'fa-user-tag', color: 'text-teal-500 dark:text-teal-400' },
            { label: 'Documento', value: doc, icon: 'fa-file-lines', color: 'text-sky-500 dark:text-sky-400' },
            { label: 'Glosa', value: cabecera.tglosa || '-', icon: 'fa-comment-dots', color: 'text-slate-400 dark:text-slate-500' },
            { label: 'Importe', value: this._formatMoneda(cabecera.timport || 0), icon: 'fa-sack-dollar', color: 'text-green-600 dark:text-green-400' }
        ];

        // Renderizado de mini-tarjetas con iconos
        this.el.modalCabecera.innerHTML = campos.map(c => `
            <div class="flex items-start gap-2.5 p-3 rounded-xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 shadow-sm hover:shadow-md transition-shadow">
                <div class="mt-0.5 ${c.color}">
                    <i class="fas ${c.icon} w-4 text-center text-sm"></i>
                </div>
                <div class="flex flex-col overflow-hidden w-full">
                    <span class="text-[10px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider mb-0.5">${this._escapeHtml(c.label)}</span>
                    <span class="text-sm font-bold text-slate-800 dark:text-slate-100 truncate" title="${this._escapeHtml(c.value)}">${this._escapeHtml(c.value)}</span>
                </div>
            </div>
        `).join('');
    }

    _renderItemsDetalle(items) {
        if (!this.el.modalDetalleBody) return;

        if (!items.length) {
            this.el.modalDetalleBody.innerHTML = '<tr><td colspan="8" class="text-center py-8 text-slate-400 dark:text-slate-500">Sin items registrados.</td></tr>';
            return;
        }

        // Diseño limpio sin bordes laterales, puro Tailwind
        this.el.modalDetalleBody.innerHTML = items.map((item, idx) => {
            return `
                <tr class="hover:bg-slate-50 dark:hover:bg-slate-800/30 transition-colors">
                    <td class="px-2 py-3 text-center text-xs font-medium text-slate-400 dark:text-slate-500">${idx + 1}</td>
                    <td class="px-2 py-3 font-medium text-slate-700 dark:text-slate-300">${this._escapeHtml(item.tcodigo || '-')}</td>
                    <td class="px-2 py-3 text-slate-600 dark:text-slate-400">${this._escapeHtml(item.nom_producto || item.tdescri || '-')}</td>
                    <td class="px-2 py-3 text-slate-500">${this._escapeHtml(item.tlote || item.tnumlot || '-')}</td>
                    <td class="px-2 py-3 text-right font-medium">${this._formatDecimal(item.tcantid || 0)}</td>
                    <td class="px-2 py-3 text-right text-slate-500">${this._formatDecimal(item.tpeso || 0)}</td>
                    <td class="px-2 py-3 text-right text-slate-500">${this._formatDecimal(item.tpreuni || 0)}</td>
                    <td class="px-2 py-3 text-right font-semibold text-slate-800 dark:text-slate-200">${this._formatDecimal(item.timport || 0)}</td>
                </tr>
            `;
        }).join('');
    }

    _abrirPdf(treg, formato, forzarDescarga = true) {
        let url = this.service.getComprobantePdfUrl(treg, formato, forzarDescarga);

        const extraParams = [];
        if (this.previewPdf.tcodtra) extraParams.push(`tcodtra=${encodeURIComponent(this.previewPdf.tcodtra)}`);
        if (this.previewPdf.talm) extraParams.push(`talm=${encodeURIComponent(this.previewPdf.talm)}`);
        if (extraParams.length) url += `&${extraParams.join('&')}`;

        window.open(url, '_blank', 'noopener');
    }

    _abrirModalPreviewPdf(treg, formato = 'a4', tcodtraFilter = null, talmFilter = null) {
        if (!this.el.modalPreviewPdf) return;

        this.previewPdf.treg = String(treg || '').trim();
        if (!this.previewPdf.treg) return;

        // Guardamos el contexto por si el usuario le da al botón rojo de "Descargar"
        this.previewPdf.tcodtra = tcodtraFilter;
        this.previewPdf.talm = talmFilter;

        if (this.el.previewPdfTitle) {
            this.el.previewPdfTitle.textContent = `Vista previa PDF - Movimiento ${this.previewPdf.treg}`;
        }

        this._cambiarTabFormato('a4', false);

        // Construir la Query String de forma limpia y directa
        const extraParams = [];
        if (this.previewPdf.tcodtra) extraParams.push(`tcodtra=${encodeURIComponent(this.previewPdf.tcodtra)}`);
        if (this.previewPdf.talm) extraParams.push(`talm=${encodeURIComponent(this.previewPdf.talm)}`);
        const contextString = extraParams.length ? `&${extraParams.join('&')}` : '';

        // Obtener las URLs base desde el servicio
        const urlA4 = this.service.getComprobantePdfUrl(this.previewPdf.treg, 'a4', false);
        const url80 = this.service.getComprobantePdfUrl(this.previewPdf.treg, '80mm', false);

        const frameA4 = document.getElementById('iframePreviewPdfA4');
        const frame80 = document.getElementById('iframePreviewPdf80');

        // Pegamos el contexto y forzamos la recarga del iframe
        if (frameA4) frameA4.src = `${urlA4}${contextString}&v=${Date.now()}`;
        if (frame80) frame80.src = `${url80}${contextString}&v=${Date.now()}`;

        this.el.modalPreviewPdf.style.display = 'flex';
        setTimeout(() => {
            this.el.modalPreviewPdf?.classList.add('show');
        }, 10);
    }

    _cambiarTabFormato(formato, actualizarEstado = true) {
        const btnA4 = document.getElementById('tabPdfA4');
        const btn80 = document.getElementById('tabPdf80');
        const frameA4 = document.getElementById('iframePreviewPdfA4');
        const frame80 = document.getElementById('iframePreviewPdf80');

        if (!btnA4 || !btn80 || !frameA4 || !frame80) return;

        if (actualizarEstado) this.previewPdf.formato = formato;

        // Cambio INSTANTÁNEO de visibilidad, sin peticiones de red
        if (formato === '80mm') {
            btn80.className = "px-3 py-1 text-xs font-bold rounded-md transition-all text-white bg-blue-600 shadow-sm";
            btnA4.className = "px-3 py-1 text-xs font-bold rounded-md transition-all text-gray-300 hover:text-white";
            frameA4.style.display = 'none';
            frame80.style.display = 'block';
        } else {
            btnA4.className = "px-3 py-1 text-xs font-bold rounded-md transition-all text-white bg-blue-600 shadow-sm";
            btn80.className = "px-3 py-1 text-xs font-bold rounded-md transition-all text-gray-300 hover:text-white";
            frameA4.style.display = 'block';
            frame80.style.display = 'none';
        }
    }

    _cerrarModalPreviewPdf() {
        if (!this.el.modalPreviewPdf) return;

        this.el.modalPreviewPdf.classList.remove('show');
        setTimeout(() => {
            this.el.modalPreviewPdf.style.display = 'none';

            const frameA4 = document.getElementById('iframePreviewPdfA4');
            const frame80 = document.getElementById('iframePreviewPdf80');
            if (frameA4) frameA4.src = '';
            if (frame80) frame80.src = '';
        }, 150);
    }

    _setFormatoPreviewPdf(formato, recargar = true) {
        const formatoNormalizado = String(formato || '').toLowerCase() === '80mm' ? '80mm' : 'a4';
        this.previewPdf.formato = formatoNormalizado;

        if (recargar) {
            this._cargarIframePreviewPdf();
        }
    }

    _cargarIframePreviewPdf() {
        if (!this.el.iframePreviewPdf) return;
        if (!this.previewPdf.treg) return;

        // 1. Vaciamos el iframe un instante para dar un efecto visual de "Cargando..." al cambiar de Tab
        this.el.iframePreviewPdf.src = 'about:blank';

        // 2. Le damos unos milisegundos para que limpie y luego inyectamos el nuevo formato
        setTimeout(() => {
            const url = this.service.getComprobantePdfUrl(
                this.previewPdf.treg,
                this.previewPdf.formato,
                false
            );

            // 3. LA CORRECCIÓN CLAVE: Usar "?" o "&" según corresponda para no romper la URL del backend
            const separador = url.includes('?') ? '&' : '?';
            this.el.iframePreviewPdf.src = `${url}${separador}_ts=${Date.now()}`;
        }, 50);
    }

    _descargarPreviewPdf() {
        if (!this.previewPdf.treg) return;
        this._abrirPdf(this.previewPdf.treg, this.previewPdf.formato, true);
    }

    _imprimirPreviewPdf() {
        // Determinamos cuál de los dos iframes está activo actualmente
        const activeIframe = this.previewPdf.formato === '80mm'
            ? document.getElementById('iframePreviewPdf80')
            : document.getElementById('iframePreviewPdfA4');

        const frameWindow = activeIframe?.contentWindow;
        if (!frameWindow) {
            window.SwalHelpers?.showWarning('No se pudo abrir el documento para imprimir.');
            return;
        }

        frameWindow.focus();
        frameWindow.print();
    }

    _buildDocumento(row) {
        const partes = [row.tdoc, row.tserie, row.tnumfac]
            .map((item) => String(item || '').trim())
            .filter((item) => item !== '' && item !== '0');

        return partes.length ? partes.join(' - ') : '-';
    }

    _resolverTipoMovimiento(codTra) {
        const prefijo = String(codTra || '').trim().charAt(0).toUpperCase();
        if (prefijo === 'E') {
            return { label: 'Entrada', className: 'chip-entrada' };
        }
        if (prefijo === 'S') {
            return { label: 'Salida', className: 'chip-salida' };
        }
        return { label: 'Movimiento', className: 'chip-neutral' };
    }

    _formatFecha(fechaIso) {
        const text = String(fechaIso || '').trim();
        if (!text) return '-';

        const parts = text.split('-');
        if (parts.length === 3) {
            return `${parts[2]}/${parts[1]}/${parts[0]}`;
        }

        return text;
    }

    _formatHora(ttime) {
        const text = String(ttime || '').trim();
        if (!text) return '';

        // Soporta "HH:MM:SS", "HH:MM" y "YYYY-MM-DD HH:MM:SS"
        const spaceParts = text.split(' ');
        const timePart = spaceParts.length > 1 ? spaceParts[1] : spaceParts[0];
        const colonParts = timePart.split(':');
        if (colonParts.length >= 2) {
            return `${colonParts[0].padStart(2, '0')}:${colonParts[1].padStart(2, '0')}`;
        }

        return timePart;
    }

    _formatMoneda(value) {
        const num = Number(value || 0);
        return new Intl.NumberFormat('es-PE', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        }).format(num);
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

    // ─── EDITAR MOVIMIENTO ──────────────────────────────────────────────────

    async _abrirModalEditar(treg) {
        this._editarData = null;

        const modal = document.getElementById('modalEditarMovimiento');
        if (!modal) return;

        if (this.el.meTraeg) this.el.meTraeg.textContent = treg;
        if (this.el.meInfoBar) this.el.meInfoBar.textContent = 'Cargando...';
        if (this.el.meRuc) this.el.meRuc.value = '';
        if (this.el.meNombre) this.el.meNombre.value = '';
        if (this.el.meGlosa) this.el.meGlosa.value = '';
        if (this.el.meErrorMsg) this.el.meErrorMsg.textContent = '';
        if (this.el.meBtnGuardar) this.el.meBtnGuardar.disabled = true;
        if (this.el.meDetalleTbody) {
            this.el.meDetalleTbody.innerHTML =
                '<tr><td colspan="8" style="text-align:center;color:#9ca3af;padding:14px;">Cargando...</td></tr>';
        }

        modal.style.display = 'flex';

        try {
            const response = await this.service.getMovimiento(treg);
            if (!response?.success) throw new Error(response?.error || 'No se pudo cargar el movimiento');

            const cab = response.data?.cabecera || {};
            const items = response.data?.detalle || [];

            this._editarData = { cab, items: items.map(d => ({ ...d })) };

            if (this.el.meInfoBar) {
                this.el.meInfoBar.innerHTML =
                    `<strong>${this._escapeHtml(cab.tcodtra || '')}</strong>` +
                    ` &nbsp;|&nbsp; ALM: ${this._escapeHtml(cab.talm || '')}` +
                    (cab.talr ? ` &rarr; ${this._escapeHtml(cab.talr)}` : '') +
                    ` &nbsp;|&nbsp; Fecha: ${this._formatFecha(cab.tfectra)}` +
                    ` &nbsp;|&nbsp; ${this._escapeHtml(cab.nom_transaccion || '')}`;
            }

            if (this.el.meRuc) this.el.meRuc.value = cab.tprocli || '';
            if (this.el.meNombre) this.el.meNombre.value = cab.tprocli || '';
            if (this.el.meGlosa) this.el.meGlosa.value = cab.tglosa || '';

            this._renderEditarDetalle();
            if (this.el.meBtnGuardar) this.el.meBtnGuardar.disabled = false;

        } catch (e) {
            if (this.el.meInfoBar) this.el.meInfoBar.textContent = 'Error: ' + e.message;
            if (this.el.meDetalleTbody) {
                this.el.meDetalleTbody.innerHTML =
                    '<tr><td colspan="8" style="text-align:center;color:#dc2626;padding:14px;">Error al cargar datos</td></tr>';
            }
        }
    }

    _renderEditarDetalle() {
        if (!this.el.meDetalleTbody || !this._editarData) return;
        const items = this._editarData.items;

        if (!items.length) {
            this.el.meDetalleTbody.innerHTML =
                '<tr><td colspan="8" style="text-align:center;color:#9ca3af;padding:14px;">Sin items</td></tr>';
            return;
        }

        this.el.meDetalleTbody.innerHTML = items.map((d, i) => `
            <tr>
                <td style="text-align:center;color:#6b7280;padding:5px 8px;">${i + 1}</td>
                <td style="font-weight:600;font-size:11px;padding:5px 8px;">${this._escapeHtml(d.tcodigo || '')}</td>
                <td style="padding:5px 8px;max-width:170px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"
                    title="${this._escapeHtml(d.nom_producto || d.tdescri || '')}">${this._escapeHtml((d.nom_producto || d.tdescri || '').substring(0, 32))}</td>
                <td style="padding:5px 8px;">${this._escapeHtml(d.tlote || '')}</td>
                <td style="padding:5px 8px;text-align:center;">${this._escapeHtml(d.tunidad || '')}</td>
                <td style="padding:5px 8px;text-align:right;">
                    <input type="number" class="me-lm-cant"
                        value="${parseFloat(d.tcantid) || 0}"
                        min="0.001" step="0.001" data-idx="${i}"
                        style="border:1px solid #f59e0b;border-radius:4px;padding:3px 5px;font-size:12px;text-align:right;width:75px;"
                        onkeydown="if(event.key==='Enter')this.blur();">
                </td>
                <td style="padding:5px 8px;text-align:right;color:#6b7280;">${this._formatDecimal(d.tpeso || 0)}</td>
                <td style="padding:5px 8px;text-align:center;">
                    <button data-quitar="${i}"
                        style="background:none;border:none;color:#dc2626;cursor:pointer;font-size:14px;line-height:1;padding:0 4px;"
                        title="Quitar item">&#10006;</button>
                </td>
            </tr>
        `).join('');

        // Eventos dinámicos: quitar item
        this.el.meDetalleTbody.querySelectorAll('[data-quitar]').forEach(btn => {
            btn.addEventListener('click', () => {
                const idx = parseInt(btn.getAttribute('data-quitar'));
                this._editarData.items.splice(idx, 1);
                this._renderEditarDetalle();
                if (this.el.meErrorMsg) {
                    this.el.meErrorMsg.textContent = this._editarData.items.length === 0
                        ? 'Debe haber al menos 1 item.' : '';
                }
            });
        });
    }

    _cerrarModalEditar() {
        const modal = document.getElementById('modalEditarMovimiento');
        if (modal) modal.style.display = 'none';
        this._editarData = null;
    }

    async _guardarEdicion() {
        if (!this._editarData) return;

        const errEl = this.el.meErrorMsg;
        if (errEl) errEl.textContent = '';

        const { cab, items } = this._editarData;

        // Leer cantidades actualizadas de los inputs
        this.el.meDetalleTbody?.querySelectorAll('.me-lm-cant').forEach(inp => {
            const idx = parseInt(inp.getAttribute('data-idx'));
            const v = parseFloat(inp.value);
            if (!isNaN(v) && v > 0 && items[idx]) items[idx].tcantid = v;
        });

        if (!items.length) {
            if (errEl) errEl.textContent = 'Debe haber al menos 1 item.';
            return;
        }

        const ruc = (this.el.meRuc?.value || '').trim();
        const nombre = (this.el.meNombre?.value || '').trim();
        const glosa = (this.el.meGlosa?.value || '').trim();

        const payload = {
            tfectra: cab.tfectra,
            tcodtra: cab.tcodtra,
            talm: cab.talm,
            talr: cab.talr || '',
            tprocli: ruc || cab.tprocli || '00000000',
            tdoc: cab.tdoc || '',
            tserie: cab.tserie || '',
            tnumfac: cab.tnumfac || '',
            tfecfac: cab.tfecfac || cab.tfectra,
            tmon: cab.tmon || 'S/.',
            tlib: cab.tlib || '',
            tordcom: cab.tordcom || '',
            tglosa: glosa || nombre || cab.tglosa || '',
            tmotivo_traslado: cab.tmotivo_traslado || '',
            detalle: items.map(d => ({
                tcodigo: d.tcodigo,
                tlote: d.tlote || '00000000',
                talr: d.talr || cab.talr || '',
                tcantid: parseFloat(d.tcantid) || 0,
                tpeso: parseFloat(d.tpeso) || 0,
                tpreuni: parseFloat(d.tpreuni) || 0,
                timport: parseFloat(d.timport) || 0,
                tkardex: parseFloat(d.tkardex) || 0,
                tcencos: d.tcencos || '',
                tcodproc: d.tcodproc || '',
                tcodsubproc: d.tcodsubproc || '',
                tcodacti: d.tcodacti || '',
                tcodtarea: d.tcodtarea || '',
            })),
        };

        const btn = this.el.meBtnGuardar;
        if (btn) { btn.disabled = true; btn.textContent = 'Guardando...'; }

        try {
            const response = await this.service.actualizarMovimiento(cab.treg, payload);
            if (!response?.success) throw new Error(response?.error || 'Error al guardar');

            this._cerrarModalEditar();
            await this.cargarMovimientos();
            window.SwalHelpers?.showSuccess('Movimiento REG ' + cab.treg + ' actualizado correctamente.');

        } catch (e) {
            if (errEl) errEl.textContent = e.message || 'Error de comunicación';
        } finally {
            if (btn) { btn.disabled = false; btn.textContent = '✓ Guardar cambios'; }
        }
    }
}
