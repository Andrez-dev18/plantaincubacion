/**
 * Controlador de Cargapollo por Día — Layout 3 Paneles
 */
class CargapolloPorDiaController {
    constructor() {
        this.service = new CargapolloPorDiaService();
        this.proyeccionActual = null;
        this.datosActuales = null;
        this.fechaMinCalendario = null;
        this.fechaMaxCalendario = null;
        this.dataTableInstances = {}; // Almacenar instancias de DataTables
        this.init();
    }

    async init() {
        await this.cargarProyecciones();
        this.setupEventListeners();
    }

    async cargarProyecciones() {
        try {
            this.showLoading(true);
            const response = await this.service.listarProyecciones();

            if (response.success && response.data) {
                const select = document.getElementById('selectProyeccion');
                if (!select) {
                    console.error('Elemento selectProyeccion no encontrado');
                    return;
                }
                select.innerHTML = '<option value="">Seleccionar proyección...</option>';

                const isValida = (ind) => String(ind || '').trim().toUpperCase() === 'VALIDO';
                let proyeccionValida = null;

                response.data.forEach(item => {
                    const nombre = typeof item === 'object' ? item.nombre : item;
                    const indicador = typeof item === 'object' ? item.indicador : null;
                    if (!nombre) return;
                    const opt = document.createElement('option');
                    opt.value = nombre;
                    opt.textContent = nombre;
                    opt.dataset.indicador = indicador || '';
                    select.appendChild(opt);
                    if (!proyeccionValida && isValida(indicador)) proyeccionValida = nombre;
                });

                if (proyeccionValida) {
                    select.value = proyeccionValida;
                    if (window.jQuery) {
                        window.jQuery('#selectProyeccion').val(proyeccionValida).trigger('change');
                    }
                }
            }
        } catch (e) {
            console.error(e);
        } finally {
            this.showLoading(false);
        }
    }

    async cargarDatos() {
        const proyeccion = document.getElementById('selectProyeccion').value;
        if (!proyeccion) {
            this.showWarning('Por favor selecciona una proyección');
            return;
        }

        try {
            this.showLoading(true);
            this.proyeccionActual = proyeccion;

            // Badge
            const badge = document.getElementById('badgeProyeccion');
            const badgeCentral = document.getElementById('badgeCentral');
            if (badge) {
                badge.textContent = proyeccion;
                badge.style.display = 'inline-block';
            }
            if (badgeCentral) {
                badgeCentral.textContent = proyeccion;
                badgeCentral.style.display = 'inline-block';
            }

            // 1. PRIMERO: Cargar y renderizar panel central
            const reporte = await this.service.obtenerDatos(proyeccion);
            if (reporte.success && reporte.data) {
                this.datosActuales = reporte.data;
                this.renderTabla(reporte.data);
                const btnExcel = document.getElementById('btnExportarExcel');
                const btnPDF = document.getElementById('btnExportarPDF');
                const btnFiltros = document.getElementById('btnFiltros');
                const btnCopiar = document.getElementById('btnCopiarCalendario');
                if (btnExcel) btnExcel.disabled = false;
                if (btnPDF) btnPDF.disabled = false;
                if (btnFiltros) btnFiltros.disabled = false;
                if (btnCopiar) btnCopiar.disabled = false;
            } else {
                this.showError(reporte.message || 'Error al cargar reporte');
            }

            // 2. LUEGO: Cargar y renderizar panel izquierdo (calendario)
            await this.renderCalendario(reporte.data);

            // 3. POR ÚLTIMO: Cargar y renderizar panel derecho (resumen)
            const resumen = await this.service.obtenerResumen(proyeccion);
            if (resumen.success && resumen.data) {
                this.renderResumenPanel(resumen.data);
            }

        } catch (e) {
            console.error(e);
            this.showError('Error de conexión');
        } finally {
            this.showLoading(false);
        }
    }

