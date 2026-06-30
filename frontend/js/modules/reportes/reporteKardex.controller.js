class ReporteKardexController {
    constructor() {
        this.kardexService = new window.ReporteKardexService();
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
            const response = await this.kardexService.getAlmacenesSelect();

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
            const response = await this.kardexService.getLineas();

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
            const response = await this.kardexService.getCodigos();

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
            const response = await this.kardexService.getReportekardex(filtros);

            if (response && response.success) {
                // Capturamos el texto del almacén seleccionado
                const selectZona = document.getElementById('filterZonaAlmacen');
                const almacenNombre = selectZona && selectZona.selectedIndex >= 0 
                    ? selectZona.options[selectZona.selectedIndex].text 
                    : '';
                
                this.renderizarTabla(response.data, filtros.formato, almacenNombre);
            } else {
                this.mostrarNotificacion(response?.message || 'Error al obtener los datos de KARDEX', 'error');
            }
        } catch (error) {
            console.error('Error:', error);
            this.mostrarNotificacion('Error de conexión con el servidor', 'error');
        } finally {
            this.mostrarCargando(false);
        }
    }

    renderizarTabla(data, formato, almacenNombre = '') {
        const thead = document.querySelector('#tablaKardex thead');
        const tbody = document.querySelector('#tablaKardex tbody');

        if (!data || data.length === 0) {
            // El colspan cambia a 11 columnas (Valor/Peso) o 7 (Cantidad)
            const colspanEmpty = (formato === 'VALOR' || formato === 'PESO') ? 11 : 7;
            tbody.innerHTML = `<tr><td colspan="${colspanEmpty}" class="px-3 py-8 text-center text-gray-500">No se encontraron movimientos en el Kardex para los filtros aplicados.</td></tr>`;
            return;
        }

        const formatNum = (num, decimals = 2) => {
            if (num === '' || num === null || num === undefined) return '';
            return new Intl.NumberFormat('en-US', { minimumFractionDigits: decimals, maximumFractionDigits: decimals }).format(num);
        };

        // ── 1. RECONSTRUCCIÓN DINÁMICA DE CABECERAS PARA KARDEX ──
        if (formato === 'VALOR' || formato === 'PESO') {
            const tituloSeccion = formato === 'VALOR' ? 'VALORADO' : 'PESO';
            thead.innerHTML = `
                <tr>
                    <th rowspan="2" class="px-3 py-2 bg-blue-700 border-b border-gray-300 text-center align-middle">FECHA</th>
                    <th rowspan="2" class="px-3 py-2 bg-blue-700 border-b border-gray-300 text-center align-middle">CODTRA</th>
                    <th rowspan="2" class="px-3 py-2 bg-blue-700 border-b border-gray-300 text-center align-middle">NRO. DOC</th>
                    <th rowspan="2" class="px-3 py-2 bg-blue-700 border-b border-gray-300 text-left align-middle">DESCRIPCION</th>
                    <th colspan="3" class="px-3 py-1 bg-blue-800 border-b border-l border-gray-300 text-center tracking-widest"><--- UNIDADES ---></th>
                    <th colspan="4" class="px-3 py-1 bg-blue-900 border-b border-l border-gray-300 text-center tracking-widest"><--- ${tituloSeccion} ---></th>
                </tr>
                <tr>
                    <th class="px-3 py-1.5 bg-blue-700 border-b border-l border-gray-300 text-right">ENTRADA</th>
                    <th class="px-3 py-1.5 bg-blue-700 border-b border-gray-300 text-right">SALIDA</th>
                    <th class="px-3 py-1.5 bg-blue-700 border-b border-gray-300 text-right">STOCK</th>
                    <th class="px-3 py-1.5 bg-blue-700 border-b border-l border-gray-300 text-right">ENTRADA</th>
                    <th class="px-3 py-1.5 bg-blue-700 border-b border-gray-300 text-right">SALIDA</th>
                    <th class="px-3 py-1.5 bg-blue-700 border-b border-gray-300 text-right">STOCK</th>
                    <th class="px-3 py-1.5 bg-blue-700 border-b border-gray-300 text-right">VA_UNT</th>
                </tr>
            `;
        } else {
            // Cabecera simplificada para "CANTIDAD"
            thead.innerHTML = `
                <tr>
                    <th class="px-3 py-2.5 bg-blue-700 border-b border-gray-300 text-center">FECHA</th>
                    <th class="px-3 py-2.5 bg-blue-700 border-b border-gray-300 text-center">CODTRA</th>
                    <th class="px-3 py-2.5 bg-blue-700 border-b border-gray-300 text-center">NRO. DOC</th>
                    <th class="px-3 py-2.5 bg-blue-700 border-b border-gray-300 text-left">DESCRIPCION</th>
                    <th class="px-3 py-2.5 bg-blue-700 border-b border-l border-gray-300 text-right">ENTRADA</th>
                    <th class="px-3 py-2.5 bg-blue-700 border-b border-gray-300 text-right">SALIDA</th>
                    <th class="px-3 py-2.5 bg-blue-700 border-b border-gray-300 text-right">STOCK</th>
                </tr>
            `;
        }

        const fragment = document.createDocumentFragment();

        // ── 2. ITERACIÓN SOBRE EL ARREGLO DE PRODUCTOS ──
        data.forEach(producto => {

            // A. Fila de Título del Producto (Gris)
            const trHeader = document.createElement('tr');
            trHeader.className = "bg-gray-200/60 font-bold text-gray-900 border-y border-gray-300";
            const colspanProd = (formato === 'VALOR' || formato === 'PESO') ? 11 : 7;
            
            trHeader.innerHTML = `
                <td colspan="${colspanProd}" class="px-3 py-2 uppercase tracking-wider text-sm">
                    <div class="text-xs text-gray-500 mb-0.5">ALMACÉN: ${almacenNombre}</div>
                    <div class="text-xs text-blue-800 font-black tracking-tight">${producto.codigo} - ${producto.descripcion}</div>
                    <div class="text-xs text-gray-500 mt-0.5">LOTE: ${producto.lote}</div>
                </td>
            `;
            fragment.appendChild(trHeader);

            // B. Fila de Saldo Inicial
            const trSaldo = document.createElement('tr');
            trSaldo.className = "bg-gray-50 border-b border-gray-200 text-gray-800 font-semibold";
            let saldoExt = formato === 'PESO' ? producto.saldo_inicial.peso : producto.saldo_inicial.val;

            if (formato === 'VALOR' || formato === 'PESO') {
                trSaldo.innerHTML = `
                    <td colspan="4" class="px-3 py-2 text-right uppercase tracking-widest text-xs pr-8">SALDO:</td>
                    <td class="px-3 py-2 border-l border-gray-200"></td>
                    <td class="px-3 py-2"></td>
                    <td class="px-3 py-2 text-right font-black">${formatNum(producto.saldo_inicial.cant)}</td>
                    <td class="px-3 py-2 border-l border-gray-200"></td>
                    <td class="px-3 py-2"></td>
                    <td class="px-3 py-2 text-right font-black">${formatNum(saldoExt)}</td>
                    <td class="px-3 py-2 text-right text-gray-500">${formatNum(producto.saldo_inicial.pu)}</td>
                `;
            } else {
                trSaldo.innerHTML = `
                    <td colspan="4" class="px-3 py-2 text-right uppercase tracking-widest text-xs pr-8">SALDO:</td>
                    <td class="px-3 py-2 border-l border-gray-200"></td>
                    <td class="px-3 py-2"></td>
                    <td class="px-3 py-2 text-right font-black">${formatNum(producto.saldo_inicial.cant)}</td>
                `;
            }
            fragment.appendChild(trSaldo);

            // ── ACUMULADORES PARA LA FILA FINAL DE TOTALES ──
            let sumEntCant = 0, sumSalCant = 0;
            let sumEntExt = 0, sumSalExt = 0;

            // C. Iteración sobre los Movimientos (Detalle)
            producto.detalle.forEach(mov => {
                const tr = document.createElement('tr');
                tr.className = "hover:bg-blue-50/40 transition-colors text-gray-700";

                let fechaFormateada = mov.fecha;
                if (fechaFormateada && fechaFormateada.includes('-')) {
                    const partes = fechaFormateada.split('-');
                    fechaFormateada = `${partes[2]}/${partes[1]}`;
                }

                let entExt = formato === 'PESO' ? mov.ent_peso : mov.ent_val;
                let salExt = formato === 'PESO' ? mov.sal_peso : mov.sal_val;
                let stoExt = formato === 'PESO' ? mov.sto_peso : mov.sto_val;

                // Sumamos a los acumuladores globales del producto
                sumEntCant += mov.ent_cant;
                sumSalCant += mov.sal_cant;
                sumEntExt += entExt;
                sumSalExt += salExt;

                // ── SOLUCIÓN 3: MEJORANDO LA DESCRIPCIÓN ──
                const descriMov = (mov.nomref && mov.nomref.trim() !== '') ? mov.nomref.substring(0, 25) : mov.descri;

                if (formato === 'VALOR' || formato === 'PESO') {
                    tr.innerHTML = `
                        <td class="px-3 py-2 text-center whitespace-nowrap">${fechaFormateada}</td>
                        <td class="px-3 py-2 text-center font-mono">${mov.codtra}</td>
                        <td class="px-3 py-2 text-center">${mov.tnumfac || ''}</td> 
                        <td class="px-3 py-2 max-w-[220px] truncate" title="${descriMov}">${descriMov}</td>
                        <td class="px-3 py-2 text-right border-l border-gray-100 text-blue-700">${mov.ent_cant > 0 ? formatNum(mov.ent_cant) : ''}</td>
                        <td class="px-3 py-2 text-right text-red-600">${mov.sal_cant > 0 ? formatNum(mov.sal_cant) : ''}</td>
                        <td class="px-3 py-2 text-right font-bold text-gray-900">${formatNum(mov.sto_cant)}</td>
                        <td class="px-3 py-2 text-right border-l border-gray-100 text-blue-700">${entExt > 0 ? formatNum(entExt) : ''}</td>
                        <td class="px-3 py-2 text-right text-red-600">${salExt > 0 ? formatNum(salExt) : ''}</td>
                        <td class="px-3 py-2 text-right font-bold text-gray-900">${formatNum(stoExt)}</td>
                        <td class="px-3 py-2 text-right text-gray-500">${formatNum(mov.pu)}</td>
                    `;
                } else {
                    tr.innerHTML = `
                        <td class="px-3 py-2 text-center whitespace-nowrap">${fechaFormateada}</td>
                        <td class="px-3 py-2 text-center font-mono">${mov.codtra}</td>
                        <td class="px-3 py-2 text-center">${mov.tnumfac || ''}</td>
                        <td class="px-3 py-2 max-w-[220px] truncate" title="${descriMov}">${descriMov}</td>
                        <td class="px-3 py-2 text-right border-l border-gray-100 text-blue-700">${mov.ent_cant > 0 ? formatNum(mov.ent_cant) : ''}</td>
                        <td class="px-3 py-2 text-right text-red-600">${mov.sal_cant > 0 ? formatNum(mov.sal_cant) : ''}</td>
                        <td class="px-3 py-2 text-right font-bold text-gray-900">${formatNum(mov.sto_cant)}</td>
                    `;
                }
                fragment.appendChild(tr);
            });

            // ── SOLUCIÓN 4: FILA FINAL DE TOTALES ──
            const trTotal = document.createElement('tr');
            trTotal.className = "bg-white text-gray-900 font-bold";
            if (formato === 'VALOR' || formato === 'PESO') {
                trTotal.innerHTML = `
                    <td colspan="4" class="px-3 py-2 text-right"></td>
                    <td class="px-3 py-2 text-right border-t border-gray-900 text-blue-700">${sumEntCant > 0 ? formatNum(sumEntCant) : ''}</td>
                    <td class="px-3 py-2 text-right border-t border-gray-900 text-red-600">${sumSalCant > 0 ? formatNum(sumSalCant) : ''}</td>
                    <td class="px-3 py-2 text-right border-t border-gray-900"></td>
                    <td class="px-3 py-2 text-right border-t border-gray-900 text-blue-700">${sumEntExt > 0 ? formatNum(sumEntExt) : ''}</td>
                    <td class="px-3 py-2 text-right border-t border-gray-900 text-red-600">${sumSalExt > 0 ? formatNum(sumSalExt) : ''}</td>
                    <td colspan="2" class="px-3 py-2 border-t border-gray-900"></td>
                `;
            } else {
                trTotal.innerHTML = `
                    <td colspan="4" class="px-3 py-2 text-right"></td>
                    <td class="px-3 py-2 text-right border-t border-gray-900 text-blue-700">${sumEntCant > 0 ? formatNum(sumEntCant) : ''}</td>
                    <td class="px-3 py-2 text-right border-t border-gray-900 text-red-600">${sumSalCant > 0 ? formatNum(sumSalCant) : ''}</td>
                    <td class="px-3 py-2 text-right border-t border-gray-900"></td>
                `;
            }
            fragment.appendChild(trTotal);
        });

        tbody.innerHTML = '';
        tbody.appendChild(fragment);
        this.mostrarNotificacion('Kardex generado con éxito', 'success');
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

        if (this.dataTable) this.dataTable.search('');
    }

    exportarPDF() {
        try {
            this.mostrarNotificacion('Generando PDF, por favor espere...', 'info');

            // 1. Obtenemos filtros
            const filtros = this.obtenerFiltros();

            // 2. Ejecutamos el endpoint desde el service
            this.kardexService.exportarReportePdf(filtros);

        } catch (error) {
            console.error('Error al exportar el PDF:', error);
            this.mostrarNotificacion('Ocurrió un error al intentar abrir el PDF.', 'error');
        }
    }

    obtenerFiltros() {
        const fechas = this.getFechasFiltro();
        
        const selectZona = document.getElementById('filterZonaAlmacen');
        const zona = selectZona?.value || '010';
        // Capturamos el nombre completo (Ej: "010 - ALM LA JOYA")
        const almacenNombre = selectZona && selectZona.selectedIndex >= 0 
            ? selectZona.options[selectZona.selectedIndex].text 
            : '010';

        const lineasSelect = document.getElementById('filterLineas');
        const lineasValores = lineasSelect ? Array.from(lineasSelect.selectedOptions).map(o => o.value).filter(v => v !== '') : [];

        const codigosSelect = document.getElementById('filterCodigos');
        const codigosValores = codigosSelect ? Array.from(codigosSelect.selectedOptions).map(o => o.value).filter(v => v !== '') : [];

        let formato = 'VALOR'; // Por defecto
        
        const selectFormato = document.getElementById('filterFormato');
        
        if (selectFormato && selectFormato.value) {
            // Extrae el valor seleccionado (VALOR, PESO, o CANTIDAD) y lo pasa a mayúsculas
            formato = selectFormato.value.toUpperCase();
        }

        return {
            fechaInicio: fechas.inicio,
            fechaFin: fechas.fin,
            zona: zona,
            almacenNombre: almacenNombre, // <-- Nuevo dato enviado
            lineasValores: lineasValores.join(','),
            codigosValores: codigosValores.join(','),
            formato: formato
        };
    }
}

window.ReporteKardexController = new ReporteKardexController();