/**
 * Controlador para Ver Calendario (fechaproy)
 */
class VerCalendarioController {
    constructor() {
        this.service = new SecuenciaBaseProyeccionService();
        this.proyeccion = this.obtenerProyeccionDeURL();
        this.dataTable = null;
        
        this.init();
    }

    async init() {
        if (!this.proyeccion) {
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: 'No se especificó una proyección'
            }).then(() => {
                window.history.back();
            });
            return;
        }

        document.getElementById('proyeccionTitle').textContent = `Proyección: ${this.proyeccion}`;
        await this.cargarDatos();
    }

    /**
     * Obtiene la proyección desde la URL
     */
    obtenerProyeccionDeURL() {
        const params = new URLSearchParams(window.location.search);
        return params.get('proyeccion');
    }

    /**
     * Carga los datos de fechaproy
     */
    async cargarDatos() {
        try {
            this.showLoading(true);
            
            const response = await this.service.obtenerCalendario(this.proyeccion);
            
            if (response.success && response.data) {
                this.renderizarTabla(response.data);
                this.calcularEstadisticas(response.data);
            } else {
                Swal.fire({
                    icon: 'warning',
                    title: 'Sin datos',
                    text: 'No hay datos de calendario. Ejecuta primero "Crear Calendario"'
                });
            }
        } catch (error) {
            console.error('Error al cargar datos:', error);
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: 'Error al cargar los datos del calendario'
            });
        } finally {
            this.showLoading(false);
        }
    }

    /**
     * Renderiza la tabla con DataTables
     */
    renderizarTabla(datos) {
        if (this.dataTable) {
            this.dataTable.destroy();
        }

        const tbody = document.querySelector('#tablaCalendario tbody');
        tbody.innerHTML = '';

        datos.forEach(row => {
            const tr = document.createElement('tr');
            
            // Calcular días totales
            const fecaqp = new Date(row.fecaqp);
            const fecdespo = new Date(row.fecdespo);
            const diasTotales = Math.ceil((fecdespo - fecaqp) / (1000 * 60 * 60 * 24));

            tr.innerHTML = `
                <td class="font-semibold">${row.campana || '-'}</td>
                <td class="text-center">${row.galpon || '-'}</td>
                <td class="text-center"><span class="date-badge">${this.formatFecha(row.fecaqp)}</span></td>
                <td class="text-center"><span class="date-badge green">${this.formatFecha(row.fecliqui)}</span></td>
                <td class="text-center"><span class="date-badge red">${this.formatFecha(row.feclima)}</span></td>
                <td class="text-center"><span class="date-badge yellow">${this.formatFecha(row.fecdespo)}</span></td>
                <td class="text-center font-semibold">${diasTotales} días</td>
            `;
            
            tbody.appendChild(tr);
        });

        // Inicializar DataTable
        this.dataTable = $('#tablaCalendario').DataTable({
            responsive: true,
            language: {
                url: 'https://cdn.datatables.net/plug-ins/1.13.6/i18n/es-ES.json'
            },
            dom: 'Bfrtip',
            buttons: [
                {
                    extend: 'excel',
                    text: '<i class="fas fa-file-excel mr-2"></i>Excel',
                    className: 'dt-button',
                    title: `Calendario_${this.proyeccion}`,
                    exportOptions: {
                        columns: ':visible'
                    }
                },
                {
                    extend: 'pdf',
                    text: '<i class="fas fa-file-pdf mr-2"></i>PDF',
                    className: 'dt-button',
                    title: `Calendario_${this.proyeccion}`,
                    orientation: 'landscape',
                    exportOptions: {
                        columns: ':visible'
                    }
                },
                {
                    extend: 'print',
                    text: '<i class="fas fa-print mr-2"></i>Imprimir',
                    className: 'dt-button'
                }
            ],
            pageLength: 25,
            order: [[2, 'asc']],
            columnDefs: [
                { targets: '_all', className: 'text-center' }
            ]
        });
    }

    /**
     * Calcula estadísticas
     */
    calcularEstadisticas(datos) {
        const totalFechas = datos.length;
        const galponesUnicos = new Set(datos.map(row => row.galpon)).size;
        
        // Ordenar por fecha
        const fechasOrdenadas = datos.map(row => new Date(row.fecaqp)).sort((a, b) => a - b);
        const primeraFecha = fechasOrdenadas.length > 0 ? this.formatFechaCorta(fechasOrdenadas[0]) : '-';
        const ultimaFecha = fechasOrdenadas.length > 0 ? this.formatFechaCorta(fechasOrdenadas[fechasOrdenadas.length - 1]) : '-';

        document.getElementById('statTotal').textContent = totalFechas;
        document.getElementById('statGalpones').textContent = galponesUnicos;
        document.getElementById('statPrimeraFecha').textContent = primeraFecha;
        document.getElementById('statUltimaFecha').textContent = ultimaFecha;
    }

    // Utilidades
    formatFecha(fecha) {
        if (!fecha || fecha === '0000-00-00') return '-';
        const [anio, mes, dia] = fecha.split('-');
        return `${dia}/${mes}/${anio}`;
    }

    formatFechaCorta(fecha) {
        const date = new Date(fecha);
        const dia = String(date.getDate()).padStart(2, '0');
        const mes = String(date.getMonth() + 1).padStart(2, '0');
        const anio = date.getFullYear();
        return `${dia}/${mes}/${anio}`;
    }

    showLoading(show) {
        const overlay = document.getElementById('loadingOverlay');
        if (overlay) {
            overlay.style.display = show ? 'flex' : 'none';
        }
    }
}