    /** Panel CENTRAL — tabla pivotada */
    renderTabla(datos) {
        const headerRow = document.getElementById('headerRow');
        const tableBody = document.getElementById('tableBody');
        const tabla = document.getElementById('tablaReporte');
        const empty = document.getElementById('emptyReporte');

        // Validar que los elementos existan
        if (!headerRow || !tableBody || !tabla || !empty) {
            console.error('Elementos del DOM no encontrados en renderTabla');
            return;
        }

        if (!datos || !datos.granjas || datos.granjas.length === 0) {
            tabla.style.display = 'none';
            empty.style.display = 'flex';
            return;
        }

        // Destruir DataTable existente si existe (verificar con jQuery también)
        if ($.fn.DataTable.isDataTable('#tablaReporte')) {
            $('#tablaReporte').DataTable().destroy();
        }
        if (this.dataTableInstances.tablaReporte) {
            this.dataTableInstances.tablaReporte = null;
        }

        tabla.style.display = 'table';
        empty.style.display = 'none';

        // Limpiar headers dinámicos
        headerRow.querySelectorAll('.dyn').forEach(h => h.remove());

        // Headers granjas (pollos)
        datos.granjas.forEach(g => {
            const th = document.createElement('th');
            th.className = 'dyn';
            th.textContent = g.granja_completa;
            headerRow.appendChild(th);
        });

        // Headers fechas liquidación
        datos.granjas.forEach(g => {
            const th = document.createElement('th');
            th.className = 'dyn';
            th.textContent = `F.Liq ${g.granja_completa}`;
            th.style.background = '#92400e';
            headerRow.appendChild(th);
        });

        // Header total
        const thT = document.createElement('th');
        thT.className = 'dyn';
        thT.textContent = 'Total';
        thT.style.background = '#166534';
        headerRow.appendChild(thT);

        // Filas
        tableBody.innerHTML = '';
        const filas = Object.values(datos.datos).sort((a, b) =>
            a.anio !== b.anio ? a.anio - b.anio : a.semana - b.semana
        );

        filas.forEach(sem => {
            const tr = document.createElement('tr');
            let total = 0;

            tr.innerHTML = `
                <td class="col-semana">${sem.semana}</td>
                <td>${sem.anio}</td>
                <td>${sem.mes}</td>
                <td>${sem.n_cart || 0}</td>
                <td class="col-pollos">${this.fmt(sem.pollos_semana)}</td>
            `;

            datos.granjas.forEach(g => {
                const td = document.createElement('td');
                td.className = 'col-granja';
                const v = sem.granjas[g.granja_completa] || 0;
                td.textContent = v > 0 ? this.fmt(v) : '';
                total += v;
                tr.appendChild(td);
            });

            datos.granjas.forEach(g => {
                const td = document.createElement('td');
                td.className = 'col-fecha';
                td.textContent = sem.fechas[g.granja_completa] || '';
                tr.appendChild(td);
            });

            const tdT = document.createElement('td');
            tdT.className = 'col-total';
            tdT.textContent = this.fmt(total);
            tr.appendChild(tdT);

            tableBody.appendChild(tr);
        });

        // Inicializar DataTable con scrolling virtual
        this.dataTableInstances.tablaReporte = $(tabla).DataTable({
            paging: true,
            pageLength: 25,
            lengthMenu: [[10, 25, 50, 100, -1], [10, 25, 50, 100, "Todos"]],
            searching: true,
            ordering: true,
            info: true,
            autoWidth: false,
            // language: { url: '//cdn.datatables.net/plug-ins/1.13.7/i18n/es-ES.json' }, // Deshabilitado por error CORS
            scrollY: 'calc(100vh - 280px)',
            scrollX: true,
            scrollCollapse: true,
            order: [[0, 'asc']]
        });
    }

