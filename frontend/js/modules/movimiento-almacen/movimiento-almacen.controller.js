// movimiento-almacen.controller.js
class MovimientoAlmacenController extends Component {
    constructor() {
        super('#form-movimiento');
        this.service = new MovimientoAlmacenService();
        this.transaccFlag = {};   // flags de coal activos
        this.detalle = [];   // filas del grid en memoria
        this.tregActual = null;
        this.modoEdicion = false;
        this._productosReporte = [];
        this._productosComboActual = [];
        this._productosPageSize = 200;
        this._productosOffset = 0;
        this._productosHasMore = true;
        this._productosLoading = false;
        this._productosTermActual = '';
        this._productosIndex = new Set();
        this._productosSearchTimer = null;
        this._lotes = [];
        this._productoActual = null;
        this._ultimoAvisoLote = { key: '', at: 0 };
        this._popupCantidadInsuficienteActivo = false;
        this._ultimoPopupCantidad = { key: '', at: 0 };
        this._kardexBase = { qstock: 0, pstock: 0, cosuni: 0, vstock: 0 };
        this._kardexContext = { codigo: '', lote: '00000000', alma: '' };
        this._kardexActual = { qstock: 0, pstock: 0, cosuni: 0, vstock: 0 };
        this._campoCodigoReporteActivo = 'desde';
        this._abcProcesos = [];
        this._abcModalState = {
            level: 0,
            selected: { proc: '', subp: '', acti: '', tarea: '' },
            names: { proc: '', subp: '', acti: '', tarea: '' },
            rows: []
        };
        this._abcAutoFlowActivo = false;
        this._reporteKardexPreview = {
            urlBase: '',
            formato: 'a4'
        };

        this._draftKey = 'draft_movimiento_almacen_v1';
        this._autoSaveDraft = this._debounce(() => this._guardarBorradorLocal(), 1500);
        this.filaSeleccionadaIndex = -1;
    }

    _setFieldValue(id, value) {
        const el = document.getElementById(id);
        if (!el) return false;
        el.value = (value === null || value === undefined || value === 'null') ? '' : value;
        return true;
    }

    _getFieldValue(id, fallback = '') {
        const el = document.getElementById(id);
        if (!el) return fallback;
        return el.value ?? fallback;
    }

    _getCodigoProductoActual() {
        const codigoEnCampo = this._getFieldValue('grid-tcodigo', '');
        if (codigoEnCampo) return codigoEnCampo;
        return this._productoActual?.codigo || '';
    }

    _getDescripcionProductoActual() {
        const codigo = this._getCodigoProductoActual();
        if (!codigo) return '';

        const producto = this._productosReporte?.find(p => p.tcodigo === codigo);
        return producto?.tdescri || this._productoActual?.descri || '';
    }

    _getLibroActual() {
        const libroInput = this._getFieldValue('tlib', '');
        if (libroInput) return libroInput;

        const codalm = this._getFieldValue('talm', '');
        const alm = this._almacenes?.find(a => a.codalm === codalm);
        return alm?.libro || 'AL';
    }

    _esUnidadKgs() {
        const unidad = (this._getFieldValue('grid-tunidad', '') || '').trim().toUpperCase();
        return ['KG', 'KGS', 'KILO', 'KILOS', 'KILOGRAMO', 'KILOGRAMOS'].includes(unidad);
    }

    _aplicarModoUnidadGrid() {
        const esKgs = this._esUnidadKgs();
        const inputPrecio = document.getElementById('grid-tpreuni');
        const inputImporte = document.getElementById('grid-timport');

        if (inputPrecio) {
            inputPrecio.disabled = esKgs;
            inputPrecio.readOnly = esKgs;
            if (esKgs) {
                inputPrecio.value = '0';
                inputPrecio.classList.add('opacity-60', 'cursor-not-allowed');
            } else {
                inputPrecio.classList.remove('opacity-60', 'cursor-not-allowed');
            }
        }

        if (inputImporte) {
            inputImporte.readOnly = !esKgs;
            inputImporte.disabled = false;
            if (esKgs) {
                inputImporte.classList.remove('bg-gray-50', 'border-dashed', 'text-gray-600');
            } else {
                inputImporte.classList.add('bg-gray-50', 'border-dashed', 'text-gray-600');
            }
        }
    }

    _actualizarImporteGrid() {
        const inputCantidad = document.getElementById('grid-tcantid');
        const inputPrecio = document.getElementById('grid-tpreuni');
        const inputImporte = document.getElementById('grid-timport');
        if (!inputCantidad || !inputPrecio || !inputImporte) return;

        const cantidad = parseFloat(inputCantidad.value || '0') || 0;
        const precio = parseFloat(inputPrecio.value || '0') || 0;

        if (!this._esUnidadKgs()) {
            inputImporte.value = this._redondear(cantidad * precio).toFixed(2);
        }
    }

    _actualizarPrecioDesdeImporteGrid() {
        if (!this._esUnidadKgs()) return;

        const inputCantidad = document.getElementById('grid-tcantid');
        const inputPrecio = document.getElementById('grid-tpreuni');
        const inputImporte = document.getElementById('grid-timport');
        if (!inputCantidad || !inputPrecio || !inputImporte) return;

        const cantidad = parseFloat(inputCantidad.value || '0') || 0;
        const importe = parseFloat(inputImporte.value || '0') || 0;
        const precio = cantidad > 0 ? (importe / cantidad) : 0;
        inputPrecio.value = this._redondear(precio).toFixed(4);
    }

    async init() {
        this._initSearchableSelects();
        this._initFormNavigation();
        this._aplicarFechaActualPorDefecto();

        const resultados = await Promise.allSettled([
            this._cargarAlmacenes(),
            this._cargarTransacciones(),
            this._cargarTiposDocumento(),
            this._cargarCentrosCosto(),
            this._cargarClientesProveedores(),
            this._cargarProcesos(),
            this._cargarProductos(),
            this._cargarLotes(),
        ]);
        resultados.forEach((r, i) => {
            if (r.status === 'rejected') {
                console.warn('Carga inicial - fallo en módulo', i, ':', r.reason?.message || r.reason);
            }
        });

        this._bindEventos();
        this._bindFocusProductoListener();

        // AQUÍ ESTÁ EL CAMBIO: Le pasamos 'true' (isInit)
        await this._resetFormulario(true);

        setTimeout(() => this._verificarBorrador(), 500);
    }

    // ── Inicializar selectores con búsqueda ──────────────────────────────────
    _initSearchableSelects() {
        // Inicializar todos los selectores con búsqueda
        initSearchableSelects(document, {
            placeholder: 'Buscar...',
            noResults: 'Sin resultados',
            autoFocus: true,
            moveToNextOnSelect: true
        });
    }

    // ── Inicializar navegación automática con Enter ──────────────────────────
    _initFormNavigation() {
        // Inicializar navegación automática en el formulario
        this.formNavigation = initFormNavigation('#form-movimiento', {
            enterToNextField: true,
            skipReadonly: true,
            skipDisabled: true,
            skipHidden: true,
            textareaRequiresCtrl: true,
            autoOpenSearchableSelect: true
        });

        // Enfocar el primer campo al cargar (Almacén Origen)
        setTimeout(() => {
            const primerCampo = document.getElementById('talm');
            if (primerCampo) {
                if (primerCampo.searchableSelectInstance?.displayField) {
                    primerCampo.searchableSelectInstance.displayField.focus();
                } else {
                    primerCampo.focus();
                }
            }
        }, 300);
    }

    // ── Bind listener para mostrar formulario al enfocar producto ────────────
    _bindFocusProductoListener() {
        const selectorProducto = document.getElementById('grid-buscar-producto');
        if (selectorProducto && selectorProducto.searchableSelectInstance) {
            const instance = selectorProducto.searchableSelectInstance;
            const displayField = instance.displayField;
            if (displayField) {
                displayField.addEventListener('focus', async () => {
                    // Solo cargar productos si no están cargados aún
                    if (!this._productosReporte.length && !this._productosLoading) {
                        await this._cargarProductos();
                    }
                });
            }

            if (!instance._remoteProductoBound) {
                instance._remoteProductoBound = true;

                instance.searchInput.addEventListener('input', () => {
                    if (this._productosSearchTimer) {
                        clearTimeout(this._productosSearchTimer);
                    }

                    const term = (instance.searchInput.value || '').trim();

                    // Si borró todo el texto, restaurar la lista completa inmediatamente (sin debounce)
                    if (!term) {
                        this._buscarProductosCombo('');
                        return;
                    }

                    this._productosSearchTimer = setTimeout(() => {
                        this._buscarProductosCombo(term);
                    }, 250);
                });

                instance.optionsList.addEventListener('scroll', () => {
                    if (this._productosTermActual) return;
                    if (!this._productosHasMore || this._productosLoading) return;

                    const list = instance.optionsList;
                    const nearBottom = (list.scrollTop + list.clientHeight) >= (list.scrollHeight - 20);
                    if (nearBottom) {
                        this._cargarMasProductosCombo();
                    }
                });
            }
        }
    }

    // ── Carga de combos ──────────────────────────────────────────────────────

    async _cargarAlmacenes() {
        const res = await this.service.getAlmacenes();
        this._almacenes = res.data;
        const opciones = '<option value="">-- Almacén --</option>' +
            res.data.map(a => `<option value="${a.codalm}">${a.codalm} - ${a.descri}</option>`).join('');

        const talmEl = document.getElementById('talm');
        talmEl.innerHTML = opciones;
        if (talmEl.searchableSelectInstance) {
            talmEl.searchableSelectInstance.loadOptions();
        }

        // Cargar también el almacén destino
        const talrEl = document.getElementById('talr');
        talrEl.innerHTML = '<option value="">-- Destino --</option>' +
            res.data.map(a => `<option value="${a.codalm}">${a.codalm} - ${a.descri}</option>`).join('');
        if (talrEl.searchableSelectInstance) {
            talrEl.searchableSelectInstance.loadOptions();
        }
    }

    async _cargarTransacciones() {
        const res = await this.service.getTransacciones();
        const sel = document.getElementById('tcodtra');
        sel.innerHTML = '<option value="">-- Transacción --</option>' +
            res.data.map(t => `<option value="${t.codtra}">${t.codtra} - ${t.descri}</option>`).join('');

        // Actualizar SearchableSelect si existe
        if (sel.searchableSelectInstance) {
            sel.searchableSelectInstance.loadOptions();
        }

        // Guardar flags indexados
        this._transacciones = {};
        res.data.forEach(t => this._transacciones[t.codtra] = t);
    }

    async _cargarTiposDocumento() {
        const res = await this.service.getTiposDocumento();
        const sel = document.getElementById('tdoc');
        sel.innerHTML = '<option value="">-- Tipo Doc --</option>' +
            res.data.map(d => `<option value="${d.tipdoc}">${d.tipdoc} - ${d.descri}</option>`).join('');

        if (sel.searchableSelectInstance) {
            sel.searchableSelectInstance.loadOptions();
        }
    }

    async _cargarCentrosCosto() {
        const res = await this.service.getCentrosCosto();
        this._centrosCosto = res.data;

        // Cargar en tcencos_dest (centros de costo destino)
        const sel = document.getElementById('tcencos_dest');
        if (sel) {
            sel.innerHTML = '<option value="">-- Centro Costo --</option>' +
                res.data.map(c => `<option value="${c.codigo}">${c.codigo} - ${c.nombre}</option>`).join('');

            if (sel.searchableSelectInstance) {
                sel.searchableSelectInstance.loadOptions();
            }
        }

        // Cargar en grid-tcencos (grid de items)
        const gridCencos = document.getElementById('grid-tcencos');
        if (gridCencos) {
            gridCencos.innerHTML = '<option value="">Buscar código o nombre...</option>' +
                res.data.map(c => {
                    const codigo = (c.codigo || '').trim();
                    const nombre = (c.nombre || '').trim();
                    return `<option value="${codigo}" data-nombre="${nombre}" data-display="${codigo}">${codigo} - ${nombre}</option>`;
                }).join('');

            // Inicializar SearchableSelect si existe la clase
            if (typeof SearchableSelect !== 'undefined') {
                if (!gridCencos.searchableSelectInstance) {
                    gridCencos.searchableSelectInstance = new SearchableSelect(gridCencos, {
                        placeholder: 'Buscar...',
                        noResults: 'Sin resultados',
                        autoFocus: true,
                        moveToNextOnSelect: false // navegación manual vía change
                    });
                } else {
                    gridCencos.searchableSelectInstance.loadOptions();
                    gridCencos.searchableSelectInstance.options.autoFocus = true;
                    gridCencos.searchableSelectInstance.options.moveToNextOnSelect = false;
                }

                // Registrar handlers de navegación UNA sola vez (flag _navBound)
                const inst = gridCencos.searchableSelectInstance;
                if (inst && !inst._navBound) {
                    inst._navBound = true;

                    // Mostrar solo el código cuando se selecciona y actualizar la etiqueta de nombre
                    gridCencos.addEventListener('change', () => {
                        if (gridCencos.value && gridCencos.searchableSelectInstance?.displayField) {
                            const span = gridCencos.searchableSelectInstance.displayField.querySelector('span');
                            if (span) {
                                span.textContent = gridCencos.value;
                            }
                        }
                        this._actualizarCencosNombreLabel();
                    });

                    // Selección de cencos → navegar a producto y abrirlo
                    gridCencos.addEventListener('change', (e) => {
                        if (!e.target.value) return;
                        setTimeout(() => {
                            const producto = document.getElementById('grid-buscar-producto');
                            if (producto?.searchableSelectInstance) {
                                producto.searchableSelectInstance.displayField.focus();
                                setTimeout(() => producto.searchableSelectInstance.open(), 50);
                            }
                        }, 80);
                    });

                    // Enter en cencos cerrado → ir a producto solo si ya tiene un valor seleccionado
                    inst.displayField.addEventListener('keydown', (e) => {
                        if (e.key === 'Enter' && !inst.isOpen && inst.originalSelect.value !== '') {
                            e.preventDefault();
                            e.stopImmediatePropagation();
                            const producto = document.getElementById('grid-buscar-producto');
                            if (producto?.searchableSelectInstance) {
                                producto.searchableSelectInstance.displayField.focus();
                                setTimeout(() => producto.searchableSelectInstance.open(), 50);
                            } else {
                                producto?.focus();
                            }
                        }
                    }, true); // capture: true para ejecutar antes que attachEvents
                }
            }
        }
    }

    async _cargarClientesProveedores() {
        const res = await this.service.getClientesProveedores();
        const sel = document.getElementById('tprocli');
        if (!sel) return;

        this._renderOpcionesProveedores(Array.isArray(res.data) ? res.data : []);

        // Búsqueda remota cuando el usuario escribe en el selector
        if (sel.searchableSelectInstance && !sel.searchableSelectInstance._remoteProveedorBound) {
            sel.searchableSelectInstance._remoteProveedorBound = true;
            sel.searchableSelectInstance.searchInput.addEventListener('input', () => {
                if (this._proveedoresSearchTimer) clearTimeout(this._proveedoresSearchTimer);
                const term = (sel.searchableSelectInstance.searchInput.value || '').trim();
                this._proveedoresSearchTimer = setTimeout(async () => {
                    if (term.length < 2) return; // esperar al menos 2 chars
                    const r = await this.service.getClientesProveedores(term);
                    this._renderOpcionesProveedores(Array.isArray(r.data) ? r.data : []);
                    const inst = sel.searchableSelectInstance;
                    if (inst) {
                        if (document.activeElement !== inst.searchInput) {
                            inst.searchInput.value = term;
                        }
                        inst.filterOptions();
                    }
                }, 300);
            });
        }
    }

    _renderOpcionesProveedores(items) {
        const sel = document.getElementById('tprocli');
        if (!sel) return;
        sel.innerHTML = '<option value="">Buscar código o nombre...</option>' +
            items.map(cp => {
                const valor = (cp.tprocli || '').trim();
                if (!valor) return '';
                const nombre = (cp.nombre || '').trim() || 'SIN NOMBRE';
                const etiqueta = `${valor} - ${nombre}`;
                return `<option value="${valor}" data-display="${etiqueta}">${etiqueta}</option>`;
            }).join('');
        if (sel.searchableSelectInstance) {
            sel.searchableSelectInstance.loadOptions();
        }
    }

    async _cargarProcesos() {
        const res = await this.service.getProcesos();
        this._abcProcesos = Array.isArray(res.data) ? res.data : [];
    }

    async _cargarProductos() {
        await this._cargarProductosPorTramo({ reset: true, term: '' });
    }

    _renderOpcionesProductosCombo(lista, term = '') {
        const sel = document.getElementById('grid-buscar-producto');
        if (!sel) return;

        const opcionesProductos = (Array.isArray(lista) ? lista : []).map(p => {
            const stock = parseFloat(p.tstock || 0);
            const stockLabel = stock > 0 ? ` [Stk: ${Number.isInteger(stock) ? stock : stock.toFixed(2)}]` : '';
            return `<option value="${p.tcodigo}"
                    data-descri="${p.tdescri}"
                    data-unidad="${p.tunidad}"
                    data-peso="${p.tpeso}"
                    data-cuenta="${p.tcuenta}">
                    ${p.tcodigo} - ${p.tdescri}${stockLabel}
                </option>`;
        }).join('');

        const optionCargarMas = (!term && this._productosHasMore)
            ? '<option value="__CARGAR_MAS__" data-display="Cargar más productos...">Cargar más productos...</option>'
            : '';

        sel.innerHTML = '<option value="">🔍 Buscar producto...</option>' + opcionesProductos + optionCargarMas;

        if (sel.searchableSelectInstance) {
            const inst = sel.searchableSelectInstance;
            if (term && document.activeElement !== inst.searchInput) {
                inst.searchInput.value = term;
            }
            inst.loadOptions();
        }
    }

    async _cargarProductosPorTramo({ reset = false, term = '' } = {}) {
        if (this._productosLoading) return;

        const termino = String(term || '').trim();
        const esBusqueda = termino !== '';

        if (reset) {
            this._productosOffset = 0;
            this._productosHasMore = true;
            this._productosTermActual = termino;

            if (!esBusqueda) {
                this._productosReporte = [];
                this._productosIndex = new Set();
            }
        }

        if (!this._productosHasMore) {
            if (esBusqueda) {
                this._renderOpcionesProductosCombo(this._productosComboActual, termino);
            }
            return;
        }

        this._productosLoading = true;

        try {
            const res = await this.service.getProductos({
                q: termino,
                limit: this._productosPageSize,
                offset: this._productosOffset,
                alma: document.getElementById('talm')?.value || '',
                codtra: document.getElementById('tcodtra')?.value || ''
            });

            const rows = Array.isArray(res.data) ? res.data : [];
            this._productosHasMore = rows.length === this._productosPageSize;

            if (esBusqueda) {
                this._productosComboActual = rows;
                this._productosOffset = rows.length;
                this._renderOpcionesProductosCombo(this._productosComboActual, termino);
            } else {
                const nuevos = rows.filter(p => {
                    const cod = String(p?.tcodigo || '').trim();
                    if (!cod || this._productosIndex.has(cod)) return false;
                    this._productosIndex.add(cod);
                    return true;
                });

                this._productosReporte.push(...nuevos);
                this._productosComboActual = this._productosReporte.slice();
                this._productosOffset += rows.length;
                this._renderOpcionesProductosCombo(this._productosComboActual, '');
            }
        } catch (e) {
            console.error('Error cargando productos:', e);
            const sel = document.getElementById('grid-buscar-producto');
            if (sel) {
                sel.innerHTML = '<option value="">⚠️ Error cargando productos</option>';
                if (sel.searchableSelectInstance) {
                    sel.searchableSelectInstance.loadOptions();
                }
            }
        } finally {
            this._productosLoading = false;
        }
    }

