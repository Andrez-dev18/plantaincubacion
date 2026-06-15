/**
 * Controlador de Secuencia Base de Proyeccion
 * Gestiona la logica del modulo de secuencia base
 */
class SecuenciaBaseProyeccionController {
    constructor() {
        this.service = new SecuenciaBaseProyeccionService();
        this._galponesService = null; // Lazy initialization
        this.proyeccionActual = null;
        this.datosActuales = [];
        this.filaSeleccionada = null;
        this.dataTableInstance = null; // Instancia de DataTable
        this.draggedRow = null;
        this.init();
    }

    // Getter para servicio de galpones con lazy initialization
    get galponesService() {
        if (!this._galponesService) {
            if (typeof GalponesService !== 'undefined') {
                this._galponesService = new GalponesService();
            } else {
                console.error('GalponesService no está disponible');
                this._galponesService = null;
            }
        }
        return this._galponesService;
    }

    async init() {
        await this.cargarProyecciones();
        this.setupEventListeners();
        this.mostrarMensajeVacio(true);
    }

    /**
     * Carga la lista de proyecciones
     */
    async cargarProyecciones() {
        try {
            this.showLoading(true);
            const response = await this.service.listarProyecciones();
            
            if (response.success && response.data) {
                const select = document.getElementById('selectProyeccion');
                select.innerHTML = '<option value="">Seleccionar proyección...</option>';
                
                const isValida = (indicador) => {
                    return String(indicador || '').trim().toUpperCase() === 'VALIDO';
                };
                
                let proyeccionValida = null;
                
                response.data.forEach(item => {
                    const nombre = typeof item === 'object' && item !== null ? item.nombre : item;
                    const indicador = typeof item === 'object' && item !== null ? item.indicador : null;
                    
                    if (!nombre) {
                        return;
                    }
                    
                    const option = document.createElement('option');
                    option.value = nombre;
                    option.textContent = nombre;
                    option.dataset.indicador = indicador || '';
                    select.appendChild(option);
                    
                    if (!proyeccionValida && isValida(indicador)) {
                        proyeccionValida = nombre;
                    }
                });
                
                if (proyeccionValida && !select.value) {
                    select.value = proyeccionValida;
                    if (window.jQuery) {
                        window.jQuery('#selectProyeccion').val(proyeccionValida).trigger('change');
                    }
                }
                
                console.log('✅ Proyecciones cargadas:', response.data.length);
            } else {
                this.showError('No se pudieron cargar las proyecciones');
            }
        } catch (error) {
            console.error('Error al cargar proyecciones:', error);
            this.showError('Error de conexión al cargar proyecciones');
        } finally {
            this.showLoading(false);
        }
    }

    /**
     * Carga los datos de ccosbase
     */
    async cargarDatos() {
        const select = document.getElementById('selectProyeccion');
        const proyeccion = select.value;
        
        if (!proyeccion) {
            this.showWarning('Por favor selecciona una proyección');
            return;
        }
        
        try {
            // Si viene de moverRegistro, no mostrar loading
            if (!this._omitLoadingOverlay) {
                this.showLoading(true);
            }
            
            // Cargar todos los datos paginando automáticamente
            const response = await this.service.obtenerDatosBaseCompletos(proyeccion);
            
            if (response.success && response.data) {
                this.proyeccionActual = proyeccion;
                this.datosActuales = response.data;
                // Renderizar tabla
                this.renderTabla(response.data);
                // Solo mostrar éxito si NO es movimiento de registro
                if (!this._omitSuccessPopup) {
                    this.showSuccess('Datos cargados correctamente');
                }
                this._omitSuccessPopup = false;
                this._omitLoadingOverlay = false;
                console.log('✅ Datos cargados:', response.data.length, 'registros');
            } else {
                this.showError(response.message || 'Error al cargar datos');
            }
        } catch (error) {
            console.error('Error al cargar datos:', error);
            this.showError('Error de conexión al cargar datos');
        } finally {
            if (!this._omitLoadingOverlay) {
                this.showLoading(false);
            }
            this._omitLoadingOverlay = false;
        }
    }

    /**
     * Renderiza la tabla con los datos
     */
    renderTabla(datos) {
        const tableBody = document.getElementById('tableBody');
        const table = document.getElementById('tablaSecuencia');
        const emptyMessage = document.getElementById('emptyMessage');
        
        // Destruir DataTable existente si existe
        if (this.dataTableInstance) {
            this.dataTableInstance.destroy();
            this.dataTableInstance = null;
        }
        
        if (!datos || datos.length === 0) {
            this.mostrarMensajeVacio(true);
            return;
        }
        
        this.mostrarMensajeVacio(false);
        
        tableBody.innerHTML = '';
        
        datos.forEach((row, index) => {
            const tr = document.createElement('tr');
            tr.className = 'cursor-move';
            tr.dataset.index = index;
            tr.dataset.secuencia = row.secuencia;
            tr.draggable = true;
            
            tr.innerHTML = `
                <td class="text-center font-semibold">${row.codigo || '-'}</td>
                <td class="text-left">${row.nombre || '-'}</td>
                <td class="text-right">${row.area || '-'}</td>
                <td class="text-right">${row.densidad || '-'}</td>
                <td>${row.zona || '-'}</td>
                <td class="text-center font-semibold">${row.secuencia || '-'}</td>
                <td class="text-center">${this.formatFecha(row.fecini)}</td>
                <td class="text-center">${this.formatCampana(row.campana)}</td>
                <td class="text-center">${row.galpon || '-'}</td>
                <td class="text-center">
                    <span class="px-2 py-1 rounded text-xs font-semibold ${row.swac === 'A' ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800'}">
                        ${row.swac || '-'}
                    </span>
                </td>
                <td class="text-center">${row.diasefec || '0'}</td>
                <td class="text-right">${this.formatPercent(row.mortalidad)}</td>
                <td class="text-center">${row.diasdesc || '0'}</td>
                <td class="text-right font-semibold">${this.formatNumber(row.pollos)}</td>
            `;
            
            // Click para seleccionar fila
            tr.addEventListener('click', () => this.seleccionarFila(tr, index));
            
            // Eventos de drag & drop
            this.setupDragAndDrop(tr);
            
            tableBody.appendChild(tr);
        });

        // Inicializar DataTable
        this.initDataTable();
    }

    /**
     * Selecciona una fila de la tabla
     */
    seleccionarFila(tr, index) {
        // Quitar selección anterior
        document.querySelectorAll('#tableBody tr').forEach(row => {
            row.classList.remove('selected');
        });
        
        // Seleccionar nueva fila
        tr.classList.add('selected');
        this.filaSeleccionada = index;
    }

    /**
     * Configura drag and drop para una fila
     */
    setupDragAndDrop(tr) {
        // Cuando se inicia el arrastre
        tr.addEventListener('dragstart', (e) => {
            this.draggedRow = e.currentTarget;
            tr.style.opacity = '0.5';
            tr.classList.add('dragging');
            e.dataTransfer.effectAllowed = 'move';
            e.dataTransfer.setData('text/html', tr.innerHTML);
        });

        // Cuando termina el arrastre
        tr.addEventListener('dragend', (e) => {
            tr.style.opacity = '1';
            tr.classList.remove('dragging');
            this.draggedRow = null;
            
            // Remover todos los indicadores visuales
            document.querySelectorAll('#tableBody tr').forEach(row => {
                row.classList.remove('drag-over-top', 'drag-over-bottom');
            });
        });

        // Cuando se arrastra sobre una fila
        tr.addEventListener('dragover', (e) => {
            e.preventDefault();
            e.dataTransfer.dropEffect = 'move';
            
            if (this.draggedRow && this.draggedRow !== tr) {
                const rect = tr.getBoundingClientRect();
                const midPoint = rect.top + rect.height / 2;
                
                // Remover clases previas
                tr.classList.remove('drag-over-top', 'drag-over-bottom');
                
                // Indicar si va arriba o abajo
                if (e.clientY < midPoint) {
                    tr.classList.add('drag-over-top');
                } else {
                    tr.classList.add('drag-over-bottom');
                }
            }
        });

        // Cuando sale de una fila
        tr.addEventListener('dragleave', (e) => {
            tr.classList.remove('drag-over-top', 'drag-over-bottom');
        });

        // Cuando se suelta sobre una fila
        tr.addEventListener('drop', async (e) => {
            e.preventDefault();
            e.stopPropagation();

            const draggedElement = this.draggedRow;

            if (!draggedElement || !draggedElement.dataset || !draggedElement.dataset.secuencia) {
                tr.classList.remove('drag-over-top', 'drag-over-bottom');
                return;
            }

            if (!tr.dataset || !tr.dataset.secuencia) {
                tr.classList.remove('drag-over-top', 'drag-over-bottom');
                return;
            }
            
            if (draggedElement !== tr) {
                const secuenciaOrigen = parseInt(draggedElement.dataset.secuencia);
                const secuenciaDestino = parseInt(tr.dataset.secuencia);
                
                // Permitir mover a cualquier posición
                if (secuenciaOrigen === secuenciaDestino) {
                    console.log('⚠️ Ya está en esa posición');
                    tr.classList.remove('drag-over-top', 'drag-over-bottom');
                    return;
                }

                const diferencia = Math.abs(secuenciaDestino - secuenciaOrigen);
                const direccion = secuenciaDestino < secuenciaOrigen ? 'arriba' : 'abajo';
                
                console.log(`🔄 Moviendo secuencia ${secuenciaOrigen} → ${secuenciaDestino} (${diferencia} posiciones ${direccion})`);
                
                // Mover directamente a la posición destino
                try {
                    this._omitLoadingOverlay = true;
                    const response = await this.service.moverSecuenciaA(
                        this.proyeccionActual,
                        secuenciaOrigen,
                        secuenciaDestino
                    );
                    
                    if (response.success) {
                        this._omitSuccessPopup = true;
                        await this.cargarDatos();
                        console.log(`✓ Movido ${diferencia} ${diferencia === 1 ? 'posición' : 'posiciones'} ${direccion}`);
                    } else {
                        console.error('Error al mover:', response.message);
                    }
                } catch (error) {
                    console.error('Error de conexión:', error);
                }
            }
            
            tr.classList.remove('drag-over-top', 'drag-over-bottom');
        });
    }

    /**
     * Mueve una secuencia a una nueva posición específica
     */
    async moverSecuenciaA(secuenciaOrigen, nuevaPosicion) {
        try {
            this.showLoading(true);
            
            const response = await this.service.moverSecuenciaA(
                this.proyeccionActual,
                secuenciaOrigen,
                nuevaPosicion
            );
            
            if (response.success) {
                await this.cargarDatos();
                this.showSuccess('Secuencia movida correctamente');
            } else {
                this.showError(response.message || 'Error al mover secuencia');
            }
        } catch (error) {
            console.error('Error al mover secuencia:', error);
            this.showError('Error de conexión');
        } finally {
            this.showLoading(false);
        }
    }