    /** Panel IZQUIERDO — calendario detallado por día (server-side) */
    async renderCalendario(datos) {
        const tabla = document.getElementById('tablaCalendario');
        const empty = document.getElementById('emptyCalendario');
        const body  = document.getElementById('bodyCalendario');

        // Validar que los elementos existan
        if (!tabla || !empty || !body) {
            console.error('Elementos del DOM no encontrados en renderCalendario');
            return;
        }

        // Destruir DataTable existente si existe (verificar con jQuery también)
        if ($.fn.DataTable.isDataTable('#tablaCalendario')) {
            $('#tablaCalendario').DataTable().destroy();
        }
        if (this.dataTableInstances.tablaCalendario) {
            this.dataTableInstances.tablaCalendario = null;
        }

        // Limpiar cuerpo completamente
        body.innerHTML = '';

        try {
            console.log('Cargando calendario para proyección:', this.proyeccionActual);
            
            // Cargar datos de fechaproy
            const response = await this.service.obtenerFechaProy(this.proyeccionActual);
            
            console.log('Respuesta fechaproy:', response);
            
            if (!response || !response.success || !response.data || response.data.length === 0) {
                tabla.style.display = 'none';
                empty.style.display = 'flex';
                const emptySpan = empty.querySelector('span');
                if (emptySpan) {
                    emptySpan.textContent = 'No hay datos de calendario para esta proyección';
                }
                console.log('Sin datos de calendario');
                return;
            }

            tabla.style.display = 'table';
            empty.style.display = 'none';

            // Guardar fechas min/max para el filtro de recalcular
            const fechas = response.data.map(row => row.fecha).sort();
            this.fechaMinCalendario = fechas[0];
            this.fechaMaxCalendario = fechas[fechas.length - 1];

            // Renderizar filas con input editable para cargas
            response.data.forEach(row => {
                const tr = document.createElement('tr');
                tr.innerHTML = `
                    <td class="text-center">${row.semana || ''}</td>
                    <td class="text-center">${this.formatearFecha(row.fecha)}</td>
                    <td class="text-center">
                        <input type="number" 
                               class="input-cargas" 
                               value="${row.cargas || 1}" 
                               min="1" 
                               max="10"
                               data-fecha="${row.fecha}"
                               data-original="${row.cargas || 1}"
                               style="width:50px; text-align:center; border:1px solid #ddd; border-radius:4px; padding:2px 4px;">
                    </td>
                `;
                body.appendChild(tr);
            });

            // Agregar evento de cambio a los inputs
            body.querySelectorAll('.input-cargas').forEach(input => {
                input.addEventListener('change', async (e) => {
                    const fecha = e.target.dataset.fecha;
                    const cargas = parseInt(e.target.value) || 1;
                    const original = parseInt(e.target.dataset.original) || 1;
                    
                    if (cargas !== original) {
                        e.target.style.backgroundColor = '#fff3cd';
                        e.target.style.borderColor = '#ffc107';
                        
                        // Actualizar en la base de datos
                        try {
                            const result = await this.service.actualizarCargas(this.proyeccionActual, fecha, cargas);
                            if (result.success) {
                                e.target.dataset.original = cargas;
                                e.target.style.backgroundColor = '#d4edda';
                                e.target.style.borderColor = '#28a745';
                                
                                // NOTA: No recalcular automáticamente. El usuario debe usar el botón "Recalcular".
                                // await this.service.recalcular(this.proyeccionActual);
                                
                                // Recargar tabla de detalle para mostrar cambios
                                await this.cargarDatos();
                                
                                setTimeout(() => {
                                    e.target.style.backgroundColor = '';
                                    e.target.style.borderColor = '#ddd';
                                }, 1500);
                            }
                        } catch (err) {
                            console.error('Error al actualizar cargas:', err);
                            e.target.value = original;
                            e.target.style.backgroundColor = '#f8d7da';
                            e.target.style.borderColor = '#dc3545';
                        }
                    }
                });
            });

            // Inicializar DataTable simple (sin server-side)
            this.dataTableInstances.tablaCalendario = $(tabla).DataTable({
                paging: true,
                pageLength: 20,
                lengthMenu: [[10, 20, 50, 100, -1], [10, 20, 50, 100, 'Todos']],
                searching: true,
                ordering: true,
                info: true,
                autoWidth: false,
                // language: { url: '//cdn.datatables.net/plug-ins/1.13.7/i18n/es-ES.json' }, // Deshabilitado por error CORS
                scrollY: 'calc(100vh - 280px)',
                scrollCollapse: true,
                order: [[1, 'asc']]  // Ordenar por FECHA (columna 1) de menor a mayor
            });

        } catch (error) {
            console.error('Error al cargar calendario:', error);
            console.error('Mensaje:', error.message);
            console.error('Stack:', error.stack);
            tabla.style.display = 'none';
            empty.style.display = 'flex';
            const emptySpan = empty.querySelector('span');
            if (emptySpan) {
                emptySpan.textContent = 'Error al cargar el calendario: ' + (error.message || 'Error desconocido');
            }
        }
    }

