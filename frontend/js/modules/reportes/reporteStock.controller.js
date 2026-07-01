class ReporteStockController {
    constructor() {
        this.stockService = new window.ReporteStockService();
        this.transaccionSeleccionada = '';
        this.dataTransacciones = [];

        this.combosDinamicos = {};
    }

    async init() {
        this.initFiltersUI();
        this.setupToggleFiltros();
        this.setupEventListeners();

        await this.cargarFiltroAlmacenes();
        await this.cargarFiltroLineas();
        await this.cargarFiltroCodigos();
    }

    async cargarFiltroAlmacenes() {
        const selectAlmacen = document.getElementById('filterZonaAlmacen');
        if (!selectAlmacen) return;

        try {
            const response = await this.stockService.getAlmacenesSelect();

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

    async cargarFiltroLineas() {
        const selectLineas = document.getElementById('filterLineas');
        if (!selectLineas) return;

        try {
            const response = await this.stockService.getLineas();

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
            const response = await this.stockService.getCodigos();

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
    }

    async aplicarFiltros() {
        this.mostrarNotificacion('Aplicando filtros...', 'info');
        await this.cargarReporteGrid();
    }

    async cargarReporteGrid() {
        this.mostrarCargando(true);
        try {
            const filtros = this.obtenerFiltros();
            const response = await this.stockService.getReporteStock(filtros);

            if (response && response.success) {
                this.renderizarTabla(response.data, filtros.quiebre, filtros.formato, filtros.agruparPorLote);
            } else {
                this.mostrarNotificacion(response?.message || 'Error al obtener los datos de stock', 'error');
            }
        } catch (error) {
            console.error('Error:', error);
            this.mostrarNotificacion('Error de conexión con el servidor', 'error');
        } finally {
            this.mostrarCargando(false);
        }
    }

    renderizarTabla(data, quiebre, formato, agruparPorLote = 'NO') {
        const thead = document.querySelector('#tablaStock thead');
        const tbody = document.querySelector('#tablaStock tbody');

        // Determinamos cuántas columnas ocupa la tabla según el formato
        let colspanTabla = 9; // Por defecto UNIDADES
        if (formato === 'VALOR' || formato === 'PESO') colspanTabla = 11;
        if (formato === 'RESUMEN') colspanTabla = 5;

        // ── 1. RECONSTRUCCIÓN DINÁMICA DE CABECERAS ──
        if (formato === 'VALOR' || formato === 'PESO') {
            const tituloSeccion = formato === 'VALOR' ? 'VALOR' : 'PESO';
            thead.innerHTML = `
                <tr>
                    <th rowspan="2" class="px-3 py-2 bg-blue-700 border-b border-gray-300 text-center align-middle">Codigo</th>
                    <th rowspan="2" class="px-3 py-2 bg-blue-700 border-b border-gray-300 text-left align-middle">Descripcion</th>
                    <th colspan="4" class="px-3 py-1 bg-blue-800 border-b border-l border-gray-300 text-center tracking-widest"><--- CANTIDAD ---></th>
                    <th colspan="5" class="px-3 py-1 bg-blue-900 border-b border-l border-gray-300 text-center tracking-widest"><--- ${tituloSeccion} ---></th>
                </tr>
                <tr>
                    <th class="px-3 py-1.5 bg-blue-700 border-b border-l border-gray-300 text-right">Inicio</th>
                    <th class="px-3 py-1.5 bg-blue-700 border-b border-gray-300 text-right">Entrada</th>
                    <th class="px-3 py-1.5 bg-blue-700 border-b border-gray-300 text-right">Salida</th>
                    <th class="px-3 py-1.5 bg-blue-700 border-b border-gray-300 text-right">Stock</th>
                    <th class="px-3 py-1.5 bg-blue-700 border-b border-l border-gray-300 text-right">Inicio</th>
                    <th class="px-3 py-1.5 bg-blue-700 border-b border-gray-300 text-right">Entrada</th>
                    <th class="px-3 py-1.5 bg-blue-700 border-b border-gray-300 text-right">Salida</th>
                    <th class="px-3 py-1.5 bg-blue-700 border-b border-gray-300 text-right">Stock</th>
                    <th class="px-3 py-1.5 bg-blue-700 border-b border-gray-300 text-right">Prom</th>
                </tr>
            `;
        } else if (formato === 'RESUMEN') {
            thead.innerHTML = `
                <tr>
                    <th class="px-3 py-2.5 bg-blue-700 border-b border-gray-300 text-center">Codigo</th>
                    <th class="px-3 py-2.5 bg-blue-700 border-b border-gray-300 text-left">Descripcion</th>
                    <th class="px-3 py-2.5 bg-blue-700 border-b border-gray-300 text-right">Stock Unidade</th>
                    <th class="px-3 py-2.5 bg-blue-700 border-b border-gray-300 text-right">Stock Valorado</th>
                    <th class="px-3 py-2.5 bg-blue-700 border-b border-gray-300 text-right">Prec</th>
                </tr>
            `;
        } else {
            thead.innerHTML = `
                <tr>
                    <th class="px-3 py-2.5 bg-blue-700 border-b border-gray-300 text-center">Codigo</th>
                    <th class="px-3 py-2.5 bg-blue-700 border-b border-gray-300 text-left">Descripcion</th>
                    <th class="px-3 py-2.5 bg-blue-700 border-b border-gray-300 text-right">Inicio</th>
                    <th class="px-3 py-2.5 bg-blue-700 border-b border-gray-300 text-right">Entrada</th>
                    <th class="px-3 py-2.5 bg-blue-700 border-b border-gray-300 text-right">Consumo</th>
                    <th class="px-3 py-2.5 bg-blue-700 border-b border-gray-300 text-right">Ajuste</th>
                    <th class="px-3 py-2.5 bg-blue-700 border-b border-gray-300 text-right">Stock</th>
                    <th class="px-3 py-2.5 bg-blue-700 border-b border-gray-300 text-right">Diario</th>
                    <th class="px-3 py-2.5 bg-blue-700 border-b border-gray-300 text-right">Alcance</th>
                </tr>
            `;
        }

        if (!data || data.length === 0) {
            tbody.innerHTML = `<tr><td colspan="${colspanTabla}" class="px-3 py-8 text-center text-gray-500">No se encontraron registros de stock para los filtros aplicados.</td></tr>`;
            return;
        }

        const formatNum = (num, decimals = 2) => {
            if (num === '' || num === null || num === undefined) return '';
            return new Intl.NumberFormat('en-US', { minimumFractionDigits: decimals, maximumFractionDigits: decimals }).format(num);
        };
        const fragment = document.createDocumentFragment();

        let valorQuiebreActual = '';

        let subIniU = 0, subEntU = 0, subSalU = 0, subStoU = 0;
        let subIniExt = 0, subEntExt = 0, subSalExt = 0, subStoExt = 0;

        let totIniU = 0, totEntU = 0, totSalU = 0, totStoU = 0;
        let totIniExt = 0, totEntExt = 0, totSalExt = 0, totStoExt = 0;

        const inyectarTotal = (titulo, esGeneral = false) => {
            const tr = document.createElement('tr');
            const bgClass = esGeneral ? 'bg-gray-100 border-b-2 border-gray-400' : 'bg-gray-50 border-y border-gray-300';
            const textClass = esGeneral ? 'text-gray-900 font-black' : 'text-gray-900 font-bold';

            tr.className = `${bgClass} ${textClass}`;

            const sIniU = esGeneral ? totIniU : subIniU; const sEntU = esGeneral ? totEntU : subEntU;
            const sSalU = esGeneral ? totSalU : subSalU; const sStoU = esGeneral ? totStoU : subStoU;

            let sIniExt = esGeneral ? totIniExt : subIniExt; let sEntExt = esGeneral ? totEntExt : subEntExt;
            let sSalExt = esGeneral ? totSalExt : subSalExt; let sStoExt = esGeneral ? totStoExt : subStoExt;

            if (formato === 'VALOR' || formato === 'PESO') {
                if (formato === 'PESO') {
                    if (sIniExt === 0) sIniExt = ''; if (sEntExt === 0) sEntExt = '';
                    if (sSalExt === 0) sSalExt = ''; if (sStoExt === 0) sStoExt = '';
                }
                tr.innerHTML = `
                    <td colspan="2" class="px-3 py-2.5 text-right uppercase tracking-wider text-xs">${titulo}</td>
                    <td class="px-3 py-2.5 text-right border-l border-gray-200">${formatNum(sIniU, 2)}</td>
                    <td class="px-3 py-2.5 text-right text-blue-700">${formatNum(sEntU, 2)}</td>
                    <td class="px-3 py-2.5 text-right text-red-600">${formatNum(sSalU, 2)}</td>
                    <td class="px-3 py-2.5 text-right font-black">${formatNum(sStoU, 2)}</td>
                    <td class="px-3 py-2.5 text-right border-l border-gray-200">${formatNum(sIniExt, 2)}</td>
                    <td class="px-3 py-2.5 text-right text-blue-700">${formatNum(sEntExt, 2)}</td>
                    <td class="px-3 py-2.5 text-right text-red-600">${formatNum(sSalExt, 2)}</td>
                    <td class="px-3 py-2.5 text-right font-black">${formatNum(sStoExt, 2)}</td>
                    <td class="px-3 py-2.5 text-right"></td>
                `;
            } else if (formato === 'RESUMEN') {
                tr.innerHTML = `
                    <td colspan="2" class="px-3 py-2.5 text-right uppercase tracking-wider text-xs">${titulo}</td>
                    <td class="px-3 py-2.5 text-right font-black">${formatNum(sStoU, 2)}</td>
                    <td class="px-3 py-2.5 text-right font-black">${formatNum(sStoExt, 2)}</td>
                    <td class="px-3 py-2.5 text-right"></td>
                `;
            } else {
                tr.innerHTML = `
                    <td colspan="2" class="px-3 py-2.5 text-right uppercase tracking-wider text-xs">${titulo}</td>
                    <td class="px-3 py-2.5 text-right">${formatNum(sIniU, 2)}</td>
                    <td class="px-3 py-2.5 text-right text-blue-700">${formatNum(sEntU, 2)}</td>
                    <td class="px-3 py-2.5 text-right text-red-600">${formatNum(sSalU, 2)}</td>
                    <td class="px-3 py-2.5 text-right"></td>
                    <td class="px-3 py-2.5 text-right font-black">${formatNum(sStoU, 2)}</td>
                    <td class="px-3 py-2.5 text-right"></td>
                    <td class="px-3 py-2.5 text-right"></td>
                `;
            }
            fragment.appendChild(tr);
        };

        data.forEach((item, index) => {
            let valorControl = ''; let descripcionQuiebre = '';
            if (quiebre === 'LINEA') { valorControl = item.linea_codigo; descripcionQuiebre = `${item.linea_codigo || '00'} - ${item.linea_descri || 'SIN LÍNEA'}`; }
            else if (quiebre === 'CUENTA') { valorControl = item.cuenta_codigo; descripcionQuiebre = `${item.cuenta_codigo || '000000'} - ${item.cuenta_descri || 'SIN CUENTA'}`; }
            else { valorControl = item.alma_codigo; descripcionQuiebre = `${item.alma_codigo || '000'}   ${item.alma_descri || 'ALMACÉN NO DEFINIDO'}`; }

            if (valorQuiebreActual !== '' && valorQuiebreActual !== valorControl) {
                inyectarTotal('TOTAL GRUPO :');
                subIniU = subEntU = subSalU = subStoU = 0;
                subIniExt = subEntExt = subSalExt = subStoExt = 0;
            }

            if (valorQuiebreActual !== valorControl) {
                const trGroup = document.createElement('tr');
                trGroup.className = "bg-gray-200/60 font-bold text-gray-900 border-y border-gray-300";
                trGroup.innerHTML = `<td colspan="${colspanTabla}" class="px-3 py-2 uppercase tracking-wider text-sm">${descripcionQuiebre}</td>`;
                fragment.appendChild(trGroup);
                valorQuiebreActual = valorControl;
            }

            const iniExt = formato === 'PESO' ? item.inicio_p : item.inicio_v;
            const entExt = formato === 'PESO' ? item.entrada_p : item.entrada_v;
            const salExt = formato === 'PESO' ? item.salida_p : item.salida_v;
            const stoExt = formato === 'PESO' ? item.stock_p : item.stock_v;

            subIniU += parseFloat(item.inicio_u) || 0; subEntU += parseFloat(item.entrada_u) || 0; subSalU += parseFloat(item.salida_u) || 0; subStoU += parseFloat(item.stock_u) || 0;
            totIniU += parseFloat(item.inicio_u) || 0; totEntU += parseFloat(item.entrada_u) || 0; totSalU += parseFloat(item.salida_u) || 0; totStoU += parseFloat(item.stock_u) || 0;

            subIniExt += parseFloat(iniExt) || 0; subEntExt += parseFloat(entExt) || 0; subSalExt += parseFloat(salExt) || 0; subStoExt += parseFloat(stoExt) || 0;
            totIniExt += parseFloat(iniExt) || 0; totEntExt += parseFloat(entExt) || 0; totSalExt += parseFloat(salExt) || 0; totStoExt += parseFloat(stoExt) || 0;

            const tr = document.createElement('tr');
            tr.className = "hover:bg-blue-50/40 transition-colors";

            if (formato === 'VALOR' || formato === 'PESO') {
                tr.innerHTML = `
                    <td class="px-3 py-2 font-mono text-center font-semibold text-gray-700">${item.codigo}</td>
                    <td class="px-3 py-2 max-w-sm truncate" title="${item.descripcion}">
    <div>${item.descripcion}</div>
    ${agruparPorLote === 'SI' ? `<div class="text-[10px] text-purple-600 font-semibold tracking-wide mt-0.5">LOTE: ${item.lote || '00000000'}</div>` : ''}
</td>
                    <td class="px-3 py-2 text-right border-l border-gray-100">${formatNum(item.inicio_u, 2)}</td>
                    <td class="px-3 py-2 text-right text-blue-700 font-medium">${formatNum(item.entrada_u, 2)}</td>
                    <td class="px-3 py-2 text-right text-red-600 font-medium">${formatNum(item.salida_u, 2)}</td>
                    <td class="px-3 py-2 text-right font-bold text-gray-900">${formatNum(item.stock_u, 2)}</td>
                    <td class="px-3 py-2 text-right border-l border-gray-100">${formatNum(iniExt, 2)}</td>
                    <td class="px-3 py-2 text-right text-blue-700 font-medium">${formatNum(entExt, 2)}</td>
                    <td class="px-3 py-2 text-right text-red-600 font-medium">${formatNum(salExt, 2)}</td>
                    <td class="px-3 py-2 text-right font-bold text-gray-900">${formatNum(stoExt, 2)}</td>
                    <td class="px-3 py-2 text-right text-gray-500">${formatNum(item.precio_promedio, 2)}</td>
                `;
            } else if (formato === 'RESUMEN') {
                // FoxPro no pinta el promedio si el stock es cero en esta vista
                const promFormat = (parseFloat(item.stock_u) === 0 && parseFloat(item.precio_promedio) === 0) ? '' : formatNum(item.precio_promedio, 2);
                tr.innerHTML = `
                    <td class="px-3 py-2 font-mono text-center font-semibold text-gray-700">${item.codigo}</td>
                    <td class="px-3 py-2 max-w-sm truncate" title="${item.descripcion}">
    <div>${item.descripcion}</div>
    ${agruparPorLote === 'SI' ? `<div class="text-[10px] text-purple-600 font-semibold tracking-wide mt-0.5">LOTE: ${item.lote || '00000000'}</div>` : ''}
</td>
                    <td class="px-3 py-2 text-right font-bold text-gray-900">${formatNum(item.stock_u, 2)}</td>
                    <td class="px-3 py-2 text-right font-bold text-gray-900">${formatNum(item.stock_v, 2)}</td>
                    <td class="px-3 py-2 text-right text-gray-500">${promFormat}</td>
                `;
            } else {
                tr.innerHTML = `
                    <td class="px-3 py-2 font-mono text-center font-semibold text-gray-700">${item.codigo}</td>
                    <td class="px-3 py-2 max-w-sm truncate" title="${item.descripcion}">
    <div>${item.descripcion}</div>
    ${agruparPorLote === 'SI' ? `<div class="text-[10px] text-purple-600 font-semibold tracking-wide mt-0.5">LOTE: ${item.lote || '00000000'}</div>` : ''}
</td>
                    <td class="px-3 py-2 text-right">${formatNum(item.inicio_u, 2)}</td>
                    <td class="px-3 py-2 text-right text-blue-700 font-medium">${formatNum(item.entrada_u, 2)}</td>
                    <td class="px-3 py-2 text-right text-red-600 font-medium">${formatNum(item.salida_u, 2)}</td>
                    <td class="px-3 py-2 text-right text-gray-400">0.00</td>
                    <td class="px-3 py-2 text-right font-bold text-gray-900">${formatNum(item.stock_u, 2)}</td>
                    <td class="px-3 py-2 text-right text-gray-400">0.00</td>
                    <td class="px-3 py-2 text-right text-gray-400">0.00</td>
                `;
            }
            fragment.appendChild(tr);

            if (index === data.length - 1) {
                inyectarTotal('TOTAL GRUPO :');
                inyectarTotal('TOTAL GENERAL :', true);
            }
        });

        tbody.innerHTML = '';
        tbody.appendChild(fragment);
        this.mostrarNotificacion('Reporte de stock generado con éxito', 'success');
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

        if (this.combosDinamicos['lineas']) {
            this.combosDinamicos['lineas'].reset();
        }

        if (this.combosDinamicos['codigos']) {
            this.combosDinamicos['codigos'].reset();
        }

        const selectFormato = document.getElementById('filterFormato');
        if (selectFormato) selectFormato.value = 'UNIDADES';

        const selectQuiebre = document.getElementById('filterQuiebre');
        if (selectQuiebre) selectQuiebre.value = 'ALMACEN';

        const selectAgruparLote = document.getElementById('filterAgruparLote');
        if (selectAgruparLote) selectAgruparLote.value = 'NO';

        if (this.dataTable) this.dataTable.search('');
    }

    exportarPDF() {
        try {
            this.mostrarNotificacion('Generando PDF, por favor espere...', 'info');

            // 1. Obtenemos filtros
            const filtros = this.obtenerFiltros();

            // 2. Ejecutamos el endpoint desde el service
            this.stockService.exportarReportePdf(filtros);

        } catch (error) {
            console.error('Error al exportar el PDF:', error);
            this.mostrarNotificacion('Ocurrió un error al intentar abrir el PDF.', 'error');
        }
    }

    obtenerFiltros() {
        const fechas = this.getFechasFiltro();
        const zona = document.getElementById('filterZonaAlmacen')?.value || '';

        const lineasSelect = document.getElementById('filterLineas');
        const lineasValores = lineasSelect ? Array.from(lineasSelect.selectedOptions).map(o => o.value).filter(v => v !== '') : [];

        const codigosSelect = document.getElementById('filterCodigos');
        const codigosValores = codigosSelect ? Array.from(codigosSelect.selectedOptions).map(o => o.value).filter(v => v !== '') : [];

        const formato = document.getElementById('filterFormato')?.value || 'RESUMEN';
        const quiebre = document.getElementById('filterQuiebre')?.value || 'ALMACEN';
        const agruparPorLote = document.getElementById('filterAgruparLote')?.value || 'NO';

        return {
            fechaInicio: fechas.inicio,
            fechaFin: fechas.fin,
            zona: zona,
            lineasValores: lineasValores.join(','),
            codigosValores: codigosValores.join(','),
            formato: formato,
            quiebre: quiebre,
            agruparPorLote: agruparPorLote
        };
    }
}

window.ReporteStockController = new ReporteStockController();