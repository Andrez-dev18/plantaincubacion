/**
 * SimulacionEscenariosController
 * 
 * Controlador para el módulo de Simulación de Escenarios de Carga
 * Maneja la interfaz, cálculos locales y comunicación con el backend
 */
class SimulacionEscenariosController {
    
    constructor() {
        this.service = new SimulacionEscenariosService();
        this.escenarios = [];
        this.proyecciones = [];
        
        // Constantes
        this.MORTALIDAD_DEFAULT = 0.0361;
        this.PORCENTAJES_ZONAS = {
            'La Joya': 63.8,
            'Mollendo': 30.4,
            'San Lucas': 5.8
        };
        
        this.initElements();
        this.initEventListeners();
        this.cargarProyecciones();
    }

    /**
     * Inicializar referencias DOM
     */
    initElements() {
        this.selProyeccion = document.getElementById('selProyeccion');
        this.tbodyEscenarios = document.getElementById('tbodyEscenarios');
        this.resumenComparativo = document.getElementById('resumenComparativo');
        this.tarjetasResumen = document.getElementById('tarjetasResumen');
        this.totalEscenarios = document.getElementById('totalEscenarios');
        this.loadingOverlay = document.getElementById('loadingOverlay');
    }

    /**
     * Configurar event listeners
     */
    initEventListeners() {
        // Los botones están llamando a funciones globales desde el HTML
    }

    /**
     * Cargar lista de proyecciones
     */
    async cargarProyecciones() {
        try {
            this.showLoading(true);
            this.proyecciones = await this.service.obtenerProyecciones();
            
            this.selProyeccion.innerHTML = '<option value="">Seleccione...</option>';
            
            this.proyecciones.forEach(proy => {
                const option = document.createElement('option');
                option.value = proy.nombre;
                option.textContent = proy.nombre;
                this.selProyeccion.appendChild(option);
            });
            
        } catch (error) {
            this.showToast('Error al cargar proyecciones: ' + error.message, 'error');
        } finally {
            this.showLoading(false);
        }
    }

    /**
     * Cargar escenarios de la proyección seleccionada
     */
    async cargarEscenarios() {
        const proyeccion = this.selProyeccion.value;
        
        if (!proyeccion) {
            this.showToast('Seleccione una proyección', 'error');
            return;
        }

        try {
            this.showLoading(true);
            
            let escenarios = await this.service.listarEscenarios(proyeccion);
            
            // Si no hay escenarios, crear los predeterminados
            if (escenarios.length === 0) {
                escenarios = this.crearEscenariosPredeterminados(proyeccion);
            }
            
            this.escenarios = escenarios;
            this.totalEscenarios.textContent = escenarios.length;
            
            this.renderTabla();
            this.renderResumen();
            
            this.showToast('Escenarios cargados correctamente', 'success');
            
        } catch (error) {
            this.showToast('Error al cargar escenarios: ' + error.message, 'error');
        } finally {
            this.showLoading(false);
        }
    }