    /**
     * Formatea fecha a formato legible
     */
    formatearFecha(fecha) {
        if (!fecha || fecha === '0000-00-00') return '';
        const d = new Date(fecha + 'T00:00:00');
        const dia = String(d.getDate()).padStart(2, '0');
        const mes = String(d.getMonth() + 1).padStart(2, '0');
        const anio = d.getFullYear();
        return `${anio}/${mes}/${dia}`;
    }

    /** Panel DERECHO — resumen oferta/demanda (server-side) */
    renderResumenPanel(datos) {
        const tabla = document.getElementById('tablaResumen');
        const empty = document.getElementById('emptyResumen');
        const body  = document.getElementById('bodyResumen');

        // Validar que los elementos existan
        if (!tabla || !empty || !body) {
            console.error('Elementos del DOM no encontrados en renderResumenPanel');
            return;
        }

        // Destruir DataTable existente si existe (verificar con jQuery también)
        if ($.fn.DataTable.isDataTable('#tablaResumen')) {
            $('#tablaResumen').DataTable().destroy();
        }
        if (this.dataTableInstances.tablaResumen) {
            this.dataTableInstances.tablaResumen = null;
        }

        // Limpiar cuerpo
        body.innerHTML = '';
        tabla.style.display = 'table';
        empty.style.display = 'none';

        // Inicializar DataTable con server-side processing
        const apiUrl = `${window.apiBaseUrl}/api/cargapollo/resumen-paginated?proyeccion=${encodeURIComponent(this.proyeccionActual)}`;
        
        this.dataTableInstances.tablaResumen = $(tabla).DataTable({
            serverSide: true,
            processing: true,
            ajax: {
                url: apiUrl,
                type: 'GET',
                dataSrc: 'data',
                error: function(xhr, error, code) {
                    console.error('Error al cargar resumen:', error);
                }
            },
            columns: [
                { data: 0 },
                { data: 1, className: 'col-semana' },
                { data: 2, className: 'text-right' },
                { data: 3, className: 'text-right' },
                { 
                    data: 4, 
                    className: 'text-right',
                    render: function(data, type, row) {
                        // Extraer número de la cadena formateada
                        const num = parseFloat(data.replace(/\./g, '').replace(',', '.'));
                        const className = num >= 0 ? 'dif-pos' : 'dif-neg';
                        const sign = num >= 0 ? '+' : '';
                        return `<span class="${className}">${sign}${data}</span>`;
                    }
                }
            ],
            paging: true,
            pageLength: 15,
            lengthMenu: [[10, 15, 25, 50], [10, 15, 25, 50]],
            searching: true,
            ordering: true,
            info: true,
            autoWidth: false,
            // language: { // Deshabilitado por error CORS
            //     url: '//cdn.datatables.net/plug-ins/1.13.7/i18n/es-ES.json',
            //     processing: 'Cargando datos...'
            // },
            scrollY: 'calc(100vh - 280px)',
            scrollCollapse: true,
            order: [[0, 'asc'], [1, 'asc']]
        });
    }

    exportarExcel() {
        if (!this.proyeccionActual) return;
        this.showInfo('Generando Excel...');
        this.service.exportarExcel(this.proyeccionActual);
    }