    async _buscarProductosCombo(term = '') {
        const termino = String(term || '').trim();

        if (!termino) {
            this._productosTermActual = '';
            if (!this._productosReporte.length) {
                await this._cargarProductosPorTramo({ reset: true, term: '' });
            } else {
                this._productosComboActual = this._productosReporte.slice();
                this._renderOpcionesProductosCombo(this._productosComboActual, '');
            }
            return;
        }

        await this._cargarProductosPorTramo({ reset: true, term: termino });
    }

    async _cargarMasProductosCombo() {
        if (this._productosLoading || this._productosTermActual || !this._productosHasMore) return;
        await this._cargarProductosPorTramo({ reset: false, term: '' });
    }

    async _cargarLotes(alma = '', codigo = '', fecha = '') {
        const sel = document.getElementById('grid-tlote');
        if (!sel) return;

        const loteActual = sel.value || '';

        if (!alma || !codigo || !fecha) {
            this._lotes = [];
            sel.innerHTML = '<option value="">🔎 Buscar lote...</option>';
            if (sel.searchableSelectInstance) {
                sel.searchableSelectInstance.loadOptions();
            }
            return;
        }

        try {
            const res = await this.service.getLotes(alma, codigo, fecha);
            const lotesRaw = Array.isArray(res.data) ? res.data : [];

            // Mantener solo un registro por lote y usar lote como texto visible.
            const lotesUnicos = [];
            const lotesVistos = new Set();
            lotesRaw.forEach((l) => {
                const loteValor = (l?.lote ?? '').toString().trim();
                if (!loteValor || lotesVistos.has(loteValor)) return;
                lotesVistos.add(loteValor);
                lotesUnicos.push({ ...l, lote: loteValor });
            });

            // Siempre: eliminar lotes donde AMBOS cantidad y peso son 0 (sin movimiento alguno)
            const sinMovimiento = (l) =>
                parseFloat(l.cantidad || 0) === 0 && parseFloat(l.peso || 0) === 0;

            // Para transacciones de tipo S (salida), solo mostrar lotes con stock > 0
            const codtra = (document.getElementById('tcodtra')?.value || '').trim();
            const esSalida = codtra.charAt(0).toUpperCase() === 'S';
            this._lotes = esSalida
                ? lotesUnicos.filter(l => parseFloat(l.cantidad || 0) > 0)
                : lotesUnicos;

            sel.innerHTML = '<option value="">🔎 Buscar lote...</option>' +
                this._lotes.map(l => {
                    const stkCan = parseFloat(l.cantidad || 0);
                    const stkPes = parseFloat(l.peso || 0);
                    const stockInfo = (l.cantidad !== undefined)
                        ? ` (Can: ${Number.isInteger(stkCan) ? stkCan : stkCan.toFixed(2)} - Pes: ${Number.isInteger(stkPes) ? stkPes : stkPes.toFixed(2)})`
                        : '';
                    return `<option value="${l.lote}">${l.lote}${stockInfo}</option>`;
                }).join('');

            if (loteActual && this._lotes.some(l => l.lote === loteActual)) {
                sel.value = loteActual;
            }

            if (sel.searchableSelectInstance) {
                sel.searchableSelectInstance.loadOptions();
            }
        } catch (e) {
            console.error('Error cargando lotes:', e);
            sel.innerHTML = '<option value="">⚠️ Error cargando lotes</option>';
            if (sel.searchableSelectInstance) {
                sel.searchableSelectInstance.loadOptions();
            }
        }
    }

    _obtenerCamposFaltantesParaLote() {
        const faltantes = [];
        const alma = (this._getFieldValue('talm', '') || '').trim();
        const codigo = (this._getCodigoProductoActual() || '').trim();
        const fecha = (this._getFieldValue('tfectra', '') || '').trim();

        if (!alma) faltantes.push('Almacen');
        if (!codigo) faltantes.push('Producto');
        if (!fecha) faltantes.push('Fecha');

        return faltantes;
    }

    _validarPrecondicionesLote(mostrarAviso = false) {
        const faltantes = this._obtenerCamposFaltantesParaLote();
        if (faltantes.length === 0) return true;

        if (mostrarAviso) {
            const key = faltantes.join('|');
            const ahora = Date.now();
            const avisoReciente = this._ultimoAvisoLote.key === key && (ahora - this._ultimoAvisoLote.at) < 900;

            if (!avisoReciente) {
                const textoCampos = faltantes.join(', ');
                const prefijo = faltantes.length === 1 ? 'campo' : 'campos';
                this._popupWarning(`Antes de buscar lote, complete el ${prefijo}: ${textoCampos}.`);
                this._ultimoAvisoLote = { key, at: ahora };
            }
        }

        return false;
    }

    _protegerSelectorLote() {
        const loteSelect = document.getElementById('grid-tlote');
        const instance = loteSelect?.searchableSelectInstance;
        if (!instance || instance._precondicionesLoteBound) return;

        const openOriginal = instance.open.bind(instance);
        instance.open = () => {
            if (!this._validarPrecondicionesLote(true)) {
                return;
            }
            openOriginal();
        };

        instance._precondicionesLoteBound = true;
    }

    // ── Binding de eventos ───────────────────────────────────────────────────

    _bindEventos() {

        const form = document.getElementById('form-movimiento');
        if (form) {
            form.addEventListener('input', () => this._autoSaveDraft());
            form.addEventListener('change', () => this._autoSaveDraft());
        }

        const bind = (id, eventName, handler) => {
            const el = document.getElementById(id);
            if (el) el.addEventListener(eventName, handler);
        };

        // Cambio de transacción → aplicar flags de coal
        document.getElementById('tcodtra').addEventListener('change', e => {
            const val = e.target.value;
            this._aplicarFlagsTransaccion(val);
            // Mostrar sección de ítems automáticamente al seleccionar una transacción
            if (val) {
                this._mostrarSeccionItems({ enfocarProducto: false, abrirSelector: false });
            }

            // [NUEVO] Autocompletado especial para la transacción S003
            if (val === 'S003') {
                this._inS003Autocomplete = true;

                // 1. Autocompletar Cliente/Proveedor con Granja Rinconada del Sur S.A. (RUC 20419158462)
                const tprocli = document.getElementById('tprocli');
                if (tprocli) {
                    let optionExists = false;
                    for (let i = 0; i < tprocli.options.length; i++) {
                        if (tprocli.options[i].value === '20419158462') {
                            optionExists = true;
                            break;
                        }
                    }
                    if (!optionExists) {
                        const opt = document.createElement('option');
                        opt.value = '20419158462';
                        opt.textContent = '20419158462 - GRANJA RINCONADA DEL SUR S.A.';
                        opt.setAttribute('data-display', '20419158462 - GRANJA RINCONADA DEL SUR S.A.');
                        tprocli.appendChild(opt);
                    }
                    tprocli.value = '20419158462';
                    if (tprocli.searchableSelectInstance) {
                        tprocli.searchableSelectInstance.updateDisplayText();
                    }
                    tprocli.dispatchEvent(new Event('change', { bubbles: true }));
                }

                // 2. Autocompletar Tipo de Documento con GI - GUIA INTERNA
                const tdoc = document.getElementById('tdoc');
                if (tdoc) {
                    let optionExists = false;
                    for (let i = 0; i < tdoc.options.length; i++) {
                        if (tdoc.options[i].value === 'GI') {
                            optionExists = true;
                            break;
                        }
                    }
                    if (!optionExists) {
                        const opt = document.createElement('option');
                        opt.value = 'GI';
                        opt.textContent = 'GI - GUIA INTERNA';
                        tdoc.appendChild(opt);
                    }
                    tdoc.value = 'GI';
                    if (tdoc.searchableSelectInstance) {
                        tdoc.searchableSelectInstance.updateDisplayText();
                    }
                    tdoc.dispatchEvent(new Event('change', { bubbles: true }));
                }

                // 3. Enfocar el input de Serie (tserie)
                setTimeout(() => {
                    const tserie = document.getElementById('tserie');
                    if (tserie) {
                        tserie.focus();
                        if (typeof tserie.select === 'function') tserie.select();
                    }
                    this._inS003Autocomplete = false;
                }, 150);
            }
        });

        // Validación de fecha al salir del campo (tanto con tabulador como con click de ratón en otro lado)
        document.getElementById('tfectra').addEventListener('blur', async e => {
            const fecha = e.target.value;
            if (!fecha) return;

            const esValida = await this._validarFecha(fecha);
            if (!esValida) {
                mostrarModal('Fecha Inválida', 'La fecha ingresada no corresponde al año fiscal o no es válida. Por favor, coloque una fecha correcta.', '❌')
                .then(() => {
                    setTimeout(() => {
                        document.getElementById('tfectra').focus();
                    }, 50);
                });
            }
        });

        // Enter en fecha: validar y prevenir avance si es incorrecto o fuera de año
        document.getElementById('tfectra').addEventListener('keydown', async e => {
            if (e.key === 'Enter') {
                const fecha = e.target.value;
                if (!fecha) {
                    e.preventDefault();
                    e.stopPropagation();
                    mostrarModal('Fecha Requerida', 'Debe ingresar una fecha para continuar.', '⚠️');
                    return;
                }

                // Prevenir avance automático preventivamente
                e.preventDefault();
                e.stopPropagation();

                const esValida = await this._validarFecha(fecha);
                if (!esValida) {
                    mostrarModal('Fecha Inválida', 'La fecha ingresada no corresponde al año fiscal o no es válida. Por favor, coloque una fecha correcta.', '❌')
                    .then(() => {
                        setTimeout(() => e.target.focus(), 50);
                    });
                } else {
                    if (this.formNavigation) {
                        this.formNavigation.moveToNextField(e.target);
                    }
                }
            }
        });

        document.getElementById('tfectra').addEventListener('change', async () => {
            await this._actualizarTipoCambio();
            const alma = document.getElementById('talm')?.value || '';
            const codigo = this._getCodigoProductoActual();
            const fecha = document.getElementById('tfectra')?.value || '';
            await this._cargarLotes(alma, codigo, fecha);
        });

        // El tipo de cambio se calcula en base a Fecha Doc.
        document.getElementById('tfecfac')?.addEventListener('change', async () => {
            await this._actualizarTipoCambio();
        });
        document.getElementById('tfecfac')?.addEventListener('blur', async () => {
            await this._actualizarTipoCambio();
        });

        document.getElementById('tmon').addEventListener('change', () => {
            this._toggleTipoCambioUI();
        });

        // Enter en moneda: si soles → saltar a observación; si dólar → ir a tipo de cambio
        document.getElementById('tmon')?.addEventListener('keydown', (e) => {
            if (e.key !== 'Enter') return;
            e.preventDefault();
            e.stopPropagation();
            const grupoCambio = document.getElementById('grupo-tipo-cambio');
            if (grupoCambio && grupoCambio.style.display !== 'none') {
                document.getElementById('tcambio-tipo')?.focus();
            } else {
                const tglosa = document.getElementById('tglosa');
                if (tglosa) {
                    tglosa.focus();
                    tglosa.select();
                }
            }
        });

        document.getElementById('tcambio-tipo')?.addEventListener('change', async () => {
            await this._actualizarTipoCambio();
        });

        // Cambio de almacén → llenar libro y moneda
        document.getElementById('talm').addEventListener('change', async e => {
            const codalm = e.target.value;
            const codigo = this._getCodigoProductoActual();
            const fecha = document.getElementById('tfectra')?.value || '';
            if (!codalm) {
                await this._cargarLotes('', codigo, fecha);
                return;
            }
            const alm = this._almacenes?.find(a => a.codalm === codalm);
            if (alm) {
                this._setFieldValue('tlib', alm.libro || 'AL');
                document.getElementById('tmon').value = alm.moneda || 'S/';
            }

            this._toggleTipoCambioUI();
            await this._cargarLotes(codalm, codigo, fecha);

            // Validar que origen y destino no sean iguales
            this._validarAlmacenes();
        });

        // Cambio de almacén destino → validar que no sea igual al origen
        document.getElementById('talr').addEventListener('change', () => {
            this._validarAlmacenes();
        });

        // Selección de producto en grid (ahora es un select)
        document.getElementById('grid-buscar-producto').addEventListener('change', async e => {
            const codigo = e.target.value;
            if (!codigo) return;

            if (codigo === '__CARGAR_MAS__') {
                e.target.value = '';
                await this._cargarMasProductosCombo();
                return;
            }

            const option = e.target.selectedOptions[0];
            const data = {
                codigo: codigo,
                descri: option.dataset.descri || '',
                unidad: option.dataset.unidad || '',
                peso: option.dataset.peso || '0',
                cuenta: option.dataset.cuenta || ''
            };

            await this._seleccionarProducto(data);
        });

        document.getElementById('grid-tlote')?.addEventListener('change', async e => {
            const lote = e.target.value || '00000000';
            const codigo = this._getCodigoProductoActual();
            const alma = document.getElementById('talm')?.value || '';

            // Sincronizar inmediatamente desde los lotes cargados en memoria
            if (codigo && alma) {
                const loteData = this._lotes?.find(l => String(l.lote).trim() === String(lote).trim());
                if (loteData) {
                    const qstock = Number(loteData.cantidad ?? 0);
                    const pstock = Number(loteData.peso ?? 0);
                    
                    this._kardexBase = {
                        qstock: Number.isFinite(qstock) ? qstock : 0,
                        pstock: Number.isFinite(pstock) ? pstock : 0,
                        cosuni: this._kardexBase?.cosuni ?? 0,
                        vstock: this._kardexBase?.vstock ?? 0
                    };
                    
                    this._kardexContext = {
                        codigo: String(codigo ?? '').trim(),
                        lote: this._normalizarLote(lote),
                        alma: String(alma ?? '').trim()
                    };

                    this._actualizarResumenKardexDisponible({ codigo, lote });
                }
            }

            if (codigo && alma) {
                await this._consultarKardex(codigo, lote, alma);
            }
        });

        this._protegerSelectorLote();

        const cantidadEl = document.getElementById('grid-tcantid');
        const pesoEl = document.getElementById('grid-tpeso');
        const precioEl = document.getElementById('grid-tpreuni');
        const importeEl = document.getElementById('grid-timport');

        cantidadEl?.addEventListener('input', () => this._actualizarImporteGrid());
        cantidadEl?.addEventListener('blur', () => this._validarCantidadDisponibleLote({ mostrarPopup: true, refocusInput: true }));
        cantidadEl?.addEventListener('change', () => this._validarCantidadDisponibleLote({ mostrarPopup: true, refocusInput: true }));
        precioEl?.addEventListener('input', () => this._actualizarImporteGrid());
        importeEl?.addEventListener('input', () => this._actualizarPrecioDesdeImporteGrid());

        const onEnter = (handler) => (e) => {
            if (e.key !== 'Enter') return;
            e.preventDefault();
            handler();
        };

        // Helper: detecta tipo de transacción (S=salida, E=entrada)
        const getTipoTrans = () => (document.getElementById('tcodtra')?.value || '').trim().charAt(0).toUpperCase();

        cantidadEl?.addEventListener('keydown', onEnter(() => {
            if (this._esUnidadKgs()) {
                pesoEl?.focus();
                pesoEl?.select?.();
                return;
            }
            const tipo = getTipoTrans();
            if (tipo === 'S') {
                document.getElementById('btn-agregar-item')?.focus();
                return;
            }
            if (tipo === 'E') {
                importeEl?.focus(); importeEl?.select?.();
                return;
            }
            if (precioEl?.disabled) {
                document.getElementById('btn-agregar-item')?.focus();
                return;
            }
            precioEl?.focus();
            precioEl?.select?.();
        }));

        pesoEl?.addEventListener('keydown', onEnter(() => {
            const tipo = getTipoTrans();
            if (this._esUnidadKgs()) {
                if (tipo === 'S') {
                    this._agregarItemGrid();
                    return;
                }
                if (tipo === 'E') {
                    importeEl?.focus(); importeEl?.select?.();
                    return;
                }
                if (importeEl?.disabled) {
                    this._agregarItemGrid();
                    return;
                }
                importeEl?.focus();
                importeEl?.select?.();
                return;
            }
            if (tipo === 'S') {
                document.getElementById('btn-agregar-item')?.focus();
                return;
            }
            if (tipo === 'E') {
                importeEl?.focus(); importeEl?.select?.();
                return;
            }
            if (precioEl?.disabled) {
                document.getElementById('btn-agregar-item')?.focus();
                return;
            }
            precioEl?.focus();
            precioEl?.select?.();
        }));

        precioEl?.addEventListener('keydown', onEnter(() => {
            const btnAgregar = document.getElementById('btn-agregar-item');
            btnAgregar?.focus();
        }));

        importeEl?.addEventListener('keydown', onEnter(() => {
            if (this._esUnidadKgs()) {
                this._agregarItemGrid();
            }
        }));

        // ABC Costing via modal (codigo concatenado)
        document.getElementById('btn-grid-abc')?.addEventListener('click', () => this._abrirModalAbc());

        // Permitir entrada manual en ABC y abrir modal con F1 SOLO cuando está enfocado
        const abcDisplay = document.getElementById('grid-costoabc-display');
        if (abcDisplay) {
            abcDisplay.addEventListener('keydown', (e) => {
                // Solo abrir modal con F1 si el campo está enfocado
                if (e.key === 'F1' && document.activeElement === abcDisplay) {
                    e.preventDefault();
                    this._abrirModalAbc();
                    return;
                }

                // Flujo secuencial: Enter en ABC -> Cencos
                if (e.key === 'Enter') {
                    e.preventDefault();
                    const actionInput = document.getElementById('sr-action-input');
                    if (actionInput) {
                        actionInput.focus();
                        actionInput.select();
                    }
                }
            });

            // Validar que solo se ingresen números (máximo 8 dígitos) - sin autocompletar
            abcDisplay.addEventListener('input', (e) => {
                let value = e.target.value.replace(/\D/g, ''); // Solo números
                if (value.length > 8) value = value.substring(0, 8);
                e.target.value = value; // Sin padding automático
            });

            // Al perder el foco, completar con ceros y actualizar la ruta
            abcDisplay.addEventListener('blur', (e) => {
                if (e.target.value) {
                    e.target.value = e.target.value.padEnd(8, '0').substring(0, 8);
                    this._actualizarRutaAbcDesdeDisplay();
                }
            });
        }

        document.getElementById('btn-cerrar-modal-abc')?.addEventListener('click', () => this._cerrarModalAbc());
        document.getElementById('btn-cancelar-modal-abc')?.addEventListener('click', () => this._cerrarModalAbc());
        document.getElementById('btn-abc-volver')?.addEventListener('click', () => this._abcVolverNivel());
        document.getElementById('btn-aceptar-modal-abc')?.addEventListener('click', () => this._aceptarModalAbc());
        document.getElementById('abc-buscar')?.addEventListener('input', () => this._renderListaAbc());

        // Atajos de teclado y navegación en Modal ABC
        const modalAbc = document.getElementById('modal-abc');
        if (modalAbc) {
            modalAbc.addEventListener('keydown', (e) => {
                if (e.key === 'Escape') {
                    e.preventDefault();
                    e.stopPropagation();
                    this._cerrarModalAbc();
                }
            });
        }

        const buscarInput = document.getElementById('abc-buscar');
        if (buscarInput) {
            buscarInput.addEventListener('keydown', (e) => {
                const tbody = document.getElementById('abc-lista-body');
                if (!tbody) return;
                const rows = tbody.querySelectorAll('tr');

                if (e.key === 'ArrowDown') {
                    e.preventDefault();
                    if (rows.length > 0) {
                        this._abcHighlightIndex = Math.min(this._abcHighlightIndex + 1, rows.length - 1);
                        this._actualizarHighlightAbc();
                    }
                } else if (e.key === 'ArrowUp') {
                    e.preventDefault();
                    if (rows.length > 0) {
                        this._abcHighlightIndex = Math.max(this._abcHighlightIndex - 1, 0);
                        this._actualizarHighlightAbc();
                    }
                } else if (e.key === 'Enter') {
                    e.preventDefault();
                    if (e.ctrlKey) {
                        // Ctrl + Enter: Aceptar selección actual inmediatamente
                        this._aceptarModalAbc();
                    } else if (rows.length > 0 && this._abcHighlightIndex >= 0 && this._abcHighlightIndex < rows.length) {
                        const highlightedRow = rows[this._abcHighlightIndex];
                        this._seleccionarFilaAbc(highlightedRow);
                    }
                }
            });
        }

        // Botones
        bind('btn-nuevo', 'click', () => this._resetFormulario());
        bind('btn-quitar-item', 'click', () => this._quitarFilaSeleccionada());
        bind('btn-grabar', 'click', () => this._grabar());
        bind('btn-borrar-mov', 'click', () => this._borrarMovimiento());
        bind('btn-borrar-todos', 'click', () => this._borrarTodos());
        bind('btn-agregar-item', 'click', () => this._agregarItemGrid());
        bind('btn-importar-exportar', 'click', () => this._abrirModalImportExport());
        bind('btn-ver-movimientos', 'click', () => this._verMovimientos());
        bind('btn-generar-reporte', 'click', () => this._vistaPreviaMovimientoSimple());

        // Modal importar / exportar
        bind('btn-cerrar-modal-import-export', 'click', () => this._cerrarModalImportExport());
        bind('btn-cancelar-import-export', 'click', () => this._cerrarModalImportExport());
        bind('btn-exportar-items', 'click', () => this._exportarItemsTxt());
        bind('btn-importar-items', 'click', async () => {
            await this._importarItemsTxt();
        });
        bind('btn-descargar-formato', 'click', () => this._descargarFormatoTxt());
        bind('file-import-items', 'change', (e) => this._cargarArchivoImportacion(e));

        // Modal reporte kardex
        document.getElementById('btn-cerrar-modal-reporte')?.addEventListener('click', () => this._cerrarModalReporte());
        document.getElementById('btn-cancelar-reporte')?.addEventListener('click', () => this._cerrarModalReporte());
        document.getElementById('btn-procesar-reporte')?.addEventListener('click', () => this._generarReporteKardexPdf());
        document.getElementById('rep-items-todo')?.addEventListener('change', () => this._toggleRangoCodigoReporte());
        document.getElementById('rep-items-codigo')?.addEventListener('change', () => this._toggleRangoCodigoReporte());
        document.getElementById('btn-rep-item-desde')?.addEventListener('click', () => this._abrirModalReporteItems('desde'));
        document.getElementById('btn-rep-item-hasta')?.addEventListener('click', () => this._abrirModalReporteItems('hasta'));
        document.getElementById('btn-cerrar-modal-reporte-items')?.addEventListener('click', () => this._cerrarModalReporteItems());
        document.getElementById('btn-cancelar-modal-reporte-items')?.addEventListener('click', () => this._cerrarModalReporteItems());
        document.getElementById('rep-item-buscar-codigo')?.addEventListener('input', () => this._renderListaItemsReporte());
        document.getElementById('rep-item-buscar-descripcion')?.addEventListener('input', () => this._renderListaItemsReporte());
        document.getElementById('btn-volver-reporte-preview')?.addEventListener('click', () => this._cerrarModalReportePreview());
        document.getElementById('vista-reporte-kardex-preview')?.addEventListener('click', (e) => {
            if (e.target?.id === 'vista-reporte-kardex-preview') {
                this._cerrarModalReportePreview();
            }
        });

        // Mostrar sección de items cuando se llegue al último campo del formulario
        const ultimoCampo = document.getElementById('tglosa');
        if (ultimoCampo) {
            // Solo revelar la sección, sin enfocar el producto
            // (La navegación Enter → grid-tcencos la maneja FormNavigation)
            ultimoCampo.addEventListener('focus', () => {
                this._mostrarSeccionItems({ enfocarProducto: false, abrirSelector: false });
            });
        }

        // Atajos de teclado
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') {
                const vista = document.getElementById('vista-reporte-kardex-preview');
                if (vista && vista.style.display !== 'none') {
                    e.preventDefault();
                    this._cerrarModalReportePreview();
                    return;
                }
            }