    /**
     * Crear escenarios predeterminados
     */
    crearEscenariosPredeterminados(proyeccion) {
        const zonasBase = [
            { zona: 'La Joya', porcentaje_zona: 63.8, tpo_crianza_dias: 44.0 },
            { zona: 'Mollendo', porcentaje_zona: 30.4, tpo_crianza_dias: 43.0 },
            { zona: 'San Lucas', porcentaje_zona: 5.8, tpo_crianza_dias: 45.0 }
        ];

        const escenarios = [
            {
                cod_escenario: 'ACTUAL',
                nom_escenario: 'Escenario Actual',
                proyeccion: proyeccion,
                n_galpones_2640: 73,
                n_galpones_1800: 65,
                pollos_por_galpon: 38800,
                cargas_semanales: 13.5,
                tpo_limpieza_dias: 5.0,
                notas: '',
                zonas: JSON.parse(JSON.stringify(zonasBase))
            },
            {
                cod_escenario: 'ESC1',
                nom_escenario: 'Escenario 1',
                proyeccion: proyeccion,
                n_galpones_2640: 73,
                n_galpones_1800: 71,
                pollos_por_galpon: 38800,
                cargas_semanales: 13.5,
                tpo_limpieza_dias: 7.0,
                notas: '',
                zonas: JSON.parse(JSON.stringify(zonasBase))
            },
            {
                cod_escenario: 'ESC2',
                nom_escenario: 'Escenario 2',
                proyeccion: proyeccion,
                n_galpones_2640: 73,
                n_galpones_1800: 71,
                pollos_por_galpon: 38800,
                cargas_semanales: 14.0,
                tpo_limpieza_dias: 7.0,
                notas: '',
                zonas: [
                    { zona: 'La Joya', porcentaje_zona: 63.8, tpo_crianza_dias: 43.0 },
                    { zona: 'Mollendo', porcentaje_zona: 30.4, tpo_crianza_dias: 43.0 },
                    { zona: 'San Lucas', porcentaje_zona: 5.8, tpo_crianza_dias: 44.0 }
                ]
            }
        ];

        // Calcular cada escenario
        escenarios.forEach(e => this.calcularLocalmente(e));

        return escenarios;
    }