    // Destruir todas las DataTables
    destroyAllDataTables() {
        // Destruir usando jQuery DataTable API para mayor confiabilidad
        const tablesIds = ['#tablaReporte', '#tablaCalendario', '#tablaResumen'];
        tablesIds.forEach(tableId => {
            if ($.fn.DataTable.isDataTable(tableId)) {
                $(tableId).DataTable().destroy();
            }
        });
        
        // Limpiar referencias
        Object.keys(this.dataTableInstances).forEach(key => {
            this.dataTableInstances[key] = null;
        });
    }

    setupEventListeners() {
        document.getElementById('btnCargarDatos')
            ?.addEventListener('click', () => this.cargarDatos());
        document.getElementById('btnExportarExcel')
            ?.addEventListener('click', () => this.exportarExcel());
        document.getElementById('btnCopiarCalendario')
            ?.addEventListener('click', () => this.copiarCalendario());
    }

    /**
     * Copia el calendario de otra proyección a la proyección actual
     */
    async copiarCalendario() {
        if (!this.proyeccionActual) {
            this.showWarning('Selecciona una proyección primero');
            return;
        }

        // Obtener todas las proyecciones desde el backend para tener indicador
        try {
            this.showLoading(true);
            const response = await this.service.listarProyecciones();
            this.showLoading(false);

            if (!response.success || !response.data || response.data.length === 0) {
                this.showWarning('No hay proyecciones disponibles');
                return;
            }

            // Filtrar proyecciones (excluir la actual)
            const proyecciones = response.data.filter(p => p.nombre !== this.proyeccionActual);

            if (proyecciones.length === 0) {
                this.showWarning('No hay otras proyecciones disponibles para copiar');
                return;
            }

            // Construir options y buscar la proyección válida
            const options = {};
            let proyeccionValida = null;

            proyecciones.forEach(p => {
                const esValida = String(p.indicador || '').trim().toUpperCase() === 'VALIDO';
                const badge = esValida ? ' ✓ VALIDO' : '';
                options[p.nombre] = p.nombre + badge;
                
                if (esValida && !proyeccionValida) {
                    proyeccionValida = p.nombre; // Guardar la primera válida encontrada
                }
            });

            const { value: proyeccionOrigen } = await Swal.fire({
                title: 'Copiar Calendario',
                text: 'Seleccione la proyección origen desde donde copiar el calendario',
                input: 'select',
                inputOptions: options,
                inputValue: proyeccionValida || '', // Pre-seleccionar la válida
                inputPlaceholder: 'Seleccione una proyección...',
                showCancelButton: true,
                confirmButtonText: 'Copiar',
                cancelButtonText: 'Cancelar',
                confirmButtonColor: '#16a34a',
                customClass: {
                    container: 'swal-container-scroll',
                    popup: 'swal-popup-scroll'
                },
                didOpen: () => {
                    const selectElement = Swal.getInput();
                    if (selectElement) {
                        selectElement.size = 8;
                        selectElement.style.height = 'auto';
                        selectElement.style.minHeight = '200px';
                        selectElement.style.maxHeight = '300px';
                        selectElement.style.overflowY = 'auto';
                        selectElement.style.padding = '8px';
                        selectElement.style.border = '1px solid #d1d5db';
                        selectElement.style.borderRadius = '8px';

                        // Estilizar opciones válidas en verde
                        Array.from(selectElement.options).forEach(option => {
                            if (option.value) {
                                const proyeccion = proyecciones.find(p => p.nombre === option.value);
                                if (proyeccion) {
                                    const esValida = String(proyeccion.indicador || '').trim().toUpperCase() === 'VALIDO';
                                    if (esValida) {
                                        option.style.color = '#16a34a';
                                        option.style.fontWeight = '600';
                                    }
                                }
                            }
                        });
                    }
                },
                inputValidator: (value) => {
                    if (!value) {
                        return 'Debes seleccionar una proyección';
                    }
                }
            });

            if (proyeccionOrigen) {
                const confirmado = await Swal.fire({
                    title: '¿Estás seguro?',
                    html: `
                        <p>Se eliminará el calendario actual (si existe) y se copiará el calendario de:</p>
                        <p class="font-bold text-green-600 my-2">${proyeccionOrigen}</p>
                        <p class="text-sm text-gray-500">Esto incluye fechas, semanas y cargas de pollos.</p>
                    `,
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonText: 'Sí, copiar',
                    cancelButtonText: 'Cancelar',
                    confirmButtonColor: '#16a34a'
                });

                if (confirmado.isConfirmed) {
                    try {
                        this.showLoading(true);
                        const response = await this.service.copiarCalendario(this.proyeccionActual, proyeccionOrigen);
                        
                        if (response.success) {
                            const msg = `Calendario copiado correctamente: ${response.data.fechas_copiadas || 0} fechas`;
                            this.showSuccess(msg);
                            // Recargar datos para mostrar el calendario copiado
                            await this.cargarDatos();
                        } else {
                            this.showError(response.message || 'Error al copiar calendario');
                        }
                    } catch (error) {
                        console.error('Error al copiar calendario:', error);
                        this.showError('Error de conexión');
                    } finally {
                        this.showLoading(false);
                    }
                }
            }
        } catch (error) {
            console.error('Error al cargar proyecciones:', error);
            this.showError('Error al cargar proyecciones');
            this.showLoading(false);
        }
    }