            if (!e.altKey || e.ctrlKey) return;

            const esAtajoAltMas =
                e.key === '+' ||
                e.code === 'NumpadAdd' ||
                (e.code === 'Equal' && e.shiftKey);

            if (esAtajoAltMas) {
                e.preventDefault();
                e.stopPropagation();
                this._agregarItemGrid();
                return;
            }

            if (e.shiftKey) return;

            const key = (e.key || '').toLowerCase();
            const acciones = {
                // Atajos del detalle
                g: () => this._agregarItemGrid(),
                i: () => this._abrirModalImportExport(),

                // Atajos de botones inferiores
                c: () => this._grabar(),
                n: () => this._resetFormulario(),
                q: () => this._quitarFilaSeleccionada(),
                b: () => this._borrarMovimiento(),
                t: () => this._borrarTodos(),
                v: () => this._verMovimientos(),
                r: () => this._vistaPreviaMovimientoSimple(),
            };

            const accion = acciones[key];
            if (!accion) return;

            e.preventDefault();
            e.stopPropagation();
            accion();
        });

        // Escuchar evento personalizado para mostrar formulario de items
        document.addEventListener('showItemsForm', () => {
            this._mostrarSeccionItems({ enfocarProducto: false, abrirSelector: false });
        });

        // [NUEVO] Atajos de teclado para la fila de ingreso (Añadir, Cambiar, Anular)
        const actionInput = document.getElementById('sr-action-input');
        if (actionInput) {
            actionInput.addEventListener('keydown', (e) => {
                const key = e.key.toLowerCase();

                if (key === '+' || key === 'enter') {
                    e.preventDefault();
                    this._agregarItemGrid();
                    actionInput.value = ''; // Limpiar cajita
                } 
                else if (key === 'c') {
                    e.preventDefault();
                    actionInput.value = ''; // Limpiar cajita
                    const producto = document.getElementById('grid-buscar-producto');
                    if (producto?.searchableSelectInstance?.displayField) {
                        producto.searchableSelectInstance.displayField.focus();
                        setTimeout(() => producto.searchableSelectInstance.open(), 50);
                    } else if (producto) {
                        producto.focus();
                    }
                } 
                else if (key === 'a') {
                    e.preventDefault();
                    actionInput.value = ''; // Limpiar cajita
                    this._limpiarCamposGrid();
                }
            });

            // Evitar que letras basura queden en la cajita
            actionInput.addEventListener('input', (e) => {
                const val = e.target.value.toLowerCase();
                if (!['+', 'c', 'a'].includes(val)) {
                    e.target.value = '';
                }
            });
        }

        // [NUEVO] Comportamiento de navegación y apertura por Enter en selects nativos
        document.querySelectorAll('#form-movimiento select').forEach(select => {
            select.addEventListener('keydown', e => {
                if (e.key === 'Enter') {
                    if (select.value === '') {
                        e.preventDefault();
                        e.stopPropagation();
                        if (typeof select.showPicker === 'function') {
                            try {
                                select.showPicker();
                            } catch (err) {
                                console.warn('showPicker no disponible:', err);
                            }
                        }
                    }
                }
            });

            select.addEventListener('change', e => {
                if (e.isTrusted && select.value !== '') {
                    if (select.id !== 'grid-buscar-producto') {
                        setTimeout(() => {
                            if (this.formNavigation) {
                                this.formNavigation.moveToNextField(select);
                            }
                        }, 50);
                    }
                }
            });
        });

        this._toggleRangoCodigoReporte();
    }

    _abrirModalReporte() {
        const modal = document.getElementById('modal-reporte-kardex');
        if (!modal) return;

        const codAlmacen = document.getElementById('talm')?.value || '';
        const almacenSeleccionado = this._almacenes?.find(a => a.codalm === codAlmacen);
        const zona = codAlmacen
            ? `${codAlmacen} - ${almacenSeleccionado?.descri || ''}`.trim()
            : 'Sin almacen seleccionado';

        const fechaFormulario = document.getElementById('tfectra')?.value || '';
        document.getElementById('rep-zona').value = zona;
        document.getElementById('rep-fecha-desde').value = fechaFormulario;
        document.getElementById('rep-fecha-hasta').value = fechaFormulario;

        modal.style.display = 'flex';
        setTimeout(() => modal.classList.add('show'), 10);
    }

    _cerrarModalReporte() {
        const modal = document.getElementById('modal-reporte-kardex');
        if (!modal) return;

        modal.classList.remove('show');
        setTimeout(() => {
            modal.style.display = 'none';
        }, 200);
    }

    _toggleRangoCodigoReporte() {
        const usaRango = document.getElementById('rep-items-codigo')?.checked;
        const contenedor = document.getElementById('rep-rango-codigos');
        const codigoDesde = document.getElementById('rep-codigo-desde');
        const codigoHasta = document.getElementById('rep-codigo-hasta');
        const btnDesde = document.getElementById('btn-rep-item-desde');
        const btnHasta = document.getElementById('btn-rep-item-hasta');

        if (!contenedor || !codigoDesde || !codigoHasta || !btnDesde || !btnHasta) return;

        contenedor.style.opacity = usaRango ? '1' : '0.6';
        contenedor.style.pointerEvents = usaRango ? '' : 'none';
        codigoDesde.disabled = !usaRango;
        codigoHasta.disabled = !usaRango;
        btnDesde.disabled = !usaRango;
        btnHasta.disabled = !usaRango;

        if (!usaRango) {
            codigoDesde.value = '';
            codigoHasta.value = '';
        }
    }

    _abrirModalReporteItems(campo) {
        this._campoCodigoReporteActivo = campo === 'hasta' ? 'hasta' : 'desde';
        const modal = document.getElementById('modal-reporte-items');
        if (!modal) return;

        const inputCodigo = document.getElementById('rep-item-buscar-codigo');
        const inputDescripcion = document.getElementById('rep-item-buscar-descripcion');
        if (inputCodigo) inputCodigo.value = '';
        if (inputDescripcion) inputDescripcion.value = '';

        this._renderListaItemsReporte();
        modal.style.display = 'flex';
        setTimeout(() => modal.classList.add('show'), 10);
    }

    _cerrarModalReporteItems() {
        const modal = document.getElementById('modal-reporte-items');
        if (!modal) return;

        modal.classList.remove('show');
        setTimeout(() => {
            modal.style.display = 'none';
        }, 200);
    }

    _renderListaItemsReporte() {
        const tbody = document.getElementById('rep-items-body');
        if (!tbody) return;

        const filtroCodigo = (document.getElementById('rep-item-buscar-codigo')?.value || '').trim().toLowerCase();
        const filtroDescripcion = (document.getElementById('rep-item-buscar-descripcion')?.value || '').trim().toLowerCase();

        const lista = (this._productosReporte || []).filter(item => {
            const codigo = String(item.tcodigo || '').toLowerCase();
            const descripcion = String(item.tdescri || '').toLowerCase();
            const okCodigo = !filtroCodigo || codigo.includes(filtroCodigo);
            const okDescripcion = !filtroDescripcion || descripcion.includes(filtroDescripcion);
            return okCodigo && okDescripcion;
        });

        if (!lista.length) {
            tbody.innerHTML = '<tr><td colspan="3" class="text-center text-gray-400 py-6">No hay items para el filtro actual.</td></tr>';
            return;
        }

        const escapeHtml = (v) => String(v ?? '')
            .replaceAll('&', '&amp;')
            .replaceAll('<', '&lt;')
            .replaceAll('>', '&gt;')
            .replaceAll('"', '&quot;')
            .replaceAll("'", '&#39;');

        tbody.innerHTML = lista.map(item => `
            <tr class="hover:bg-blue-50" data-codigo="${escapeHtml(item.tcodigo)}">
                <td>${escapeHtml(item.tcodigo)}</td>
                <td>${escapeHtml(item.tdescri)}</td>
                <td>${escapeHtml(item.tunidad)}</td>
            </tr>
        `).join('');

        Array.from(tbody.querySelectorAll('tr')).forEach(row => {
            row.addEventListener('click', () => {
                const codigo = row.dataset.codigo || '';
                this._seleccionarCodigoReporte(codigo);
            });
        });
    }

    _seleccionarCodigoReporte(codigo) {
        const input = this._campoCodigoReporteActivo === 'hasta'
            ? document.getElementById('rep-codigo-hasta')
            : document.getElementById('rep-codigo-desde');

        if (input) {
            input.value = codigo;
        }

        this._cerrarModalReporteItems();
    }

    _ocultarSeccionItems({ immediate = false } = {}) {
        const formulario = document.getElementById('formulario-agregar-item');
        if (!formulario) return;

        formulario.style.transition = 'opacity 0.22s ease-out, transform 0.22s ease-out';
        formulario.style.opacity = '0';
        formulario.style.transform = 'translateY(-10px)';

        if (immediate) {
            formulario.style.display = 'none';
            return;
        }

        setTimeout(() => {
            formulario.style.display = 'none';
        }, 220);
    }

    // Mostrar sección de items con animación
    _mostrarSeccionItems({ enfocarProducto = true, abrirSelector = true } = {}) {
        const formulario = document.getElementById('formulario-agregar-item');
        if (!formulario) return;

        const yaVisible = formulario.style.display !== 'none' && formulario.style.opacity !== '0';

        // Si ya está visible y se pide enfocar, ir directo al cencos (primer campo del bloque)
        if (yaVisible) {
            if (enfocarProducto) {
                const primerCampo = document.getElementById('grid-tcencos');
                if (primerCampo?.searchableSelectInstance?.displayField) {
                    primerCampo.searchableSelectInstance.displayField.focus();
                    if (abrirSelector) {
                        setTimeout(() => primerCampo.searchableSelectInstance.open(), 50);
                    }
                } else if (primerCampo) {
                    primerCampo.focus();
                }
            }
            return;
        }

        formulario.style.display = 'block';
        formulario.style.opacity = '0';
        formulario.style.transform = 'translateY(-10px)';
        formulario.style.transition = 'opacity 0.3s ease-out, transform 0.3s ease-out';

        setTimeout(() => {
            formulario.style.opacity = '1';
            formulario.style.transform = 'translateY(0)';

            // Hacer scroll suave al formulario de items y enfocar el selector de productos
            setTimeout(() => {
                formulario.scrollIntoView({ behavior: 'smooth', block: 'nearest' });

                // Enfocar el primer campo (cencos) solo si no hay ningún campo enfocado en el formulario
                const focusedElement = document.activeElement;
                const formularioContainsFocus = formulario.contains(focusedElement);

                if (!formularioContainsFocus && enfocarProducto) {
                    const primerCampo = document.getElementById('grid-tcencos');
                    if (primerCampo?.searchableSelectInstance?.displayField) {
                        primerCampo.searchableSelectInstance.displayField.focus();
                        if (abrirSelector) {
                            setTimeout(() => primerCampo.searchableSelectInstance.open(), 200);
                        }
                    } else if (primerCampo) {
                        primerCampo.focus();
                    }
                }
            }, 100);
        }, 50);
    }

    // ── Aplicar flags de transacción (coal) ──────────────────────────────────
    // Controla visibilidad/habilitación según los boolean flags de la tabla coal

    _aplicarFlagsTransaccion(codtra) {
        if (!codtra || !this._transacciones) return;
        const t = this._transacciones[codtra] ?? {};
        this.transaccFlag = t;

        this._toggleCampo('grupo-emidoc', t.emidoc == 1, ['tdoc']); // tdoc, tserie, tnumfac — tdoc siempre habilitado
        this._toggleCampo('grupo-precio', t.precio == 1); // col Precio en grid
        this._toggleCampo('grupo-cencos-destino', t.gentsa == 1); // tcencos_dest - mostrar solo si gentsa == 1
        this._toggleCampo('grupo-almacen-destino', t.gentsa == 1); // talr - mostrar solo si gentsa == 1
        this._toggleCampo('grupo-guia', t.gragui == 1); // chofer, brevete, placa
        this._toggleCampo('grupo-motivo', t.pidemotivo == 1);
        this._toggleCampo('grupo-moneda', t.pmoned == 1);
        this._toggleCampo('grupo-tipo-cambio', t.pmoned == 1);
        this._toggleCampo('grupo-observacion', true); // siempre habilitado
        this._toggleCampo('grupo-ordcom', t.ordcom == 1);

        // Actualizar encabezados del grid
        const mostrarPrecio = t.precio == 1;
        this._mostrarPrecio = mostrarPrecio;
        document.getElementById('col-precio').style.display = mostrarPrecio ? '' : 'none';
        document.getElementById('col-importe').style.display = mostrarPrecio ? '' : 'none';
        // Actualizar td del tfoot de precio (placeholder)
        const colPrecioTot = document.getElementById('col-precio-tot');
        if (colPrecioTot) colPrecioTot.style.display = mostrarPrecio ? '' : 'none';
        // Ocultar/mostrar total importe del footer
        document.querySelectorAll('.col-importe').forEach(el => {
            el.style.display = mostrarPrecio ? '' : 'none';
        });
        // Re-renderizar grid para sincronizar celdas
        this._renderGrid();
    }

    _toggleCampo(id, habilitado, skipIds = []) {
        const el = document.getElementById(id);
        if (!el) return;

        // Siempre visible, pero deshabilitar todos los inputs y selects hijos
        const inputs = el.querySelectorAll('input, select, textarea');
        inputs.forEach(input => {
            // No tocar los campos excluidos (siempre habilitados)
            if (skipIds.includes(input.id)) {
                input.disabled = false;
                if (input.searchableSelectInstance) {
                    const d = input.searchableSelectInstance.displayField;
                    if (d) { d.removeAttribute('disabled'); d.style.pointerEvents = ''; d.style.opacity = ''; d.tabIndex = 0; }
                }
                return;
            }
            input.disabled = !habilitado;

            // Si el input es un select con SearchableSelect, también deshabilitar el display
            if (input.searchableSelectInstance) {
                const displayField = input.searchableSelectInstance.displayField;
                if (displayField) {
                    if (habilitado) {
                        displayField.removeAttribute('disabled');
                        displayField.style.pointerEvents = '';
                        displayField.style.opacity = '';
                        displayField.tabIndex = 0;
                    } else {
                        displayField.setAttribute('disabled', 'true');
                        displayField.style.pointerEvents = 'none';
                        displayField.style.opacity = '0.6';
                        displayField.tabIndex = -1;
                    }
                }
            }
        });

        // Añadir clase visual para indicar que está deshabilitado
        if (habilitado) {
            el.classList.remove('campo-deshabilitado');
        } else {
            el.classList.add('campo-deshabilitado');
        }
    }

    // ── Validación de fecha ───────────────────────────────────────────────────

    async _validarFecha(fecha) {
        if (!fecha) return false;
        try {
            const res = await this.service.verificarFecha(fecha);
            const { valida, mensaje } = res.data;
            const msgEl = document.getElementById('msg-fecha');
            if (!valida) {
                msgEl.textContent = mensaje;
                msgEl.style.display = 'block';
                document.getElementById('tfectra').value = '';
                document.getElementById('btn-grabar').disabled = true;
                return false;
            } else {
                msgEl.style.display = 'none';
                document.getElementById('btn-grabar').disabled = false;
                return true;
            }
        } catch (e) {
            console.warn('No se pudo verificar fecha (asumiendo válida):', e.message);
            const msgEl = document.getElementById('msg-fecha');
            if (msgEl) msgEl.style.display = 'none';
            const btnGrabar = document.getElementById('btn-grabar');
            if (btnGrabar) btnGrabar.disabled = false;
            return true;
        }
    }

    _toggleTipoCambioUI() {
        const grupo = document.getElementById('grupo-tipo-cambio');
        const tipoSel = document.getElementById('tcambio-tipo');
        const valorInput = document.getElementById('tcambio-valor');
        if (!grupo || !tipoSel || !valorInput) return;

        const esDolar = this._getFieldValue('tmon') === 'US$';
        grupo.style.display = esDolar ? '' : 'none';

        if (!esDolar) {
            tipoSel.value = 'compra';
            valorInput.value = '';
            valorInput.readOnly = true;
            return;
        }

        if (!tipoSel.value) {
            tipoSel.value = 'compra';
        }

        this._actualizarTipoCambio();
    }

    async _actualizarTipoCambio() {
        const tipoSel = document.getElementById('tcambio-tipo');
        const valorInput = document.getElementById('tcambio-valor');
        if (!tipoSel || !valorInput) return;

        if (this._getFieldValue('tmon') !== 'US$') return;

        const tipo = tipoSel.value || 'compra';
        if (tipo === 'manual') {
            valorInput.readOnly = false;
            valorInput.value = '';
            return;
        }

        valorInput.readOnly = true;
        // Regla: usar Fecha Doc. para la consulta de tipo de cambio.
        const fecha = this._getFieldValue('tfecfac') || this._getFieldValue('tfectra');
        if (!fecha) {
            valorInput.value = '';
            return;
        }

        try {
            const res = await this.service.getTipoCambio(fecha);
            const data = res.data || null;
            if (!data) {
                valorInput.value = '';
                return;
            }

            const valor = tipo === 'venta' ? data.lib_venta : data.lib_compra;
            valorInput.value = valor ?? '';
        } catch (e) {
            console.error('Error obteniendo tipo de cambio:', e);
            valorInput.value = '';
        }
    }

    // ── Validación de almacenes ───────────────────────────────────────────────

    _validarAlmacenes() {
        const almOrigen = document.getElementById('talm').value;
        const almDestino = document.getElementById('talr').value;

        // Si la transacción requiere almacén destino (gentsa == 1), es obligatorio
        if (this.transaccFlag?.gentsa == 1 && !almDestino) {
            this._popupWarning('Debe seleccionar un Almacén de Destino.');
            const talrEl = document.getElementById('talr');
            if (talrEl?.searchableSelectInstance) {
                talrEl.searchableSelectInstance.displayField.focus();
                setTimeout(() => talrEl.searchableSelectInstance.open(), 50);
            } else {
                talrEl?.focus();
            }
            return false;
        }

        // Si ambos están llenos y son iguales
        if (almOrigen && almDestino && almOrigen === almDestino) {
            mostrarModal(
                'Validación de Almacenes',
                'El Almacén Origen y Destino no pueden ser el mismo. Por favor, seleccione un almacén diferente.',
                '⚠️'
            );

            // Limpiar el almacén destino
            document.getElementById('talr').value = '';

            // Actualizar el SearchableSelect si existe
            const talrEl = document.getElementById('talr');
            if (talrEl.searchableSelectInstance) {
                talrEl.searchableSelectInstance.updateDisplayText();
            }

            return false;
        }
        return true;
    }

    // ── Búsqueda de productos ─────────────────────────────────────────────────

    // ── Selección de producto ─────────────────────────────────────────────────

    async _seleccionarProducto(data) {
        this._productoActual = {
            codigo: data.codigo || '',
            descri: data.descri || '',
            unidad: data.unidad || '',
            peso: data.peso || '0',
            cuenta: data.cuenta || ''
        };

        this._setFieldValue('grid-tcodigo', data.codigo);
        this._setFieldValue('grid-tunidad', data.unidad);
        this._setFieldValue('grid-tpeso', data.peso);
        this._setFieldValue('grid-tctabal', data.cuenta);
        this._aplicarModoUnidadGrid();
        this._actualizarImporteGrid();

        // Consultar kardex para el stock actual
        const alma = document.getElementById('talm').value;
        const fecha = document.getElementById('tfectra').value || '';
        if (alma && data.codigo && fecha) {
            await this._cargarLotes(alma, data.codigo, fecha);
        }
        const lote = document.getElementById('grid-tlote').value || '00000000';
        if (alma && data.codigo) {
            this._consultarKardex(data.codigo, lote, alma);
        }

        // Mantener flujo rapido: no abrir modal ABC automatico tras seleccionar producto.
        setTimeout(() => {
            this._enfocarSiguienteCampoDespuesAbc();
        }, 120);
    }

    _enfocarSiguienteCampoDespuesAbc() {
        // Con el nuevo orden (cencos→producto→lote→...), después de seleccionar
        // el producto el foco debe ir al lote y abrir su selector.
        const activeEl = document.activeElement;
        const producto = document.getElementById('grid-buscar-producto');
        const cencos = document.getElementById('grid-tcencos');

        // Si el foco ya avanzó al lote o más allá (por ejemplo, por la navegación rápida con Enter),
        // no debemos robarle el foco ni volver a abrir el lote.
        const focusEnCencosOProducto = 
            (cencos?.searchableSelectInstance?.displayField === activeEl) ||
            (cencos?.searchableSelectInstance?.searchInput === activeEl) ||
            (producto?.searchableSelectInstance?.displayField === activeEl) ||
            (producto?.searchableSelectInstance?.searchInput === activeEl);

        if (!focusEnCencosOProducto && activeEl !== document.body) {
            return;
        }

        const lote = document.getElementById('grid-tlote');
        if (lote && lote.value !== '') {
            return; // Ya seleccionó lote, no volver a enfocar
        }

        if (lote?.searchableSelectInstance?.displayField) {
            const inst = lote.searchableSelectInstance;
            if (inst.isOpen) return;
            inst.displayField.focus();
            setTimeout(() => inst.open(), 80);
            return;
        }
        if (lote) {
            lote.focus();
        }
    }

    _normalizarLote(valor) {
        const lote = String(valor ?? '').trim();
        return lote || '00000000';
    }

    _getLoteActualGrid() {
        const loteSelect = this._getFieldValue('grid-tlote', '');
        const loteInput = this._getFieldValue('grid-tnumlot', '');
        return this._normalizarLote(loteSelect || loteInput);
    }

    _getCantidadReservadaEnDetalle(codigo, lote) {
        const cod = String(codigo ?? '').trim();
        const lot = this._normalizarLote(lote);
        if (!cod) return 0;

        return this.detalle.reduce((acumulado, item) => {
            const codigoItem = String(item?.tcodigo ?? '').trim();
            const loteItem = this._normalizarLote(item?.tlote || item?.tnumlot);
            if (codigoItem !== cod || loteItem !== lot) return acumulado;

            const cantidad = Number(item?.tcantid ?? 0);
            return acumulado + (Number.isFinite(cantidad) ? cantidad : 0);
        }, 0);
    }

    _actualizarResumenKardexDisponible({ codigo = '', lote = '' } = {}) {
        const codigoActual = String(codigo || this._kardexContext.codigo || this._getCodigoProductoActual() || '').trim();
        const loteActual = this._normalizarLote(lote || this._kardexContext.lote || this._getLoteActualGrid());

        const reservado = this._getCantidadReservadaEnDetalle(codigoActual, loteActual);

        const baseCantidadRaw = Number(this._kardexBase?.qstock ?? 0);
        const basePesoRaw = Number(this._kardexBase?.pstock ?? 0);
        const basePrecioRaw = Number(this._kardexBase?.cosuni ?? 0);
        const baseImporteRaw = Number(this._kardexBase?.vstock ?? 0);

        const baseCantidad = Number.isFinite(baseCantidadRaw) ? baseCantidadRaw : 0;
        const basePeso = Number.isFinite(basePesoRaw) ? basePesoRaw : 0;
        const basePrecio = Number.isFinite(basePrecioRaw) ? basePrecioRaw : 0;
        const baseImporte = Number.isFinite(baseImporteRaw) ? baseImporteRaw : 0;

        // Determinar dirección de la transacción (entrada o salida)
        const codtra = this._getFieldValue('tcodtra', '');
        const esSalida = String(codtra).charAt(0).toUpperCase() === 'S';

        // SALIDA: mostrar stock disponible (validación de no despachar más de lo que hay)
        // ENTRADA: mostrar stock base (no descontar nada, se está sumando al inventario)
        const disponibleCantidad = esSalida
            ? Math.max(0, baseCantidad - reservado)
            : baseCantidad;

        this._kardexActual = {
            qstock: disponibleCantidad,
            pstock: basePeso,
            cosuni: basePrecio,
            vstock: baseImporte
        };

        const stockRef = document.getElementById('grid-stock-ref');
        if (stockRef) {
            if (esSalida) {
                stockRef.textContent = `Stock: ${baseCantidad.toFixed(2)} | En detalle: ${reservado.toFixed(2)} | Disponible: ${disponibleCantidad.toFixed(2)}`;
            } else {
                stockRef.textContent = `Stock: ${baseCantidad.toFixed(2)} | A ingresar: ${reservado.toFixed(2)} | Total proyectado: ${(baseCantidad + reservado).toFixed(2)}`;
            }
        }

        const setResumen = (id, etiqueta, valor) => {
            const el = document.getElementById(id);
            if (!el) return;
            const n = Number(valor);
            el.textContent = `${etiqueta}: ${Number.isFinite(n) ? n.toFixed(2) : '0.00'}`;
        };

        setResumen('grid-res-cantidad', 'Cantidad', disponibleCantidad);
        setResumen('grid-res-peso', 'Peso', basePeso);
        setResumen('grid-res-precio', 'Precio', basePrecio);
        setResumen('grid-res-importe', 'Importe', baseImporte);
    }

    _validarCantidadDisponibleLote({ mostrarPopup = false, refocusInput = false } = {}) {
        if (!this._kardexContext?.codigo || !this._kardexContext?.alma) return true;

        // Solo validar stock para SALIDAS; en ENTRADAS no hay límite de stock
        const codtra = this._getFieldValue('tcodtra', '');
        const esSalida = String(codtra).charAt(0).toUpperCase() === 'S';
        if (!esSalida) return true;

        const inputCantidad = document.getElementById('grid-tcantid');
        const cantidad = Number(inputCantidad?.value ?? 0);
        const cantidadSolicitada = Number.isFinite(cantidad) ? cantidad : 0;
        if (cantidadSolicitada <= 0) return true;

        const disponibleRaw = Number(this._kardexActual?.qstock ?? 0);
        const disponible = Number.isFinite(disponibleRaw) ? disponibleRaw : 0;

        if (cantidadSolicitada <= disponible) return true;

        if (mostrarPopup) {
            if (this._popupCantidadInsuficienteActivo) return false;
            if (window.Swal?.isVisible?.()) return false;

            const key = [
                this._kardexContext?.codigo || '',
                this._kardexContext?.lote || '',
                cantidadSolicitada.toFixed(4),
                disponible.toFixed(4)
            ].join('|');
            const now = Date.now();
            const esRepetidoReciente = this._ultimoPopupCantidad.key === key && (now - this._ultimoPopupCantidad.at) < 900;
            if (esRepetidoReciente) return false;

            this._popupCantidadInsuficienteActivo = true;
            this._ultimoPopupCantidad = { key, at: now };

            if (document.activeElement && typeof document.activeElement.blur === 'function') {
                document.activeElement.blur();
            }

            Promise.resolve(
                this._popupWarning(`No hay cantidad suficiente en el lote actual. Disponible: ${disponible.toFixed(2)}.`)
            ).finally(() => {
                this._popupCantidadInsuficienteActivo = false;

                if (refocusInput && inputCantidad) {
                    setTimeout(() => {
                        inputCantidad.focus();
                        if (typeof inputCantidad.select === 'function') {
                            inputCantidad.select();
                        }
                    }, 0);
                }
            });
            return false;
        }

        return false;
    }

    _recalcularResumenStockSegunContexto() {
        const { codigo, lote, alma } = this._kardexContext || {};
        if (!codigo || !alma) return;
        this._actualizarResumenKardexDisponible({ codigo, lote });
    }

    _actualizarCencosNombreLabel() {
        const selectCencos = document.getElementById('grid-tcencos');
        const lblNombre = document.getElementById('grid-res-cencos-nombre');
        if (!lblNombre) return;

        if (selectCencos && selectCencos.value) {
            const option = selectCencos.selectedOptions[0];
            const nombre = option ? (option.dataset.nombre || '') : '';
            lblNombre.textContent = nombre ? `Centro: ${nombre}` : 'Centro: -';
        } else {
            lblNombre.textContent = 'Centro: -';
        }
    }

    async _consultarKardex(codigo, lote, alma) {
        const res = await this.service.getKardex(codigo, lote, alma);
        const k = res?.data || {};

        const qstock = Number(k.qstock ?? 0);
        const pstock = Number(k.pstock ?? 0);
        const cosuni = Number(k.cosuni ?? 0);
        const vstock = Number(k.vstock ?? 0);

        this._kardexBase = {
            qstock: Number.isFinite(qstock) ? qstock : 0,
            pstock: Number.isFinite(pstock) ? pstock : 0,
            cosuni: Number.isFinite(cosuni) ? cosuni : 0,
            vstock: Number.isFinite(vstock) ? vstock : 0
        };

        this._kardexContext = {
            codigo: String(codigo ?? '').trim(),
            lote: this._normalizarLote(lote),
            alma: String(alma ?? '').trim()
        };

        this._actualizarResumenKardexDisponible({ codigo, lote });

        // Auto-rellenar precio unitario con costo unitario del kardex para movimientos de SALIDA
        const codtra = (document.getElementById('tcodtra')?.value || '').trim();
        const esSalida = codtra.charAt(0).toUpperCase() === 'S';
        if (esSalida && cosuni > 0) {
            const precioEl = document.getElementById('grid-tpreuni');
            if (precioEl && !parseFloat(precioEl.value)) {
                precioEl.value = cosuni.toFixed(4);
                this._actualizarImporteGrid();
            }
        }
    }

    _padAbc(value) {
        return String(value ?? '').padStart(2, '0').slice(-2);
    }

    _getAbcConcatenado(codes = null) {
        const src = codes || {
            proc: document.getElementById('tcodproc')?.value || '00',
            subp: document.getElementById('tcodsubproc')?.value || '00',
            acti: document.getElementById('tcodacti')?.value || '00',
            tarea: document.getElementById('tcodtarea')?.value || '00',
        };

        return [src.proc, src.subp, src.acti, src.tarea]
            .map(v => this._padAbc(v))
            .join('');
    }

    _actualizarAbcDisplay() {
        const input = document.getElementById('grid-costoabc-display');
        if (!input) return;
        input.value = this._getAbcConcatenado();
    }

    _actualizarRutaAbcDesdeDisplay() {
        const input = document.getElementById('grid-costoabc-display');
        if (!input) return;

        const value = (input.value || '').padEnd(8, '0').substring(0, 8);

        // Dividir en 4 partes de 2 dígitos
        const proc = value.substring(0, 2);
        const subp = value.substring(2, 4);
        const acti = value.substring(4, 6);
        const tarea = value.substring(6, 8);

        // Actualizar los campos ocultos
        document.getElementById('tcodproc').value = proc;
        document.getElementById('tcodsubproc').value = subp;
        document.getElementById('tcodacti').value = acti;
        document.getElementById('tcodtarea').value = tarea;

        // Actualizar la ruta usando los nombres del modal si existen
        this._actualizarRutaAbc();
    }

    _actualizarRutaAbc() {
        const rutaEl = document.getElementById('grid-abc-ruta');
        if (!rutaEl) return;

        const proc = document.getElementById('tcodproc')?.value || '00';
        const subp = document.getElementById('tcodsubproc')?.value || '00';
        const acti = document.getElementById('tcodacti')?.value || '00';
        const tarea = document.getElementById('tcodtarea')?.value || '00';

        const value = `${proc}${subp}${acti}${tarea}`;

        if (!value || value === '00000000') {
            rutaEl.textContent = 'Sin ruta seleccionada';
            rutaEl.title = 'Sin ruta seleccionada';
            return;
        }

        // Intentar usar los nombres guardados del modal ABC
        const nombres = this._abcModalState?.names || {};
        const partes = [];

        if (proc !== '00') {
            const nombreProc = nombres.proc || this._abcProcesos?.find(p => p.tcod_proceso === proc)?.tnom_proceso || proc;
            partes.push(nombreProc);
        }

        if (subp !== '00') {
            partes.push(nombres.subp || subp);
        }

        if (acti !== '00') {
            partes.push(nombres.acti || acti);
        }

        if (tarea !== '00') {
            partes.push(nombres.tarea || tarea);
        }

        const rutaTexto = partes.length > 0 ? partes.join(' > ') : value;
        rutaEl.textContent = rutaTexto;
        rutaEl.title = rutaTexto;
    }

    _setAbcCodes(codes) {
        document.getElementById('tcodproc').value = this._padAbc(codes.proc || '00');
        document.getElementById('tcodsubproc').value = this._padAbc(codes.subp || '00');
        document.getElementById('tcodacti').value = this._padAbc(codes.acti || '00');
        document.getElementById('tcodtarea').value = this._padAbc(codes.tarea || '00');
        this._actualizarAbcDisplay();
        this._actualizarRutaAbc(); // Actualizar la ruta usando los nombres del modal
    }

    _actualizarChipsAbc() {
        const { selected } = this._abcModalState;
        const proc = this._padAbc(selected.proc || '00');
        const subp = this._padAbc(selected.subp || '00');
        const acti = this._padAbc(selected.acti || '00');
        const tarea = this._padAbc(selected.tarea || '00');

        const setText = (id, txt) => {
            const el = document.getElementById(id);
            if (el) el.textContent = txt;
        };

        setText('abc-chip-proc', proc);
        setText('abc-chip-subp', subp);
        setText('abc-chip-acti', acti);
        setText('abc-chip-tarea', tarea);
        setText('abc-full-code', `${proc}${subp}${acti}${tarea}`);

        const stageMap = ['Proceso', 'Subproceso', 'Actividad', 'Tarea'];
        setText('abc-etapa', stageMap[this._abcModalState.level] || 'Tarea');

        const descEl = document.getElementById('abc-etapa-desc');
        if (descEl) {
            const descMap = [
                'Seleccione un proceso',
                selected.proc ? `Proceso ${proc}: seleccione subproceso` : 'Seleccione un proceso primero',
                selected.subp ? `Subproceso ${subp}: seleccione actividad` : 'Seleccione subproceso primero',
                selected.acti ? `Actividad ${acti}: seleccione tarea` : 'Seleccione actividad primero'
            ];
            descEl.textContent = descMap[this._abcModalState.level] || '';
        }

        const btnBack = document.getElementById('btn-abc-volver');
        if (btnBack) btnBack.disabled = this._abcModalState.level === 0;

        // Actualizar la guia de ruta DENTRO del modal (#abc-ruta)
        const rutaModalEl = document.getElementById('abc-ruta');
        if (rutaModalEl) {
            const nombres = this._abcModalState.names || {};
            const partes = [];
            if (selected.proc && selected.proc !== '00') partes.push(nombres.proc || proc);
            if (selected.subp && selected.subp !== '00') partes.push(nombres.subp || subp);
            if (selected.acti && selected.acti !== '00') partes.push(nombres.acti || acti);
            if (selected.tarea && selected.tarea !== '00') partes.push(nombres.tarea || tarea);
            rutaModalEl.textContent = partes.length > 0 ? partes.join(' > ') : 'Sin ruta seleccionada';
        }
    }

    async _cargarNivelAbc(level) {
        const s = this._abcModalState.selected;
        let rows = [];

        if (level === 0) {
            rows = this._abcProcesos.map(r => ({ codigo: r.tcod_proceso, nombre: r.tnom_proceso }));
        } else if (level === 1 && s.proc) {
            const res = await this.service.getSubprocesos(s.proc);
            rows = (res.data || []).map(r => ({ codigo: r.tcod_subproc, nombre: r.tnom_subproc }));
        } else if (level === 2 && s.proc && s.subp) {
            const res = await this.service.getActividades(s.proc, s.subp);
            rows = (res.data || []).map(r => ({ codigo: r.tcod_acti, nombre: r.tnom_acti }));
        } else if (level === 3 && s.proc && s.subp && s.acti) {
            const res = await this.service.getTareas(s.proc, s.subp, s.acti);
            rows = (res.data || []).map(r => ({ codigo: r.tcod_tarea, nombre: r.tnom_tarea }));
        }

        this._abcModalState.rows = rows;
    }

    _renderListaAbc() {
        const tbody = document.getElementById('abc-lista-body');
        if (!tbody) return;

        const filtro = (document.getElementById('abc-buscar')?.value || '').trim().toLowerCase();
        const lista = (this._abcModalState.rows || []).filter(r => {
            const cod = String(r.codigo || '').toLowerCase();
            const nom = String(r.nombre || '').toLowerCase();
            return !filtro || cod.includes(filtro) || nom.includes(filtro);
        });

        if (!lista.length) {
            tbody.innerHTML = '<tr><td colspan="2" class="text-center text-gray-400 py-6">Sin datos para este nivel.</td></tr>';
            return;
        }

        const escapeHtml = (v) => String(v ?? '')
            .replaceAll('&', '&amp;')
            .replaceAll('<', '&lt;')
            .replaceAll('>', '&gt;')
            .replaceAll('"', '&quot;')
            .replaceAll("'", '&#39;');

        tbody.innerHTML = lista.map(item => `
            <tr data-cod="${escapeHtml(this._padAbc(item.codigo))}" data-nom="${escapeHtml(item.nombre)}">
                <td>${escapeHtml(this._padAbc(item.codigo))}</td>
                <td>${escapeHtml(item.nombre)}</td>
            </tr>
        `).join('');

        this._abcHighlightIndex = 0;
        this._actualizarHighlightAbc();

        Array.from(tbody.querySelectorAll('tr')).forEach(row => {
            row.addEventListener('click', () => {
                this._seleccionarFilaAbc(row);
            });
        });
    }

    _actualizarHighlightAbc() {
        const tbody = document.getElementById('abc-lista-body');
        if (!tbody) return;

        const rows = tbody.querySelectorAll('tr');
        if (rows.length === 0) return;

        if (this._abcHighlightIndex < 0) this._abcHighlightIndex = 0;
        if (this._abcHighlightIndex >= rows.length) this._abcHighlightIndex = rows.length - 1;

        rows.forEach((row, index) => {
            if (index === this._abcHighlightIndex) {
                row.classList.add('bg-blue-50/70', 'border-l-4', 'border-blue-500');
                row.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
            } else {
                row.classList.remove('bg-blue-50/70', 'border-l-4', 'border-blue-500');
            }
        });
    }

    async _seleccionarFilaAbc(row) {
        if (!row) return;
        const cod = row.dataset.cod || '00';
        const nom = row.dataset.nom || '';
        const s = this._abcModalState.selected;
        const n = this._abcModalState.names;

        if (this._abcModalState.level === 0) {
            s.proc = cod; n.proc = nom;
            s.subp = ''; n.subp = '';
            s.acti = ''; n.acti = '';
            s.tarea = ''; n.tarea = '';
            this._abcModalState.level = 1;
        } else if (this._abcModalState.level === 1) {
            s.subp = cod; n.subp = nom;
            s.acti = ''; n.acti = '';
            s.tarea = ''; n.tarea = '';
            this._abcModalState.level = 2;
        } else if (this._abcModalState.level === 2) {
            s.acti = cod; n.acti = nom;
            s.tarea = ''; n.tarea = '';
            this._abcModalState.level = 3;
        } else {
            s.tarea = cod; n.tarea = nom;
        }

        this._actualizarChipsAbc();
        await this._cargarNivelAbc(this._abcModalState.level);
        this._renderListaAbc();

        const buscar = document.getElementById('abc-buscar');
        if (buscar) {
            buscar.value = '';
            buscar.focus();
        }

        // Si ya completamos todos los niveles (nivel 3 es Tarea y ya fue seleccionada),
        // podemos aceptar automáticamente el modal para ahorrarle pasos al usuario.
        if (this._abcModalState.level === 3 && s.tarea) {
            this._aceptarModalAbc();
        }
    }

    async _abrirModalAbc(options = {}) {
        const { autoFlow = false } = options;
        const modal = document.getElementById('modal-abc');
        if (!modal) return;

        this._abcAutoFlowActivo = !!autoFlow;
        this._abcActiveElementBeforeOpen = document.activeElement;

        const baseCodes = {
            proc: document.getElementById('tcodproc')?.value || '00',
            subp: document.getElementById('tcodsubproc')?.value || '00',
            acti: document.getElementById('tcodacti')?.value || '00',
            tarea: document.getElementById('tcodtarea')?.value || '00',
        };

        this._abcModalState.selected = {
            proc: this._padAbc(baseCodes.proc),
            subp: this._padAbc(baseCodes.subp),
            acti: this._padAbc(baseCodes.acti),
            tarea: this._padAbc(baseCodes.tarea),
        };
        this._abcModalState.names = { proc: '', subp: '', acti: '', tarea: '' };
        this._abcModalState.level = 0;

        this._actualizarChipsAbc();
        await this._cargarNivelAbc(0);
        this._renderListaAbc();

        const buscar = document.getElementById('abc-buscar');
        if (buscar) buscar.value = '';

        modal.style.display = 'flex';
        setTimeout(() => {
            modal.classList.add('show');
            if (buscar) buscar.focus();
        }, 10);
    }

    _cerrarModalAbc(options = {}) {
        const { continuarFlujo = this._abcAutoFlowActivo } = options;
        const modal = document.getElementById('modal-abc');
        if (!modal) return;

        this._abcAutoFlowActivo = false;

        modal.classList.remove('show');
        setTimeout(() => {
            modal.style.display = 'none';

            if (continuarFlujo) {
                this._enfocarSiguienteCampoDespuesAbc();
            } else if (this._abcActiveElementBeforeOpen) {
                try {
                    this._abcActiveElementBeforeOpen.focus();
                } catch (e) {
                    console.warn('Error al enfocar el elemento de origen:', e);
                }
            }
            this._abcActiveElementBeforeOpen = null;
        }, 200);
    }

    async _abcVolverNivel() {
        if (this._abcModalState.level <= 0) return;
        this._abcModalState.level -= 1;
        await this._cargarNivelAbc(this._abcModalState.level);
        this._actualizarChipsAbc();
        this._renderListaAbc();
    }

    _aceptarModalAbc() {
        const s = this._abcModalState.selected;
        const continuarFlujo = this._abcAutoFlowActivo;
        this._setAbcCodes({
            proc: s.proc || '00',
            subp: s.subp || '00',
            acti: s.acti || '00',
            tarea: s.tarea || '00'
        });
        this._cerrarModalAbc({ continuarFlujo });
    }

    _popupSuccess(message) {
        if (window.SwalHelpers?.showSuccess) {
            return window.SwalHelpers.showSuccess(message);
        }
        if (typeof mostrarModal === 'function') {
            return mostrarModal('Éxito', message, '✅');
        }
        return Promise.resolve(null);
    }

    _popupWarning(message) {
        if (window.SwalHelpers?.showWarning) {
            return window.SwalHelpers.showWarning(message);
        }
        if (typeof mostrarModal === 'function') {
            return mostrarModal('Advertencia', message, '⚠️');
        }
        return Promise.resolve(null);
    }

    _popupError(message) {
        if (window.SwalHelpers?.showError) {
            return window.SwalHelpers.showError(message);
        }
        if (typeof mostrarModal === 'function') {
            return mostrarModal('Error', message, '❌');
        }
        return Promise.resolve(null);
    }

    async _popupConfirm(title, text) {
        if (window.SwalHelpers?.showConfirm) {
            return await window.SwalHelpers.showConfirm(title, text);
        }

        if (window.Swal?.fire) {
            const result = await window.Swal.fire({
                title,
                text,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Aceptar',
                cancelButtonText: 'Cancelar'
            });
            return !!result.isConfirmed;
        }

        if (typeof mostrarModal === 'function') {
            mostrarModal(title, text, '⚠️');
        }
        return false;
    }

    async _popupOpcionesGuardar(formatoSeleccionado) {
        const formatoLabel = formatoSeleccionado === '80mm' ? 'Ticket 80mm' : 'A4';

        if (window.Swal?.fire) {
            const isDark = document.body?.classList.contains('dark-mode');
            const themeOptions = isDark
                ? {
                    background: '#111827',
                    color: '#e5e7eb',
                    confirmButtonColor: '#2563eb',
                    denyButtonColor: '#334155',
                    cancelButtonColor: '#64748b'
                }
                : {
                    background: '#ffffff',
                    color: '#1f2937',
                    confirmButtonColor: '#3085d6',
                    denyButtonColor: '#4b5563',
                    cancelButtonColor: '#6b7280'
                };

            let _altHandler = null;

            const result = await window.Swal.fire({
                ...themeOptions,
                title: 'Guardar movimiento',
                html: `Formato seleccionado: <b>${formatoLabel}</b><br><small>Elija una opción para continuar.</small>`,
                icon: 'question',
                showCancelButton: true,
                showDenyButton: true,
                confirmButtonText: 'Guardar e imprimir',
                denyButtonText: 'Solo guardar',
                cancelButtonText: 'Cancelar',
                reverseButtons: true,
                didOpen: (popup) => {
                    // Inyectar etiquetas de atajo encima de cada botón
                    const actions = popup.querySelector('.swal2-actions');
                    if (actions) {
                        const labelStyle = 'display:block; font-size:10px; opacity:0.55; font-family:monospace; letter-spacing:0.05em; text-align:center; margin-bottom:3px;';
                        const wrapBtn = (selector, label) => {
                            const btn = actions.querySelector(selector);
                            if (!btn) return;
                            const wrap = document.createElement('div');
                            wrap.style.cssText = 'display:inline-flex; flex-direction:column; align-items:center;';
                            const kbdEl = document.createElement('span');
                            kbdEl.textContent = label;
                            kbdEl.style.cssText = labelStyle;
                            btn.parentNode.insertBefore(wrap, btn);
                            wrap.appendChild(kbdEl);
                            wrap.appendChild(btn);
                        };
                        wrapBtn('.swal2-cancel', 'Alt + C');
                        wrapBtn('.swal2-deny', 'Alt + G');
                        wrapBtn('.swal2-confirm', 'Alt + I');
                    }

                    // Captura con true para que Swal no bloquee el evento
                    _altHandler = (e) => {
                        if (!e.altKey) return;
                        const key = e.key.toLowerCase();
                        if (key === 'i') {
                            e.preventDefault();
                            popup.querySelector('.swal2-confirm')?.click();
                        } else if (key === 'g') {
                            e.preventDefault();
                            popup.querySelector('.swal2-deny')?.click();
                        } else if (key === 'c') {
                            e.preventDefault();
                            popup.querySelector('.swal2-cancel')?.click();
                        }
                    };
                    document.addEventListener('keydown', _altHandler, true);
                },
                willClose: () => {
                    if (_altHandler) {
                        document.removeEventListener('keydown', _altHandler, true);
                        _altHandler = null;
                    }
                }
            });

            if (result.isConfirmed) return 'guardar-imprimir';
            if (result.isDenied) return 'guardar';
            return null;
        }

        const confirmado = window.confirm('Aceptar = Guardar e imprimir. Cancelar = Solo guardar.');
        return confirmado ? 'guardar-imprimir' : 'guardar';
    }

    _getFormatoImpresionSeleccionado() {
        const formato = document.querySelector('input[name="formato-impresion"]:checked')?.value || 'a4';
        return formato === '80mm' ? '80mm' : 'a4';
    }

    _escapeHtml(value) {
        return String(value ?? '')
            .replaceAll('&', '&amp;')
            .replaceAll('<', '&lt;')
            .replaceAll('>', '&gt;')
            .replaceAll('"', '&quot;')
            .replaceAll("'", '&#39;');
    }

    _buildHtmlImpresionMovimiento({ treg, formato }) {
        const esTicket = formato === '80mm';
        const fechaRaw = this._getFieldValue('tfectra', '');
        const partesFecha = String(fechaRaw || '').split('-');
        const fechaCabecera = (partesFecha.length === 3)
            ? `${partesFecha[2]}/${partesFecha[1]}/${partesFecha[0]}`
            : this._escapeHtml(fechaRaw || '-');

        const horaCabecera = new Date().toLocaleTimeString('es-PE', {
            hour12: false,
            hour: '2-digit',
            minute: '2-digit',
            second: '2-digit'
        });

        const totalProductos = this.detalle.length;
        const totalCantidadRaw = this.detalle.reduce((acc, item) => acc + (parseFloat(item.tcantid || 0) || 0), 0);
        const totalCantidad = Number.isInteger(totalCantidadRaw)
            ? String(totalCantidadRaw)
            : totalCantidadRaw.toFixed(2);

        const rowsA4 = this.detalle.map((item) => {
            const cantidadRaw = Number(item.tcantid || 0);
            const cantidad = Number.isInteger(cantidadRaw) ? String(cantidadRaw) : cantidadRaw.toFixed(2);
            const pesoRaw = Number(item.tpeso || 0);
            const peso = Number.isInteger(pesoRaw) ? String(pesoRaw) : pesoRaw.toFixed(2);
            const precioRaw = Number(item.tpreuni || 0);
            const precio = Number.isInteger(precioRaw) ? String(precioRaw) : precioRaw.toFixed(2);
            const importeRaw = Number(item.timport || 0);
            const importe = Number.isInteger(importeRaw) ? String(importeRaw) : importeRaw.toFixed(2);
            const lote = this._escapeHtml(item.tnumlot || item.tlote || '');
            const cencos = this._escapeHtml(item.tcencos || '');
            const abc = `${this._padAbc(item.tcodproc || '00')}${this._padAbc(item.tcodsubproc || '00')}${this._padAbc(item.tcodacti || '00')}${this._padAbc(item.tcodtarea || '00')}`;

            return `
                <tr>
                    <td>${this._escapeHtml(item.tcodigo || '')}</td>
                    <td>${this._escapeHtml(item.tdescri || '')}</td>
                    <td>${cencos}</td>
                    <td>${this._escapeHtml(abc)}</td>
                    <td>${lote}</td>
                    <td class="num">${cantidad}</td>
                    <td class="num">${peso}</td>
                    <td class="num">${precio}</td>
                    <td class="num">${importe}</td>
                </tr>`;
        }).join('');

        const rowsTicket = this.detalle.map((item) => {
            const cantidadRaw = Number(item.tcantid || 0);
            const cantidad = Number.isInteger(cantidadRaw) ? String(cantidadRaw) : cantidadRaw.toFixed(2);
            const lote = this._escapeHtml(item.tnumlot || item.tlote || '');
            const codigo = this._escapeHtml(item.tcodigo || '');
            const descripcion = this._escapeHtml(item.tdescri || '');

            return `
                <tr>
                    <td>${codigo}</td>
                    <td>${descripcion}</td>
                    <td>${lote}</td>
                    <td class="num">${cantidad}</td>
                </tr>`;
        }).join('');

        const tableHead = esTicket
            ? `
                <tr>
                    <th class="w-codigo">CODIGO</th>
                    <th class="w-descripcion">DESCRIPCION</th>
                    <th class="w-lote">LOTE</th>
                    <th class="num w-cant">CANT</th>
                </tr>`
            : `
                <tr>
                    <th class="w-codigo">CODIGO</th>
                    <th class="w-descripcion">DESCRIPCION</th>
                    <th class="w-cencos">CENCOS</th>
                    <th class="w-abc">ABC</th>
                    <th class="w-lote">LOTE</th>
                    <th class="num w-cant">CANT</th>
                    <th class="num w-peso">PESO KG</th>
                    <th class="num w-precio">PRECIO</th>
                    <th class="num w-importe">IMPORTE</th>
                </tr>`;

        const rows = esTicket ? rowsTicket : rowsA4;
        const colspan = esTicket ? 4 : 9;

        const footerA4 = `
            <div class="footer">
                <div class="footer-item">
                    <span class="label">Nro Productos:</span>
                    <span class="value">${this._escapeHtml(String(totalProductos))}</span>
                </div>
                <div class="footer-item">
                    <span class="label">TOTAL CANT:</span>
                    <span class="value">${this._escapeHtml(totalCantidad)}</span>
                </div>
            </div>`;

        const footerTicket = `
            <div class="footer-ticket">
                <div class="footer-ticket-item">
                    <span class="label">Nro Productos:</span>
                    <span class="value">${this._escapeHtml(String(totalProductos))}</span>
                </div>
                <div class="footer-ticket-item">
                    <span class="label">TOTAL CANT:</span>
                    <span class="value">${this._escapeHtml(totalCantidad)}</span>
                </div>
            </div>`;

        return `<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>VISTA PREVIA DE MIS PRODUCTOS A ENTREGAR</title>
    <style>
        @page { size: ${esTicket ? '80mm auto' : 'A4'}; margin: ${esTicket ? '4mm' : '10mm'}; }
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            color: #111827;
            font-size: ${esTicket ? '10px' : '11px'};
            background: ${esTicket ? '#ffffff' : '#e5e7eb'};
            padding: ${esTicket ? '0' : '12px'};
        }
        .sheet {
            width: ${esTicket ? '72mm' : '210mm'};
            min-height: ${esTicket ? 'auto' : '297mm'};
            max-width: 100%;
            margin: 0 auto;
            box-sizing: border-box;
            padding: ${esTicket ? '6px' : '14px'};
            background: #ffffff;
            border: ${esTicket ? 'none' : '1px solid #cbd5e1'};
            box-shadow: ${esTicket ? 'none' : '0 6px 18px rgba(15, 23, 42, 0.18)'};
        }
        .meta-row { display: flex; justify-content: space-between; align-items: center; margin-bottom: ${esTicket ? '8px' : '14px'}; font-size: ${esTicket ? '10px' : '12px'}; }
        .titulo { text-align: center; font-size: ${esTicket ? '14px' : '22px'}; font-weight: 700; margin: 0 0 ${esTicket ? '8px' : '14px'} 0; }
        table { width: 100%; border-collapse: collapse; margin-top: 6px; }
        th, td { border: 1px solid #111827; padding: ${esTicket ? '4px' : '6px'} ${esTicket ? '3px' : '6px'}; vertical-align: top; font-size: ${esTicket ? '10px' : '13px'}; }
        th { text-align: center; font-weight: 700; }
        td.num, th.num { text-align: right; }
        .w-codigo { width: ${esTicket ? '18%' : '11%'}; }
        .w-descripcion { width: ${esTicket ? '46%' : '28%'}; }
        .w-cencos { width: 9%; }
        .w-abc { width: 11%; }
        .w-lote { width: ${esTicket ? '22%' : '12%'}; }
        .w-cant { width: ${esTicket ? '14%' : '8%'}; }
        .w-peso { width: 8%; }
        .w-precio { width: 7%; }
        .w-importe { width: 8%; }
        .footer { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 8px; margin-top: 14px; }
        .footer-item { display: flex; justify-content: space-between; align-items: center; border: 1px solid #111827; padding: 6px 10px; font-size: 14px; font-weight: 700; min-height: 40px; box-sizing: border-box; }
        .footer-item .label { white-space: nowrap; }
        .footer-item .value { min-width: 28px; margin-left: 10px; text-align: right; }
        .footer-ticket { margin-top: 10px; border-top: 1px solid #111827; padding-top: 8px; }
        .footer-ticket-item { display: flex; justify-content: space-between; align-items: center; padding: 2px 0; font-size: 11px; font-weight: 700; }
    </style>
</head>
<body>
    <div class="sheet">
        <div class="meta-row">
            <div>${this._escapeHtml(fechaCabecera)}</div>
            <div>${this._escapeHtml(horaCabecera)}</div>
        </div>

        <h1 class="titulo">VISTA PREVIA DE MIS PRODUCTOS A ENTREGAR</h1>

        <table>
            <thead>
                ${tableHead}
            </thead>
            <tbody>
                ${rows || `<tr><td colspan="${colspan}" style="text-align:center;">SIN ITEMS</td></tr>`}
            </tbody>
        </table>

        ${esTicket ? footerTicket : footerA4}
    </div>
</body>
</html>`;
    }

    _extraerNombreArchivoDescarga(disposition, fallbackName) {
        const fallback = fallbackName || `movimiento_${Date.now()}.pdf`;
        if (!disposition) return fallback;

        const utf8Match = disposition.match(/filename\*=UTF-8''([^;]+)/i);
        if (utf8Match?.[1]) {
            try {
                return decodeURIComponent(utf8Match[1]);
            } catch {
                return utf8Match[1];
            }
        }

        const quotedMatch = disposition.match(/filename="([^"]+)"/i);
        if (quotedMatch?.[1]) return quotedMatch[1];

        const plainMatch = disposition.match(/filename=([^;]+)/i);
        if (plainMatch?.[1]) return plainMatch[1].trim();

        return fallback;
    }

    _vistaPreviaMovimientoSimple() {
        if (!this.detalle.length) {
            this._popupWarning('Agregue al menos un item para ver la vista previa.');
            return;
        }

        const formato = this._getFormatoImpresionSeleccionado();
        const treg = this.tregActual || this._getFieldValue('treg', 'PREVIO') || 'PREVIO';
        const html = this._buildHtmlImpresionMovimiento({ treg, formato });
        const tituloFormato = formato === '80mm' ? 'Vista previa de mis productos a entregar (Ticket 80mm)' : 'Vista previa de mis productos a entregar';
        this._abrirModalReportePreviewHtml(html, tituloFormato);
    }

    async _imprimirMovimientoGuardado({ treg, formato }) {
        const formatoSeguro = formato === '80mm' ? '80mm' : 'a4';
        const url = this.service.getMovimientoPdfUrl(treg, {
            formato: formatoSeguro,
            download: 1
        });

        try {
            const response = await fetch(url, {
                method: 'GET',
                credentials: 'include'
            });

            if (!response.ok) {
                throw new Error(`HTTP ${response.status}`);
            }

            const blob = await response.blob();
            const today = new Date().toISOString().split('T')[0];
            const fallbackName = `movimiento_${treg}_${today}.pdf`;
            const fileName = this._extraerNombreArchivoDescarga(
                response.headers.get('content-disposition'),
                fallbackName
            );

            const blobUrl = URL.createObjectURL(blob);
            const link = document.createElement('a');
            link.href = blobUrl;
            link.download = fileName;
            link.style.display = 'none';
            document.body.appendChild(link);
            link.click();
            link.remove();

            setTimeout(() => URL.revokeObjectURL(blobUrl), 1000);
        } catch (error) {
            console.error('Error descargando PDF del movimiento:', error);
            this._popupWarning('El movimiento se guardo, pero no se pudo descargar el PDF.');
        }
    }

    // ── Grid de detalle ───────────────────────────────────────────────────────

    _agregarItemGrid() {
        // Asegurar que la sección de items esté visible (sin enfocar producto)
        this._mostrarSeccionItems({ enfocarProducto: false, abrirSelector: false });

        const codigo = this._getCodigoProductoActual();

        // Obtener la descripción del producto desde el select
        const descripcion = this._getDescripcionProductoActual() || 'Producto sin descripción';

        const inputCantidad = document.getElementById('grid-tcantid');
        const inputPrecio = document.getElementById('grid-tpreuni');
        const inputPeso = document.getElementById('grid-tpeso');
        const inputLote = document.getElementById('grid-tlote');
        const inputNumLote = document.getElementById('grid-tnumlot');

        const item = {
            tcodigo: codigo,
            tdescri: descripcion,
            tcantid: parseFloat(inputCantidad?.value || '0') || 0,
            tpreuni: parseFloat(inputPrecio?.value || '0') || 0,
            tpeso: parseFloat(inputPeso?.value || '0') || 0,
            tsacos: 0,
            tnumlot: (inputNumLote?.value || inputLote?.value || '00000000'),
            tlote: inputLote?.value || '00000000',
            tcencos: document.getElementById('grid-tcencos').value,
            tctabal: document.getElementById('grid-tctabal').value,
            tcodproc: document.getElementById('tcodproc').value || '00',
            tcodsubproc: document.getElementById('tcodsubproc').value || '00',
            tcodacti: document.getElementById('tcodacti').value || '00',
            tcodtarea: document.getElementById('tcodtarea').value || '00',
            talr: document.getElementById('talr').value || '',
            tprod: 'P',
            ttoneladas: 0,
            tkardex: parseFloat(document.getElementById('grid-timport')?.value || '0') || 0,
        };

        if (!item.tcodigo) { this._popupWarning('Seleccione un producto.'); return; }
        if (item.tcantid <= 0) { this._popupWarning('La cantidad debe ser mayor a 0.'); return; }
        if (!item.tcencos) {
            this._popupWarning('Debe seleccionar un Centro de Costo (CENCOS).');
            const cencosEl = document.getElementById('grid-tcencos');
            if (cencosEl?.searchableSelectInstance) {
                cencosEl.searchableSelectInstance.displayField.focus();
                setTimeout(() => cencosEl.searchableSelectInstance.open(), 50);
            } else {
                cencosEl?.focus();
            }
            return;
        }

        const alma = (document.getElementById('talm')?.value || '').trim();
        const loteActual = this._normalizarLote(item.tlote || item.tnumlot);
        const contextoCoincide = this._kardexContext.codigo === item.tcodigo
            && this._kardexContext.lote === loteActual
            && this._kardexContext.alma === alma;

        if (!contextoCoincide) {
            this._kardexContext = {
                codigo: item.tcodigo,
                lote: loteActual,
                alma: alma
            };
            this._recalcularResumenStockSegunContexto();
        }

        if (!this._validarCantidadDisponibleLote({ mostrarPopup: true, refocusInput: true })) {
            return;
        }

        if (this._esUnidadKgs()) {
            const importeManual = parseFloat(document.getElementById('grid-timport')?.value || '0') || 0;
            item.timport = this._redondear(importeManual);
            item.tpreuni = item.tcantid > 0 ? this._redondear(item.timport / item.tcantid) : 0;
            if (item.tpeso <= 0) {
                this._popupWarning('Para unidad KGS debe ingresar el peso.');
                return;
            }
        } else {
            item.timport = this._redondear(item.tcantid * item.tpreuni);
        }

        if (item.timport < 0) {
            this._popupWarning('El importe no puede ser negativo.');
            return;
        }

        this.detalle.push(item);
        this._renderGrid();
        this._limpiarCamposGrid();
        this._actualizarTotales();
        this._autoSaveDraft();
    }

    _eliminarItemGrid(idx) {
        this.detalle.splice(idx, 1);
        this._renderGrid();
        this._actualizarTotales();
        this._recalcularResumenStockSegunContexto();
        this._autoSaveDraft();
    }

    _seleccionarFila(idx) {
        if (this.filaSeleccionadaIndex === idx) {
            this.filaSeleccionadaIndex = -1;
        } else {
            this.filaSeleccionadaIndex = idx;
        }
        this._renderGrid();
    }

    _quitarFilaSeleccionada() {
        if (this.filaSeleccionadaIndex === undefined || this.filaSeleccionadaIndex === -1) {
            if (this.detalle.length > 0) {
                this._eliminarItemGrid(this.detalle.length - 1);
            } else {
                this._popupWarning('Seleccione una fila de la grilla para quitar.');
            }
            return;
        }

        this._eliminarItemGrid(this.filaSeleccionadaIndex);
        this.filaSeleccionadaIndex = -1;
    }

    async _cambiarItemGrid(idx) {
        const item = this.detalle[idx];
        if (!item) return;

        const selectProducto = document.getElementById('grid-buscar-producto');
        if (selectProducto) {
            let optionProducto = Array.from(selectProducto.options).find(opt => opt.value === item.tcodigo);
            if (!optionProducto) {
                optionProducto = document.createElement('option');
                optionProducto.value = item.tcodigo;
                optionProducto.textContent = `${item.tcodigo} - ${item.tdescri || ''}`;
                optionProducto.dataset.descri = item.tdescri || '';
                optionProducto.dataset.unidad = item.tunidad || '';
                optionProducto.dataset.peso = item.tpeso ?? '0';
                optionProducto.dataset.cuenta = item.tctabal || '';
                selectProducto.appendChild(optionProducto);
            }
            selectProducto.value = item.tcodigo || '';
            if (selectProducto.searchableSelectInstance) {
                selectProducto.searchableSelectInstance.loadOptions();
                selectProducto.searchableSelectInstance.updateDisplayText();
            }
            this._autoSaveDraft();
        }

        const alma = document.getElementById('talm')?.value || '';
        const fecha = document.getElementById('tfectra')?.value || '';
        if (alma && item.tcodigo && fecha) {
            await this._cargarLotes(alma, item.tcodigo, fecha);
        }

        const selectLote = document.getElementById('grid-tlote');
        const loteValue = item.tlote || item.tnumlot || '';
        if (selectLote && loteValue) {
            const existeLote = Array.from(selectLote.options).some(opt => opt.value === loteValue);
            if (!existeLote) {
                const optionLote = document.createElement('option');
                optionLote.value = loteValue;
                optionLote.textContent = loteValue;
                selectLote.appendChild(optionLote);
            }
            selectLote.value = loteValue;
            if (selectLote.searchableSelectInstance) {
                selectLote.searchableSelectInstance.loadOptions();
                selectLote.searchableSelectInstance.updateDisplayText();
            }
        }

        const selectCencos = document.getElementById('grid-tcencos');
        if (selectCencos && item.tcencos) {
            const existeCencos = Array.from(selectCencos.options).some(opt => opt.value === item.tcencos);
            if (!existeCencos) {
                const optionCencos = document.createElement('option');
                optionCencos.value = item.tcencos;
                optionCencos.textContent = item.tcencos;
                optionCencos.dataset.display = item.tcencos;
                selectCencos.appendChild(optionCencos);
            }
            selectCencos.value = item.tcencos;
            if (selectCencos.searchableSelectInstance) {
                selectCencos.searchableSelectInstance.loadOptions();
                selectCencos.searchableSelectInstance.updateDisplayText();
            }
            this._actualizarCencosNombreLabel();
        }

        this._productoActual = {
            codigo: item.tcodigo || '',
            descri: item.tdescri || '',
            unidad: item.tunidad || '',
            peso: item.tpeso ?? '0',
            cuenta: item.tctabal || ''
        };

        this._setFieldValue('grid-tcodigo', item.tcodigo || '');
        this._setFieldValue('grid-tcantid', item.tcantid ?? 0);
        this._setFieldValue('grid-tpreuni', item.tpreuni ?? 0);
        this._setFieldValue('grid-tpeso', item.tpeso ?? 0);
        this._setFieldValue('grid-timport', item.timport ?? 0);
        this._setFieldValue('grid-tnumlot', item.tnumlot || loteValue);
        this._setFieldValue('grid-tctabal', item.tctabal || '');

        this._setAbcCodes({
            proc: item.tcodproc || '00',
            subp: item.tcodsubproc || '00',
            acti: item.tcodacti || '00',
            tarea: item.tcodtarea || '00'
        });

        this._aplicarModoUnidadGrid();
        this.detalle.splice(idx, 1);
        this._renderGrid();
        this._actualizarTotales();
        this._recalcularResumenStockSegunContexto();
        const actionInput = document.getElementById('sr-action-input');
        if (actionInput) {
            actionInput.focus();
        } else {
            this._enfocarSiguienteCampoDespuesAbc();
        }
    }

    _renderGrid() {
        const tbody = document.getElementById('grid-tbody');
        if (!this.detalle.length) {
            tbody.innerHTML = '<tr><td colspan="10" class="text-center text-gray-400 py-8">Sin ítems</td></tr>';
            return;
        }
        const mostrarPrecio = this._mostrarPrecio !== false;
        const dNone = 'style="display:none"';
        tbody.innerHTML = this.detalle.map((it, i) => {
            const isSelected = this.filaSeleccionadaIndex === i;
            const rowClass = isSelected 
                ? 'bg-blue-50/70 dark:bg-slate-800/80 border-l-4 border-blue-500 transition-all cursor-pointer' 
                : 'hover:bg-gray-50/50 dark:hover:bg-slate-850/40 transition-all cursor-pointer';

            return `
                <tr onclick="movAlmCtrl._seleccionarFila(${i})" class="${rowClass}">
                    <td class="px-4 py-3">${it.tcodigo}</td>
                    <td class="px-4 py-3">${it.tdescri}</td>
                    <td class="px-4 py-3">${it.tcencos}</td>
                    <td class="px-4 py-3 font-mono">${`${this._padAbc(it.tcodproc)}${this._padAbc(it.tcodsubproc)}${this._padAbc(it.tcodacti)}${this._padAbc(it.tcodtarea)}`}</td>
                    <td class="px-4 py-3">${it.tnumlot}</td>
                    <td class="px-4 py-3 text-right">${it.tcantid}</td>
                    <td class="px-4 py-3 text-right">${it.tpeso}</td>
                    <td class="px-4 py-3 text-right col-precio" ${mostrarPrecio ? '' : dNone}>${it.tpreuni}</td>
                    <td class="px-4 py-3 text-right col-importe" ${mostrarPrecio ? '' : dNone}>${it.timport}</td>
                    <td class="px-4 py-3 text-center">
                        <button onclick="event.stopPropagation(); movAlmCtrl._cambiarItemGrid(${i})"
                            class="text-blue-400 hover:text-blue-300 mr-3" title="Cambiar">↺</button>
                        <button onclick="event.stopPropagation(); movAlmCtrl._eliminarItemGrid(${i})"
                            class="text-red-500 hover:text-red-700" title="Eliminar">✕</button>
                    </td>
                </tr>`;
        }).join('');
    }

    _actualizarTotales() {
        const totCantid = this.detalle.reduce((s, i) => s + parseFloat(i.tcantid || 0), 0);
        const totPeso = this.detalle.reduce((s, i) => s + i.tpeso, 0);
        const totImporte = this.detalle.reduce((s, i) => s + i.timport, 0);
        document.getElementById('total-cant').textContent = this._redondear(totCantid).toFixed(2);
        document.getElementById('total-peso').textContent = this._redondear(totPeso).toFixed(2);
        document.getElementById('total-importe').textContent = this._redondear(totImporte).toFixed(2);
    }

    // ── Grabar ────────────────────────────────────────────────────────────────

    async _grabar() {
        const formatoImpresion = this._getFormatoImpresionSeleccionado();
        const accion = await this._popupOpcionesGuardar(formatoImpresion);
        if (!accion) return;

        const imprimirDespues = accion === 'guardar-imprimir';
        await this._guardarMovimiento({ imprimirDespues, formatoImpresion });
    }

    async _guardarMovimiento({ imprimirDespues = false, formatoImpresion = 'a4' } = {}) {
        if (!this.detalle.length) { this._popupWarning('Agregue al menos un ítem.'); return; }

        // Validar que los almacenes no sean iguales
        if (!this._validarAlmacenes()) {
            return;
        }

        const talrFormulario = this._getFieldValue('talr', '');
        const cencosDestinoFormulario = this._getFieldValue('tcencos_dest', '');
        const detallePayload = this.detalle.map(item => ({
            ...item,
            talr: item.talr || talrFormulario || '',
            tcencos: item.tcencos || cencosDestinoFormulario || ''
        }));

        const payload = {
            tfectra: this._getFieldValue('tfectra', ''),
            tcodtra: this._getFieldValue('tcodtra', ''),
            talm: this._getFieldValue('talm', ''),
            talr: talrFormulario,
            tprocli: this._getFieldValue('tprocli', ''),
            tdoc: this._getFieldValue('tdoc', ''),
            tserie: this._getFieldValue('tserie', ''),
            tnumfac: this._getFieldValue('tnumfac', ''),
            tmon: this._getFieldValue('tmon', 'S/') || 'S/',
            tlib: this._getLibroActual(),
            tordcom: this._getFieldValue('tordcom', ''),
            tglosa: this._getFieldValue('tglosa', ''),
            tcostmin: this._getFieldValue('tcostmin', 0) || 0,
            tcod_conductor: document.getElementById('tcod_conductor')?.value || '',
            tplaca: document.getElementById('tplaca')?.value || '',
            tmotivo_traslado: this._getFieldValue('tmotivo_traslado', ''),
            tcencos_dest: cencosDestinoFormulario,
            tfecfac: this._getFieldValue('tfecfac', ''),
            tuser: (() => {
                try {
                    const usuario = JSON.parse(sessionStorage.getItem('usuario') || '{}');
                    // Usamos las llaves reales que acabas de ver en la consola
                    return usuario.username || usuario.id_usuario || 'ADMIN';
                } catch {
                    return 'ADMIN';
                }
            })(),
            detalle: detallePayload,
        };

        let guardadoExitoso = false;

        try {
            document.getElementById('btn-grabar').disabled = true;
            const esEdicion = this.modoEdicion && !!this.tregActual;
            const actualizarMovimiento = (treg, data) => {
                if (typeof this.service.actualizar === 'function') {
                    return this.service.actualizar(treg, data);
                }

                // Fallback defensivo por caché del navegador con versión antigua del servicio.
                return Http.put(`${this.service.base}/cabecera/${encodeURIComponent(treg)}`, data);
            };

            const res = esEdicion
                ? await actualizarMovimiento(this.tregActual, payload)
                : await this.service.crear(payload);

            this.tregActual = res.data.treg;
            this._popupSuccess(`${res.data.mensaje} - Registro #${res.data.treg}`);
            guardadoExitoso = true;

            sessionStorage.removeItem(this._draftKey);

            if (imprimirDespues) {
                await this._imprimirMovimientoGuardado({
                    treg: res.data.treg,
                    formato: formatoImpresion
                });
            }
        } catch (e) {
            this._popupError(`Error: ${e.message ?? 'Error al grabar.'}`);
        } finally {
            if (guardadoExitoso) {
                await this._resetFormulario();
            }
            document.getElementById('btn-grabar').disabled = false;
        }
    }

    async _borrarMovimiento() {
        if (!this.tregActual) { this._popupWarning('No hay movimiento cargado.'); return; }

        const confirmado = await this._popupConfirm(
            'Confirmar eliminación',
            `¿Eliminar movimiento #${this.tregActual}?`
        );
        if (!confirmado) return;

        try {
            await this.service.eliminar(this.tregActual);
            this._popupSuccess('Movimiento eliminado.');
            this._resetFormulario();
        } catch (e) {
            this._popupError(e.message || 'No se pudo eliminar el movimiento.');
        }
    }

    async _borrarTodos() {
        const confirmado = await this._popupConfirm(
            'Limpiar detalle',
            '¿Limpiar todos los ítems del detalle?'
        );
        if (!confirmado) return;

        this.detalle = [];
        this._renderGrid();
        this._actualizarTotales();
        
        sessionStorage.removeItem(this._draftKey);
    }

    // ── Ver movimientos ───────────────────────────────────────────────────────

    async _verMovimientos() {
        try {
            // Mostrar el modal
            mostrarModalMovimientos();

            // Cargar movimientos
            const res = await this.service.listar({ limit: 100 });
            const movimientos = (res.data || [])
                .slice()
                .sort((a, b) => (parseInt(a.treg, 10) || 0) - (parseInt(b.treg, 10) || 0));

            // Renderizar la tabla
            const tbody = document.getElementById('tabla-movimientos-body');
            const texto = (valor) => {
                if (valor === null || valor === undefined || valor === '') return '-';
                return String(valor)
                    .replaceAll('&', '&amp;')
                    .replaceAll('<', '&lt;')
                    .replaceAll('>', '&gt;')
                    .replaceAll('"', '&quot;')
                    .replaceAll("'", '&#39;');
            };

            if (movimientos.length === 0) {
                tbody.innerHTML = '<tr><td colspan="24" class="text-center text-gray-400 py-8">No hay movimientos registrados</td></tr>';
                return;
            }

            tbody.innerHTML = movimientos.map(mov => `
                <tr onclick="movAlmCtrl._cargarMovimiento('${mov.treg}')" class="hover:bg-blue-50">
                    <td class="font-semibold text-blue-600">${texto(mov.treg)}</td>
                    <td>${texto(mov.tfectra)}</td>
                    <td>${texto(mov.tcodtra)} - ${texto(mov.nom_transaccion)}</td>
                    <td>${texto(mov.talm)} - ${texto(mov.nom_almacen)}</td>
                    <td>${texto(mov.talr)}</td>
                    <td>${texto(mov.tprocli)}</td>
                    <td>${texto(mov.tmon)}</td>
                    <td>${texto(mov.tlib)}</td>
                    <td>${texto(mov.tdoc)}</td>
                    <td>${texto(mov.tserie)}</td>
                    <td>${texto(mov.tnumfac)}</td>
                    <td>${texto(mov.tfecfac)}</td>
                    <td>${texto(mov.tcencos_dest)}</td>
                    <td>${texto(mov.tordcom)}</td>
                    <td>${texto(mov.tmotivo_traslado)}</td>
                    <td class="max-w-xs truncate" title="${texto(mov.tglosa)}">${texto(mov.tglosa)}</td>
                    <td>${texto(mov.tcostmin)}</td>
                    <td>${texto(mov.tpesotot)}</td>
                    <td>${texto(mov.timport)}</td>
                    <td>${texto(mov.tcod_conductor)}</td>
                    <td>${texto(mov.tplaca)}</td>
                    <td>${texto(mov.tuser)}</td>
                    <td>${texto(mov.tdate)}</td>
                    <td>${texto(mov.ttime)}</td>
                </tr>
            `).join('');

        } catch (e) {
            console.error('Error cargando movimientos:', e);
            mostrarModal('Error', 'No se pudieron cargar los movimientos. ' + (e.message || ''), '❌');
        }
    }

    async _generarReporteKardexPdf(modoDirecto = false) {
        try {
            const alma = document.getElementById('talm')?.value || '';
            if (!alma) {
                this._popupWarning('Seleccione un almacen antes de generar el reporte.');
                return;
            }

            const fechaFormulario = document.getElementById('tfectra')?.value || '';
            const hoy = new Date().toISOString().split('T')[0];

            const modoItems = modoDirecto
                ? 'todo'
                : (document.querySelector('input[name="rep-modo-items"]:checked')?.value || 'todo');

            const fechaDesde = modoDirecto
                ? (fechaFormulario || hoy)
                : (document.getElementById('rep-fecha-desde')?.value || '');

            const fechaHasta = modoDirecto
                ? (fechaFormulario || hoy)
                : (document.getElementById('rep-fecha-hasta')?.value || '');

            const codigoDesde = modoDirecto
                ? ''
                : (document.getElementById('rep-codigo-desde')?.value || '').trim();

            const codigoHasta = modoDirecto
                ? ''
                : (document.getElementById('rep-codigo-hasta')?.value || '').trim();

            const stockCon = modoDirecto
                ? 'valor'
                : (document.querySelector('input[name="rep-stock-con"]:checked')?.value || 'valor');

            const tipo = modoDirecto
                ? 'detalle'
                : (document.querySelector('input[name="rep-tipo"]:checked')?.value || 'detalle');

            if (!fechaDesde || !fechaHasta) {
                this._popupWarning('Ingrese el rango de fechas para generar el reporte.');
                return;
            }

            if (fechaDesde > fechaHasta) {
                this._popupWarning('La fecha desde no puede ser mayor que la fecha hasta.');
                return;
            }

            let codIni = '';
            let codFin = '';
            if (modoItems === 'codigo') {
                codIni = codigoDesde;
                codFin = codigoHasta;

                if (!codIni || !codFin) {
                    this._popupWarning('Seleccione Codigo Desde y Hasta para el reporte por rango.');
                    return;
                }

                if (codIni.localeCompare(codFin, 'es', { sensitivity: 'base' }) > 0) {
                    this._popupWarning('Codigo Desde no puede ser mayor que Codigo Hasta.');
                    return;
                }
            }

            const filtros = {
                alma,
                fecha_desde: fechaDesde,
                fecha_hasta: fechaHasta,
                tipo,
                stock_con: stockCon,
                modo_items: modoItems,
            };

            if (codIni) filtros.codigo_desde = codIni;
            if (codFin) filtros.codigo_hasta = codFin;

            const res = await this.service.getReporteKardex(filtros);
            const payload = res.data || {};
            const filas = Array.isArray(payload.rows) ? payload.rows : [];

            if (!filas.length) {
                this._popupWarning('No hay datos para el rango seleccionado.');
                return;
            }

            const basePdfUrl = this.service.getReporteKardexPdfUrl(filtros);
            this._abrirModalReportePreview(basePdfUrl);
            if (!modoDirecto) {
                this._cerrarModalReporte();
            }
        } catch (e) {
            console.error('Error generando reporte kardex:', e);
            this._popupError(`No se pudo generar el reporte. ${e.message || ''}`.trim());
        }
    }

    _abrirModalReportePreview(basePdfUrl) {
        const vista = document.getElementById('vista-reporte-kardex-preview');
        if (!vista) return;

        const titulo = document.getElementById('titulo-reporte-preview');
        if (titulo) {
            titulo.textContent = 'Visualizador de PDF - Reporte Kardex Valorado';
        }

        this._reporteKardexPreview.urlBase = String(basePdfUrl || '').trim();
        this._reporteKardexPreview.formato = 'a4';
        this._cargarIframeReportePreview();

        vista.style.display = 'flex';
    }

    _abrirModalReportePreviewHtml(html, title = 'Visualizador de PDF') {
        const vista = document.getElementById('vista-reporte-kardex-preview');
        const iframe = document.getElementById('iframe-reporte-kardex-preview');
        if (!vista || !iframe) return;

        const titulo = document.getElementById('titulo-reporte-preview');
        if (titulo) {
            titulo.textContent = title;
        }

        this._reporteKardexPreview.urlBase = '';

        if ('srcdoc' in iframe) {
            iframe.srcdoc = html;
            iframe.src = 'about:blank';
        } else {
            const blob = new Blob([html], { type: 'text/html;charset=utf-8' });
            iframe.src = URL.createObjectURL(blob);
        }

        vista.style.display = 'flex';
    }

    _cerrarModalReportePreview() {
        const vista = document.getElementById('vista-reporte-kardex-preview');
        if (!vista) return;

        vista.style.display = 'none';

        const iframe = document.getElementById('iframe-reporte-kardex-preview');
        if (iframe) {
            iframe.src = '';
            if ('srcdoc' in iframe) {
                iframe.srcdoc = '';
            }
        }

        setTimeout(() => {
            const btnReporte = document.getElementById('btn-generar-reporte');
            if (btnReporte && typeof btnReporte.focus === 'function') {
                btnReporte.focus();
            }
        }, 50);
    }

    _imprimirReportePreview() {
        const iframe = document.getElementById('iframe-reporte-kardex-preview');
        const frameWindow = iframe?.contentWindow;
        if (!frameWindow) {
            this._popupWarning('No se pudo abrir el documento para imprimir.');
            return;
        }

        frameWindow.focus();
        frameWindow.print();
    }

    _setFormatoReportePreview(formato, opciones = {}) {
        const formatoNormalizado = String(formato || '').toLowerCase() === '80mm' ? '80mm' : 'a4';
        this._reporteKardexPreview.formato = formatoNormalizado;

        const btnA4 = document.getElementById('btn-reporte-formato-a4');
        const btn80 = document.getElementById('btn-reporte-formato-80mm');
        if (btnA4) btnA4.classList.toggle('active', formatoNormalizado === 'a4');
        if (btn80) btn80.classList.toggle('active', formatoNormalizado === '80mm');

        const recargar = opciones.recargar !== false;
        if (recargar) {
            this._cargarIframeReportePreview();
        }
    }

    _cargarIframeReportePreview() {
        const iframe = document.getElementById('iframe-reporte-kardex-preview');
        const base = (this._reporteKardexPreview.urlBase || '').trim();
        if (!iframe || !base) return;

        const sep = base.includes('?') ? '&' : '?';
        const formato = encodeURIComponent(this._reporteKardexPreview.formato || 'a4');
        iframe.src = `${base}${sep}formato=${formato}&_ts=${Date.now()}`;
    }

    async _cargarMovimiento(treg) {
        try {
            // Cerrar modal de lista
            cerrarModalMovimientos();

            // Obtener el movimiento completo con sus detalles
            const res = await this.service.getMovimiento(treg);
            const data = res.data;

            if (!data || !data.cabecera) {
                mostrarModal('Error', 'No se encontró el movimiento solicitado.', '❌');
                return;
            }

            const mov = data.cabecera;
            const detalle = data.detalle || [];
            const talrCargado = mov.talr || (detalle[0]?.talr ?? '');
            const cencosDestCargado = mov.tcencos_dest || (detalle[0]?.tcencos ?? '');

            // Cargar datos del encabezado
            this._setFieldValue('treg', mov.treg || '');
            document.getElementById('tfectra').value = mov.tfectra || '';
            document.getElementById('talm').value = mov.talm || '';
            document.getElementById('tcodtra').value = mov.tcodtra || '';
            document.getElementById('talr').value = talrCargado;
            document.getElementById('tprocli').value = mov.tprocli || '';
            document.getElementById('tmon').value = mov.tmon || 'S/';
            document.getElementById('tdoc').value = mov.tdoc || '';
            document.getElementById('tserie').value = mov.tserie || '';
            document.getElementById('tnumfac').value = mov.tnumfac || '';
            document.getElementById('tfecfac').value = mov.tfecfac || '';
            this._setFieldValue('tlib', mov.tlib || '');
            this._setFieldValue('tordcom', mov.tordcom || '');
            this._setFieldValue('tmotivo_traslado', mov.tmotivo_traslado || '');
            document.getElementById('tcencos_dest').value = cencosDestCargado;
            document.getElementById('tglosa').value = mov.tglosa || '';
            this._setFieldValue('tcostmin', mov.tcostmin || 0);
            this._toggleTipoCambioUI();

            // Actualizar todos los SearchableSelects
            ['talm', 'tcodtra', 'talr', 'tmon', 'tdoc', 'tmotivo_traslado', 'tcencos_dest'].forEach(id => {
                const el = document.getElementById(id);
                if (el && el.searchableSelectInstance) {
                    el.searchableSelectInstance.updateDisplayText();
                }
            });

            // Aplicar flags de transacción
            if (mov.tcodtra) {
                this._aplicarFlagsTransaccion(mov.tcodtra);
            }

            // Cargar detalle (items)
            this.detalle = detalle.map(item => ({
                tcodigo: item.tcodigo || '',
                tdescri: item.tdescri || item.nom_producto || '',
                tcantid: parseFloat(item.tcantid) || 0,
                tpreuni: parseFloat(item.tpreuni) || 0,
                tpeso: parseFloat(item.tpeso) || 0,
                tsacos: parseFloat(item.tsacos) || 0,
                tnumlot: item.tnumlot || '',
                tlote: item.tlote || '00000000',
                talr: item.talr || talrCargado,
                tcencos: item.tcencos || '',
                tctabal: item.tctabal || '',
                tcodproc: item.tcodproc || '00',
                tcodsubproc: item.tcodsubproc || '00',
                tcodacti: item.tcodacti || '00',
                tcodtarea: item.tcodtarea || '00',
                tprod: item.tprod || 'P',
                ttoneladas: parseFloat(item.ttoneladas) || 0,
                timport: parseFloat(item.timport) || 0
            }));

            this._renderGrid();
            this._actualizarTotales();
            this._mostrarSeccionItems({ enfocarProducto: false, abrirSelector: false });

            // Guardar el registro actual para edición
            this.tregActual = mov.treg;
            this.modoEdicion = true;

            // Scroll al inicio
            window.scrollTo({ top: 0, behavior: 'smooth' });

            mostrarModal('Movimiento Cargado', `Movimiento #${mov.treg} cargado correctamente. Puede editarlo.`, '✅');

        } catch (e) {
            console.error('Error cargando movimiento:', e);
            mostrarModal('Error', 'No se pudo cargar el movimiento. ' + (e.message || ''), '❌');
        }
    }

    // ── Utilidades ────────────────────────────────────────────────────────────

    async _resetFormulario(isInit = false) {
        this.tregActual = null;
        this.modoEdicion = false;
        this.detalle = [];
        this._productoActual = null;
        this._lotes = [];
        this.filaSeleccionadaIndex = -1;
        this._kardexBase = { qstock: 0, pstock: 0, cosuni: 0, vstock: 0 };
        this._kardexContext = { codigo: '', lote: '00000000', alma: '' };
        this._kardexActual = { qstock: 0, pstock: 0, cosuni: 0, vstock: 0 };
        document.getElementById('form-movimiento').reset();
        this._ocultarSeccionItems({ immediate: true });

        this._aplicarFechaActualPorDefecto(true);
        try {
            const resReg = await this.service.getNuevoReg();
            this._setFieldValue('treg', resReg.data.treg);
        } catch (e) {
            console.error('Error cargando nuevo registro:', e);
        }
        document.getElementById('msg-fecha').style.display = 'none';
        this._renderGrid();
        this._actualizarTotales();
        this._toggleTipoCambioUI();

        const selectLote = document.getElementById('grid-tlote');
        if (selectLote) {
            selectLote.innerHTML = '<option value="">🔎 Buscar lote...</option>';
            selectLote.value = '';
        }

        this._actualizarCombosBuscablesFormulario({ recargarOpciones: true });
        this._actualizarCencosNombreLabel();

        ['grupo-emidoc', 'grupo-guia', 'grupo-motivo', 'grupo-cencos',
            'grupo-ordcom', 'grupo-observacion', 'grupo-moneda', 'grupo-tipo-cambio'].forEach(id => {
                this._toggleCampo(id, false);
            });

        setTimeout(() => {
            const primerCampo = document.getElementById('talm');
            if (primerCampo) {
                if (primerCampo.searchableSelectInstance?.displayField) {
                    primerCampo.searchableSelectInstance.displayField.focus();
                } else {
                    primerCampo.focus();
                    if (primerCampo.select && typeof primerCampo.select === 'function') {
                        primerCampo.select();
                    }
                }
                window.scrollTo({ top: 0, behavior: 'smooth' });
            }
        }, 100);

        if (!isInit) {
            sessionStorage.removeItem(this._draftKey);
        }
    }

    _refrescarComboBuscable(selectEl, { recargarOpciones = false } = {}) {
        if (!selectEl?.searchableSelectInstance) return;

        const instance = selectEl.searchableSelectInstance;
        if (recargarOpciones) {
            instance.loadOptions();
        }

        instance.updateDisplayText();

        if (instance.searchInput) {
            instance.searchInput.value = '';
            instance.filterOptions();
        }

        if (instance.isOpen) {
            instance.close();
        }
    }

    _actualizarCombosBuscablesFormulario({ recargarOpciones = false } = {}) {
        const selects = document.querySelectorAll('#form-movimiento select');
        selects.forEach((selectEl) => {
            this._refrescarComboBuscable(selectEl, { recargarOpciones });
        });
    }

     _limpiarCamposGrid() {
        ['grid-tcodigo', 'grid-tcantid', 'grid-tpreuni',
            'grid-tpeso', 'grid-tnumlot', 'grid-tlote',
            'grid-tctabal', 'grid-timport', 'grid-costoabc-display'].forEach(id => {
                const el = document.getElementById(id);
                if (el) el.value = '';
            });
        this._productoActual = null;
        this._lotes = [];

        // Resetear el select de productos
        const selectProducto = document.getElementById('grid-buscar-producto');
        if (selectProducto) {
            selectProducto.value = '';
            this._refrescarComboBuscable(selectProducto, { recargarOpciones: true });
        }

        // Resetear el select de lotes
        const selectLote = document.getElementById('grid-tlote');
        if (selectLote) {
            selectLote.innerHTML = '<option value="">🔎 Buscar lote...</option>';
            selectLote.value = '';
            this._refrescarComboBuscable(selectLote, { recargarOpciones: true });
        }

        // Mantener el select de cencos seleccionado (solo cerrar si está abierto)
        const selectCencos = document.getElementById('grid-tcencos');
        if (selectCencos) {
            this._refrescarComboBuscable(selectCencos, { recargarOpciones: false });
        }

        const ref = document.getElementById('grid-stock-ref');
        if (ref) ref.textContent = '';

        this._kardexBase = { qstock: 0, pstock: 0, cosuni: 0, vstock: 0 };
        this._kardexContext = { codigo: '', lote: '00000000', alma: '' };
        this._kardexActual = { qstock: 0, pstock: 0, cosuni: 0, vstock: 0 };

        const resumenIds = [
            ['grid-res-cantidad', 'Cantidad'],
            ['grid-res-peso', 'Peso'],
            ['grid-res-precio', 'Precio'],
            ['grid-res-importe', 'Importe']
        ];
        resumenIds.forEach(([id, etiqueta]) => {
            const el = document.getElementById(id);
            if (el) el.textContent = `${etiqueta}: 0.00`;
        });

        this._aplicarModoUnidadGrid();

        // Resetear códigos ABC y limpiar nombres guardados
        this._abcModalState.names = { proc: '', subp: '', acti: '', tarea: '' };
        this._setAbcCodes({ proc: '00', subp: '00', acti: '00', tarea: '00' });

        // Enfocar el selector de productos para agregar un nuevo ítem
        setTimeout(() => {
            if (selectCencos) {
                if (selectCencos.searchableSelectInstance) {
                    selectCencos.searchableSelectInstance.displayField.focus();
                } else {
                    selectCencos.focus();
                }
            }
        }, 100);
    }

    _llenarSelect(id, data, valKey, txtKey) {
        const sel = document.getElementById(id);
        if (!sel || sel.tagName !== 'SELECT') return;

        // Crear opciones con formato corto para display
        sel.innerHTML = '<option value="">-- Seleccionar --</option>' +
            data.map(d => {
                const codigo = d[valKey];
                const nombre = d[txtKey];
                // Mostrar solo código cuando esté seleccionado, pero código + nombre en el dropdown
                return `<option value="${codigo}" data-nombre="${nombre}" data-display="${codigo}">${codigo} - ${nombre}</option>`;
            }).join('');

        // Si el select tiene un SearchableSelect asociado, actualizar las opciones
        if (sel.searchableSelectInstance) {
            sel.searchableSelectInstance.loadOptions();
        } else {
            // Si no tiene SearchableSelect, inicializarlo ahora
            if (typeof initSearchableSelects === 'function') {
                const instance = new SearchableSelect(sel, {
                    placeholder: 'Buscar...',
                    noResults: 'Sin resultados',
                    autoFocus: true,
                    moveToNextOnSelect: true
                });
                sel.searchableSelectInstance = instance;
            }
        }

        // Agregar evento para mostrar solo el código cuando se selecciona
        sel.addEventListener('change', function () {
            if (this.value && this.searchableSelectInstance) {
                const selectedOption = this.options[this.selectedIndex];
                if (selectedOption && this.searchableSelectInstance.displayField) {
                    // Mostrar solo el código en el campo de display
                    const span = this.searchableSelectInstance.displayField.querySelector('span');
                    if (span) {
                        span.textContent = this.value;
                    }
                }
            }
        });
    }

    _abrirModalImportExport() {
        const txt = document.getElementById('txt-import-export');
        const fileInput = document.getElementById('file-import-items');

        if (txt && !txt.value.trim()) {
            txt.value = this._buildItemsTxt();
        }
        if (fileInput) {
            fileInput.value = '';
        }

        if (typeof mostrarModalImportExport === 'function') {
            mostrarModalImportExport();
        }
    }

    _cerrarModalImportExport() {
        if (typeof cerrarModalImportExport === 'function') {
            cerrarModalImportExport();
        }
    }

    _buildItemsTxt(includeHeader = false) {
        const columns = [
            'tcodigo', 'tdescri', 'tcantid', 'tpreuni', 'tpeso', 'tnumlot',
            'tcencos', 'timport', 'tctabal', 'tcodproc', 'tcodsubproc', 'tcodacti', 'tcodtarea'
        ];

        const rows = this.detalle.map(it => [
            it.tcodigo || '',
            it.tdescri || '',
            this._redondear(parseFloat(it.tcantid) || 0),
            this._redondear(parseFloat(it.tpreuni) || 0),
            this._redondear(parseFloat(it.tpeso) || 0),
            it.tnumlot || '',
            it.tcencos || '',
            this._redondear(parseFloat(it.timport) || 0),
            it.tctabal || '',
            it.tcodproc || '00',
            it.tcodsubproc || '00',
            it.tcodacti || '00',
            it.tcodtarea || '00',
        ].map(v => String(v).replace(/\|/g, '/')).join('|'));

        if (includeHeader) {
            return [columns.join('|'), ...rows].join('\n');
        }

        return rows.join('\n');
    }

    _descargarArchivoTexto(nombreArchivo, contenido) {
        const blob = new Blob([contenido], { type: 'text/plain;charset=utf-8' });
        const url = URL.createObjectURL(blob);
        const link = document.createElement('a');
        link.href = url;
        link.download = nombreArchivo;
        document.body.appendChild(link);
        link.click();
        link.remove();
        URL.revokeObjectURL(url);
    }

    _exportarItemsTxt() {
        if (!this.detalle.length) {
            this._popupWarning('No hay ítems para exportar.');
            return;
        }

        const contenido = this._buildItemsTxt(false);
        const txt = document.getElementById('txt-import-export');
        if (txt) {
            txt.value = contenido;
        }

        const fecha = new Date().toISOString().slice(0, 10).replace(/-/g, '');
        this._descargarArchivoTexto(`movimiento-items-${fecha}.txt`, contenido);
        this._popupSuccess('Ítems exportados en formato TXT con separador |.');
    }

    _descargarFormatoTxt() {
        const header = this._buildItemsTxt(true).split('\n')[0] ||
            'tcodigo|tdescri|tcantid|tpreuni|tpeso|tnumlot|tcencos|timport|tctabal|tcodproc|tcodsubproc|tcodacti|tcodtarea';
        const ejemplo = 'PRD001|Producto ejemplo|10|5.5|12.75|LOTE01|CC001|55|601101|01|01|01|01';
        this._descargarArchivoTexto('formato-importacion-items.txt', `${header}\n${ejemplo}`);
    }

    _toNumber(value, fallback = 0) {
        const raw = String(value ?? '').trim().replace(',', '.');
        const parsed = parseFloat(raw);
        return Number.isFinite(parsed) ? parsed : fallback;
    }

    _parseItemsTxt(content) {
        const lines = String(content || '')
            .split(/\r?\n/)
            .map(line => line.trim())
            .filter(Boolean);

        if (!lines.length) {
            throw new Error('No hay datos para importar.');
        }

        const hasHeader = /^tcodigo\|/i.test(lines[0]);
        const dataLines = hasHeader ? lines.slice(1) : lines;

        if (!dataLines.length) {
            throw new Error('El archivo solo contiene cabecera y no tiene ítems.');
        }

        return dataLines.map((line, index) => {
            const cols = line.split('|');
            if (cols.length < 13) {
                throw new Error(`Línea ${index + 1}: se esperaban 13 columnas separadas por |.`);
            }

            const tcodigo = (cols[0] || '').trim();
            const tdescri = (cols[1] || '').trim();
            const tcantid = this._toNumber(cols[2], 0);
            const tpreuni = this._toNumber(cols[3], 0);
            const tpeso = this._toNumber(cols[4], 0);
            const tnumlot = (cols[5] || '').trim();
            const tcencos = (cols[6] || '').trim();
            const timportCol = this._toNumber(cols[7], NaN);
            const tctabal = (cols[8] || '').trim();
            const tcodproc = (cols[9] || '00').trim() || '00';
            const tcodsubproc = (cols[10] || '00').trim() || '00';
            const tcodacti = (cols[11] || '00').trim() || '00';
            const tcodtarea = (cols[12] || '00').trim() || '00';

            if (!tcodigo) {
                throw new Error(`Línea ${index + 1}: código vacío.`);
            }
            if (tcantid <= 0) {
                throw new Error(`Línea ${index + 1}: cantidad debe ser mayor a 0.`);
            }

            const timport = Number.isFinite(timportCol)
                ? this._redondear(timportCol)
                : this._redondear(tcantid * tpreuni);

            return {
                tcodigo,
                tdescri,
                tcantid,
                tpreuni,
                tpeso,
                tsacos: 0,
                tnumlot,
                tlote: tnumlot || '00000000',
                tcencos,
                tctabal,
                tcodproc,
                tcodsubproc,
                tcodacti,
                tcodtarea,
                talr: document.getElementById('talr').value || '',
                tprod: 'P',
                ttoneladas: 0,
                timport,
            };
        });
    }

    async _importarItemsTxt() {
        const txt = document.getElementById('txt-import-export');
        const content = txt?.value || '';

        try {
            const importedItems = this._parseItemsTxt(content);

            if (this.detalle.length) {
                const confirmar = await this._popupConfirm(
                    'Reemplazar ítems actuales',
                    'La importación reemplazará los ítems actuales. ¿Desea continuar?'
                );
                if (!confirmar) {
                    return;
                }
            }

            this.detalle = importedItems;
            this._renderGrid();
            this._actualizarTotales();
            this._cerrarModalImportExport();
            this._popupSuccess(`Se importaron ${importedItems.length} ítem(s) correctamente.`);
        } catch (error) {
            this._popupError(error.message || 'No se pudo importar el archivo.');
        }
    }

    _cargarArchivoImportacion(event) {
        const file = event.target?.files?.[0];
        if (!file) return;

        const reader = new FileReader();
        reader.onload = () => {
            const txt = document.getElementById('txt-import-export');
            if (txt) {
                txt.value = String(reader.result || '');
            }
        };
        reader.onerror = () => {
            this._popupError('No se pudo leer el archivo seleccionado.');
        };
        reader.readAsText(file, 'utf-8');
    }

    // Equivalente al Redondear() del VBA
    _redondear(numero) {
        const parteEntera = Math.floor(numero);
        const decimales = (numero - parteEntera).toFixed(3).substring(2); // 3 dígitos
        let dos = parseInt(decimales.substring(0, 2));
        const tres = parseInt(decimales[2]);
        if (tres >= 5) {
            dos += 1;
            if (dos >= 100) return parteEntera + 1;
        }
        return parseFloat(`${parteEntera}.${String(dos).padStart(2, '0')}`);
    }

    _debounce(fn, ms) {
        let t; return (...a) => { clearTimeout(t); t = setTimeout(() => fn(...a), ms); };
    }

    _getFechaHoyLocalISO() {
        const now = new Date();
        now.setMinutes(now.getMinutes() - now.getTimezoneOffset());
        return now.toISOString().slice(0, 10);
    }

    _aplicarFechaActualPorDefecto(force = false) {
        const hoy = this._getFechaHoyLocalISO();
        const fechaMovimiento = document.getElementById('tfectra');
        const fechaDocumento = document.getElementById('tfecfac');

        if (fechaMovimiento && (force || !fechaMovimiento.value)) {
            fechaMovimiento.value = hoy;
        }

        if (fechaDocumento && (force || !fechaDocumento.value)) {
            fechaDocumento.value = hoy;
        }
    }

    // ── GESTIÓN DE BORRADORES (SESSION STORAGE) ──────────────────────────────

    _guardarBorradorLocal() {
        // No autoguardamos si estamos editando un movimiento ya existente
        if (this.modoEdicion || this.tregActual) return;

        const draft = {
            tfectra: this._getFieldValue('tfectra', ''),
            talm: this._getFieldValue('talm', ''),
            tcodtra: this._getFieldValue('tcodtra', ''),
            talr: this._getFieldValue('talr', ''),
            tcencos_dest: this._getFieldValue('tcencos_dest', ''),
            tprocli: this._getFieldValue('tprocli', ''),
            tdoc: this._getFieldValue('tdoc', ''),
            tserie: this._getFieldValue('tserie', ''),
            tnumfac: this._getFieldValue('tnumfac', ''),
            tfecfac: this._getFieldValue('tfecfac', ''),
            tmon: this._getFieldValue('tmon', 'S/'),
            tglosa: this._getFieldValue('tglosa', ''),
            detalle: this.detalle || []
        };
        sessionStorage.setItem(this._draftKey, JSON.stringify(draft));
    }

    async _verificarBorrador() {
        const draftStr = sessionStorage.getItem(this._draftKey);
        if (!draftStr) return;

        try {
            const draft = JSON.parse(draftStr);
            
            if (!draft.detalle || draft.detalle.length === 0) {
                sessionStorage.removeItem(this._draftKey);
                return;
            }

            const isDark = document.body?.classList.contains('dark-mode');
            const result = await window.Swal.fire({
                title: '¿Recuperar movimiento?',
                text: 'Se encontró un registro no guardado en tu sesión anterior.',
                icon: 'info',
                showCancelButton: true,
                confirmButtonText: 'Sí, recuperar',
                cancelButtonText: 'No, descartar',
                background: isDark ? '#1f2937' : '#ffffff',
                color: isDark ? '#f3f4f6' : '#111827',
                confirmButtonColor: '#10b981', 
                cancelButtonColor: '#6b7280',
                reverseButtons: true
            });

            if (result.isConfirmed) {
                await this._restaurarBorrador(draft);
                if (window.SwalHelpers?.showSuccess) {
                    window.SwalHelpers.showSuccess('El movimiento ha sido restaurado.');
                } else {
                    window.Swal.fire({ title: 'Recuperado', text: 'La información ha sido restaurada.', icon: 'success', timer: 1500, showConfirmButton: false });
                }
            } else {
                sessionStorage.removeItem(this._draftKey);
            }
        } catch (e) {
            console.error('Error procesando el borrador local:', e);
            sessionStorage.removeItem(this._draftKey);
        }
    }

    async _restaurarBorrador(draft) {
        this._setFieldValue('tfectra', draft.tfectra);
        this._setFieldValue('talm', draft.talm);
        this._setFieldValue('tcodtra', draft.tcodtra);
        this._setFieldValue('talr', draft.talr);
        this._setFieldValue('tcencos_dest', draft.tcencos_dest);
        this._setFieldValue('tprocli', draft.tprocli);
        this._setFieldValue('tdoc', draft.tdoc);
        this._setFieldValue('tserie', draft.tserie);
        this._setFieldValue('tnumfac', draft.tnumfac);
        this._setFieldValue('tfecfac', draft.tfecfac);
        this._setFieldValue('tmon', draft.tmon || 'S/');
        this._setFieldValue('tglosa', draft.tglosa);

        // Disparar lógica de UI
        if (draft.tcodtra) this._aplicarFlagsTransaccion(draft.tcodtra);
        this._toggleTipoCambioUI();

        // Actualizar visualmente los combos de búsqueda
        this._actualizarCombosBuscablesFormulario({ recargarOpciones: false });

        // Recuperar ítems del grid
        this.detalle = draft.detalle || [];
        this._renderGrid();
        this._actualizarTotales();

        if (this.detalle.length > 0) {
            this._mostrarSeccionItems({ enfocarProducto: false, abrirSelector: false });
        }
    }

}

// Instancia global accesible desde el HTML
const movAlmCtrl = new MovimientoAlmacenController();
document.addEventListener('DOMContentLoaded', () => movAlmCtrl.init());
