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

        this.searchTimeout = null;
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
            if (this.dataTable) {
                this.dataTable.ajax.reload();
            }
        });

        // Botón Limpiar filtros
        this.el.btnLimpiarFiltros?.addEventListener('click', () => {
            this._setDefaultDates(true);
            if (this.el.searchInput) this.el.searchInput.value = '';
            if (this.el.filterAlmacenOrigen) this.el.filterAlmacenOrigen.value = '';
            if (this.el.filterSerie) this.el.filterSerie.value = '';
            if (this.el.filterNumero) this.el.filterNumero.value = '';
            if (this.dataTable) {
                this.dataTable.search('').ajax.reload();
            }
        });

        // Buscador general (RUC o Razón Social) con debounce
        this.el.searchInput?.addEventListener('input', () => {
            clearTimeout(this.searchTimeout);
            this.searchTimeout = setTimeout(() => {
                if (this.dataTable) {
                    this.dataTable.search(this.el.searchInput.value || '').draw();
                }
            }, 800);
        });

        // Cambio rápido de almacén
        this.el.filterAlmacenOrigen?.addEventListener('change', () => {
            if (this.dataTable) {
                this.dataTable.ajax.reload();
            }
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
            } else if (action === 'eliminar') {
                this._eliminarGuia(treg, serie, numero);
            } else if (action === 'consultar') {
                this._consultarGuia(serie, numero, treg);
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

    async listarGuias() {
        if (this.dataTable) {
            this.dataTable.ajax.reload();
            return;
        }

        this.dataTable = $('#tablaListaGuias').DataTable({
            serverSide: true,
            dom: 'ltrip',
            processing: true,
            searching: true,
            pageLength: 10,
            searchDelay: 800,
            lengthMenu: [10, 25, 50, 100],
            ajax: {
                url: this.service.base + '/listar',
                type: 'GET',
                data: (d) => {
                    d.talm = this.el.filterAlmacenOrigen?.value || '';
                    d.fecini = this.el.filterFechaDesde?.value || '';
                    d.fecfin = this.el.filterFechaHasta?.value || '';
                    d.serie = this.el.filterSerie?.value?.trim() || '';
                    d.numero = this.el.filterNumero?.value?.trim() || '';
                },
                dataSrc: (json) => {
                    this.rows = json.data || [];
                    return json.data;
                },
                error: (xhr, error, thrown) => {
                    console.error('Error al cargar guías:', error, thrown);
                    window.SwalHelpers?.showError('No se pudo cargar el listado de guías de remisión.');
                }
            },
            columns: [
                { 
                    data: null, 
                    className: 'px-4 py-3 text-center font-mono',
                    orderable: false,
                    render: (data, type, row, meta) => {
                        return meta.row + meta.settings._iDisplayStart + 1;
                    }
                },
                { 
                    data: 'fecha_emision', 
                    className: 'px-4 py-3 text-left font-mono text-slate-700',
                    render: (data) => this._formatFecha(data)
                },
                { 
                    data: 'tipo_doc', 
                    className: 'px-4 py-3 text-center font-mono text-slate-700'
                },
                { 
                    data: 'serie', 
                    className: 'px-4 py-3 text-center font-mono font-semibold'
                },
                { 
                    data: 'numero', 
                    className: 'px-4 py-3 text-center font-mono font-semibold'
                },
                { 
                    data: 'cliente_ruc', 
                    className: 'px-4 py-3 text-left font-mono'
                },
                { 
                    data: 'cliente_razon_social', 
                    className: 'px-4 py-3 text-left max-w-[200px] truncate text-slate-700',
                    render: (data) => `<span title="${this._escapeHtml(data)}">${this._escapeHtml(data)}</span>`
                },
                { 
                    data: 'almacen_origen', 
                    className: 'px-4 py-3 text-left text-slate-700',
                    render: (data, type, row) => {
                        return `<span class="font-mono text-gray-800 font-semibold">${this._escapeHtml(data)}</span>` + 
                            (row.nom_almacen ? `<span class="text-gray-400 text-[10px]"> - ${this._escapeHtml(row.nom_almacen)}</span>` : '');
                    }
                },
                { 
                    data: 'bultos', 
                    className: 'px-4 py-3 text-right font-mono text-slate-700',
                    render: (data) => this._formatEntero(data)
                },
                { 
                    data: 'peso_total', 
                    className: 'px-4 py-3 text-right font-bold font-mono text-slate-700',
                    render: (data) => this._formatDecimal(data)
                },
                { 
                    data: 'usuario_registro', 
                    className: 'px-4 py-3 text-left font-mono text-slate-700',
                    render: (data) => this._escapeHtml(data || '-')
                },
                { 
                    data: null, 
                    className: 'px-4 py-3 text-center',
                    orderable: false,
                    render: (data, type, row) => {
                        const actionSerie = row.serie || '';
                        const actionNumero = row.numero || '';
                        return `
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
                                <button class="action-btn action-consultar" data-action="consultar" 
                                    data-treg="${this._escapeHtml(row.treg)}"
                                    data-serie="${this._escapeHtml(actionSerie)}" 
                                    data-numero="${this._escapeHtml(actionNumero)}" 
                                    title="Consultar Estado SUNAT/NubeFact">
                                    <i class="fas fa-cloud"></i>
                                </button>
                            </div>
                        `;
                        /*
                        btn eliminar
                        <button class="action-btn action-delete" data-action="eliminar" 
                                    data-treg="${this._escapeHtml(row.treg)}" 
                                    data-serie="${this._escapeHtml(actionSerie)}" 
                                    data-numero="${this._escapeHtml(actionNumero)}" 
                                    title="Eliminar Guía">
                                    <i class="fas fa-trash-alt"></i>
                                </button>
                        */
                    }
                }
            ],
            language: {
                url: 'https://cdn.datatables.net/plug-ins/1.13.7/i18n/es-ES.json'
            },
            ordering: false,
            autoWidth: false,
            drawCallback: () => {
                this._setLoading(false);
            },
            preDrawCallback: () => {
                this._setLoading(true);
            }
        });
    }

    _setLoading(isLoading) {
        if (this.el.loadingMessage) {
            this.el.loadingMessage.style.display = isLoading ? 'flex' : 'none';
        }
    }

    _imprimirGuia(treg) {
        if (!treg) {
            window.SwalHelpers?.showWarning('Registro no válido para la impresión.');
            return;
        }

        const url = this.service.getImprimirPdfUrl(treg);
        window.open(url, '_blank', 'noopener');
    }

    async _eliminarGuia(treg, serie, numero) {
        if (!treg) {
            window.SwalHelpers?.showWarning('Identificador de registro de guía no válido para eliminar.');
            return;
        }

        const docIdentificador = `${serie || ''}-${numero || ''}`;
        
        const confirm = await window.Swal.fire({
            title: '¿Está seguro de eliminar?',
            text: `Se eliminará la Guía de Remisión ${docIdentificador} y todos sus registros y detalles asociados de forma segura. Esta acción no se puede deshacer.`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Sí, eliminar',
            cancelButtonText: 'Cancelar',
            confirmButtonColor: '#dc2626',
            cancelButtonColor: '#6b7280'
        });

        if (!confirm.isConfirmed) return;

        try {
            this._setLoading(true);
            const response = await this.service.deleteGuia(treg);
            
            if (response && response.success) {
                window.Swal.fire('Eliminado', response.message || 'La guía ha sido eliminada correctamente.', 'success');
                if (this.dataTable) {
                    this.dataTable.ajax.reload();
                }
            } else {
                throw new Error(response?.message || 'No se pudo eliminar la guía.');
            }
        } catch (error) {
            console.error('Error al eliminar la guía:', error);
            window.Swal.fire('Error', error.message || 'Ocurrió un error al intentar eliminar la guía.', 'error');
        } finally {
            this._setLoading(false);
        }
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

    async _consultarGuia(serie, numero, treg) {
        if (!serie || !numero) {
            window.Swal.fire({
                icon: 'warning',
                title: 'Parámetros Incompletos',
                text: 'La serie y el número son requeridos para la consulta.'
            });
            return;
        }

        try {
            window.Swal.fire({
                title: 'Consultando SUNAT/NubeFact',
                html: `Consultando estado de la Guía <b>${this._escapeHtml(serie)}-${this._escapeHtml(numero)}</b>...<br><br><small>Esto puede tardar unos segundos.</small>`,
                allowOutsideClick: false,
                didOpen: () => {
                    window.Swal.showLoading();
                }
            });

            const response = await this.service.consultarGuia(serie, numero, treg);

            if (response && response.errors) {
                window.Swal.fire({
                    icon: 'error',
                    title: 'Error de Consulta',
                    text: response.errors
                });
                return;
            }

            if (response && response.aceptada_por_sunat === true) {
                // Extraer el hash de Sunat desde la cadena de código QR
                const qr = response.cadena_para_codigo_qr || '';
                const parts = qr.split('|');
                // En el formato estándar de SUNAT de 10 columnas, el hash está en el índice 9 (décimo elemento)
                const hash = parts.length > 9 ? parts[9] : 'No disponible en cadena QR';
                const sunatDescription = response.sunat_description || 'La guía ha sido aceptada por SUNAT.';
                const pdfUrl = response.enlace_del_pdf || null;

                let htmlContent = `
                    <div class="text-left space-y-3 font-sans">
                        <p class="text-sm text-green-700 bg-green-50 p-2.5 rounded-lg border border-green-200">
                            <strong class="font-bold text-green-800"><i class="fas fa-check-circle mr-1"></i> Estado:</strong> ACEPTADA POR SUNAT
                        </p>
                        <p class="text-xs text-slate-600">
                            <strong>Mensaje SUNAT:</strong> ${this._escapeHtml(sunatDescription)}
                        </p>
                        <div class="bg-slate-50 p-2.5 rounded-lg border border-slate-200 font-mono text-xs break-all">
                            <strong>HASH SUNAT:</strong><br>
                            <span class="text-blue-700 font-bold select-all">${this._escapeHtml(hash)}</span>
                        </div>
                    </div>
                `;

                const confirmOptions = {
                    icon: 'success',
                    title: `Guía Aceptada (${serie}-${numero})`,
                    html: htmlContent,
                    showCancelButton: true,
                    confirmButtonText: '<i class="fas fa-file-pdf mr-1"></i> Ver PDF Oficial',
                    cancelButtonText: 'Cerrar',
                    confirmButtonColor: '#ea1b0c', // Naranja para PDF
                    cancelButtonColor: '#6b7280'
                };

                if (!pdfUrl) {
                    confirmOptions.showCancelButton = false;
                    confirmOptions.confirmButtonText = 'Aceptar';
                    confirmOptions.confirmButtonColor = '#2563eb';
                }

                const result = await window.Swal.fire(confirmOptions);

                if (result.isConfirmed && pdfUrl) {
                    window.open(pdfUrl, '_blank', 'noopener');
                }
            } else if (response && response.aceptada_por_sunat === false) {
                const sunatDescription = response.sunat_description || 'El envío fue recibido pero SUNAT aún está procesándolo.';
                window.Swal.fire({
                    icon: 'info',
                    title: `SUNAT Procesando (${serie}-${numero})`,
                    text: sunatDescription
                });
            } else {
                window.Swal.fire({
                    icon: 'warning',
                    title: 'Estado Desconocido',
                    text: response?.message || response?.sunat_description || 'La respuesta de NubeFact no indica un estado claro.'
                });
            }

        } catch (error) {
            console.error('Error al consultar la guía:', error);
            window.Swal.fire({
                icon: 'error',
                title: 'Error de Conexión',
                text: error.message || 'No se pudo comunicar con el servidor para consultar el estado.'
            });
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