    /**
     * Recalcula el calendario completo
     * Toma las cargas definidas por fecha y redistribuye las granjas
     */
    async recalcular() {
        if (!this.proyeccionActual) {
            this.showWarning('Primero selecciona una proyección');
            return false;
        }

        // Obtener fechas por defecto del calendario
        const fechaDesdeDefault = this.fechaMinCalendario || '';
        const fechaHastaDefault = this.fechaMaxCalendario || '';
        
        // Formatear fechas para mostrar en el mensaje
        const fechaDesdeFormateada = fechaDesdeDefault ? this.formatearFecha(fechaDesdeDefault) : '';
        const fechaHastaFormateada = fechaHastaDefault ? this.formatearFecha(fechaHastaDefault) : '';
        const rangoTexto = (fechaDesdeFormateada && fechaHastaFormateada) 
            ? `<div style="margin-top:8px; padding:8px; background:#f3f4f6; border-radius:4px; font-size:12px;">
                <strong>Rango actual:</strong> ${fechaDesdeFormateada} al ${fechaHastaFormateada}
               </div>`
            : '';

        const result = await Swal.fire({
            icon: 'question',
            title: '¿Recalcular calendario?',
            html: `
                <p style="margin-bottom:12px;">
                    <strong>⚠️ Se redistribuirán TODAS las granjas desde cero</strong>
                </p>
                <p style="margin-bottom:16px; font-size:13px; color:#666;">
                    Cualquier cambio en las cargas afecta toda la proyección completa porque 
                    las granjas se asignan secuencialmente. Este proceso puede tardar unos segundos.
                </p>
                ${rangoTexto}
                <div style="text-align:left; margin:0 auto; max-width:300px; margin-top:16px;">
                    <label style="font-weight:600; font-size:13px; margin-bottom:4px; display:block;">Fecha Desde (referencia):</label>
                    <input type="date" id="fechaDesde" class="swal2-input" value="${fechaDesdeDefault}" style="width:100%; margin-top:0;">
                    <label style="font-weight:600; font-size:13px; margin-bottom:4px; display:block; margin-top:12px;">Fecha Hasta (referencia):</label>
                    <input type="date" id="fechaHasta" class="swal2-input" value="${fechaHastaDefault}" style="width:100%; margin-top:0;">
                </div>
            `,
            showCancelButton: true,
            confirmButtonText: 'Sí, recalcular',
            cancelButtonText: 'Cancelar',
            confirmButtonColor: '#6d28d9',
            preConfirm: () => {
                const fechaDesde = document.getElementById('fechaDesde').value;
                const fechaHasta = document.getElementById('fechaHasta').value;
                
                if (!fechaDesde || !fechaHasta) {
                    Swal.showValidationMessage('Debes seleccionar ambas fechas');
                    return false;
                }
                
                if (fechaDesde > fechaHasta) {
                    Swal.showValidationMessage('La fecha desde no puede ser mayor que la fecha hasta');
                    return false;
                }
                
                return { fechaDesde, fechaHasta };
            }
        });

        if (!result.isConfirmed || !result.value) {
            return false; // Usuario canceló
        }

        try {
            this.showLoading(true);
            
            const { fechaDesde, fechaHasta } = result.value;
            const response = await this.service.recalcular(this.proyeccionActual, fechaDesde, fechaHasta);
            
            if (response.success) {
                this.showSuccess(`✓ Calendario recalculado completamente. ${response.data?.granjas_procesadas || 0} granjas procesadas en ${response.data?.fechas_procesadas || 0} fechas.`);
                
                // Recargar todos los datos
                await this.cargarDatos();
                return true; // Éxito
            } else {
                this.showError(response.message || 'Error al recalcular');
                return false;
            }
        } catch (error) {
            console.error('Error al recalcular:', error);
            this.showError('Error al recalcular el calendario');
            return false;
        } finally {
            this.showLoading(false);
        }
    }