    /**
     * Renderizar tabla de escenarios
     */
    renderTabla() {
        const filas = this.obtenerFilas();
        let html = '';

        filas.forEach(fila => {
            // Fila de grupo
            if (fila.grupo) {
                html += `
                    <tr class="bg-gradient-to-r from-blue-600 to-blue-700">
                        <td colspan="6" class="px-4 py-2 font-bold text-white text-xs uppercase tracking-wider border-b border-blue-800">
                            <i class="fas ${fila.icon || 'fa-cog'} mr-2"></i> ${fila.grupo}
                        </td>
                    </tr>`;
                return;
            }

            // Fila de datos
            const celdas = this.escenarios.map((esc, idx) => {
                const val = this.obtenerValor(esc, fila.key);
                const fmt = this.formatearValor(val, fila);

                if (fila.editable) {
                    return `
                        <td class="text-center px-2 py-2 border-b border-r border-gray-100">
                            <input
                                type="number"
                                step="${fila.decimal ? '0.1' : '1'}"
                                value="${val || 0}"
                                data-esc="${idx}"
                                data-key="${fila.key}"
                                onchange="controller.actualizarCampo(${idx}, '${fila.key}', this.value)"
                                class="cell-editable w-full text-center rounded px-2 py-1"
                            />
                        </td>`;
                } else {
                    return `
                        <td class="cell-calculated ${fila.negrita ? 'highlight' : ''} text-center px-2 py-2 border-b border-r border-gray-100" 
                            id="cel_${fila.key}_${idx}">
                            ${fmt}
                        </td>`;
                }
            });

            const labelClass = fila.negrita
                ? 'font-bold text-gray-800'
                : fila.indent
                    ? 'text-gray-600 pl-8'
                    : 'text-gray-700';

            html += `
                <tr class="hover:bg-blue-50 transition-colors ${fila.negrita ? 'bg-amber-50' : ''}">
                    <td class="px-4 py-2 border-b border-r border-gray-200 ${labelClass}">
                        ${fila.indent ? '↳ ' : ''}${fila.label}
                    </td>
                    ${celdas.join('')}
                    <td class="px-4 py-2 border-b border-r border-gray-200 text-gray-600 text-xs">
                        ${fila.unidad || ''}
                    </td>
                    <td class="px-4 py-2 border-b border-gray-200 text-gray-500 text-xs italic">
                        ${fila.nota ? '→ ' + fila.nota : ''}
                    </td>
                </tr>`;
        });

        // FILA DE NOTAS (una por escenario)
        const celdas = this.escenarios.map((esc, idx) => {
            const notasValue = (esc.notas || '').replace(/"/g, '&quot;');
            return `
                <td class="text-center px-2 py-2 border-b border-r border-gray-100">
                    <textarea
                        rows="2"
                        placeholder="Agregar notas..."
                        data-esc="${idx}"
                        onchange="controller.actualizarNotas(${idx}, this.value)"
                        class="w-full text-xs text-left rounded px-2 py-1 border border-gray-300 focus:border-blue-500 focus:ring-1 focus:ring-blue-500 resize-none"
                    >${notasValue}</textarea>
                </td>`;
        });

        html += `
            <tr class="bg-yellow-50 hover:bg-yellow-100 transition-colors">
                <td class="px-4 py-2 border-b border-r border-gray-200 font-semibold text-gray-800">
                    <i class="fas fa-sticky-note mr-2 text-yellow-600"></i> Notas
                </td>
                ${celdas.join('')}
                <td class="px-4 py-2 border-b border-r border-gray-200 text-gray-600 text-xs">
                    Texto
                </td>
                <td class="px-4 py-2 border-b border-gray-200 text-gray-500 text-xs italic">
                    → Observaciones personales
                </td>
            </tr>`;

        this.tbodyEscenarios.innerHTML = html;
    }

    /**
     * Obtener estructura de filas
     */
    obtenerFilas() {
        return [
            { grupo: 'INFRAESTRUCTURA', icon: 'fa-warehouse' },
            { key: 'n_galpones_2640', label: 'N° Galpones 2,640 m²', editable: true, unidad: 'Galpones', nota: '' },
            { key: 'n_galpones_1800', label: 'N° Galpones 1,800 m²', editable: true, unidad: 'Galpones', nota: '' },
            { key: 'total_galpones', label: 'TOTAL Galpones', editable: false, unidad: 'Galpones', nota: '', negrita: true },
            { key: 'area_total_m2', label: 'Área total', editable: false, unidad: 'm²', nota: '' },
            { key: 'total_galp_std_2640', label: 'N° Total Galpones est. 2,640 m²', editable: false, unidad: 'Galpones', nota: '' },

            { grupo: 'PRODUCCIÓN', icon: 'fa-drumstick-bite' },
            { key: 'pollos_por_galpon', label: 'N° pollos criados por galpón', editable: true, unidad: 'Pollos / Galpón', nota: '' },
            { key: 'cargas_semanales', label: 'N° de cargas semanales', editable: true, unidad: 'Galpones / Semana', nota: '', decimal: 1 },
            { key: 'pollos_semana', label: 'N° pollos criados por semana', editable: false, unidad: 'Pollos / Semana', nota: '' },
            { key: 'oferta_pollos_semana', label: 'N° pollos oferta por semana', editable: false, unidad: 'Pollos / Semana', nota: '' },
            { key: 'cargas_diario', label: 'N° de cargas diario', editable: false, unidad: 'Galpones / Día', nota: 'Redondeado a un decimal' },

            { grupo: 'TIEMPOS', icon: 'fa-clock' },
            { key: 'tpo_ciclo_crianza', label: 'Tiempo de ciclo de crianza', editable: false, unidad: 'Días', nota: 'Redondeado a un decimal', negrita: true },
            { key: 'tpo_crianza_ponderado', label: 'Tiempo de crianza', editable: false, unidad: 'Días', nota: 'Redondeado a un decimal', negrita: true },
            { key: 'zona_la_joya', label: 'La Joya (63.8%)', editable: true, unidad: 'Días', nota: 'Redondeado a un decimal', indent: true, decimal: 1 },
            { key: 'zona_mollendo', label: 'Mollendo (30.4%)', editable: true, unidad: 'Días', nota: 'Redondeado a un decimal', indent: true, decimal: 1 },
            { key: 'zona_san_lucas', label: 'San Lucas (5.8%)', editable: true, unidad: 'Días', nota: 'Redondeado a un decimal', indent: true, decimal: 1 },
            { key: 'tpo_descanso_total', label: 'Tiempo de descanso total', editable: false, unidad: 'Días', nota: 'Redondeado a CERO decimales', negrita: true },
            { key: 'tpo_limpieza_dias', label: 'Tiempo de Limpieza y Desinfección', editable: true, unidad: 'Días', nota: 'Redondeado a un decimal', decimal: 1 },
            { key: 'tpo_descanso_efectivo', label: 'Tiempo de descanso efectivo', editable: false, unidad: 'Días', nota: 'Redondeado a CERO decimales', negrita: true },
        ];
    }

    /**
     * Obtener valor de un escenario según la clave
     */
    obtenerValor(esc, key) {
        const zonaMap = {
            'zona_la_joya': 'La Joya',
            'zona_mollendo': 'Mollendo',
            'zona_san_lucas': 'San Lucas'
        };

        if (zonaMap[key]) {
            const zona = (esc.zonas || []).find(z => z.zona === zonaMap[key]);
            return zona ? zona.tpo_crianza_dias : 0;
        }

        return esc[key] ?? 0;
    }

    /**
     * Formatear valores para visualización
     */
    formatearValor(val, fila) {
        if (val === null || val === undefined) return '—';
        const num = parseFloat(val);
        if (isNaN(num)) return val;

        const grandesKeys = ['area_total_m2', 'pollos_por_galpon', 'pollos_semana', 'oferta_pollos_semana'];
        if (grandesKeys.includes(fila?.key)) {
            return num.toLocaleString('es-PE');
        }

        if (fila?.decimal) return num.toFixed(fila.decimal);
        return num % 1 === 0 ? num.toLocaleString('es-PE') : num.toFixed(1);
    }

    /**
     * Actualizar campo y recalcular
     */
    actualizarCampo(idx, key, valor) {
        const zonaMap = {
            'zona_la_joya': 'La Joya',
            'zona_mollendo': 'Mollendo',
            'zona_san_lucas': 'San Lucas'
        };

        if (zonaMap[key]) {
            const zonaNombre = zonaMap[key];
            if (!this.escenarios[idx].zonas) this.escenarios[idx].zonas = [];
            
            const zona = this.escenarios[idx].zonas.find(z => z.zona === zonaNombre);
            if (zona) {
                zona.tpo_crianza_dias = parseFloat(valor);
            } else {
                this.escenarios[idx].zonas.push({
                    zona: zonaNombre,
                    porcentaje_zona: this.PORCENTAJES_ZONAS[zonaNombre],
                    tpo_crianza_dias: parseFloat(valor)
                });
            }
        } else {
            this.escenarios[idx][key] = parseFloat(valor);
        }

        this.calcularLocalmente(this.escenarios[idx]);
        this.actualizarCeldasCalculadas(idx);
        this.renderResumen();
    }

    /**
     * Actualizar notas de un escenario
     */
    actualizarNotas(idx, valor) {
        this.escenarios[idx].notas = valor;
        // No es necesario recalcular, solo mantener el valor actualizado
    }

    /**
     * Calcular localmente un escenario
     */
    calcularLocalmente(e) {
        const g2640 = parseInt(e.n_galpones_2640) || 0;
        const g1800 = parseInt(e.n_galpones_1800) || 0;
        const pollosGalpon = parseInt(e.pollos_por_galpon) || 0;
        const cargasSem = parseFloat(e.cargas_semanales) || 0;
        const tpoLimpieza = parseFloat(e.tpo_limpieza_dias) || 0;
        const zonas = e.zonas || [];

        // Infraestructura
        e.total_galpones = g2640 + g1800;
        e.area_total_m2 = (g2640 * 2640) + (g1800 * 1800);
        e.total_galp_std_2640 = Math.round(e.area_total_m2 / 2640);

        // Producción
        e.pollos_semana = Math.round(pollosGalpon * cargasSem);
        e.oferta_pollos_semana = Math.round(e.pollos_semana * (1 - this.MORTALIDAD_DEFAULT));
        e.cargas_diario = Math.round((cargasSem / 7) * 10) / 10;

        // Tiempos
        const crianzaPonderada = zonas.reduce((acc, z) => {
            const pct = z.porcentaje_zona !== undefined ? z.porcentaje_zona : this.PORCENTAJES_ZONAS[z.zona];
            return acc + (parseFloat(z.tpo_crianza_dias) * pct / 100);
        }, 0);

        e.tpo_crianza_ponderado = Math.round(crianzaPonderada * 10) / 10;
        e.tpo_ciclo_crianza = e.total_galp_std_2640 > 0 && cargasSem > 0
            ? Math.round((e.total_galp_std_2640 / cargasSem) * 7 * 10) / 10
            : 0;
        e.tpo_descanso_total = Math.round(e.tpo_ciclo_crianza - e.tpo_crianza_ponderado);
        e.tpo_descanso_efectivo = Math.round(e.tpo_descanso_total - tpoLimpieza);
    }

    /**
     * Actualizar solo celdas calculadas
     */
    actualizarCeldasCalculadas(idx) {
        const calculados = [
            'total_galpones', 'area_total_m2', 'total_galp_std_2640',
            'pollos_semana', 'oferta_pollos_semana', 'cargas_diario',
            'tpo_ciclo_crianza', 'tpo_crianza_ponderado',
            'tpo_descanso_total', 'tpo_descanso_efectivo'
        ];

        const e = this.escenarios[idx];
        
        calculados.forEach(key => {
            const cel = document.getElementById(`cel_${key}_${idx}`);
            if (!cel) return;
            
            const val = e[key];
            const fmt = this.formatearValor(val, { key });
            cel.textContent = fmt;
        });
    }

    /**
     * Renderizar resumen comparativo
     */
    renderResumen() {
        if (!this.escenarios.length) {
            this.resumenComparativo.classList.add('hidden');
            return;
        }

        this.resumenComparativo.classList.remove('hidden');
        
        const colores = ['border-blue-300 bg-blue-50', 'border-green-300 bg-green-50', 'border-amber-300 bg-amber-50'];
        const iconos = ['📊', '📈', '📉'];

        this.tarjetasResumen.innerHTML = this.escenarios.map((e, i) => `
            <div class="summary-card ${colores[i] || 'border-gray-300 bg-gray-50'}">
                <div class="text-center font-bold text-gray-800 mb-4 text-base">
                    ${iconos[i] || '📋'} ${e.nom_escenario || e.cod_escenario}
                </div>
                <div class="space-y-2 text-xs">
                    ${this.filaResumen('Total Galpones', e.total_galpones, 'Galpones')}
                    ${this.filaResumen('Área Total', this.formatearValor(e.area_total_m2, {}), 'm²')}
                    ${this.filaResumen('Galp. Std 2,640', e.total_galp_std_2640, 'Galpones')}
                    ${this.filaResumen('Pollos / Semana', this.formatearValor(e.pollos_semana, {}), 'Pollos')}
                    ${this.filaResumen('Oferta / Semana', this.formatearValor(e.oferta_pollos_semana, {}), 'Pollos')}
                    ${this.filaResumen('Cargas / Día', e.cargas_diario, 'Galp/Día')}
                    ${this.filaResumen('Ciclo Crianza', e.tpo_ciclo_crianza, 'Días')}
                    ${this.filaResumen('T° Crianza', e.tpo_crianza_ponderado, 'Días')}
                    ${this.filaResumen('Descanso Total', e.tpo_descanso_total, 'Días')}
                    ${this.filaResumen('Descanso Efectivo', e.tpo_descanso_efectivo, 'Días')}
                </div>
            </div>
        `).join('');
    }

    /**
     * Generar fila de resumen
     */
    filaResumen(label, valor, unidad) {
        return `
            <div class="flex justify-between items-center border-b border-gray-300 pb-1">
                <span class="text-gray-600">${label}</span>
                <span class="font-semibold text-gray-800">
                    ${valor ?? '—'} <span class="text-gray-500 font-normal text-xs">${unidad}</span>
                </span>
            </div>`;
    }

    /**
     * Guardar todos los escenarios
     */
    async guardarTodos() {
        const proyeccion = this.selProyeccion.value;
        
        if (!proyeccion) {
            this.showToast('Seleccione una proyección', 'error');
            return;
        }

        try {
            this.showLoading(true);

            const promesas = this.escenarios.map(e => {
                const payload = { ...e, proyeccion };
                return this.service.guardarEscenario(payload);
            });

            const resultados = await Promise.all(promesas);
            const errores = resultados.filter(r => !r.success);

            if (errores.length > 0) {
                throw new Error('Algunos escenarios no se guardaron correctamente');
            }

            this.showToast('✅ Escenarios guardados correctamente', 'success');
            await this.cargarEscenarios();

        } catch (error) {
            this.showToast('❌ Error al guardar: ' + error.message, 'error');
        } finally {
            this.showLoading(false);
        }
    }

    /**
     * Recalcular todos los escenarios desde el servidor
     */
    async recalcularTodos() {
        try {
            this.showLoading(true);

            const promesas = this.escenarios
                .filter(e => e.id)
                .map(e => this.service.calcularEscenario(e.id));

            await Promise.all(promesas);

            this.showToast('🔄 Recálculo completado', 'success');
            await this.cargarEscenarios();

        } catch (error) {
            this.showToast('❌ Error al recalcular: ' + error.message, 'error');
        } finally {
            this.showLoading(false);
        }
    }

    /**
     * Exportar a Excel
     */
    /**
     * Exportar escenarios a Excel
     */
    exportarExcel() {
        const proyeccion = this.selProyeccion.value;
        
        if (!proyeccion) {
            this.showToast('Seleccione una proyección', 'error');
            return;
        }

        try {
            // Abrir en nueva ventana para forzar descarga
            const url = `/plantaincubacion/backend/index.php/api/simulacion-escenarios/exportar-excel?proyeccion=${encodeURIComponent(proyeccion)}`;
            window.open(url, '_blank');
            this.showToast('📥 Descargando archivo Excel...', 'success');
        } catch (error) {
            this.showToast('❌ Error al exportar: ' + error.message, 'error');
        }
    }

    /**
     * Exportar escenarios a PDF
     */
    exportarPDF() {
        const proyeccion = this.selProyeccion.value;
        
        if (!proyeccion) {
            this.showToast('Seleccione una proyección', 'error');
            return;
        }

        try {
            // Abrir en nueva ventana para forzar descarga
            const url = `/plantaincubacion/backend/index.php/api/simulacion-escenarios/exportar-pdf?proyeccion=${encodeURIComponent(proyeccion)}`;
            window.open(url, '_blank');
            this.showToast('📥 Descargando archivo PDF...', 'success');
        } catch (error) {
            this.showToast('❌ Error al exportar: ' + error.message, 'error');
        }
    }

    /**
     * Mostrar/ocultar loading
     */
    showLoading(show) {
        if (this.loadingOverlay) {
            this.loadingOverlay.style.display = show ? 'flex' : 'none';
        }
    }

    /**
     * Mostrar toast notification
     */
    showToast(message, type = 'info') {
        const container = document.getElementById('toastContainer');
        if (!container) return;

        const toast = document.createElement('div');
        toast.className = `toast ${type}`;
        toast.innerHTML = `
            <i class="fas ${type === 'success' ? 'fa-check-circle' : type === 'error' ? 'fa-exclamation-circle' : 'fa-info-circle'} mr-2"></i>
            ${message}
        `;

        container.appendChild(toast);

        setTimeout(() => {
            toast.style.opacity = '0';
            toast.style.transform = 'translateX(100%)';
            setTimeout(() => toast.remove(), 300);
        }, 3500);
    }
}