    /**
     * Copia secuencia de otra proyección
     */
    async copiarSecuencia() {
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
                title: 'Copiar Secuencia',
                text: 'Seleccione la proyección origen desde donde copiar la secuencia',
                input: 'select',
                inputOptions: options,
                inputValue: proyeccionValida || '', // Pre-seleccionar la válida
                inputPlaceholder: 'Seleccione una proyección...',
                showCancelButton: true,
                confirmButtonText: 'Copiar',
                cancelButtonText: 'Cancelar',
                confirmButtonColor: '#f97316',
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
                    text: 'Se eliminarán los datos actuales de la secuencia',
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonText: 'Sí, copiar',
                    cancelButtonText: 'Cancelar',
                    confirmButtonColor: '#f97316'
                });

                if (confirmado.isConfirmed) {
                    try {
                        this.showLoading(true);
                        const response = await this.service.copiarSecuencia(this.proyeccionActual, proyeccionOrigen);
                        
                        if (response.success) {
                            this.showSuccess('Secuencia copiada correctamente');
                            await this.cargarDatos();
                        } else {
                            this.showError(response.message || 'Error al copiar secuencia');
                        }
                    } catch (error) {
                        console.error('Error al copiar secuencia:', error);
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
     * Crea la secuencia (ETAPA 1)
     */
    async crearSecuencia() {
        if (!this.proyeccionActual) {
            this.showWarning('Selecciona una proyección primero');
            return;
        }

        const hastaCiclo = document.getElementById('inputHastaCiclo').value;
        
        if (!hastaCiclo || hastaCiclo < 1) {
            this.showWarning('Ingresa un valor válido para "Hasta Ciclo"');
            return;
        }

        const confirmado = await Swal.fire({
            title: '¿Crear Secuencia?',
            text: `Se generará la secuencia hasta el ciclo ${hastaCiclo}`,
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: 'Sí, crear',
            cancelButtonText: 'Cancelar',
            confirmButtonColor: '#2563eb'
        });

        if (confirmado.isConfirmed) {
            try {
                this.showLoading(
                    true, 
                    'Generando secuencia...', 
                    `Procesando hasta ciclo ${hastaCiclo}. Esto puede tardar unos minutos.`,
                    true // Mostrar tip de optimización
                );
                const response = await this.service.crearSecuencia(this.proyeccionActual, hastaCiclo);
                
                if (response.success) {
                    this.showSuccess(`Secuencia creada: ${response.registros || 0} registros`);
                    await this.verSecuencia();
                } else {
                    this.showError(response.message || 'Error al crear secuencia');
                }
            } catch (error) {
                console.error('Error al crear secuencia:', error);
                this.showError('Error de conexión');
            } finally {
                this.showLoading(false);
            }
        }
    }

    /**
     * Crea el calendario (ETAPA 2)
     */
    async crearCalendario() {
        if (!this.proyeccionActual) {
            this.showWarning('Selecciona una proyección primero');
            return;
        }

        const confirmado = await Swal.fire({
            title: '¿Crear Calendario?',
            text: 'Se generará el calendario de fechas para la proyección',
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: 'Sí, crear',
            cancelButtonText: 'Cancelar',
            confirmButtonColor: '#16a34a'
        });

        if (confirmado.isConfirmed) {
            try {
                this.showLoading(
                    true,
                    'Generando calendario...',
                    'Calculando fechas y distribución. Puede tomar varios minutos.',
                    true // Mostrar tip de optimización
                );
                const response = await this.service.crearCalendario(this.proyeccionActual);
                
                if (response.success) {
                    this.showSuccess(`Calendario creado: ${response.registros || 0} fechas`);
                } else {
                    this.showError(response.message || 'Error al crear calendario');
                }
            } catch (error) {
                console.error('Error al crear calendario:', error);
                this.showError('Error de conexión');
            } finally {
                this.showLoading(false);
            }
        }
    }

    /**
     * Copia el calendario de otra proyección
     */
    async copiarCalendario() {
        if (!this.proyeccionActual) {
            this.showWarning('Selecciona una proyección primero');
            return;
        }

        // Obtener todas las proyecciones disponibles excepto la actual
        const select = document.getElementById('selectProyeccion');
        const options = {};
        let proyeccionValidaPorDefecto = null;
        
        const isValida = (indicador) => {
            return String(indicador || '').trim().toUpperCase() === 'VALIDO';
        };
        
        Array.from(select.options).forEach(option => {
            if (option.value && option.value !== this.proyeccionActual) {
                const indicador = option.dataset.indicador || '';
                const esValida = isValida(indicador);
                
                // Agregar indicador visual para proyecciones validadas
                if (esValida) {
                    options[option.value] = `✓ ${option.textContent} - VALIDAR`;
                    // Guardar la primera proyección válida para pre-selección
                    if (!proyeccionValidaPorDefecto) {
                        proyeccionValidaPorDefecto = option.value;
                    }
                } else {
                    options[option.value] = option.textContent;
                }
            }
        });

        if (Object.keys(options).length === 0) {
            this.showWarning('No hay otras proyecciones disponibles para copiar');
            return;
        }

        const { value: proyeccionOrigen } = await Swal.fire({
            title: 'Copiar Calendario',
            text: 'Seleccione la proyección origen desde donde copiar el calendario',
            input: 'select',
            inputOptions: options,
            inputValue: proyeccionValidaPorDefecto || '', // Pre-seleccionar proyección validada
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
                    
                    // Aplicar estilos verdes a las opciones validadas
                    Array.from(selectElement.options).forEach(opt => {
                        if (opt.textContent.includes('✓') && opt.textContent.includes('VALIDAR')) {
                            opt.style.color = '#16a34a';
                            opt.style.fontWeight = '600';
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
                    this.showLoading(true, 'Copiando calendario...', 'Transfiriendo fechas y datos de carga');
                    const response = await this.service.copiarCalendario(this.proyeccionActual, proyeccionOrigen);
                    
                    if (response.success) {
                        const msg = `Calendario copiado correctamente: ${response.data.fechas_copiadas || 0} fechas`;
                        this.showSuccess(msg);
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
    }

    /**
     * Configura event listeners
     */
        setupEventListeners() {
        // Cargar datos cuando se selecciona una proyección (Select2)
        if (window.jQuery) {
            const $select = window.jQuery('#selectProyeccion');
            if ($select.length) {
                $select.on('change.select2', (e) => this.cargarDatos());
            }
        }
        
        document.getElementById('btnCargarDatos')?.addEventListener('click', () => this.cargarDatos());
        document.getElementById('btnCopiarSecuencia')?.addEventListener('click', () => this.copiarSecuencia());
        
        // Botones de acciones
        document.getElementById('btnNuevo')?.addEventListener('click', () => this.mostrarFormularioNuevo());
        document.getElementById('btnEliminar')?.addEventListener('click', () => this.eliminarRegistro());
        document.getElementById('btnSubir')?.addEventListener('click', () => this.moverRegistro('subir'));
        document.getElementById('btnBajar')?.addEventListener('click', () => this.moverRegistro('bajar'));
        
        // ETAPA 1
        document.getElementById('btnCrearSecuencia')?.addEventListener('click', () => this.crearSecuencia());
        document.getElementById('btnVerSecuencia')?.addEventListener('click', () => this.verSecuencia());
        
        // ETAPA 2
        document.getElementById('btnCrearCalendario')?.addEventListener('click', () => this.crearCalendario());
        document.getElementById('btnCopiarCalendario')?.addEventListener('click', () => this.copiarCalendario());
        document.getElementById('btnVerCalendario')?.addEventListener('click', () => this.verCalendario());
        
        // Navegación
        document.getElementById('btnVolverPaso1')?.addEventListener('click', () => this.volverPaso1());
        document.getElementById('btnSiguiente')?.addEventListener('click', () => this.siguientePaso());
    }


        async mostrarFormularioNuevo() {
        if (!this.proyeccionActual) {
            this.showWarning('Selecciona una proyección primero');
            return;
        }

        const ultimaSecuencia = this.datosActuales.length > 0 
            ? Math.max(...this.datosActuales.map(d => parseInt(d.secuencia))) 
            : 1000;

        const granjas = await this.obtenerGranjas();
        const self = this; // Capturar referencia para usar en event listeners

        const { value: formValues } = await Swal.fire({
            title: '<strong>Nuevo Registro Base</strong>',
            html: `
                <div class="swal-form-nuevo">
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Granja *</label>
                            <select id="selectGranja" class="swal2-select" required data-nombre="">
                                <option value="" data-nombre="">Seleccionar granja...</option>
                                ${granjas.map(g => `<option value="${g.id}" data-nombre="${g.nombre}">${g.id} - ${g.nombre}</option>`).join('')}
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Campaña *</label>
                            <input type="text" id="inputCampana" class="swal2-input" placeholder="Ej: CARGA 2025" maxlength="10" required>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Galpón *</label>
                            <select id="selectGalpon" class="swal2-select" required disabled>
                                <option value="">Primero selecciona una granja</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Actividad (SWAC) *</label>
                            <select id="selectSwac" class="swal2-select" required>
                                <option value="A" selected>A - Activo</option>
                                <option value="I">I - Inactivo</option>
                            </select>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Área de la Granja (m²) *</label>
                            <input type="number" id="inputArea" class="swal2-input" step="1" placeholder="2640" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Densidad *</label>
                            <input type="number" id="inputDensidad" class="swal2-input" step="1" placeholder="45" required>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Zona de la Granja</label>
                            <input type="text" id="inputZona" class="swal2-input" placeholder="Ej: 0ZONA 1" maxlength="15">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Secuencia de la Granja *</label>
                            <input type="number" id="inputSecuencia" class="swal2-input" value="${ultimaSecuencia + 1}" required>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group full-width">
                            <label class="form-label">Cantidad de Pollos *</label>
                            <input type="number" id="inputPollos" class="swal2-input" placeholder="Se calcula automáticamente" style="background-color: #f3f4f6; font-weight: 600;" readonly>
                        </div>
                    </div>
                </div>

                <style>
                    .swal-form-nuevo {
                        text-align: left;
                        padding: 1rem 0.5rem;
                    }
                    .form-row {
                        display: grid;
                        grid-template-columns: 1fr 1fr;
                        gap: 0.75rem;
                        margin-bottom: 0.5rem;
                    }
                    .form-group {
                        display: flex;
                        flex-direction: column;
                    }
                    .form-group.full-width {
                        grid-column: 1 / -1;
                    }
                    .form-label {
                        font-weight: 600;
                        color: #1f2937;
                        font-size: 0.8125rem;
                        margin-bottom: 0.25rem;
                        text-align: left;
                    }
                    .swal2-input, .swal2-select {
                        margin: 0 !important;
                        width: 100% !important;
                        padding: 0.5rem !important;
                        border: 1px solid #d1d5db !important;
                        border-radius: 0.375rem !important;
                        font-size: 0.875rem !important;
                        height: auto !important;
                    }
                    .swal2-input:focus, .swal2-select:focus {
                        border-color: #2563eb !important;
                        outline: none !important;
                        box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1) !important;
                    }
                    .swal2-html-container {
                        max-height: 600px !important;
                        overflow-y: auto !important;
                    }
                </style>
            `,
            width: '650px',
            showCancelButton: true,
            confirmButtonText: '<i class="fas fa-save mr-2"></i>Guardar',
            cancelButtonText: 'Cancelar',
            confirmButtonColor: '#10b981',
            didOpen: async () => {
                // Referencias a elementos del formulario
                const selectGranja = document.getElementById('selectGranja');
                const inputCampana = document.getElementById('inputCampana');
                const selectGalpon = document.getElementById('selectGalpon');
                const inputSecuencia = document.getElementById('inputSecuencia');
                const inputArea = document.getElementById('inputArea');
                const inputDensidad = document.getElementById('inputDensidad');
                const inputPollos = document.getElementById('inputPollos');
                const inputZona = document.getElementById('inputZona');
                const selectSwac = document.getElementById('selectSwac');

                // Auto-calcular pollos
                const calcularPollos = () => {
                    const area = parseFloat(inputArea.value) || 0;
                    const densidad = parseFloat(inputDensidad.value) || 0;
                    const pollos = Math.round(area * densidad);
                    inputPollos.value = pollos > 0 ? pollos : '';
                };

                inputArea.addEventListener('input', calcularPollos);
                inputDensidad.addEventListener('input', calcularPollos);

                // Variable compartida para almacenar los galpones cargados
                let galponesActuales = [];

                // Handler para cambio de granja - Cargar galpones
                selectGranja.addEventListener('change', async () => {
                    const codigoGranja = selectGranja.value;
                    console.log('🔍 Granja seleccionada:', codigoGranja);
                    
                    if (!codigoGranja) {
                        selectGalpon.innerHTML = '<option value="">Primero selecciona una granja</option>';
                        selectGalpon.disabled = true;
                        galponesActuales = [];
                        return;
                    }

                    // Cargar galpones
                    selectGalpon.disabled = true;
                    selectGalpon.innerHTML = '<option value="">Cargando galpones...</option>';

                    try {
                        console.log('📡 Cargando galpones para granja:', codigoGranja);
                        // Cargar galpones y guardar en variable compartida
                        galponesActuales = await self.obtenerGalponesPorGranja(codigoGranja);
                        console.log('✅ Galpones cargados:', galponesActuales.length, galponesActuales);
                        
                        if (galponesActuales.length === 0) {
                            selectGalpon.innerHTML = '<option value="">No hay galpones disponibles</option>';
                        } else {
                            selectGalpon.innerHTML = '<option value="">Seleccionar galpón...</option>' +
                                galponesActuales.map(g => `<option value="${g.galpon}">${g.galpon}</option>`).join('');
                            selectGalpon.disabled = false;
                        }
                    } catch (error) {
                        console.error('❌ Error al cargar galpones:', error);
                        selectGalpon.innerHTML = '<option value="">Error al cargar galpones</option>';
                        galponesActuales = [];
                    }
                });

                // Handler para cambio de galpón - Buscar datos existentes o auto-fill desde características
                selectGalpon.addEventListener('change', async () => {
                    const numeroGalpon = selectGalpon.value;
                    
                    if (!numeroGalpon) {
                        return;
                    }

                    // Obtener código de granja seleccionada
                    const codigoGranja = selectGranja.value;
                    
                    if (!codigoGranja) {
                        return;
                    }

                    try {
                        console.log('🔍 Verificando datos existentes para:', {
                            proyeccion: self.proyeccionActual,
                            granja: codigoGranja,
                            galpon: numeroGalpon
                        });

                        // PASO 1: Buscar si ya existe un registro en ccosbase para esta combinación
                        const registroExistente = self.datosActuales.find(d => 
                            String(d.codigo).trim().toLowerCase() === String(codigoGranja).trim().toLowerCase() &&
                            String(d.galpon).trim() === String(numeroGalpon).trim()
                        );

                        if (registroExistente) {
                            // Si existe, auto-rellenar TODOS los campos con el registro existente
                            console.log('✅ Registro existente encontrado:', registroExistente);
                            
                            inputCampana.value = registroExistente.campana || '';
                            inputArea.value = registroExistente.area || '';
                            inputDensidad.value = registroExistente.densidad || '';
                            inputZona.value = registroExistente.zona || '';
                            inputSecuencia.value = registroExistente.secuencia || '';
                            selectSwac.value = registroExistente.swac || 'A';
                            inputPollos.value = registroExistente.pollos || '';
                            
                            console.log('📝 Campos rellenados con datos existentes');
                            console.log('   Campaña:', inputCampana.value);
                            console.log('   Área:', inputArea.value);
                            console.log('   Densidad:', inputDensidad.value);
                            console.log('   Zona:', inputZona.value);
                            console.log('   Secuencia:', inputSecuencia.value);
                            console.log('   SWAC:', selectSwac.value);
                            console.log('   Pollos:', inputPollos.value);
                            
                            // Mostrar mensaje informativo
                            Swal.showValidationMessage('ℹ️ Datos existentes cargados. Puedes editarlos.');
                            
                        } else {
                            // PASO 2: Si NO existe, cargar características del galpón desde la lista ya cargada
                            console.log('ℹ️ No existe registro. Buscando características del galpón...');
                            
                            // Buscar el galpón en la lista de galpones ya cargada
                            const galponEncontrado = galponesActuales.find(g => 
                                String(g.galpon).trim() === String(numeroGalpon).trim()
                            );
                            
                            if (galponEncontrado) {
                                console.log('📋 Características del galpón encontradas:', galponEncontrado);
                                
                                // Mapeo de características desde índices numéricos
                                // El backend devuelve las características con índices numéricos:
                                // "1": zona, "2": subzona, "9": área, etc.
                                
                                // Zona (índice "1")
                                if (galponEncontrado["1"]) {
                                    inputZona.value = String(galponEncontrado["1"]).trim();
                                    console.log(`   ✅ Zona: ${inputZona.value}`);
                                }
                                
                                // Área (índice "9")
                                if (galponEncontrado["9"]) {
                                    const areaNum = parseFloat(galponEncontrado["9"]);
                                    if (!isNaN(areaNum) && areaNum > 0) {
                                        inputArea.value = areaNum;
                                        console.log(`   ✅ Área: ${areaNum}`);
                                    }
                                }
                                
                                // Densidad: usar valor por defecto (no viene en características)
                                inputDensidad.value = 45;
                                console.log('   ℹ️ Densidad por defecto: 45');
                                
                                // Recalcular pollos automáticamente
                                calcularPollos();
                                
                                console.log('✅ Datos auto-rellenados desde características del galpón');
                            } else {
                                console.log('ℹ️ No se encontraron características para este galpón');
                                // Establecer valores por defecto
                                inputDensidad.value = 45;
                                calcularPollos();
                            }
                        }
                    } catch (error) {
                        console.error('❌ Error al procesar datos del galpón:', error);
                        // Establecer valores por defecto en caso de error
                        inputDensidad.value = 45;
                        calcularPollos();
                    }
                });
            },
            preConfirm: () => {
                const secuencia = document.getElementById('inputSecuencia').value;
                const campana = document.getElementById('inputCampana').value;
                const selectGranja = document.getElementById('selectGranja');
                const codigo = selectGranja.value;
                const nombre = selectGranja.options[selectGranja.selectedIndex]?.dataset.nombre || '';
                const galpon = document.getElementById('selectGalpon').value;
                const zona = document.getElementById('inputZona').value;
                const area = document.getElementById('inputArea').value;
                const densidad = document.getElementById('inputDensidad').value;
                const swac = document.getElementById('selectSwac').value;
                const pollos = document.getElementById('inputPollos').value;

                // Validaciones
                if (!secuencia || !campana || !codigo || !galpon || !area || !densidad || !pollos) {
                    Swal.showValidationMessage('Por favor completa todos los campos obligatorios (*)');
                    return false;
                }

                if (parseInt(secuencia) <= 0) {
                    Swal.showValidationMessage('La secuencia debe ser mayor a 0');
                    return false;
                }

                if (campana.length > 10) {
                    Swal.showValidationMessage('La campaña no puede tener más de 10 caracteres');
                    return false;
                }

                // Valores por defecto para campos no mostrados
                const fechaActual = new Date().toISOString().split('T')[0];
                
                return {
                    proyeccion: this.proyeccionActual,
                    secuencia: parseInt(secuencia),
                    campana: campana.trim(),
                    codigo: codigo,
                    nombre: nombre,
                    galpon: galpon.trim(),
                    fecini: fechaActual,
                    zona: zona.trim() || '',
                    area: parseFloat(area),
                    densidad: parseFloat(densidad),
                    diasefec: 44,          // Valor por defecto
                    diasdesc: 0,            // Valor por defecto
                    mortalidad: 0.05,       // Valor por defecto (5%)
                    swac: swac,
                    pollos: parseInt(pollos)
                };
            }
        });

        if (formValues) {
            await this.guardarNuevoRegistro(formValues);
        }
    }


    /**
     * Guarda nuevo registro en la base de datos
     */
    async guardarNuevoRegistro(datos) {
        try {
            this.showLoading(true);
            
            // El nombre ya viene desde el formulario, no necesitamos buscarlo
            console.log('Guardando registro:', datos);

            const response = await this.service.guardarBase(datos);
            
            if (response.success) {
                this.showSuccess('Registro guardado exitosamente');
                await this.cargarDatos();
            } else {
                this.showError(response.message || 'Error al guardar registro');
            }
        } catch (error) {
            console.error('Error al guardar registro:', error);
            this.showError('Error de conexión');
        } finally {
            this.showLoading(false);
        }
    }

    /**
     * Obtiene lista de galpones desde ccos
     */
    async obtenerGalpones() {
        try {
            const response = await this.service.obtenerGalpones();
            if (response.success) {
                return response.data;
            }
            return [];
        } catch (error) {
            console.error('Error al obtener galpones:', error);
            return [];
        }
    }

    async obtenerGranjas() {
        try {
            // Usar servicio de galpones para obtener granjas
            const response = await this.galponesService.obtenerGranjas();
            if (response.success) {
                return response.data;
            }
            return [];
        } catch (error) {
            console.error('Error al obtener granjas:', error);
            return [];
        }
    }

    async obtenerGalponesPorGranja(codigoGranja) {
        try {
            console.log('🔧 obtenerGalponesPorGranja - codigoGranja:', codigoGranja);
            // Usar servicio de galpones para mayor confiabilidad
            const response = await this.galponesService.obtenerGalponesPorGranja(codigoGranja);
            console.log('🔧 obtenerGalponesPorGranja - response:', response);
            if (response.success) {
                return response.data;
            }
            console.warn('⚠️ Respuesta sin éxito:', response);
            return [];
        } catch (error) {
            console.error('❌ Error al obtener galpones por granja:', error);
            return [];
        }
    }

    async obtenerCampanasPorGranja(codigoGranja) {
        try {
            const response = await this.service.obtenerCampanasPorGranja(codigoGranja);
            if (response.success) {
                return response.data;
            }
            return [];
        } catch (error) {
            console.error('Error al obtener campañas por granja:', error);
            return [];
        }
    }

    async obtenerDatosGalpon(idGalpon) {
        try {
            const response = await this.service.obtenerDatosGalpon(idGalpon);
            if (response.success) {
                return response.data;
            }
            return null;
        } catch (error) {
            console.error('Error al obtener datos del galpón:', error);
            return null;
        }
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

    formatFecha(fecha) {
        if (!fecha || fecha === '0000-00-00') return '-';
        const [anio, mes, dia] = fecha.split('-');
        return `${dia}/${mes}/${anio}`;
    }

    formatCampana(campana) {
        if (!campana) return '-';
        // Si es un número puro, formatearlo con 3 dígitos
        const numero = parseInt(campana);
        if (!isNaN(numero) && campana.trim() === numero.toString()) {
            return numero.toString().padStart(3, '0');
        }
        return campana;
    }

    mostrarMensajeVacio(mostrar) {
        const table = document.getElementById('tablaSecuencia');
        const emptyMessage = document.getElementById('emptyMessage');
        
        if (mostrar) {
            table.style.display = 'none';
            emptyMessage.style.display = 'block';
        } else {
            table.style.display = 'table';
            emptyMessage.style.display = 'none';
        }
    }

    showLoading(show, message = 'Procesando solicitud...', submessage = 'Por favor espera', showTips = false) {
        const overlay = document.getElementById('loadingOverlay');
        const messageEl = document.getElementById('loadingMessage');
        const submessageEl = document.getElementById('loadingSubmessage');
        const tipsEl = document.getElementById('loadingTips');
        
        if (overlay) {
            overlay.style.display = show ? 'flex' : 'none';
            
            if (show && messageEl && submessageEl) {
                messageEl.textContent = message;
                submessageEl.textContent = submessage;
                tipsEl.style.display = showTips ? 'block' : 'none';
            }
        }
    }

    showSuccess(message) {
        Swal.fire({ icon: 'success', title: 'Éxito', text: message, timer: 2000, showConfirmButton: false });
    }

    showError(message) {
        Swal.fire({ icon: 'error', title: 'Error', text: message });
    }

    showWarning(message) {
        Swal.fire({ icon: 'warning', title: 'Advertencia', text: message });
    }

    showInfo(message) {
        Swal.fire({ icon: 'info', title: 'Información', text: message });
    }

        /**
     * Elimina registro seleccionado
     */
    async eliminarRegistro() {
        if (this.filaSeleccionada === null) {
            this.showWarning('Selecciona un registro primero');
            return;
        }

        const registro = this.datosActuales[this.filaSeleccionada];

        const result = await Swal.fire({
            title: '¿Eliminar registro?',
            html: `Se eliminará la secuencia <strong>${registro.secuencia}</strong>`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#ef4444',
            cancelButtonColor: '#6b7280',
            confirmButtonText: 'Sí, eliminar',
            cancelButtonText: 'Cancelar'
        });

        if (result.isConfirmed) {
            try {
                this.showLoading(true);
                
                const response = await this.service.eliminarBase({
                    proyeccion: this.proyeccionActual,
                    id: registro.id,
                    codigo: registro.codigo,
                    galpon: registro.galpon,
                    campana: registro.campana,
                    secuencia: registro.secuencia
                });
                
                if (response.success) {
                    this.showSuccess('Registro eliminado');
                    this.filaSeleccionada = null;
                    await this.cargarDatos();
                } else {
                    this.showError(response.message || 'Error al eliminar');
                }
            } catch (error) {
                console.error('Error:', error);
                this.showError('Error de conexión');
            } finally {
                this.showLoading(false);
            }
        }
    }

    /**
     * Mueve registro hacia arriba o abajo (permite múltiples posiciones)
     */
    async moverRegistro(direccion) {
        if (this.filaSeleccionada === null) {
            this.showWarning('Selecciona un registro primero');
            return;
        }

        const registro = this.datosActuales[this.filaSeleccionada];
        const secuenciaActual = parseInt(registro.secuencia);
        const maxSecuencia = Math.max(...this.datosActuales.map(d => parseInt(d.secuencia)));
        const minSecuencia = Math.min(...this.datosActuales.map(d => parseInt(d.secuencia)));

        // Calcular máximo de posiciones disponibles
        const maxPosiciones = direccion === 'subir' 
            ? secuenciaActual - minSecuencia 
            : maxSecuencia - secuenciaActual;

        if (maxPosiciones === 0) {
            const mensaje = direccion === 'subir' 
                ? 'Ya está en la primera posición' 
                : 'Ya está en la última posición';
            this.showWarning(mensaje);
            return;
        }

        // Solicitar cuántas posiciones mover
        const { value: numPosiciones } = await Swal.fire({
            title: `¿Cuántas posiciones mover ${direccion === 'subir' ? 'arriba' : 'abajo'}?`,
            html: `
                <div style="text-align: left; margin: 0 auto; max-width: 300px;">
                    <p style="margin-bottom: 12px; color: #666; font-size: 14px;">
                        <strong>Posición actual:</strong> ${secuenciaActual}<br>
                        <strong>Rango disponible:</strong> ${minSecuencia} - ${maxSecuencia}
                    </p>
                    <label style="font-weight: 600; font-size: 13px; margin-bottom: 4px; display: block;">
                        Número de posiciones (máx: ${maxPosiciones}):
                    </label>
                    <input type="number" 
                           id="numPosiciones" 
                           class="swal2-input" 
                           value="1" 
                           min="1" 
                           max="${maxPosiciones}" 
                           step="1"
                           style="width: 100%; margin-top: 0; text-align: center; font-size: 18px; font-weight: 600;">
                </div>
            `,
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: 'Mover',
            cancelButtonText: 'Cancelar',
            confirmButtonColor: direccion === 'subir' ? '#3b82f6' : '#10b981',
            focusConfirm: false,
            preConfirm: () => {
                const valor = parseInt(document.getElementById('numPosiciones').value);
                if (!valor || valor < 1) {
                    Swal.showValidationMessage('Ingresa un número válido (mínimo 1)');
                    return false;
                }
                if (valor > maxPosiciones) {
                    Swal.showValidationMessage(`No puedes mover más de ${maxPosiciones} posiciones`);
                    return false;
                }
                return valor;
            }
        });

        if (!numPosiciones) return;

        // Calcular nueva posición
        const nuevaPosicion = direccion === 'subir' 
            ? secuenciaActual - numPosiciones 
            : secuenciaActual + numPosiciones;

        try {
            // No mostrar overlay de loading al mover registro ni al recargar datos
            this._omitLoadingOverlay = true;
            const response = await this.service.moverSecuenciaA(
                this.proyeccionActual,
                secuenciaActual,
                nuevaPosicion
            );
            if (response.success) {
                this._omitSuccessPopup = true;
                await this.cargarDatos();
                this.showSuccess(`✓ Se movió ${numPosiciones} ${numPosiciones === 1 ? 'posición' : 'posiciones'} ${direccion === 'subir' ? 'arriba' : 'abajo'}`);
            } else {
                this.showError(response.message || 'Error al mover registro');
            }
        } catch (error) {
            console.error('Error:', error);
            this.showError('Error de conexión');
        }
    }

    /**
     * Mueve una fila por drag & drop (solo posiciones adyacentes)
     * @param {number} secuencia - Número de secuencia del registro a mover
     * @param {string} direccion - 'arriba' o 'abajo'
     */
    async moverFila(secuencia, direccion) {
        // Buscar el registro por su número de secuencia
        const registro = this.datosActuales.find(d => parseInt(d.secuencia) === secuencia);
        
        if (!registro) {
            this.showError('No se encontró el registro');
            return;
        }

        const secuenciaActual = parseInt(registro.secuencia);

        // Calcular nueva posición
        const nuevaPosicion = direccion === 'arriba' ? secuenciaActual - 1 : secuenciaActual + 1;

        // Validar movimiento
        if (direccion === 'arriba' && secuenciaActual === 1) {
            this.showWarning('No se puede subir más, ya está en la primera posición');
            return;
        }

        const maxSecuencia = Math.max(...this.datosActuales.map(d => parseInt(d.secuencia)));
        if (direccion === 'abajo' && secuenciaActual === maxSecuencia) {
            this.showWarning('No se puede bajar más, ya está en la última posición');
            return;
        }

        try {
            // No mostrar overlay de loading al mover registro ni al recargar datos
            this._omitLoadingOverlay = true;
            const response = await this.service.moverSecuenciaA(
                this.proyeccionActual,
                secuenciaActual,
                nuevaPosicion
            );
            if (response.success) {
                this._omitSuccessPopup = true;
                await this.cargarDatos();
            } else {
                this.showError(response.message || 'Error al mover registro');
            }
        } catch (error) {
            console.error('Error:', error);
            this.showError('Error de conexión');
        }
    }

    /**
     * Volver al paso 1 (gestión de proyecciones)
     */
    volverPaso1() {
        window.location.href = 'dashboard-gestion-proyecciones.html';
    }

    /**
     * Nueva proyección base
     */
    async nuevaProyeccionBase() {
        const { value: nombreProyeccion } = await Swal.fire({
            title: 'Nueva Proyección Base',
            input: 'text',
            inputLabel: 'Nombre de la proyección:',
            inputPlaceholder: 'Ej: CARGA 2026 (13.5 SEM) v1',
            showCancelButton: true,
            confirmButtonText: 'Crear',
            cancelButtonText: 'Cancelar',
            confirmButtonColor: '#2563eb',
            inputValidator: (value) => {
                if (!value || value.trim() === '') {
                    return 'Debes ingresar un nombre';
                }
                if (value.length > 100) {
                    return 'El nombre no puede tener más de 100 caracteres';
                }
            }
        });

        if (nombreProyeccion) {
            try {
                this.showLoading(true);
                
                const response = await this.service.nuevaProyeccion({ nombre: nombreProyeccion.trim() });
                
                if (response.success) {
                    this.showSuccess('Proyección creada exitosamente');
                    await this.cargarProyecciones();
                    
                    // Seleccionar la nueva proyección
                    $('#selectProyeccion').val(nombreProyeccion.trim()).trigger('change');
                    this.proyeccionActual = nombreProyeccion.trim();
                } else {
                    this.showError(response.message || 'Error al crear proyección');
                }
            } catch (error) {
                console.error('Error:', error);
                this.showError('Error de conexión');
            } finally {
                this.showLoading(false);
            }
        }
    }

    /**
     * Elimina proyección base completa
     */
    async eliminarProyeccionBase() {
        if (!this.proyeccionActual) {
            this.showWarning('Selecciona una proyección primero');
            return;
        }

        const result = await Swal.fire({
            title: '¿Eliminar proyección completa?',
            html: `
                <p>Se eliminará <strong class="text-red-600">${this.proyeccionActual}</strong> y todos sus datos asociados:</p>
                <ul class="text-left mt-3 text-sm">
                    <li>• Tabla <!--ccosbase--> (secuencia base)</li>
                    <li>• Tabla <!--ccosproy--> (secuencia generada)</li>
                    <li>• Tabla <!--fechaproy--> (calendario)</li>
                    <li>• Tabla <!--fechasemproy--> (resumen semanal)</li>
                </ul>
                <p class="text-red-600 font-bold mt-3">⚠️ Esta acción no se puede deshacer</p>
            `,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#ef4444',
            cancelButtonColor: '#6b7280',
            confirmButtonText: 'Sí, eliminar todo',
            cancelButtonText: 'Cancelar',
            input: 'checkbox',
            inputValue: 0,
            inputPlaceholder: 'Confirmo que quiero eliminar esta proyección',
            inputValidator: (result) => {
                if (!result) {
                    return 'Debes confirmar la eliminación';
                }
            }
        });

        if (result.isConfirmed && result.value === 1) {
            try {
                this.showLoading(true);
                
                const response = await this.service.eliminarProyeccion({ proyeccion: this.proyeccionActual });
                
                if (response.success) {
                    this.showSuccess('Proyección eliminada completamente');
                    this.proyeccionActual = null;
                    this.datosActuales = [];
                    this.filaSeleccionada = null;
                    await this.cargarProyecciones();
                    this.mostrarMensajeVacio(true);
                } else {
                    this.showError(response.message || 'Error al eliminar proyección');
                }
            } catch (error) {
                console.error('Error:', error);
                this.showError('Error de conexión');
            } finally {
                this.showLoading(false);
            }
        }
    }

    /**
     * Crea la secuencia proyectada (PASO 1: Procesar)
     */
    async crearSecuencia() {
        if (!this.proyeccionActual) {
            this.showWarning('Selecciona una proyección primero');
            return;
        }

        if (this.datosActuales.length === 0) {
            this.showWarning('No hay datos base para generar la secuencia. Agrega registros primero.');
            return;
        }

        const inputHastaCiclo = document.getElementById('inputHastaCiclo');
        const hastaCiclo = parseInt(inputHastaCiclo?.value || 13);

        if (!hastaCiclo || hastaCiclo < 1 || hastaCiclo > 52) {
            this.showWarning('El número de ciclos debe estar entre 1 y 52');
            return;
        }

        const result = await Swal.fire({
            title: '¿Generar Secuencia?',
            html: `
                <div class="text-left">
                    <p class="mb-2">Se generará la secuencia proyectada con los siguientes datos:</p>
                    <ul class="text-sm text-gray-600 space-y-1 ml-4">
                        <li>📊 <strong>Proyección:</strong> ${this.proyeccionActual}</li>
                        <li>📋 <strong>Registros base:</strong> ${this.datosActuales.length}</li>
                        <li>🔄 <strong>Ciclos a generar:</strong> ${hastaCiclo}</li>
                        <li>📈 <strong>Total registros:</strong> ${this.datosActuales.length * hastaCiclo}</li>
                    </ul>
                    <p class="mt-3 text-sm text-gray-500">La campaña se incrementará en 1 en cada ciclo.</p>
                </div>
            `,
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: '<i class="fas fa-cogs mr-2"></i>Generar',
            cancelButtonText: 'Cancelar',
            confirmButtonColor: '#2563eb'
        });

        if (result.isConfirmed) {
            try {
                this.showLoading(true);
                
                const response = await this.service.crearSecuencia(this.proyeccionActual, hastaCiclo);
                
                if (response.success) {
                    this.showSuccess(`Secuencia generada: ${response.registros_creados || 'N/A'} registros`);
                } else {
                    this.showError(response.message || 'Error al generar secuencia');
                }
            } catch (error) {
                console.error('Error al crear secuencia:', error);
                this.showError('Error de conexión al crear secuencia');
            } finally {
                this.showLoading(false);
            }
        }
    }

    /**
     * Visualiza la secuencia proyectada generada (PASO 1: Ver)
     */
    async verSecuencia() {
        if (!this.proyeccionActual) {
            this.showWarning('Selecciona una proyección primero');
            return;
        }

        try {
            this.showLoading(true);
            
            // Primero obtener cuántas filas hay en la secuencia base (ccosbase)
            // Este número representa cuántas granjas hay, y debe ser el tamaño de cada ciclo
            const responseBase = await this.service.obtenerDatosBase(this.proyeccionActual, 1, 1);
            
            if (!responseBase.success) {
                this.showError('Error al obtener información de la secuencia base');
                return;
            }
            
            // El número de registros en la base es cuántas granjas/filas hay por ciclo
            const registrosPorCiclo = responseBase.pagination?.total || 3;
            
            console.log('📊 Registros en secuencia base (granjas):', registrosPorCiclo);
            console.log('📄 Mostrando secuencia con', registrosPorCiclo, 'registros por página (1 ciclo completo)');
            
            // Ahora obtener la primera página de la secuencia generada con ese límite
            const primeraRespuesta = await this.service.obtenerDatosProyeccion(this.proyeccionActual, 1, registrosPorCiclo);
            
            if (!primeraRespuesta.success) {
                this.showError(primeraRespuesta.message || 'Error al obtener datos');
                return;
            }

            if (!primeraRespuesta.data || primeraRespuesta.data.length === 0) {
                this.showWarning('No hay secuencia generada. Presiona "Procesar" primero.');
                return;
            }

            const totalRegistros = primeraRespuesta.pagination?.total || primeraRespuesta.data.length;
            
            // Mostrar modal paginado - cada página es un ciclo completo
            await this.mostrarModalSecuenciaPaginado(primeraRespuesta.data, 1, registrosPorCiclo, totalRegistros);

        } catch (error) {
            console.error('Error al ver secuencia:', error);
            this.showError('Error de conexión al obtener secuencia');
        } finally {
            this.showLoading(false);
        }
    }

    /**
     * Crea el calendario (PASO 2: Crear Calendario)
     */
    async crearCalendario() {
        if (!this.proyeccionActual) {
            this.showWarning('Selecciona una proyección primero');
            return;
        }

        const result = await Swal.fire({
            title: '¿Generar Calendario?',
            html: `
                <p>Se creará el calendario de fechas para la proyección:</p>
                <p class="font-bold text-blue-600 my-3">${this.proyeccionActual}</p>
                <p class="text-sm text-gray-500">Asegúrate de haber generado la secuencia primero.</p>
            `,
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: '<i class="fas fa-calendar-plus mr-2"></i>Crear',
            cancelButtonText: 'Cancelar',
            confirmButtonColor: '#16a34a'
        });

        if (result.isConfirmed) {
            try {
                this.showLoading(true);
                
                const response = await this.service.crearCalendario(this.proyeccionActual);
                
                if (response.success) {
                    this.showSuccess(`Calendario creado: ${response.registros_creados || 'N/A'} fechas`);
                } else {
                    this.showError(response.message || 'Error al crear calendario');
                }
            } catch (error) {
                console.error('Error al crear calendario:', error);
                this.showError('Error de conexión al crear calendario');
            } finally {
                this.showLoading(false);
            }
        }
    }

    /**
     * Visualiza el calendario generado (PASO 2: Visualizar)
     */
    async verCalendario() {
        if (!this.proyeccionActual) {
            this.showWarning('Selecciona una proyección primero');
            return;
        }

        try {
            this.showLoading(true);
            
            // Determinar cuántos días por página (un ciclo= generalmente de 150-250 días)
            const registrosPorPagina = 150; // Mostrar 150 fechas por página (aprox 5 meses)
            
            console.log('📅 Solicitando calendario con paginación:', { proyeccion: this.proyeccionActual, page: 1, limit: registrosPorPagina });
            
            // Obtener la primera página
            const response = await this.service.obtenerCalendario(this.proyeccionActual, 1, registrosPorPagina);
            
            console.log('📅 Respuesta del calendario:', response);
            
            if (response.success && response.data && response.data.length > 0) {
                const totalRegistros = response.pagination?.total || response.data.length;
                
                console.log('📅 Total registros:', totalRegistros);
                
                // Mostrar modal paginado
                await this.mostrarModalCalendarioPaginado(response.data, 1, registrosPorPagina, totalRegistros);
            } else {
                this.showWarning('No hay calendario generado. Presiona "Crear Calendario" primero.');
            }
        } catch (error) {
            console.error('Error al ver calendario:', error);
            this.showError('Error de conexión al obtener calendario');
        } finally {
            this.showLoading(false);
        }
    }

    /**
     * Siguiente paso (ir al siguiente módulo)
     */
    siguientePaso() {
        if (!this.proyeccionActual) {
            this.showWarning('Selecciona una proyección primero');
            return;
        }

        Swal.fire({
            title: 'Paso Siguiente',
            html: `
                <p>Has completado la configuración de la proyección:</p>
                <p class="font-bold text-blue-600 my-3">${this.proyeccionActual}</p>
                <p class="text-sm text-gray-600">¿Deseas continuar al siguiente módulo del proceso?</p>
            `,
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: 'Continuar',
            cancelButtonText: 'Cancelar',
            confirmButtonColor: '#8b5cf6'
        }).then((result) => {
            if (result.isConfirmed) {
                // Aquí puedes redirigir al siguiente módulo
                this.showInfo('Módulo siguiente en desarrollo');
                // window.location.href = '../pages/dashboard-siguiente-modulo.html';
            }
        });
    }

    /**
     * Inicializa DataTable con configuración optimizada
     */
    initDataTable() {
        if (!window.jQuery) {
            console.warn('jQuery no disponible, no se puede inicializar DataTable');
            return;
        }

        const table = document.getElementById('tablaSecuencia');
        if (!table) return;

        try {
            this.dataTableInstance = $(table).DataTable({
                paging: true,
                pageLength: 25,
                lengthMenu: [[10, 25, 50, 100, -1], [10, 25, 50, 100, "Todos"]],
                searching: true,
                ordering: true,
                info: true,
                autoWidth: false,
                // language: { url: '//cdn.datatables.net/plug-ins/1.13.7/i18n/es-ES.json' }, // Deshabilitado por error CORS
                scrollY: '70vh',
                scrollX: true,
                scrollCollapse: true,
                order: [[5, 'asc']]  // Columna 5 = Secuencia (Código=0, Nombre=1, Área=2, Densidad=3, Zona=4, Secuencia=5)
            });
            console.log('✅ DataTable inicializado para proyecciones de cargas');
        } catch (error) {
            console.error('Error al inicializar DataTable:', error);
        }
    }

    /**
     * Muestra modal con datos de secuencia (ccosproy)
     */
    mostrarModalSecuencia(datos) {
        const tablaHTML = this.generarTablaSecuencia(datos);
        
        const modal = new Modal({
            title: `<i class="fas fa-list-ol mr-2"></i>Secuencia Generada - ${this.proyeccionActual}`,
            content: tablaHTML,
            size: 'xlarge',
            showCloseButton: true
        });
        
        modal.open();
    }

    /**
     * Muestra modal con paginación y DataTables
     */
    async mostrarModalSecuenciaPaginado(datos, paginaActual = 1, registrosPorPagina = 100, totalRegistros = 0) {
        const totalPaginas = Math.ceil(totalRegistros / registrosPorPagina);
        const registroInicio = (paginaActual - 1) * registrosPorPagina + 1;
        const registroFin = Math.min(paginaActual * registrosPorPagina, totalRegistros);
        
        // Generar opciones para el selector basadas en el valor actual
        const opcionesPagina = this.generarOpcionesPaginacion(registrosPorPagina);
        
        // Calcular el número de ciclo que se está mostrando
        const cicloActual = paginaActual;
        const totalCiclos = totalPaginas;
        
        let html = `
            <style>
                .modal-datatable-wrapper {
                    font-family: 'Segoe UI', system-ui, sans-serif;
                }
                .modal-datatable-header {
                    padding: 1rem;
                    border-bottom: 1px solid #e5e7eb;
                    display: flex;
                    justify-content: space-between;
                    align-items: center;
                    gap: 1rem;
                }
                .modal-datatable-length {
                    display: flex;
                    align-items: center;
                    gap: 0.5rem;
                    font-size: 0.875rem;
                }
                .modal-datatable-length select {
                    padding: 0.375rem 0.5rem;
                    border: 1px solid #d1d5db;
                    border-radius: 0.375rem;
                    font-size: 0.875rem;
                }
                .modal-datatable-search {
                    display: flex;
                    align-items: center;
                    gap: 0.5rem;
                }
                .modal-datatable-search input {
                    padding: 0.5rem 0.75rem;
                    border: 1px solid #d1d5db;
                    border-radius: 0.375rem;
                    font-size: 0.875rem;
                    width: 200px;
                }
                .info-ciclo {
                    background: #eff6ff;
                    border: 1px solid #bfdbfe;
                    color: #1e40af;
                    padding: 0.5rem 1rem;
                    border-radius: 0.5rem;
                    font-size: 0.875rem;
                    font-weight: 600;
                    display: inline-flex;
                    align-items: center;
                    gap: 0.5rem;
                }
            </style>

            <div class="modal-datatable-wrapper">
                <!-- Header con búsqueda y selector -->
                <div class="modal-datatable-header">
                    <div class="modal-datatable-length">
                        <span>Mostrar</span>
                        <select id="pageLength" class="cambiar-registros-pagina">
                            ${opcionesPagina}
                        </select>
                        <span>registros/ciclo</span>
                    </div>
                    <div class="info-ciclo">
                        <i class="fas fa-layer-group"></i>
                        Ciclo ${cicloActual} de ${totalCiclos}
                    </div>
                    <div class="modal-datatable-search">
                        <label style="font-size: 0.875rem;">Search:</label>
                        <input type="text" id="buscarSecuencia" placeholder="">
                    </div>
                </div>

                <!-- Tabla -->
                <div style="max-height: 65vh; overflow-y: auto;">
                    <table class="w-full border-collapse" style="width: 100%;">
                        <thead style="position: sticky; top: 0; z-index: 10;">
                            <tr style="background: #1e40af; color: white;">
                                <th style="border: 1px solid #ccc; padding: 0.75rem; text-align: left; font-weight: 600; font-size: 0.875rem;">Código</th>
                                <th style="border: 1px solid #ccc; padding: 0.75rem; text-align: left; font-weight: 600; font-size: 0.875rem;">Nombre</th>
                                <th style="border: 1px solid #ccc; padding: 0.75rem; text-align: center; font-weight: 600; font-size: 0.875rem;">Campaña</th>
                                <th style="border: 1px solid #ccc; padding: 0.75rem; text-align: center; font-weight: 600; font-size: 0.875rem;">Galpón</th>
                                <th style="border: 1px solid #ccc; padding: 0.75rem; text-align: center; font-weight: 600; font-size: 0.875rem;">Secuencia</th>
                                <th style="border: 1px solid #ccc; padding: 0.75rem; text-align: right; font-weight: 600; font-size: 0.875rem;">Pollos</th>
                                <th style="border: 1px solid #ccc; padding: 0.75rem; text-align: center; font-weight: 600; font-size: 0.875rem;">Mortalidad</th>
                                <th style="border: 1px solid #ccc; padding: 0.75rem; text-align: center; font-weight: 600; font-size: 0.875rem;">Días Efec</th>
                                <th style="border: 1px solid #ccc; padding: 0.75rem; text-align: center; font-weight: 600; font-size: 0.875rem;">SWAC</th>
                            </tr>
                        </thead>
                        <tbody id="tabla-secuencia-body">
        `;

        datos.forEach((row, index) => {
            const bgClass = index % 2 === 0 ? '#f9fafb' : '#ffffff';
            html += `
                <tr style="background: ${bgClass}; border-bottom: 1px solid #e5e7eb; cursor: move;" 
                    class="fila-secuencia" 
                    draggable="true" 
                    data-secuencia="${row.secuencia || ''}">
                    <td style="border: 1px solid #e5e7eb; padding: 0.75rem; text-align: left; font-size: 0.875rem; font-weight: 600;">${row.codigo || '-'}</td>
                    <td style="border: 1px solid #e5e7eb; padding: 0.75rem; text-align: left; font-size: 0.875rem;">${row.nombre || '-'}</td>
                    <td style="border: 1px solid #e5e7eb; padding: 0.75rem; text-align: center; font-size: 0.875rem;">${this.formatCampana(row.campana)}</td>
                    <td style="border: 1px solid #e5e7eb; padding: 0.75rem; text-align: center; font-size: 0.875rem;">${row.galpon || ''}</td>
                    <td style="border: 1px solid #e5e7eb; padding: 0.75rem; text-align: center; font-size: 0.875rem;">${row.secuencia || ''}</td>
                    <td style="border: 1px solid #e5e7eb; padding: 0.75rem; text-align: right; font-size: 0.875rem; font-weight: 600;">${this.formatNumber(row.pollos)}</td>
                    <td style="border: 1px solid #e5e7eb; padding: 0.75rem; text-align: center; font-size: 0.875rem;">${row.mortalidad || ''}</td>
                    <td style="border: 1px solid #e5e7eb; padding: 0.75rem; text-align: center; font-size: 0.875rem;">${row.diasefec || ''}</td>
                    <td style="border: 1px solid #e5e7eb; padding: 0.75rem; text-align: center; font-size: 0.875rem;">${row.swac || ''}</td>
                </tr>
            `;
        });

        html += `
                        </tbody>
                    </table>
                </div>

                <!-- Footer con paginación -->
                <div style="padding: 1rem; border-top: 1px solid #e5e7eb; background: #f9fafb; display: flex; justify-content: space-between; align-items: center; flexWrap: wrap;">
                    <div style="font-size: 0.875rem; color: #6b7280;">
                        <strong>Ciclo ${cicloActual} de ${totalCiclos}</strong> | Mostrando <strong>${registroInicio}-${registroFin}</strong> de <strong>${totalRegistros}</strong> registros totales
                    </div>
                    
                    <div style="display: flex; gap: 0.5rem; align-items: center; flex-wrap: wrap; justify-content: center;">
                        ${paginaActual > 1 ? `<button class="btn-paginacion-modal" data-pagina="1">
                            <i class="fas fa-step-backward"></i> Primer Ciclo
                        </button>` : ''}

                        ${paginaActual > 1 ? `<button class="btn-paginacion-modal" data-pagina="${paginaActual - 1}">
                            <i class="fas fa-chevron-left"></i> Ciclo Anterior
                        </button>` : ''}

                        <div style="display: flex; gap: 0.25rem;">
                            ${this.generarBotonespaginacionModal(paginaActual, totalPaginas)}
                        </div>

                        ${paginaActual < totalPaginas ? `<button class="btn-paginacion-modal" data-pagina="${paginaActual + 1}">
                            Ciclo Siguiente <i class="fas fa-chevron-right"></i>
                        </button>` : ''}

                        ${paginaActual < totalPaginas ? `<button class="btn-paginacion-modal" data-pagina="${totalPaginas}">
                            Último Ciclo <i class="fas fa-step-forward"></i>
                        </button>` : ''}
                    </div>
                </div>
            </div>

            <style>
                .btn-paginacion-modal {
                    padding: 0.375rem 0.75rem;
                    background: #2563eb;
                    color: white;
                    border: none;
                    border-radius: 0.375rem;
                    cursor: pointer;
                    font-size: 0.75rem;
                    transition: all 0.2s;
                    display: inline-flex;
                    align-items: center;
                    gap: 0.25rem;
                    font-weight: 500;
                }
                .btn-paginacion-modal:hover {
                    background: #1d4ed8;
                }
                .btn-paginacion-modal:active {
                    transform: scale(0.98);
                }
                .btn-numero-modal {
                    padding: 0.25rem 0.5rem;
                    border: 1px solid #d1d5db;
                    background: white;
                    color: #374151;
                    cursor: pointer;
                    border-radius: 0.25rem;
                    font-size: 0.75rem;
                    transition: all 0.2s;
                    text-decoration: none;
                }
                .btn-numero-modal:hover {
                    background: #f3f4f6;
                    border-color: #9ca3af;
                }
                .btn-numero-modal.activo {
                    background: #2563eb;
                    color: white;
                    border-color: #2563eb;
                    font-weight: bold;
                }
                /* Drag & Drop Styles */
                .fila-secuencia.dragging {
                    opacity: 0.5;
                    background: #dbeafe !important;
                }
                .fila-secuencia.drag-over-top {
                    border-top: 3px solid #2563eb !important;
                    box-shadow: 0 -3px 6px rgba(37, 99, 235, 0.2);
                }
                .fila-secuencia.drag-over-bottom {
                    border-bottom: 3px solid #2563eb !important;
                    box-shadow: 0 3px 6px rgba(37, 99, 235, 0.2);
                }
            </style>
        `;

        const tituloModal = `<i class="fas fa-list-ol mr-2"></i>Secuencia Generada - ${this.proyeccionActual} <span style="font-size: 0.85em; color: #6b7280;">(${registrosPorPagina} granjas/ciclo)</span>`;
        
        const modal = new Modal({
            title: tituloModal,
            content: html,
            size: 'xlarge',
            showCloseButton: true
        });

        modal.open();

        // Agregar event listeners
        setTimeout(() => {
            // Cambiar cantidad de registros por página
            document.getElementById('pageLength')?.addEventListener('change', async (e) => {
                const nuevoLimit = parseInt(e.target.value);
                await this.cargarPaginaSecuencia(1, nuevoLimit, totalRegistros);
                modal.close();
            });

            // Búsqueda en tiempo real
            const inputBusqueda = document.getElementById('buscarSecuencia');
            if (inputBusqueda) {
                inputBusqueda.addEventListener('keyup', () => {
                    const termino = inputBusqueda.value.toLowerCase();
                    document.querySelectorAll('.fila-secuencia').forEach(fila => {
                        const texto = fila.textContent.toLowerCase();
                        fila.style.display = texto.includes(termino) ? '' : 'none';
                    });
                });
            }

            // Botones de paginación
            document.querySelectorAll('.btn-paginacion-modal').forEach(btn => {
                btn.addEventListener('click', async (e) => {
                    e.preventDefault();
                    const numeroPagina = parseInt(btn.dataset.pagina);
                    await this.cargarPaginaSecuencia(numeroPagina, registrosPorPagina, totalRegistros);
                    modal.close();
                });
            });

            // Botones de números de página
            document.querySelectorAll('.btn-numero-modal').forEach(btn => {
                btn.addEventListener('click', async (e) => {
                    e.preventDefault();
                    const numeroPagina = parseInt(btn.dataset.pagina);
                    await this.cargarPaginaSecuencia(numeroPagina, registrosPorPagina, totalRegistros);
                    modal.close();
                });
            });

            // Configurar Drag & Drop en todas las filas
            this.setupDragAndDropModal(datos, paginaActual, registrosPorPagina, totalRegistros, modal);
        }, 100);
    }

    /**
     * Genera botones de números de página para modal
     */
    generarBotonespaginacionModal(paginaActual, totalPaginas) {
        let html = '';
        const maxBotones = 5;
        let inicio = Math.max(1, paginaActual - 2);
        let fin = Math.min(totalPaginas, inicio + maxBotones - 1);

        if (fin - inicio + 1 < maxBotones) {
            inicio = Math.max(1, fin - maxBotones + 1);
        }

        if (inicio > 1) {
            html += `<button class="btn-numero-modal" data-pagina="1">1</button>`;
            if (inicio > 2) {
                html += `<span style="padding: 0 0.25rem; color: #9ca3af;">...</span>`;
            }
        }

        for (let i = inicio; i <= fin; i++) {
            const clase = i === paginaActual ? 'btn-numero-modal activo' : 'btn-numero-modal';
            html += `<button class="${clase}" data-pagina="${i}">${i}</button>`;
        }

        if (fin < totalPaginas) {
            if (fin < totalPaginas - 1) {
                html += `<span style="padding: 0 0.25rem; color: #9ca3af;">...</span>`;
            }
            html += `<button class="btn-numero-modal" data-pagina="${totalPaginas}">${totalPaginas}</button>`;
        }

        return html;
    }

    /**
     * Configura drag and drop para las filas del modal
     */
    setupDragAndDropModal(datos, paginaActual, registrosPorPagina, totalRegistros, modal) {
        const filas = document.querySelectorAll('.fila-secuencia');
        const tbody = document.getElementById('tabla-secuencia-body');
        const scrollContainer = tbody.closest('div[style*="overflow-y"]');
        
        let draggedRow = null;
        let autoScrollInterval = null;
        let currentMouseY = 0; // Almacenar posición Y actual del mouse

        // Función para auto-scroll
        const handleAutoScroll = () => {
            if (!scrollContainer || !draggedRow) return;

            const containerRect = scrollContainer.getBoundingClientRect();
            const scrollZone = 80; // Zona de 80px en cada borde
            const scrollSpeed = 15; // Velocidad de scroll en px
            
            // Scroll hacia arriba
            if (currentMouseY < containerRect.top + scrollZone && scrollContainer.scrollTop > 0) {
                scrollContainer.scrollTop -= scrollSpeed;
            }
            // Scroll hacia abajo
            else if (currentMouseY > containerRect.bottom - scrollZone && 
                     scrollContainer.scrollTop < scrollContainer.scrollHeight - scrollContainer.clientHeight) {
                scrollContainer.scrollTop += scrollSpeed;
            }
        };

        filas.forEach((tr, index) => {
            // Cuando se inicia el arrastre
            tr.addEventListener('dragstart', (e) => {
                draggedRow = e.currentTarget;
                tr.style.opacity = '0.5';
                tr.classList.add('dragging');
                e.dataTransfer.effectAllowed = 'move';
                e.dataTransfer.setData('text/html', tr.innerHTML);
                
                // Iniciar auto-scroll
                autoScrollInterval = setInterval(handleAutoScroll, 50);
            });

            // Cuando termina el arrastre
            tr.addEventListener('dragend', (e) => {
                tr.style.opacity = '1';
                tr.classList.remove('dragging');
                draggedRow = null;
                
                // Detener auto-scroll
                if (autoScrollInterval) {
                    clearInterval(autoScrollInterval);
                    autoScrollInterval = null;
                }
                
                // Remover todos los indicadores visuales
                filas.forEach(row => {
                    row.classList.remove('drag-over-top', 'drag-over-bottom');
                });
            });

            // Cuando se arrastra sobre una fila
            tr.addEventListener('dragover', (e) => {
                e.preventDefault();
                e.dataTransfer.dropEffect = 'move';
                
                // Actualizar posición del mouse
                currentMouseY = e.clientY;
                
                if (draggedRow && draggedRow !== tr) {
                    const rect = tr.getBoundingClientRect();
                    const midPoint = rect.top + rect.height / 2;
                    
                    // Remover clases previas
                    tr.classList.remove('drag-over-top', 'drag-over-bottom');
                    
                    // Indicar si va arriba o abajo
                    if (e.clientY < midPoint) {
                        tr.classList.add('drag-over-top');
                    } else {
                        tr.classList.add('drag-over-bottom');
                    }
                }
            });

            // Cuando sale de una fila
            tr.addEventListener('dragleave', (e) => {
                tr.classList.remove('drag-over-top', 'drag-over-bottom');
            });

            // Cuando se suelta sobre una fila
            tr.addEventListener('drop', async (e) => {
                e.preventDefault();
                e.stopPropagation();

                // Detener auto-scroll
                if (autoScrollInterval) {
                    clearInterval(autoScrollInterval);
                    autoScrollInterval = null;
                }

                if (!draggedRow || draggedRow === tr) {
                    tr.classList.remove('drag-over-top', 'drag-over-bottom');
                    return;
                }

                // Obtener datos directamente de los atributos data-* de las filas
                const draggedSecuencia = parseInt(draggedRow.dataset.secuencia);
                const targetSecuencia = parseInt(tr.dataset.secuencia);

                // Determinar si insertar antes o después según la zona del cursor
                const rect = tr.getBoundingClientRect();
                const midPoint = rect.top + rect.height / 2;
                const posicion = e.clientY < midPoint ? 'antes' : 'despues';

                console.log(`🔄 Moviendo secuencia ${draggedSecuencia} ${posicion} de ${targetSecuencia}`);

                // Realizar el movimiento en el backend
                try {
                    this.showLoading(true);
                    
                    // Usar el servicio para mover en ccosproy
                    const response = await this.service.moverSecuenciaProyeccion(
                        this.proyeccionActual,
                        draggedSecuencia,
                        targetSecuencia,
                        posicion
                    );

                    if (response.success) {
                        this.showSuccess('Posición actualizada correctamente');
                        // Cerrar modal y recargar con la misma página
                        modal.close();
                        await this.cargarPaginaSecuencia(paginaActual, registrosPorPagina, totalRegistros);
                    } else {
                        this.showError(response.message || 'Error al mover la fila');
                    }
                } catch (error) {
                    console.error('Error al mover fila:', error);
                    this.showError('Error de conexión al mover la fila');
                } finally {
                    this.showLoading(false);
                }

                tr.classList.remove('drag-over-top', 'drag-over-bottom');
            });
        });
    }

    /**
     * Carga una página específica de la secuencia
     */
    async cargarPaginaSecuencia(numeroPagina, registrosPorPagina, totalRegistros) {
        try {
            this.showLoading(true);
            const response = await this.service.obtenerDatosProyeccion(this.proyeccionActual, numeroPagina, registrosPorPagina);
            
            if (response.success && response.data && response.data.length > 0) {
                await this.mostrarModalSecuenciaPaginado(response.data, numeroPagina, registrosPorPagina, totalRegistros);
            } else {
                this.showError('Error al cargar la página');
            }
        } catch (error) {
            console.error('Error al cargar página:', error);
            this.showError('Error al cargar los datos');
        } finally {
            this.showLoading(false);
        }
    }

    /**
     * Genera opciones para el selector de registros por página
     * Incluye el valor actual y múltiplos comunes
     */
    generarOpcionesPaginacion(valorActual) {
        // Asegurar que sea un número
        valorActual = parseInt(valorActual) || 100;
        
        const opciones = new Set([valorActual]); // Siempre incluir el valor actual
        
        // Agregar opciones comunes
        const opcionesComunes = [3, 5, 10, 13, 25, 50, 100, 200];
        opcionesComunes.forEach(op => opciones.add(op));
        
        // Agregar múltiplos del valor actual
        if (valorActual < 100) {
            opciones.add(valorActual * 2);
            opciones.add(valorActual * 3);
            opciones.add(valorActual * 5);
            opciones.add(valorActual * 10);
        }
        
        // Convertir a array ordenado
        const opcionesArray = Array.from(opciones).sort((a, b) => a - b);
        
        // Generar HTML de opciones
        return opcionesArray
            .map(valor => `<option value="${valor}" ${valor === valorActual ? 'selected' : ''}>${valor}</option>`)
            .join('');
    }

    /**
     * Genera botones de números de página
     */
    generarBotonespaginacion(paginaActual, totalPaginas) {
        let html = '';
        const maxBotones = 5;
        let inicio = Math.max(1, paginaActual - 2);
        let fin = Math.min(totalPaginas, inicio + maxBotones - 1);

        if (fin - inicio + 1 < maxBotones) {
            inicio = Math.max(1, fin - maxBotones + 1);
        }

        if (inicio > 1) {
            html += `<button class="btn-paginacion-numero" data-pagina="1">1</button>`;
            if (inicio > 2) {
                html += `<span class="px-1 text-gray-500">...</span>`;
            }
        }

        for (let i = inicio; i <= fin; i++) {
            const clase = i === paginaActual ? 'btn-paginacion-numero activo' : 'btn-paginacion-numero';
            html += `<button class="${clase}" data-pagina="${i}">${i}</button>`;
        }

        if (fin < totalPaginas) {
            if (fin < totalPaginas - 1) {
                html += `<span class="px-1 text-gray-500">...</span>`;
            }
            html += `<button class="btn-paginacion-numero" data-pagina="${totalPaginas}">${totalPaginas}</button>`;
        }

        return html;
    }

    /**
     * Genera botones de números de página para modal
     */
    generarBotonespaginacionModal(paginaActual, totalPaginas) {
        let html = '';
        const maxBotones = 5;
        let inicio = Math.max(1, paginaActual - 2);
        let fin = Math.min(totalPaginas, inicio + maxBotones - 1);

        if (fin - inicio + 1 < maxBotones) {
            inicio = Math.max(1, fin - maxBotones + 1);
        }

        if (inicio > 1) {
            html += `<button class="btn-paginacion-modal" data-pagina="1">1</button>`;
            if (inicio > 2) {
                html += `<span class="px-1 text-gray-500">...</span>`;
            }
        }

        for (let i = inicio; i <= fin; i++) {
            const clase = i === paginaActual ? 'btn-paginacion-modal activo' : 'btn-paginacion-modal';
            html += `<button class="${clase}" data-pagina="${i}">${i}</button>`;
        }

        if (fin < totalPaginas) {
            if (fin < totalPaginas - 1) {
                html += `<span class="px-1 text-gray-500">...</span>`;
            }
            html += `<button class="btn-paginacion-modal" data-pagina="${totalPaginas}">${totalPaginas}</button>`;
        }

        return html;
    }

    /**
     * Genera HTML de tabla para secuencia
     */
    generarTablaSecuencia(datos) {
        if (!datos || datos.length === 0) {
            return '<p class="text-gray-500 text-center py-4">No hay datos disponibles</p>';
        }

        let html = `
            <div style="max-height: 70vh; overflow-y: auto;">
                <table class="table-auto w-full border-collapse border border-gray-300">
                    <thead class="bg-blue-600 text-white sticky top-0" style="position: sticky; top: 0; z-index: 10;">
                        <tr>
                            <th class="border border-gray-300 px-3 py-2 text-sm">Código</th>
                            <th class="border border-gray-300 px-3 py-2 text-sm">Nombre Granja</th>
                            <th class="border border-gray-300 px-3 py-2 text-sm">Área</th>
                            <th class="border border-gray-300 px-3 py-2 text-sm">Densidad</th>
                            <th class="border border-gray-300 px-3 py-2 text-sm">Campaña</th>
                            <th class="border border-gray-300 px-3 py-2 text-sm">Galpón</th>
                            <th class="border border-gray-300 px-3 py-2 text-sm">SWAC</th>
                            <th class="border border-gray-300 px-3 py-2 text-sm">Días Efec</th>
                            <th class="border border-gray-300 px-3 py-2 text-sm">Mortalidad</th>
                            <th class="border border-gray-300 px-3 py-2 text-sm">Días Desc</th>
                            <th class="border border-gray-300 px-3 py-2 text-sm">Pollos</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white">
        `;

        datos.forEach((row, index) => {
            const bgClass = index % 2 === 0 ? 'bg-gray-50' : 'bg-white';
            html += `
                <tr class="${bgClass} hover:bg-blue-50">
                    <td class="border border-gray-300 px-3 py-2 text-sm text-center font-semibold">${row.codigo || '-'}</td>
                    <td class="border border-gray-300 px-3 py-2 text-sm text-left">${row.nombre || '-'}</td>
                    <td class="border border-gray-300 px-3 py-2 text-sm text-center">${row.area || ''}</td>
                    <td class="border border-gray-300 px-3 py-2 text-sm text-center">${row.densidad || ''}</td>
                    <td class="border border-gray-300 px-3 py-2 text-sm text-center">${this.formatCampana(row.campana)}</td>
                    <td class="border border-gray-300 px-3 py-2 text-sm text-center">${row.galpon || ''}</td>
                    <td class="border border-gray-300 px-3 py-2 text-sm text-center">${row.swac || ''}</td>
                    <td class="border border-gray-300 px-3 py-2 text-sm text-center">${row.diasefec || ''}</td>
                    <td class="border border-gray-300 px-3 py-2 text-sm text-center">${row.mortalidad || ''}</td>
                    <td class="border border-gray-300 px-3 py-2 text-sm text-center">${row.diasdesc || ''}</td>
                    <td class="border border-gray-300 px-3 py-2 text-sm text-right font-semibold">${this.formatNumber(row.pollos)}</td>
                </tr>
            `;
        });

        html += `
                    </tbody>
                </table>
            </div>
            <div class="mt-4 p-3 bg-blue-50 rounded-lg">
                <p class="text-sm text-gray-700"><strong>Total de registros:</strong> ${datos.length}</p>
            </div>
        `;

        return html;
    }

    /**
     * Muestra modal con datos de calendario
     */
    mostrarModalCalendario(datos) {
        const tablaHTML = this.generarTablaCalendario(datos);
        
        const modal = new Modal({
            title: `<i class="fas fa-calendar-alt mr-2"></i>Calendario - ${this.proyeccionActual}`,
            content: tablaHTML,
            size: 'xlarge',
            showCloseButton: true
        });
        
        modal.open();
    }

    /**
     * Genera HTML de tabla para calendario
     */
    generarTablaCalendario(datos) {
        if (!datos || datos.length === 0) {
            return '<p class="text-gray-500 text-center py-4">No hay datos disponibles</p>';
        }

        let html = `
            <div style="max-height: 70vh; overflow-y: auto;">
                <table class="table-auto w-full border-collapse border border-gray-300">
                    <thead class="bg-green-600 text-white sticky top-0" style="position: sticky; top: 0; z-index: 10;">
                        <tr>
                            <th class="border border-gray-300 px-3 py-2 text-sm">Fecha</th>
                            <th class="border border-gray-300 px-3 py-2 text-sm">Semana</th>
                            <th class="border border-gray-300 px-3 py-2 text-sm">Cargas</th>
                            <th class="border border-gray-300 px-3 py-2 text-sm">Estado</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white">
        `;

        datos.forEach((row, index) => {
            const bgClass = index % 2 === 0 ? 'bg-gray-50' : 'bg-white';
            const estadoBadge = row.flag === 'A' 
                ? '<span class="px-2 py-1 bg-green-100 text-green-800 text-xs rounded-full">Activo</span>'
                : '<span class="px-2 py-1 bg-gray-100 text-gray-800 text-xs rounded-full">Inactivo</span>';
            
            html += `
                <tr class="${bgClass} hover:bg-green-50">
                    <td class="border border-gray-300 px-3 py-2 text-sm text-center">${this.formatDate(row.fecha)}</td>
                    <td class="border border-gray-300 px-3 py-2 text-sm text-center">${row.sem || row.semana || ''}</td>
                    <td class="border border-gray-300 px-3 py-2 text-sm text-center font-semibold">${row.cargas || 0}</td>
                    <td class="border border-gray-300 px-3 py-2 text-sm text-center">${estadoBadge}</td>
                </tr>
            `;
        });

        html += `
                    </tbody>
                </table>
            </div>
            <div class="mt-4 p-3 bg-green-50 rounded-lg">
                <p class="text-sm text-gray-700"><strong>Total de fechas:</strong> ${datos.length}</p>
            </div>
        `;

        return html;
    }

    /**
     * Muestra modal con calendario paginado
     */
    async mostrarModalCalendarioPaginado(datos, paginaActual = 1, registrosPorPagina = 150, totalRegistros = 0) {
        console.log('📅 Mostrando calendario con paginación:', { paginaActual, registrosPorPagina, totalRegistros, datosLength: datos.length });
        
        const totalPaginas = Math.ceil(totalRegistros / registrosPorPagina);
        const registroInicio = (paginaActual - 1) * registrosPorPagina + 1;
        const registroFin = Math.min(paginaActual * registrosPorPagina, totalRegistros);
        
        // Generar opciones para el selector basadas en el valor actual
        const opcionesPagina = this.generarOpcionesPaginacion(registrosPorPagina);
        
        // Calcular el número de período que se está mostrando
        const periodoActual = paginaActual;
        const totalPeriodos = totalPaginas;
        
        let html = `
            <style>
                .modal-datatable-wrapper {
                    font-family: 'Segoe UI', system-ui, sans-serif;
                }
                .modal-datatable-header {
                    padding: 1rem;
                    border-bottom: 1px solid #e5e7eb;
                    display: flex;
                    justify-content: space-between;
                    align-items: center;
                    gap: 1rem;
                }
                .modal-datatable-length {
                    display: flex;
                    align-items: center;
                    gap: 0.5rem;
                    font-size: 0.875rem;
                }
                .modal-datatable-length select {
                    padding: 0.375rem 0.5rem;
                    border: 1px solid #d1d5db;
                    border-radius: 0.375rem;
                    font-size: 0.875rem;
                }
                .modal-datatable-search {
                    display: flex;
                    align-items: center;
                    gap: 0.5rem;
                }
                .modal-datatable-search input {
                    padding: 0.5rem 0.75rem;
                    border: 1px solid #d1d5db;
                    border-radius: 0.375rem;
                    font-size: 0.875rem;
                    width: 200px;
                }
                .info-periodo {
                    background: #eff6ff;
                    border: 1px solid #bfdbfe;
                    color: #1e40af;
                    padding: 0.5rem 1rem;
                    border-radius: 0.5rem;
                    font-size: 0.875rem;
                    font-weight: 600;
                    display: inline-flex;
                    align-items: center;
                    gap: 0.5rem;
                }
            </style>

            <div class="modal-datatable-wrapper">
                <!-- Header con búsqueda y selector -->
                <div class="modal-datatable-header">
                    <div class="modal-datatable-length">
                        <label for="modal-length-select">Mostrar:</label>
                        <select id="modal-length-select" class="form-select">
                            ${opcionesPagina}
                        </select>
                        <span>registros/período</span>
                    </div>
                    <span class="info-periodo">
                        <i class="fas fa-calendar-day"></i>
                        Período ${periodoActual} de ${totalPeriodos}
                    </span>
                    <div class="modal-datatable-search">
                        <label for="modal-search-input">Buscar:</label>
                        <input type="text" id="modal-search-input" placeholder="Buscar...">
                    </div>
                </div>

                <!-- Tabla -->
                <div style="max-height: 60vh; overflow-y: auto; border: 1px solid #e5e7eb;">
                    <table style="width: 100%; border-collapse: collapse; font-size: 0.875rem;">
                        <thead style="position: sticky; top: 0; background: #8b5cf6; color: white; z-index: 10;">
                            <tr>
                                <th style="border: 1px solid #7c3aed; padding: 0.75rem; text-align: center; font-weight: 600; font-size: 0.875rem;">Fecha</th>
                                <th style="border: 1px solid #7c3aed; padding: 0.75rem; text-align: center; font-weight: 600; font-size: 0.875rem;">Semana</th>
                                <th style="border: 1px solid #7c3aed; padding: 0.75rem; text-align: center; font-weight: 600; font-size: 0.875rem;">Cargas</th>
                                <th style="border: 1px solid #7c3aed; padding: 0.75rem; text-align: center; font-weight: 600; font-size: 0.875rem;">Estado</th>
                            </tr>
                        </thead>
                        <tbody>
        `;

        datos.forEach((row, index) => {
            const bgClass = index % 2 === 0 ? '#f9fafb' : '#ffffff';
            const estadoBadge = row.flag === 'A' 
                ? '<span style="padding: 0.25rem 0.5rem; background: #dcfce7; color: #166534; font-size: 0.75rem; border-radius: 9999px; font-weight: 600;">Activo</span>'
                : '<span style="padding: 0.25rem 0.5rem; background: #f3f4f6; color: #6b7280; font-size: 0.75rem; border-radius: 9999px; font-weight: 600;">Inactivo</span>';
            
            html += `
                <tr style="background: ${bgClass}; border-bottom: 1px solid #e5e7eb;">
                    <td style="border: 1px solid #e5e7eb; padding: 0.75rem; text-align: center; font-size: 0.875rem;">${this.formatDate(row.fecha)}</td>
                    <td style="border: 1px solid #e5e7eb; padding: 0.75rem; text-align: center; font-size: 0.875rem;">${row.semana || ''}</td>
                    <td style="border: 1px solid #e5e7eb; padding: 0.75rem; text-align: center; font-size: 0.875rem; font-weight: 600;">${row.cargas || 0}</td>
                    <td style="border: 1px solid #e5e7eb; padding: 0.75rem; text-align: center; font-size: 0.875rem;">${estadoBadge}</td>
                </tr>
            `;
        });

        html += `
                        </tbody>
                    </table>
                </div>

                <!-- Footer con paginación -->
                <div style="padding: 1rem; border-top: 1px solid #e5e7eb; background: #f9fafb; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap;">
                    <div style="font-size: 0.875rem; color: #6b7280;">
                        <strong>Período ${periodoActual} de ${totalPeriodos}</strong> | Mostrando <strong>${registroInicio}-${registroFin}</strong> de <strong>${totalRegistros}</strong> fechas totales
                    </div>
                    
                    <div style="display: flex; gap: 0.5rem; align-items: center; flex-wrap: wrap; justify-content: center;">
                        ${paginaActual > 1 ? `<button class="btn-paginacion-modal" data-pagina="1">
                            <i class="fas fa-step-backward"></i> Primer Período
                        </button>` : ''}

                        ${paginaActual > 1 ? `<button class="btn-paginacion-modal" data-pagina="${paginaActual - 1}">
                            <i class="fas fa-chevron-left"></i> Período Anterior
                        </button>` : ''}

                        <div style="display: flex; gap: 0.25rem;">
                            ${this.generarBotonespaginacionModal(paginaActual, totalPaginas)}
                        </div>

                        ${paginaActual < totalPaginas ? `<button class="btn-paginacion-modal" data-pagina="${paginaActual + 1}">
                            Período Siguiente <i class="fas fa-chevron-right"></i>
                        </button>` : ''}

                        ${paginaActual < totalPaginas ? `<button class="btn-paginacion-modal" data-pagina="${totalPaginas}">
                            Último Período <i class="fas fa-step-forward"></i>
                        </button>` : ''}
                    </div>
                </div>
            </div>

            <style>
                .btn-paginacion-modal {
                    padding: 0.375rem 0.75rem;
                    background: #2563eb;
                    color: white;
                    border: none;
                    border-radius: 0.375rem;
                    cursor: pointer;
                    font-size: 0.75rem;
                    transition: all 0.2s;
                    display: inline-flex;
                    align-items: center;
                    gap: 0.25rem;
                    font-weight: 500;
                }
                .btn-paginacion-modal:hover {
                    background: #1d4ed8;
                }
                .btn-paginacion-modal:active {
                    transform: scale(0.98);
                }
                .btn-numero-modal {
                    padding: 0.25rem 0.5rem;
                    border: 1px solid #d1d5db;
                    background: white;
                    color: #374151;
                    cursor: pointer;
                    border-radius: 0.25rem;
                    font-size: 0.75rem;
                    transition: all 0.2s;
                    text-decoration: none;
                }
                .btn-numero-modal:hover {
                    background: #f3f4f6;
                    border-color: #9ca3af;
                }
                .btn-numero-modal.activo {
                    background: #2563eb;
                    color: white;
                    border-color: #2563eb;
                    font-weight: 600;
                }
            </style>
        `;

        const modal = new Modal({
            title: `<i class="fas fa-calendar-alt mr-2"></i>Calendario - ${this.proyeccionActual}`,
            content: html,
            size: 'xlarge',
            showCloseButton: true
        });
        
        modal.open();

        // Agregar event listeners después de un pequeño delay
        setTimeout(() => {
            // Selector de registros por página
            const selectLength = document.getElementById('modal-length-select');
            if (selectLength) {
                selectLength.addEventListener('change', async (e) => {
                    const nuevoLimit = parseInt(e.target.value);
                    await this.cargarPaginaCalendario(1, nuevoLimit, totalRegistros);
                    modal.close();
                });
            }

            // Botones de paginación
            document.querySelectorAll('.btn-paginacion-modal').forEach(btn => {
                btn.addEventListener('click', async (e) => {
                    e.preventDefault();
                    const numeroPagina = parseInt(btn.dataset.pagina);
                    await this.cargarPaginaCalendario(numeroPagina, registrosPorPagina, totalRegistros);
                    modal.close();
                });
            });

            // Botones de números de página
            document.querySelectorAll('.btn-numero-modal').forEach(btn => {
                btn.addEventListener('click', async (e) => {
                    e.preventDefault();
                    const numeroPagina = parseInt(btn.dataset.pagina);
                    await this.cargarPaginaCalendario(numeroPagina, registrosPorPagina, totalRegistros);
                    modal.close();
                });
            });
        }, 100);
    }

    /**
     * Carga una página específica del calendario
     */
    async cargarPaginaCalendario(numeroPagina, registrosPorPagina, totalRegistros) {
        try {
            this.showLoading(true);
            const response = await this.service.obtenerCalendario(this.proyeccionActual, numeroPagina, registrosPorPagina);
            
            if (response.success && response.data && response.data.length > 0) {
                await this.mostrarModalCalendarioPaginado(response.data, numeroPagina, registrosPorPagina, totalRegistros);
            } else {
                this.showError('Error al cargar la página');
            }
        } catch (error) {
            console.error('Error al cargar página del calendario:', error);
            this.showError('Error al cargar los datos');
        } finally {
            this.showLoading(false);
        }
    }

    /**
     * Formatea fecha para mostrar
     */
    formatDate(fecha) {
        if (!fecha) return '';
        const date = new Date(fecha);
        if (isNaN(date.getTime())) return fecha;
        
        const day = String(date.getDate()).padStart(2, '0');
        const month = String(date.getMonth() + 1).padStart(2, '0');
        const year = date.getFullYear();
        
        return `${day}/${month}/${year}`;
    }

    /**
     * Formatea números con separador de miles
     */
    formatNumber(num) {
        if (num === null || num === undefined || num === '') return '';
        return Number(num).toLocaleString('es-ES');
    }

}
