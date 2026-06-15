<?php
session_start();
if (empty($_SESSION['active'])) {
    header('Location: ../../login.php');
    exit();
}

// Ruta relativa a la conexión
include_once '../../../conexion_grs_joya/conexion.php';
$conexion = conectar_joya();
if (!$conexion) {
    die("Error de conexión: " . mysqli_connect_error());
}
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Reporte de Galpones</title>

    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Font Awesome -->
    <link rel="stylesheet" href="../../assets/fontawesome/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <style>
        body {
            background: #f8f9fa;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
        }

        .btn-primary {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            box-shadow: 0 4px 6px rgba(16, 185, 129, 0.3);
            border: none;
            padding: 0.625rem 1.5rem;
            font-size: 0.875rem;
            font-weight: 600;
            color: white;
            border-radius: 0.75rem;
            transition: all 0.2s ease;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }

        .btn-primary:hover {
            background: linear-gradient(135deg, #059669 0%, #047857 100%);
            transform: translateY(-2px);
            box-shadow: 0 6px 8px rgba(16, 185, 129, 0.4);
        }

        .btn-secondary {
            background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
            box-shadow: 0 4px 6px rgba(59, 130, 246, 0.3);
            border: none;
            padding: 0.625rem 1.5rem;
            font-size: 0.875rem;
            font-weight: 600;
            color: white;
            border-radius: 0.75rem;
            transition: all 0.2s ease;
            cursor: pointer;
        }

        .btn-secondary:hover {
            background: linear-gradient(135deg, #2563eb 0%, #1d4ed8 100%);
            transform: translateY(-2px);
            box-shadow: 0 6px 8px rgba(59, 130, 246, 0.4);
        }

        .form-control {
            width: 100%;
            padding: 0.625rem 1rem;
            border: 1px solid #d1d5db;
            border-radius: 0.75rem;
            font-size: 0.875rem;
            transition: all 0.2s;
        }

        .form-control:focus {
            outline: none;
            border-color: #3b82f6;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.2);
        }

        .card {
            background: white;
            border-radius: 1rem;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
            border: 1px solid #e5e7eb;
            overflow: hidden;
        }
    </style>
</head>

<body class="bg-light">

<div class="container-fluid py-4">
    <!-- CARD FILTROS PLEGABLE -->
    <div class="mx-5 mb-6 bg-white border border-gray-200 rounded-2xl shadow-sm overflow-hidden">
        <!-- HEADER -->
        <button type="button" onclick="toggleFiltros()"
            class="w-full flex items-center justify-between px-6 py-4 bg-gray-50 hover:bg-gray-100 transition">
            <div class="flex items-center gap-2">
                <span class="text-lg">🔎</span>
                <h3 class="text-base font-semibold text-gray-800">
                    Filtros de búsqueda
                </h3>
            </div>
            <!-- ICONO -->
            <svg id="iconoFiltros" class="w-5 h-5 text-gray-600 transition-transform duration-300"
                fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
            </svg>
        </button>

        <!-- CONTENIDO PLEGABLE -->
        <div id="contenidoFiltros" class="px-6 pb-6 pt-4">
            <!-- GRID DE FILTROS -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <!-- Fecha inicio -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Fecha Desde <span class="text-red-500">*</span></label>
                    <input type="date" id="filtroFechaInicio" required
                        class="w-full px-3 py-2 text-sm rounded-lg border border-gray-300 form-control">
                </div>

                <!-- Fecha fin -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Fecha Hasta <span class="text-red-500">*</span></label>
                    <input type="date" id="filtroFechaFin" required
                        class="w-full px-3 py-2 text-sm rounded-lg border border-gray-300 form-control">
                </div>
            </div>

            <!-- GRANJA -->
            <div class="mt-6">
                <label class="block text-sm font-medium text-gray-700 mb-1">Granja (Opcional)</label>
                <select id="filtroGranja" class="w-full px-3 py-2 text-sm rounded-lg border border-gray-300 form-control">
                    <option value="">Todas las granjas</option>
                    <!-- Se cargarán dinámicamente -->
                </select>
            </div>

            <!-- FORMATO -->
            <div class="mt-6">
                <label class="block text-sm font-medium text-gray-700 mb-1">Formato de Reporte</label>
                <select id="filtroFormato" class="w-full px-3 py-2 text-sm rounded-lg border border-gray-300 form-control">
                    <option value="pdf">PDF</option>
                    <option value="excel">Excel</option>
                </select>
            </div>

            <!-- ACCIONES -->
            <div class="mt-6 flex flex-wrap justify-end gap-4">
                <button type="button" id="btnGenerarReporte"
                    class="px-6 py-2.5 rounded-lg bg-green-600 text-white hover:bg-green-700 font-medium">
                    <i class="fas fa-file-pdf mr-2"></i> Generar Reporte
                </button>
                <button type="button" id="btnLimpiarFiltros"
                    class="px-6 py-2.5 rounded-lg border border-gray-300 text-gray-700 bg-gray-100 hover:bg-gray-200 font-medium">
                    <i class="fas fa-redo mr-2"></i> Limpiar
                </button>
            </div>
        </div>
    </div>
</div>

    <script>
        let granjasData = [];

        // Toggle filtros
        function toggleFiltros() {
            const contenido = document.getElementById('contenidoFiltros');
            const icono = document.getElementById('iconoFiltros');
            contenido.classList.toggle('hidden');
            icono.classList.toggle('rotate-180');
        }

        // Cargar granjas disponibles
        async function cargarGranjas() {
            try {
                const response = await fetch('../routers/api.php?endpoint=/galpones/granjas');
                if (!response.ok) throw new Error('Error al cargar granjas');
                
                const data = await response.json();
                granjasData = Array.isArray(data) ? data : (data.data || []);
                
                const select = document.getElementById('filtroGranja');
                select.innerHTML = '<option value="">Todas las granjas</option>';
                
                granjasData.forEach(granja => {
                    const option = document.createElement('option');
                    option.value = granja.tcencos || granja.id_granja || granja.codigo;
                    option.textContent = `${granja.tcencos || granja.codigo} - ${granja.nombre || granja.tnomcencos}`;
                    select.appendChild(option);
                });
            } catch (error) {
                console.error('Error al cargar granjas:', error);
                Swal.fire('Error', 'No se pudieron cargar las granjas', 'error');
            }
        }

        // Limpiar filtros
        function limpiarFiltros() {
            document.getElementById('filtroFechaInicio').value = '';
            document.getElementById('filtroFechaFin').value = '';
            document.getElementById('filtroGranja').value = '';
            document.getElementById('filtroFormato').value = 'pdf';
        }

        // Generar reporte
        function generarReporte() {
            const fechaInicio = document.getElementById('filtroFechaInicio').value;
            const fechaFin = document.getElementById('filtroFechaFin').value;
            const formato = document.getElementById('filtroFormato').value;
            const granja = document.getElementById('filtroGranja').value;

            if (!fechaInicio || !fechaFin) {
                Swal.fire('Validación', 'Debe seleccionar las fechas de inicio y fin', 'warning');
                return;
            }

            if (fechaInicio > fechaFin) {
                Swal.fire('Validación', 'La fecha inicio debe ser menor o igual a la fecha fin', 'warning');
                return;
            }

            // Construir parámetros
            const params = new URLSearchParams();
            params.append('fecha_desde', fechaInicio);
            params.append('fecha_hasta', fechaFin);
            
            if (granja) {
                params.append('id_granja', granja);
            }

            // Determinar endpoint según formato
            let endpoint = '';
            if (formato === 'pdf') {
                endpoint = '../routers/api.php?endpoint=/galpones/exportar-pdf';
            } else {
                endpoint = '../routers/api.php?endpoint=/galpones/exportar-excel';
            }

            // Abrir reporte en nueva ventana
            const url = `${endpoint}&${params.toString()}`;
            window.open(url, '_blank');
        }

        // Event listeners
        document.getElementById('btnGenerarReporte').addEventListener('click', generarReporte);
        document.getElementById('btnLimpiarFiltros').addEventListener('click', limpiarFiltros);

        // Defaults: fechas del mes actual
        (function setFechasMesActualPorDefecto() {
            const inputInicio = document.getElementById('filtroFechaInicio');
            const inputFin = document.getElementById('filtroFechaFin');
            if (!inputInicio || !inputFin) return;

            if (inputInicio.value && inputFin.value) return;

            const hoy = new Date();
            const yyyy = hoy.getFullYear();
            const mm = String(hoy.getMonth() + 1).padStart(2, '0');
            const dd = String(hoy.getDate()).padStart(2, '0');

            const primerDiaMes = `${yyyy}-${mm}-01`;
            const hoyStr = `${yyyy}-${mm}-${dd}`;

            if (!inputInicio.value) inputInicio.value = primerDiaMes;
            if (!inputFin.value) inputFin.value = hoyStr;
        })();

        // Cargar granjas al iniciar
        cargarGranjas();
    </script>

    <!-- FOOTER -->
    <div class="text-center mt-12 mb-4">
        <p class="text-gray-500 text-sm">
            Sistema desarrollado para <strong>Granja Rinconada Del Sur S.A.</strong> -
            © <span id="currentYear"></span>
        </p>
    </div>

    <script>
        // Establecer año actual en el footer
        document.getElementById('currentYear').textContent = new Date().getFullYear();
    </script>

</body>

</html>
