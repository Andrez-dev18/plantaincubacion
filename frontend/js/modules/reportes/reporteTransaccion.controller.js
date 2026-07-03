class ReporteTransaccionController {
    constructor() {
        this.transaccionService = new window.ReporteTransaccionService();
        this.transaccionSeleccionada = '';
        this.dataTransacciones = [];

        this.combosDinamicos = {};
    }

    async init() {
        this.initFiltersUI();
        this.setupToggleFiltros();
        this.setupEventListeners();

        await this.cargarFiltroTransacciones();
        await this.cargarFiltroAlmacenes();
        await this.cargarFiltroCencos();
        await this.cargarFiltroCuentasCorrientes();
        await this.cargarFiltroLineas();
        await this.cargarFiltroCodigos();
        this.configurarEventosCombo();
    }

    async cargarFiltroAlmacenes() {
        const selectAlmacen = document.getElementById('filterZonaAlmacen');
        if (!selectAlmacen) return;

        try {
            const response = await this.transaccionService.getAlmacenesSelect();

            if (response && response.success && Array.isArray(response.data)) {
                selectAlmacen.innerHTML = '<option value="">Todas</option>';
                const fragment = document.createDocumentFragment();

                response.data.forEach(item => {
                    const option = document.createElement('option');
                    option.value = item.talm; // '010'
                    option.textContent = `${item.talm} - ${item.descri}`; // '010 - ALM LA JOYA'
                    fragment.appendChild(option);
                });

                selectAlmacen.appendChild(fragment);
            }
        } catch (error) {
            console.error("Error al cargar el select de almacenes:", error);
        }
    }

    async cargarFiltroCencos() {
        const selectCencos = document.getElementById('filterCencos');
        if (!selectCencos) return;

        try {
            const response = await this.transaccionService.getCencosSelect();

            if (response && response.success && Array.isArray(response.data)) {
                selectCencos.innerHTML = '<option value="">Todas</option>';
                const fragment = document.createDocumentFragment();

                response.data.forEach(item => {
                    const option = document.createElement('option');
                    // 🛠️ CORRECCIÓN: Usamos item.codigo en lugar del erróneo item.talm
                    option.value = item.codigo;
                    option.textContent = `${item.codigo} - ${item.nombre}`;
                    fragment.appendChild(option);
                });

                selectCencos.appendChild(fragment);

                // 🔥 LA MAGIA: Convertimos el select nativo en un MultiSelect dinámico con buscador
                this.combosDinamicos['cencos'] = new window.MultiSelectBuscador('filterCencos', 'Buscar centro de costo...');
                this.combosDinamicos['cencos'].actualizarOpcionesDesdeSelect();
            }
        } catch (error) {
            console.error("Error al cargar el select de cencos:", error);
        }
    }

    async cargarFiltroCuentasCorrientes() {
        // Asegúrate de que el ID coincida con el de tu <select> en el HTML (ej: filterCuentaCorriente)
        const selectCuentas = document.getElementById('filterCuentaCorriente');
        if (!selectCuentas) return;

        try {
            const response = await this.transaccionService.getCuentasCorrientes();

            if (response && response.success && Array.isArray(response.data)) {
                selectCuentas.innerHTML = '<option value="">Todas</option>';
                const fragment = document.createDocumentFragment();

                response.data.forEach(item => {
                    const option = document.createElement('option');
                    option.value = item.codigo; // El RUC o código interno
                    option.textContent = `${item.codigo} - ${item.nombre}`; // Ejemplo: '20100176450 - SOLGAS S.A.'
                    fragment.appendChild(option);
                });

                selectCuentas.appendChild(fragment);

                // 🔥 LA MAGIA GENÉRICA: Transformamos el select nativo usando el MultiSelect utilitario
                this.combosDinamicos['cuentas'] = new window.MultiSelectBuscador('filterCuentaCorriente', 'Buscar cuenta corriente...');
                this.combosDinamicos['cuentas'].actualizarOpcionesDesdeSelect();
            }
        } catch (error) {
            console.error("Error al cargar el select de cuentas corrientes:", error);
        }
    }

    async cargarFiltroLineas() {
        const selectLineas = document.getElementById('filterLineas');
        if (!selectLineas) return;

        try {
            const response = await this.transaccionService.getLineas();

            if (response && response.success && Array.isArray(response.data)) {
                selectLineas.innerHTML = '<option value="">Todas</option>';
                const fragment = document.createDocumentFragment();

                response.data.forEach(item => {
                    const option = document.createElement('option');
                    option.value = item.codigo;
                    option.textContent = `${item.codigo} - ${item.descri}`;
                    fragment.appendChild(option);
                });

                selectLineas.appendChild(fragment);

                this.combosDinamicos['lineas'] = new window.MultiSelectBuscador('filterLineas', 'Buscar lineas...');
                this.combosDinamicos['lineas'].actualizarOpcionesDesdeSelect();
            }
        } catch (error) {
            console.error("Error al cargar el select de lineas:", error);
        }
    }

    async cargarFiltroCodigos() {
        const selectCodigos = document.getElementById('filterCodigos');
        if (!selectCodigos) return;

        try {
            const response = await this.transaccionService.getCodigos();

            if (response && response.success && Array.isArray(response.data)) {
                selectCodigos.innerHTML = '<option value="">Todas</option>';
                const fragment = document.createDocumentFragment();

                response.data.forEach(item => {
                    const option = document.createElement('option');
                    option.value = item.codigo;
                    option.textContent = `${item.codigo} - ${item.descri}`;
                    fragment.appendChild(option);
                });

                selectCodigos.appendChild(fragment);

                this.combosDinamicos['codigos'] = new window.MultiSelectBuscador('filterCodigos', 'Buscar Codigos...');
                this.combosDinamicos['codigos'].actualizarOpcionesDesdeSelect();
            }
        } catch (error) {
            console.error("Error al cargar el select de codigos:", error);
        }
    }

    // ─────────────────────────────────────────────────────────
    // LÓGICA DEL COMBO DE TRANSACCIONES (BLINDADA)
    // ─────────────────────────────────────────────────────────
    async cargarFiltroTransacciones() {
        try {
            const response = await this.transaccionService.getTransaccionesSelect();
            if (response && response.success && Array.isArray(response.data)) {
                this.dataTransacciones = response.data;
                this.renderizarOpcionesCombo(this.dataTransacciones);
            }
        } catch (error) {
            console.error("Error al obtener transacciones:", error);
        }
    }

    renderizarOpcionesCombo(lista) {
        const contenedor = document.getElementById('comboTransaccionOpciones');
        if (!contenedor) return;

        contenedor.innerHTML = '';
        const fragment = document.createDocumentFragment();

        const optTodas = document.createElement('div');
        optTodas.className = "px-3 py-2 hover:bg-purple-50 rounded-lg cursor-pointer font-medium text-purple-600 transition-colors";
        optTodas.textContent = "Todas";
        optTodas.addEventListener('click', (e) => {
            e.stopPropagation();
            this.seleccionarOpciónCombo('', 'Todas');
        });
        fragment.appendChild(optTodas);

        lista.forEach(item => {
            const opt = document.createElement('div');
            opt.className = "px-3 py-2 hover:bg-gray-50 rounded-lg cursor-pointer text-gray-700 transition-colors truncate";
            opt.textContent = `${item.tcodtra} - ${item.descri}`;

            opt.addEventListener('click', (e) => {
                e.stopPropagation();
                this.seleccionarOpciónCombo(item.tcodtra, `${item.tcodtra} - ${item.descri}`);
            });
            fragment.appendChild(opt);
        });

        contenedor.appendChild(fragment);
    }

    seleccionarOpciónCombo(codigo, texto) {
        this.transaccionSeleccionada = codigo;
        const textoEl = document.getElementById('comboTransaccionTexto');
        if (textoEl) {
            textoEl.textContent = texto;
            textoEl.className = codigo ? "text-gray-900 font-medium" : "text-gray-500";
        }
        this.toggleDropdown(false);
    }

    configurarEventosCombo() {
        const btn = document.getElementById('comboTransaccionBtn');
        const dropdown = document.getElementById('comboTransaccionDropdown');
        const buscador = document.getElementById('comboTransaccionBuscar');

        if (!btn || !dropdown || !buscador) return;

        btn.addEventListener('click', (e) => {
            e.preventDefault();
            e.stopPropagation();
            const estaOculto = dropdown.classList.contains('hidden');
            this.toggleDropdown(estaOculto);
        });

        buscador.addEventListener('input', (e) => {
            const query = e.target.value.toLowerCase().trim();
            const filtradas = this.dataTransacciones.filter(item =>
                item.tcodtra.toLowerCase().includes(query) ||
                item.descri.toLowerCase().includes(query)
            );
            this.renderizarOpcionesCombo(filtradas);
        });

        buscador.addEventListener('click', (e) => e.stopPropagation());
        dropdown.addEventListener('click', (e) => e.stopPropagation());

        document.addEventListener('click', () => {
            this.toggleDropdown(false);
        });

        // CORRECCIÓN: Diferenciar el scroll de la página vs el scroll interno del menú
        window.addEventListener('scroll', (e) => {
            // Si el scroll viene de adentro del dropdown (la lista de opciones), ignoramos la orden de cerrar
            if (dropdown.contains(e.target)) return;

            if (!dropdown.classList.contains('hidden')) {
                this.toggleDropdown(false);
            }
        }, true);
    }

    toggleDropdown(abrir) {
        const dropdown = document.getElementById('comboTransaccionDropdown');
        const flecha = document.getElementById('comboTransaccionFlecha');
        const btn = document.getElementById('comboTransaccionBtn');

        if (!dropdown || !flecha || !btn) return;

        if (abrir) {
            // Posicionamiento absoluto respecto a la pantalla
            const rect = btn.getBoundingClientRect();
            dropdown.style.position = 'fixed';
            dropdown.style.top = `${rect.bottom + 4}px`;
            dropdown.style.left = `${rect.left}px`;
            dropdown.style.width = `${Math.max(rect.width, 240)}px`;
            dropdown.style.zIndex = '999999';

            dropdown.classList.remove('hidden');
            flecha.classList.add('rotate-180');
            setTimeout(() => document.getElementById('comboTransaccionBuscar').focus(), 50);
        } else {
            dropdown.classList.add('hidden');
            flecha.classList.remove('rotate-180');
            document.getElementById('comboTransaccionBuscar').value = '';
            this.renderizarOpcionesCombo(this.dataTransacciones);
        }
    }

    // ─────────────────────────────────────────────────────────
    // TUS FUNCIONES ORIGINALES (INTACTAS)
    // ─────────────────────────────────────────────────────────
    setupToggleFiltros() {
        const headerToggle = document.getElementById('headerToggleFiltros');
        const filterContent = document.getElementById('filterContent');
        const icon = document.getElementById('iconToggleFiltros');

        if (!headerToggle || !filterContent) return;

        filterContent.classList.remove('show');

        headerToggle.addEventListener('click', () => {
            const isOpen = filterContent.classList.contains('show');

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

    setupEventListeners() {
        document.getElementById('btnAplicarFiltros')?.addEventListener('click', () => {
            this.aplicarFiltros();
        });
        document.getElementById('btnLimpiarFiltros')?.addEventListener('click', () => this.limpiarFiltros());
        document.getElementById('btnExportarPDF')?.addEventListener('click', () => this.exportarPDF());
        document.getElementById('btnExportarExcel')?.addEventListener('click', () => this.exportarExcel());
    }

    async aplicarFiltros() {
        this.mostrarNotificacion('Aplicando filtros...', 'info');
        await this.cargarReporteGrid();
    }

    async cargarReporteGrid() {
        // Obtenemos los valores seleccionados por el usuario
        const filtros = this.obtenerFiltros();
        const tbody = document.querySelector('#tablaTransacciones tbody');

        if (!tbody) return;

        // Mostrar un estado de "Cargando" en la tabla
        tbody.innerHTML = `<tr><td colspan="13" class="px-3 py-8 text-center text-gray-500 font-medium"><i class="fas fa-spinner fa-spin mr-2"></i> Procesando reporte...</td></tr>`;

        try {
            // Llamar al backend
            const response = await this.transaccionService.getReporteTransacciones(filtros);

            if (response && response.success) {
                this.renderizarTabla(response.data);
            } else {
                tbody.innerHTML = `<tr><td colspan="13" class="px-3 py-8 text-center text-red-500"><i class="fas fa-exclamation-triangle mr-2"></i> Error: ${response?.message || 'Error desconocido'}</td></tr>`;
                this.mostrarNotificacion('Error al cargar datos', 'error');
            }
        } catch (error) {
            console.error("Error en cargarReporteGrid:", error);
            tbody.innerHTML = `<tr><td colspan="13" class="px-3 py-8 text-center text-red-500">Error de conexión al servidor</td></tr>`;
        }
    }

    renderizarTabla(data) {
        const tbody = document.querySelector('#tablaTransacciones tbody');
        const tdTotalCantidad = document.getElementById('totalCantidad');
        const tdTotalCosto = document.getElementById('totalCosto');

        if (!data || data.length === 0) {
            tbody.innerHTML = `<tr><td colspan="14" class="px-3 py-8 text-center text-gray-500">No se encontraron transacciones para los filtros aplicados.</td></tr>`;
            if (tdTotalCantidad) tdTotalCantidad.textContent = '0.00';
            if (tdTotalCosto) tdTotalCosto.textContent = '0.00';
            return;
        }

        // ── HELPER DE FORMATO: Formatea números con comas en miles y puntos en decimales ──
        const formatNum = (num, decimals = 2) => {
            return new Intl.NumberFormat('en-US', {
                minimumFractionDigits: decimals,
                maximumFractionDigits: decimals
            }).format(num);
        };

        const fragment = document.createDocumentFragment();

        // Acumuladores Globales
        let sumaCantidad = 0;
        let sumaCUnit = 0;
        let sumaCKardex = 0;
        let sumaCostoTotal = 0;

        // Acumuladores Diarios (Subtotales)
        let subtotalCantidad = 0;
        let subtotalCUnit = 0;
        let subtotalCKardex = 0;
        let subtotalCosto = 0;

        let fechaActual = '';
        let fechaCompletaActual = '';

        const agruparPor = document.querySelector('input[name="agruparPor"]:checked')?.value || 'FECHA';

        let valorControlActual = '';
        let labelSubtotalActual = '';

        // Función auxiliar actualizada con formato de comas
        const inyectarFilaSubtotal = (labelTexto, cant, totCUnit, totCKardex, costo) => {
            const trSub = document.createElement('tr');
            trSub.className = "bg-gray-50 border-y border-gray-300 font-bold text-gray-900 shadow-sm";
            trSub.innerHTML = `
            <td colspan="9" class="px-3 py-2.5 text-right uppercase tracking-wider text-xs">
                TOTALES POR ${agruparPor.replace('_', ' ')} : <span class="ml-2 font-black">${labelTexto}</span>
            </td>
            <td class="px-3 py-2.5 text-right text-blue-700 font-black">${formatNum(cant, 2)}</td>
            <td class="px-3 py-2.5 text-right text-gray-900 font-black">${formatNum(totCUnit, 3)}</td>
            <td class="px-3 py-2.5 text-right text-gray-900 font-black">${formatNum(totCKardex, 3)}</td>
            <td class="px-3 py-2.5 text-right text-gray-900 font-black">${formatNum(costo, 3)}</td>
            <td class="px-3 py-2.5 bg-gray-100/50"></td>
        `;
            fragment.appendChild(trSub);
        };

        data.forEach((item, index) => {
            const cantidad = parseFloat(item.cantidad) || 0;
            const cTotal = parseFloat(item.costo_total) || 0;
            const cUnitFila = parseFloat(item.c_unit) || 0;
            const cKardex = parseFloat(item.c_kardex) || 0;

            const cUnitCrudo = cantidad !== 0 ? (cTotal / cantidad) : 0;

            let valorControlFila = '';
            let labelParaMostrar = '';

            if (agruparPor === 'PRODUCTO') {
                valorControlFila = item.codigo;
                labelParaMostrar = item.descripcion || 'SIN DESCRIPCIÓN';
            } else if (agruparPor === 'ZONA_DESTINO') {
                valorControlFila = item.cc_dest;
                labelParaMostrar = item.cc_dest || 'SIN ZONA';
            } else if (agruparPor === 'LINEA') {
                valorControlFila = item.linea_codigo;
                labelParaMostrar = item.linea_codigo || 'SIN LÍNEA';
            } else {
                valorControlFila = item.fecha_formato;
                const anioActual = document.getElementById('fechaInicio')?.value.substring(0, 4) || new Date().getFullYear();
                const mesDia = item.fecha_formato.split('/');
                labelParaMostrar = `${mesDia[1]}/${mesDia[0]}/${anioActual}`;
            }

            if (agruparPor !== 'TOTALES') {
                if (valorControlActual !== '' && valorControlActual !== valorControlFila) {
                    inyectarFilaSubtotal(labelSubtotalActual, subtotalCantidad, subtotalCUnit, subtotalCKardex, subtotalCosto);
                    subtotalCantidad = 0;
                    subtotalCUnit = 0;
                    subtotalCKardex = 0;
                    subtotalCosto = 0;
                }
            }

            valorControlActual = valorControlFila;
            labelSubtotalActual = labelParaMostrar;

            sumaCantidad += cantidad;
            sumaCUnit += cUnitCrudo;
            sumaCKardex += cKardex;
            sumaCostoTotal += cTotal;

            subtotalCantidad += cantidad;
            subtotalCUnit += cUnitCrudo;
            subtotalCKardex += cKardex;
            subtotalCosto += cTotal;

            const tr = document.createElement('tr');
            tr.className = "hover:bg-blue-50/40 transition-colors";

            const nroDoc = item.nro_doc || '';
            const docRef = item.doc_ref || '0';
            const codPro = item.cod_pro || '';
            const provCli = item.proveedor_cliente || '';
            const cencos = item.cencos || '';
            const codigo = item.codigo || '';
            const descripcion = item.descripcion || '';
            const ccDest = item.cc_dest || '';
            const lote = item.lote || '';

            // Aplicamos formatNum a cada celda numérica con sus respectivos decimales
            tr.innerHTML = `
            <td class="px-3 py-2 text-center font-medium">${item.fecha_formato || ''}</td>
            <td class="px-3 py-2 text-center font-semibold text-gray-900">${nroDoc}</td>
            <td class="px-3 py-2 text-center text-gray-400">${docRef}</td>
            <td class="px-3 py-2 font-mono">${codPro}</td>
            <td class="px-3 py-2 max-w-xs truncate" title="${provCli}">${provCli}</td>
            <td class="px-3 py-2 text-center">${cencos}</td>
            <td class="px-3 py-2 font-mono">${codigo}</td>
            <td class="px-3 py-2 font-mono">${lote}</td>
            <td class="px-3 py-2 max-w-sm truncate" title="${descripcion}">${descripcion}</td>
            <td class="px-3 py-2 text-right font-bold text-blue-700">${formatNum(cantidad, 2)}</td>
            <td class="px-3 py-2 text-right">${formatNum(cUnitFila, 3)}</td> 
            <td class="px-3 py-2 text-right">${formatNum(cKardex, 3)}</td>
            <td class="px-3 py-2 text-right font-bold">${formatNum(cTotal, 3)}</td>
            <td class="px-3 py-2 text-center">${ccDest}</td>
        `;
            fragment.appendChild(tr);

            if (index === data.length - 1) {
                if (agruparPor === 'TOTALES') {
                    inyectarFilaSubtotal("RANGO SELECCIONADO", subtotalCantidad, subtotalCUnit, subtotalCKardex, subtotalCosto);
                } else {
                    inyectarFilaSubtotal(labelSubtotalActual, subtotalCantidad, subtotalCUnit, subtotalCKardex, subtotalCosto);
                }
            }
        });

        tbody.innerHTML = '';
        tbody.appendChild(fragment);

        const tdTotalCUnit = document.getElementById('totalCUnit');
        const tdTotalCKardex = document.getElementById('totalCKardex');

        // Formateamos también los totales del pie de página
        if (tdTotalCantidad) tdTotalCantidad.textContent = formatNum(sumaCantidad, 2);
        if (tdTotalCUnit) tdTotalCUnit.textContent = formatNum(sumaCUnit, 3);
        if (tdTotalCKardex) tdTotalCKardex.textContent = formatNum(sumaCKardex, 3);
        if (tdTotalCosto) tdTotalCosto.textContent = formatNum(sumaCostoTotal, 3);

        this.mostrarNotificacion('Reporte generado con éxito', 'success');
    }

    limpiarFiltros() {
        this.resetPeriodoFiltro();
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
            success: 'bg-green-500', error: 'bg-red-500',
            warning: 'bg-yellow-500', info: 'bg-blue-500'
        };
        const iconos = {
            success: '✅', error: '❌',
            warning: '⚠️', info: 'ℹ️'
        };

        notif.className = `${colores[tipo]} text-white px-6 py-4 rounded-lg shadow-lg mb-2 flex items-center gap-3`;
        notif.innerHTML = `<span style="font-size: 20px;">${iconos[tipo]}</span><span>${mensaje}</span>`;
        container.appendChild(notif);

        setTimeout(() => notif.remove(), 4000);
    }

    initFiltersUI() {
        const selectTipo = document.getElementById('periodoTipo');
        if (!selectTipo) return;

        const contenedores = {
            'POR_FECHA': document.getElementById('periodoPorFecha'),
            'ENTRE_FECHAS': document.getElementById('periodoEntreFechas'),
            'POR_MES': document.getElementById('periodoPorMes'),
            'ENTRE_MESES': document.getElementById('periodoEntreMeses')
        };

        const actualizarInputs = () => {
            const tipo = selectTipo.value;
            Object.values(contenedores).forEach(div => {
                if (div) div.classList.add('hidden');
            });
            if (contenedores[tipo]) {
                contenedores[tipo].classList.remove('hidden');
            }
        };

        selectTipo.addEventListener('change', actualizarInputs);

        const now = new Date();
        const offset = now.getTimezoneOffset() * 60000;
        const localDate = new Date(now.getTime() - offset);

        const hoy = localDate.toISOString().split('T')[0];
        const mesActual = localDate.toISOString().slice(0, 7);

        selectTipo.value = 'POR_FECHA';

        const setVal = (id, val) => {
            const el = document.getElementById(id);
            if (el) el.value = val;
        };

        setVal('fechaUnica', hoy);
        setVal('fechaInicio', hoy);
        setVal('fechaFin', hoy);
        setVal('mesUnico', mesActual);
        setVal('mesInicio', mesActual);
        setVal('mesFin', mesActual);

        actualizarInputs();
    }

    getFechasFiltro() {
        const tipo = document.getElementById('periodoTipo').value;
        let fInicio = ''; let fFin = '';
        const hoy = new Date();

        switch (tipo) {
            case 'TODOS': break;
            case 'ULTIMA_SEMANA':
                fFin = hoy.toISOString().split('T')[0];
                const hace7dias = new Date(hoy);
                hace7dias.setDate(hoy.getDate() - 7);
                fInicio = hace7dias.toISOString().split('T')[0];
                break;
            case 'POR_FECHA':
                const valFecha = document.getElementById('fechaUnica').value;
                fInicio = valFecha; fFin = valFecha;
                break;
            case 'ENTRE_FECHAS':
                fInicio = document.getElementById('fechaInicio').value;
                fFin = document.getElementById('fechaFin').value;
                break;
            case 'POR_MES':
                const valMes = document.getElementById('mesUnico').value;
                if (valMes) {
                    fInicio = `${valMes}-01`;
                    const [year, month] = valMes.split('-');
                    const ultimoDia = new Date(year, month, 0).getDate();
                    fFin = `${valMes}-${ultimoDia}`;
                }
                break;
            case 'ENTRE_MESES':
                const mesIni = document.getElementById('mesInicio').value;
                const mesFinVal = document.getElementById('mesFin').value;
                if (mesIni) fInicio = `${mesIni}-01`;
                if (mesFinVal) {
                    const [y, m] = mesFinVal.split('-');
                    const uDia = new Date(y, m, 0).getDate();
                    fFin = `${mesFinVal}-${uDia}`;
                }
                break;
        }
        return { inicio: fInicio, fin: fFin };
    }

    resetPeriodoFiltro() {
        const selectPeriodo = document.getElementById('periodoTipo');
        if (selectPeriodo) {
            selectPeriodo.value = 'POR_FECHA';
            selectPeriodo.dispatchEvent(new Event('change'));
        }

        const now = new Date();
        const offset = now.getTimezoneOffset() * 60000;
        const localDate = new Date(now.getTime() - offset);
        const hoy = localDate.toISOString().split('T')[0];
        const mesActual = localDate.toISOString().slice(0, 7);

        const setVal = (id, val) => {
            const el = document.getElementById(id);
            if (el) el.value = val;
        };

        setVal('fechaUnica', hoy); setVal('fechaInicio', hoy); setVal('fechaFin', hoy);
        setVal('mesUnico', mesActual); setVal('mesInicio', mesActual); setVal('mesFin', mesActual);
        setVal('filterAsignacion', ''); setVal('filterAsistencia', ''); setVal('filterZona', '');

        this.seleccionarOpciónCombo('', 'Todas');

        const selectZonaAlmacen = document.getElementById('filterZonaAlmacen');
        if (selectZonaAlmacen) {
            selectZonaAlmacen.value = '';
        }

        // Limpiar combo de Cencos
        if (this.combosDinamicos['cencos']) {
            this.combosDinamicos['cencos'].reset();
        }

        // ✅ Limpiar el nuevo combo dinámico de Cuentas Corrientes
        if (this.combosDinamicos['cuentas']) {
            this.combosDinamicos['cuentas'].reset();
        }

        if (this.combosDinamicos['lineas']) {
            this.combosDinamicos['lineas'].reset();
        }

        if (this.combosDinamicos['codigos']) {
            this.combosDinamicos['codigos'].reset();
        }

        if (this.dataTable) this.dataTable.search('');
    }

    exportarPDF() {
        try {
            this.mostrarNotificacion('Generando PDF, por favor espere...', 'info');

            // 1. Obtenemos filtros
            const filtros = this.obtenerFiltros();

            // 2. Ejecutamos el endpoint desde el service
            this.transaccionService.exportarReportePdf(filtros);

        } catch (error) {
            console.error('Error al exportar el PDF:', error);
            this.mostrarNotificacion('Ocurrió un error al intentar abrir el PDF.', 'error');
        }
    }

    exportarExcel() {
        try {
            this.mostrarNotificacion('Generando Excel, por favor espere...', 'info');

            // 1. Obtenemos filtros
            const filtros = this.obtenerFiltros();

            // 2. Ejecutamos el endpoint desde el service
            this.transaccionService.exportarReporteExcel(filtros);

        } catch (error) {
            console.error('Error al exportar el Excel:', error);
            this.mostrarNotificacion('Ocurrió un error al intentar generar el Excel.', 'error');
        }
    }

    obtenerFiltros() {
        const fechas = this.getFechasFiltro();
        const zona = document.getElementById('filterZonaAlmacen')?.value || '';
        const asignacion = document.getElementById('filterAsignacion')?.value || '';
        const asistencia = document.getElementById('filterAsistencia')?.value || '';

        const cencosSelect = document.getElementById('filterCencos');
        const cencosValores = cencosSelect ? Array.from(cencosSelect.selectedOptions).map(o => o.value).filter(v => v !== '') : [];

        const cuentasSelect = document.getElementById('filterCuentaCorriente');
        const cuentasValores = cuentasSelect ? Array.from(cuentasSelect.selectedOptions).map(o => o.value).filter(v => v !== '') : [];

        const lineasSelect = document.getElementById('filterLineas');
        const lineasValores = lineasSelect ? Array.from(lineasSelect.selectedOptions).map(o => o.value).filter(v => v !== '') : [];

        const codigosSelect = document.getElementById('filterCodigos');
        const codigosValores = codigosSelect ? Array.from(codigosSelect.selectedOptions).map(o => o.value).filter(v => v !== '') : [];

        const agruparPor = document.querySelector('input[name="agruparPor"]:checked')?.value || 'FECHA';
        
        const spanTransaccion = document.getElementById('comboTransaccionTexto');
        const transaccionNombre = spanTransaccion ? spanTransaccion.textContent.trim() : this.transaccionSeleccionada;

        return {
            fechaInicio: fechas.inicio,
            fechaFin: fechas.fin,
            zona: zona,
            asignacion: asignacion,
            asistencia: asistencia,
            cencos: cencosValores.join(','),
            cuentaCorriente: cuentasValores.join(','),
            lineasValores: lineasValores.join(','),
            codigosValores: codigosValores.join(','),
            agruparPor: agruparPor,
            transaccion: this.transaccionSeleccionada,
            transaccionNombre: transaccionNombre
        };
    }
}

window.reporteTransaccionController = new ReporteTransaccionController();