    // Utilidades
    fmt(num) {
        if (!num && num !== 0) return '';
        return Number(num).toLocaleString('es-PE');
    }

    showLoading(show) {
        const el = document.getElementById('loadingOverlay');
        if (el) el.style.display = show ? 'flex' : 'none';
    }

    showSuccess(msg) {
        Swal.fire({ icon: 'success', title: 'Éxito', text: msg, timer: 2000, showConfirmButton: false });
    }
    showError(msg) {
        Swal.fire({ icon: 'error', title: 'Error', text: msg });
    }
    showWarning(msg) {
        Swal.fire({ icon: 'warning', title: 'Advertencia', text: msg });
    }
    showInfo(msg) {
        Swal.fire({ icon: 'info', title: 'Información', text: msg, timer: 2000, showConfirmButton: false });
    }

    /**
     * Descargar calendario completo (todos los registros, no solo los paginados)
     */
    async descargarCalendarioCompleto() {
        try {
            if (!this.proyeccionActual) {
                this.showWarning('Selecciona una proyección primero');
                return;
            }

            // Mostrar loading
            this.showLoading(true);

            // Obtener TODOS los datos del calendario desde el backend
            const response = await this.service.obtenerFechaProy(this.proyeccionActual);

            this.showLoading(false);

            if (!response || !response.success || !response.data || response.data.length === 0) {
                this.showWarning('No hay datos de calendario para descargar');
                return;
            }

            // Generar contenido del archivo
            let calendario = '# Calendario de Cargas de Pollos\n';
            calendario += '# Formato aceptado: Fecha|Cargas (con fecha en formato YYYY/MM/DD)\n';
            calendario += '# Ejemplo: 2018/12/13|2\n';
            calendario += '# Edita los valores según necesites y luego importa\n';
            calendario += '# ========================================\n\n';

            // Agregar todos los registros
            response.data.forEach(registro => {
                const fecha = registro.fecha || '';
                const cargas = registro.cargas || '1';
                if (fecha) {
                    // Convertir fecha de YYYY-MM-DD a YYYY/MM/DD
                    const fechaExport = fecha.replace(/-/g, '/');
                    calendario += `${fechaExport}|${cargas}\n`;
                }
            });

            // Crear y descargar archivo
            const blob = new Blob([calendario], {type: 'text/plain'});
            const link = document.createElement('a');
            link.href = URL.createObjectURL(blob);
            link.download = `calendario_${this.proyeccionActual}_${new Date().toISOString().split('T')[0]}.txt`;
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);

            this.showSuccess(`Calendario completo descargado (${response.data.length} registros)`);

        } catch (error) {
            console.error('Error al descargar calendario:', error);
            this.showError('Error al descargar el calendario completo');
            this.showLoading(false);
        }
    }
}
