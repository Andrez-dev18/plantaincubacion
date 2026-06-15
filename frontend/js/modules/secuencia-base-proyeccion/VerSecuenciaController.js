/**
 * Controlador para Ver Secuencia Generada (ccosproy)
 */
class VerSecuenciaController {
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
     * Carga los datos de ccosproy
     */
    async cargarDatos() {
        try {
            this.showLoading(true);
            
            const response = await this.service.obtenerDatosProyeccion(this.proyeccion);
            
            if (response.success && response.data) {
                this.renderizarTabla(response.data);
                this.calcularEstadisticas(response.data);
            } else {
                Swal.fire({
                    icon: 'warning',
                    title: 'Sin datos',
                    text: 'No hay datos de secuencia generada. Ejecuta primero "Crear Secuencia"'
                });
            }
        } catch (error) {
            console.error('Error al cargar datos:', error);
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: 'Error al cargar los datos de la secuencia'
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

        const tbody = document.querySelector('#tablaSecuencia tbody');
        tbody.innerHTML = '';

        datos.forEach(row => {
            const tr = document.createElement('tr');
            
            const estadoBadge = row.swac === 'A' 
                ? '<span class="badge badge-active">Activo</span>' 
                : '<span class="badge badge-inactive">Inactivo</span>';

            tr.innerHTML = `
                <td class="font-semibold">${row.campana || '-'}</td>
                <td class="text-center">${row.galpon || '-'}</td>
                <td class="text-center">${estadoBadge}</td>
                <td class="text-center">${row.diasefec || '0'}</td>
                <td class="text-right">${this.formatPercent(row.mortalidad)}</td>
                <td class="text-center">${row.diasdesc || '0'}</td>
                <td class="text-right font-semibold text-green-600">${this.formatNumber(row.pollos)}</td>
                <td class="text-right">${this.formatNumber(row.area)}</td>
                <td class="text-center">${row.densidad || '-'}</td>
            `;
            
            tbody.appendChild(tr);
        });

        // Inicializar DataTable
        this.dataTable = $('#tablaSecuencia').DataTable({
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
                    title: `Secuencia_${this.proyeccion}`,
                    exportOptions: {
                        columns: ':visible'
                    }
                },
                {
                    extend: 'pdf',
                    text: '<i class="fas fa-file-pdf mr-2"></i>PDF',
                    className: 'dt-button',
                    title: `Secuencia_${this.proyeccion}`,
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
            order: [[0, 'asc']],
            columnDefs: [
                { targets: [2, 3, 4, 5, 6, 7, 8], className: 'text-center' }
            ]
        });
    }

    /**
     * Calcula estadísticas
     */
    calcularEstadisticas(datos) {
        const totalRegistros = datos.length;
        const totalPollos = datos.reduce((sum, row) => sum + parseInt(row.pollos || 0), 0);
        const galponesUnicos = new Set(datos.map(row => row.galpon)).size;
        const campanasUnicas = new Set(datos.map(row => row.campana)).size;

        document.getElementById('statTotal').textContent = this.formatNumber(totalRegistros);
        document.getElementById('statPollos').textContent = this.formatNumber(totalPollos);
        document.getElementById('statGalpones').textContent = galponesUnicos;
        document.getElementById('statCampanas').textContent = campanasUnicas;
    }

    // Utilidades
    formatNumber(num) {
        if (!num) return '0';
        return Number(num).toLocaleString('es-PE');
    }

    formatPercent(num) {
        if (!num) return '0%';
        return (Number(num) * 100).toFixed(2) + '%';
    }

    showLoading(show) {
        const overlay = document.getElementById('loadingOverlay');
        if (overlay) {
            overlay.style.display = show ? 'flex' : 'none';
        }
    }
}
