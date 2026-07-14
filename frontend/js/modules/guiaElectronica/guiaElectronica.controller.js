// guiaElectronica.controller.js
class GuiaElectronicaController {
    constructor() {
        this.guiaService = new window.GuiaElectronicaService();
        this._handleEscapeModal = null;
        this.debounceTimer = null;

        // Lee la configuración externa
        this.busquedasConfig = window.GuiaElectronicaConfig || {};
        this.activeSearchConfig = null;
        this.activeSearchInputId = null;

        // Arreglo en memoria para los ítems de la grilla
        this.detalleItems = [];
        this.stockMaximoPermitido = 0;
        window.guiaController = this;
    }

    async init() {
        this.setupEventListeners();
        this.setupEventTipoEnvio();
        await this.cargarZonas();
        await this.cargarTiposTransporte();
        await this.cargarMotivosTraslado();

        // Tiempo Real (Medianoche): Actualizar fechaEmision si cambia el día del sistema (cada 10 segundos)
        const getHoy = () => {
            const hoy = new Date();
            const y = hoy.getFullYear();
            const m = String(hoy.getMonth() + 1).padStart(2, '0');
            const d = String(hoy.getDate()).padStart(2, '0');
            return `${y}-${m}-${d}`;
        };
        let fechaActual = getHoy();

        // Inicializar fechas con la fecha de hoy
        const inputFechaEmision = document.getElementById('fechaEmision');
        const inputFechaTraslado = document.getElementById('fechaTraslado');
        if (inputFechaEmision) {
            inputFechaEmision.value = fechaActual;
        }
        if (inputFechaTraslado) {
            inputFechaTraslado.value = fechaActual;
        }

        setInterval(() => {
            const hoy = getHoy();
            if (hoy !== fechaActual) {
                fechaActual = hoy;
                if (inputFechaEmision) {
                    inputFechaEmision.value = hoy;
                }
            }
        }, 10000);

        // Enviar foco inicial al input de tipoEnvio (tipoEnvioAlmacen) y setear valores por defecto
        const inputClienteRuc = document.getElementById('clienteRuc');
        const inputClienteNombre = document.getElementById('clienteNombre');
        if (inputClienteRuc) inputClienteRuc.value = '20419158462';
        if (inputClienteNombre) inputClienteNombre.value = 'GRANJA RINCONADA DEL SUR S.A.';

        setTimeout(() => {
            const initialFocus = document.getElementById('tipoEnvioAlmacen');
            if (initialFocus) {
                initialFocus.focus();
            }
        }, 150);
    }

    setupEventListeners() {
        const form = document.getElementById('formGuiaRemision');
        if (form) {
            form.addEventListener('submit', (e) => e.preventDefault());
        }

        // Configurar los triggers dinámicos de búsqueda (F1 para abrir modal, Enter para buscar/autocompletar rápido)
        Object.keys(this.busquedasConfig).forEach(inputId => {
            const inputElement = document.getElementById(inputId);
            if (inputElement) {
                inputElement.addEventListener('keydown', (e) => {
                    if (e.key === 'F1') {
                        e.preventDefault();
                        this.abrirBuscadorDinamico(inputId);
                    } else if (e.key === 'Enter') {
                        const query = inputElement.value.trim();
                        if (query !== '') {
                            e.preventDefault();
                            e.stopPropagation();
                            this.buscarYAutocompletar(inputId, query);
                        }
                    }
                });
            }
        });

        // Botón de cerrar (X) en modal
        const btnCerrar = document.getElementById('btn-cerrar-modal-transportistas');
        if (btnCerrar) {
            btnCerrar.addEventListener('click', () => this.cerrarBuscadorDinamico());
        }

        // Botón de cancelar en modal
        const btnCancelar = document.getElementById('btn-cancelar-modal-transportistas');
        if (btnCancelar) {
            btnCancelar.addEventListener('click', () => this.cerrarBuscadorDinamico());
        }

        // Cerrar al hacer clic fuera del modal
        const modal = document.getElementById('modal-transportistas');
        if (modal) {
            modal.addEventListener('click', (e) => {
                if (e.target === modal) {
                    this.cerrarBuscadorDinamico();
                }
            });
        }

        // Input de búsqueda del modal
        const buscarInput = document.getElementById('buscar-transportista');
        if (buscarInput) {
            buscarInput.addEventListener('input', () => {
                const query = buscarInput.value;
                clearTimeout(this.debounceTimer);
                this.debounceTimer = setTimeout(() => {
                    this.cargarDataBuscador(query);
                }, 300);
            });

            // Navegación por teclado en el input de búsqueda del modal
            buscarInput.addEventListener('keydown', (e) => {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    clearTimeout(this.debounceTimer);
                    this.cargarDataBuscador(buscarInput.value);
                } else if (e.key === 'ArrowDown') {
                    e.preventDefault();
                    const firstRow = document.querySelector('#tabla-body-transportistas tr');
                    if (firstRow) {
                        firstRow.focus();
                    }
                }
            });
        }

        // Checkbox para mostrar todos los choferes sin relación al transportista
        const chkMostrarTodosConductores = document.getElementById('chk-mostrar-todos-conductores');
        if (chkMostrarTodosConductores) {
            chkMostrarTodosConductores.addEventListener('change', () => {
                const buscarInput = document.getElementById('buscar-transportista');
                const query = buscarInput ? buscarInput.value : '';
                this.cargarDataBuscador(query);
            });
        }

        // Listener para cambio en zonaOrigen (almacén de origen)
        const selectOrigen = document.getElementById('zonaOrigen');
        if (selectOrigen) {
            selectOrigen.addEventListener('change', () => {
                if (selectOrigen.value === '010') {
                    const inputClienteOrigen = document.getElementById('clienteOrigen');
                    if (inputClienteOrigen) {
                        inputClienteOrigen.value = '121000';
                        inputClienteOrigen.dispatchEvent(new Event('change'));
                    }
                } else {
                    this.cargarSeriesAlmacenCliente();
                }
            });
        }

        // Listener para cambio/edición en clienteOrigen
        const inputClienteOrigen = document.getElementById('clienteOrigen');
        if (inputClienteOrigen) {
            inputClienteOrigen.addEventListener('change', () => {
                this.cargarSeriesAlmacenCliente();
                this.cargarDireccionClienteOrigen(inputClienteOrigen.value);
            });
        }

        // Listener para cambio/edición en clienteDestino
        const inputClienteDestino = document.getElementById('clienteDestino');
        if (inputClienteDestino) {
            inputClienteDestino.addEventListener('change', () => {
                this.cargarDireccionClienteDestino(inputClienteDestino.value);
            });
        }

        // Listener para cambio en inputArtCencos
        const inputCencos = document.getElementById('inputArtCencos');
        if (inputCencos) {
            inputCencos.addEventListener('change', () => {
                this.procesarGalponesCencos(inputCencos.value);
            });
        }

        // Listener para cambio en el select de serie
        const selectSerie = document.getElementById('serie');
        if (selectSerie) {
            selectSerie.addEventListener('change', () => {
                this.actualizarCamposSerie();
            });
        }

        // Atajos de teclado para la fila de ingreso (Añadir, Cambiar, Anular)
        const actionInput = document.getElementById('sr-action-input');
        if (actionInput) {
            actionInput.addEventListener('keydown', (e) => {
                const key = e.key.toLowerCase();

                if (key === '+' || key === 'enter') {
                    e.preventDefault();
                    e.stopPropagation();
                    this.agregarItemGrid();
                    actionInput.value = ''; // Limpiar cajita
                } 
                else if (key === 'c') {
                    e.preventDefault();
                    e.stopPropagation();
                    actionInput.value = ''; // Limpiar cajita
                    const inputCodigo = document.getElementById('inputArtCodigo');
                    if (inputCodigo) inputCodigo.focus();
                } 
                else if (key === 'a') {
                    e.preventDefault();
                    e.stopPropagation();
                    actionInput.value = ''; // Limpiar cajita
                    this.limpiarCamposGrid();
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

        // Listener para eliminar todos los ítems de la grilla
        const btnEliminarTodos = document.getElementById('btn-eliminar-todos');
        if (btnEliminarTodos) {
            btnEliminarTodos.addEventListener('click', () => {
                window.Swal.fire({
                    title: '¿Eliminar todos?',
                    text: 'Se quitarán todos los ítems de la grilla.',
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonText: 'Sí, eliminar',
                    cancelButtonText: 'Cancelar'
                }).then((result) => {
                    if (result.isConfirmed) {
                        this.detalleItems = [];
                        this.renderizarGrid();
                        this.calcularTotales();
                    }
                });
            });
        }

        // Listener para eliminar el último ítem
        const btnEliminar = document.getElementById('btn-eliminar');
        if (btnEliminar) {
            btnEliminar.addEventListener('click', () => {
                if (this.detalleItems.length > 0) {
                    this.eliminarItemGrid(this.detalleItems.length - 1);
                } else {
                    window.Swal.fire('Información', 'No hay ítems para eliminar.', 'info');
                }
            });
        }

        // Mostrar/Ocultar Motivo y Auto-completar Cliente RUC/DNI en tiempo real
        const selectMotivo = document.getElementById('motivoTraslado');
        const contenedorMotivoOtros = document.getElementById('contenedorMotivoOtros');
        const inputMotivoOtros = document.getElementById('motivoTrasladoOtros');
        
        if (selectMotivo) {
            selectMotivo.addEventListener('change', () => {
                const motivoVal = selectMotivo.value;

                if (motivoVal === '13') {
                    if (contenedorMotivoOtros) {
                        contenedorMotivoOtros.style.display = 'block';
                    }
                } else {
                    if (contenedorMotivoOtros) {
                        contenedorMotivoOtros.style.display = 'none';
                    }
                    if (inputMotivoOtros) {
                        inputMotivoOtros.value = '';
                    }
                }

                // --- LÓGICA CORREGIDA DE AUTO-COMPLETADO DE CLIENTE ---
                const motivosExcluidos = ['01', '14', '18', '09', '13'];
                const inputClienteRuc = document.getElementById('clienteRuc');
                const inputClienteNombre = document.getElementById('clienteNombre');

                if (motivoVal && !motivosExcluidos.includes(motivoVal)) {
                    // Si el motivo exige que sea la Granja (Ej: Traslado entre almacenes), lo forzamos
                    if (inputClienteRuc) inputClienteRuc.value = '20419158462';
                    if (inputClienteNombre) inputClienteNombre.value = 'GRANJA RINCONADA DEL SUR S.A.';
                } else if (motivosExcluidos.includes(motivoVal)) {
                    // Si el motivo permite a terceros, SOLO borramos si el valor actual es el de la Granja
                    if (inputClienteRuc && inputClienteRuc.value === '20419158462') {
                        inputClienteRuc.value = '';
                        if (inputClienteNombre) inputClienteNombre.value = '';
                    }
                }
                // ------------------------------------------------------
            });
        }

        // Validación de placas no duplicadas (P y S)
        const placaP = document.getElementById('placaP');
        const placaR = document.getElementById('placaR');
        if (placaP && placaR) {
            let alertando = false;
            const validarPlacasDiferentes = (inputEditado) => {
                if (alertando || this.modalAbriendo) return;
                const valP = placaP.value.trim().toUpperCase();
                const valR = placaR.value.trim().toUpperCase();
                if (valP !== '' && valR !== '' && valP === valR) {
                    alertando = true;
                    inputEditado.blur(); // Quitar el foco inmediatamente
                    setTimeout(() => {
                        window.Swal.fire({
                            icon: 'error',
                            title: 'Placas Idénticas',
                            text: 'La placa principal (P) y la placa secundaria (S) no pueden ser las mismas.',
                            confirmButtonText: 'Corregir',
                            allowOutsideClick: false,
                            allowEscapeKey: false
                        }).then(() => {
                            setTimeout(() => {
                                inputEditado.focus();
                                inputEditado.select();
                                alertando = false;
                            }, 50);
                        });
                    }, 50);
                }
            };
            placaP.addEventListener('blur', () => validarPlacasDiferentes(placaP));
            placaR.addEventListener('blur', () => validarPlacasDiferentes(placaR));
        }

        // Listener para guardar los datos
        const btnGuardar = document.getElementById('btn-guardar');
        if (btnGuardar) {
            btnGuardar.addEventListener('click', () => this.guardarDatos());
        }

        // Listener para validar cantidad máxima en inputArtCant
        const inputArtCant = document.getElementById('inputArtCant');
        if (inputArtCant) {
            const validarCantidadExcedida = () => {
                let valor = parseFloat(inputArtCant.value) || 0;

                // Si la unidad no es KGS en almacén que inicia con M, no permitimos decimales
                const isKgs = (document.getElementById('inputArtUnd')?.value || '').toUpperCase() === 'KGS';
                const almacen = document.getElementById('zonaOrigen')?.value || '';
                const isAlmPref = almacen.startsWith('M');
                const permiteDecimales = isAlmPref && isKgs;

                if (!permiteDecimales && !Number.isInteger(valor)) {
                    valor = Math.floor(valor);
                    inputArtCant.value = valor || '';
                }

                if (valor > this.stockMaximoPermitido) {
                    window.Swal.fire({
                        icon: 'error',
                        title: 'Cantidad Excedida',
                        text: `La cantidad supera el stock disponible (Máximo: ${this.stockMaximoPermitido})`
                    });
                    inputArtCant.value = '';
                }
            };
            inputArtCant.addEventListener('input', validarCantidadExcedida);
            inputArtCant.addEventListener('change', validarCantidadExcedida);

            inputArtCant.addEventListener('keydown', (e) => {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    e.stopPropagation();
                    // Ahora siempre enviará el foco al peso para cumplir con SUNAT
                    const inputPeso = document.getElementById('inputArtPeso');
                    if (inputPeso) {
                        inputPeso.focus();
                        inputPeso.select();
                    }
                }
            });
        }

    }

    setupEventTipoEnvio() {
        const radioAlmacen = document.getElementById('tipoEnvioAlmacen');
        const radioGranja = document.getElementById('tipoEnvioGranja');
        const inputTransaccion = document.getElementById('transaccion');

        // 2. Creamos la función que cambia el valor
        const actualizarTransaccion = () => {
            if (radioAlmacen.checked) {
                inputTransaccion.value = 'S440';
            } else if (radioGranja.checked) {
                inputTransaccion.value = 'S400';
                // Regla Granja: si se marca "Granja", auto-asignar a zonaDestino el valor que tenga seleccionado zonaOrigen
                const selectOrigen = document.getElementById('zonaOrigen');
                const selectDestino = document.getElementById('zonaDestino');
                if (selectOrigen && selectDestino) {
                    selectDestino.value = selectOrigen.value;
                }
            }
        };

        // 3. Le decimos a los radio buttons que escuchen el evento 'change'
        if (radioAlmacen && radioGranja && inputTransaccion) {
            radioAlmacen.addEventListener('change', actualizarTransaccion);
            radioGranja.addEventListener('change', actualizarTransaccion);

            // 4. Ejecutamos la función una vez al cargar la página para poner el valor por defecto (S440)
            actualizarTransaccion();
        }
    }

    async cargarZonas() {
        const selectOrigen = document.getElementById('zonaOrigen');
        const selectDestino = document.getElementById('zonaDestino');

        if (!selectOrigen && !selectDestino) return;

        try {
            const response = await this.guiaService.getZonas();

            if (response && response.success && Array.isArray(response.data)) {
                const fragmentOrigen = document.createDocumentFragment();
                const fragmentDestino = document.createDocumentFragment();

                if (selectOrigen) selectOrigen.innerHTML = '<option value="">-- Seleccione Zona Origen --</option>';
                if (selectDestino) selectDestino.innerHTML = '<option value="">-- Seleccione Zona Destino --</option>';

                response.data.forEach(item => {
                    const codigo = item.codigo || item.tzona;
                    const descripcion = item.descri || item.descripcion;

                    if (selectOrigen) {
                        const opt = document.createElement('option');
                        opt.value = codigo;
                        opt.textContent = `${codigo} | ${descripcion}`;
                        fragmentOrigen.appendChild(opt);
                    }

                    if (selectDestino) {
                        const opt = document.createElement('option');
                        opt.value = codigo;
                        opt.textContent = `${codigo} | ${descripcion}`;
                        fragmentDestino.appendChild(opt);
                    }
                });

                if (selectOrigen) selectOrigen.appendChild(fragmentOrigen);
                if (selectDestino) selectDestino.appendChild(fragmentDestino);
            }
        } catch (error) {
            console.error("Error al cargar zonas de origen/destino:", error);
        }
    }

    async cargarTiposTransporte() {
        const selectTransporte = document.getElementById('tipoTransporte');
        if (!selectTransporte) return;

        try {
            const response = await this.guiaService.getTransporte();

            if (response && response.success && Array.isArray(response.data)) {
                const fragment = document.createDocumentFragment();

                selectTransporte.innerHTML = '<option value="">-- Seleccione Tipo Transporte --</option>';

                response.data.forEach(item => {
                    const codigo = item.codigo || item.cod;
                    const descripcion = item.descripcion || item.nom;

                    const opt = document.createElement('option');
                    opt.value = codigo;
                    opt.textContent = `${codigo} | ${descripcion}`;
                    fragment.appendChild(opt);
                });

                selectTransporte.appendChild(fragment);
            }
        } catch (error) {
            console.error("Error al cargar tipos de transporte:", error);
        }
    }

    async cargarMotivosTraslado() {
        const selectMotivo = document.getElementById('motivoTraslado');
        if (!selectMotivo) return;

        try {
            const response = await this.guiaService.getMotivosTraslado();

            if (response && response.success && Array.isArray(response.data)) {
                const fragment = document.createDocumentFragment();

                selectMotivo.innerHTML = '<option value="">-- Seleccione Motivo Traslado --</option>';

                response.data.forEach(item => {
                    const codigo = item.codigo || item.cod;
                    const descripcion = item.descripcion || item.nom;

                    const opt = document.createElement('option');
                    opt.value = codigo;
                    opt.textContent = `${codigo} | ${descripcion.toUpperCase()}`;
                    fragment.appendChild(opt);
                });

                selectMotivo.appendChild(fragment);
            }
        } catch (error) {
            console.error("Error al cargar motivos de traslado:", error);
        }
    }

    // ── LOGICA BUSCADOR DINAMICO REUSABLE ──────────────────────────────────

    abrirBuscadorDinamico(inputId) {
        const config = this.busquedasConfig[inputId];
        if (!config) return;

        this.activeSearchConfig = config;
        this.activeSearchInputId = inputId;

        const modal = document.getElementById('modal-transportistas');
        if (!modal) return;

        // Mostrar u ocultar checkbox de excepción de choferes
        const containerFiltro = document.getElementById('contenedor-filtro-conductores');
        const chkMostrarTodos = document.getElementById('chk-mostrar-todos-conductores');
        if (containerFiltro) {
            if (inputId === 'codConductor') {
                containerFiltro.style.display = 'flex';
                if (chkMostrarTodos) {
                    chkMostrarTodos.checked = false;
                }
            } else {
                containerFiltro.style.display = 'none';
            }
        }

        // Actualizar título de cabecera e icono
        const titleEl = modal.querySelector('.modal-header h3');
        if (titleEl) titleEl.textContent = config.title;

        const iconEl = modal.querySelector('.modal-header i');
        if (iconEl) {
            iconEl.className = config.iconClass;
        }

        // Actualizar placeholder del input y mostrar/ocultar buscador según configuración
        const buscarInput = document.getElementById('buscar-transportista');
        const containerBuscador = buscarInput ? buscarInput.parentElement.parentElement : null;
        if (containerBuscador) {
            if (config.hideSearch) {
                containerBuscador.style.display = 'none';
            } else {
                containerBuscador.style.display = 'block';
            }
        }
        if (buscarInput) {
            buscarInput.placeholder = config.placeholder || '';
            buscarInput.value = '';
        }

        // Regenerar cabeceras de la tabla
        const thead = modal.querySelector('table thead');
        if (thead) {
            thead.innerHTML = `
                <tr>
                    ${config.headers.map((h, idx) => {
                let widthClass = '';
                let alignClass = '';
                let borderClass = idx < config.headers.length - 1 ? 'border-r border-slate-100' : '';
                if (idx === 0) {
                    widthClass = 'w-12';
                    alignClass = 'text-center';
                } else if (h === 'Estado' || h === 'DEL') {
                    alignClass = 'text-center';
                }
                return `<th class="px-4 py-3 ${alignClass} ${widthClass} ${borderClass}">${h}</th>`;
            }).join('')}
                </tr>
            `;
        }

        // Mostrar modal
        modal.style.display = 'flex';
        setTimeout(() => modal.classList.add('show'), 10);
        this.modalAbriendo = true;
        if (buscarInput && !config.hideSearch) buscarInput.focus();
        this.modalAbriendo = false;

        // Cargar datos iniciales
        this.cargarDataBuscador('');

        // Registrar tecla Escape
        this._handleEscapeModal = (e) => {
            if (e.key === 'Escape') {
                this.cerrarBuscadorDinamico();
            }
        };
        document.addEventListener('keydown', this._handleEscapeModal);
    }

    cerrarBuscadorDinamico(autoAdvance = false) {
        const modal = document.getElementById('modal-transportistas');
        if (!modal) return;

        modal.classList.remove('show');
        setTimeout(() => {
            modal.style.display = 'none';
        }, 200);

        if (this._handleEscapeModal) {
            document.removeEventListener('keydown', this._handleEscapeModal);
            this._handleEscapeModal = null;
        }

        const triggerInputId = this.activeSearchInputId;

        if (autoAdvance) {
            // Avanzar al siguiente input usando el navegador exclusivo
            if (window.guiaNav) {
                window.guiaNav.avanzarDesdeCampo(triggerInputId);
            }
        } else {
            // Retornar el foco al input disparador
            const triggerInput = document.getElementById(triggerInputId);
            if (triggerInput) triggerInput.focus();
        }
    }

    async buscarYAutocompletar(inputId, query) {
        const config = this.busquedasConfig[inputId];
        if (!config) return;

        this.activeSearchInputId = inputId;
        this.activeSearchConfig = config;

        try {
            // Obtener los datos usando la función fetchData de la configuración
            const results = await config.fetchData(this.guiaService, query);
            
            let data = [];
            if (results && results.success && Array.isArray(results.data)) {
                data = results.data;
            } else if (Array.isArray(results)) {
                data = results;
            }

            // Filtrar localmente si el resultado contiene múltiples registros para hallar coincidencia exacta
            if (data.length > 1 && query) {
                const queryUpper = query.trim().toUpperCase();
                
                // 1. Intentar coincidencia exacta en código, placa o lote
                const exactMatches = data.filter(item => {
                    const code = (item.codigo || item.placa || item.lote || '').toString().toUpperCase();
                    const name = (item.nombre || item.descri || item.descripcion || item.marca || '').toString().toUpperCase();
                    return code === queryUpper || name === queryUpper;
                });

                if (exactMatches.length === 1) {
                    data = exactMatches;
                } else {
                    // 2. Intentar coincidencia parcial
                    const partialMatches = data.filter(item => {
                        const code = (item.codigo || item.placa || item.lote || '').toString().toUpperCase();
                        const name = (item.nombre || item.descri || item.descripcion || item.marca || '').toString().toUpperCase();
                        return code.includes(queryUpper) || name.includes(queryUpper);
                    });
                    if (partialMatches.length === 1) {
                        data = partialMatches;
                    }
                }
            }

            if (data.length === 1) {
                // Exactamente 1 resultado: autocompletar directamente sin modal
                const item = data[0];
                config.onSelect(item);

                // Ejecutar lógica adicional según el input
                if (inputId === 'clienteOrigen') {
                    this.cargarSeriesAlmacenCliente();
                    this.cargarDireccionClienteOrigen(item.codigo);
                } else if (inputId === 'clienteDestino') {
                    this.cargarDireccionClienteDestino(item.codigo);
                } else if (inputId === 'inputArtCencos') {
                    this.procesarGalponesCencos(item.codigo);
                }

                // Avanzar al siguiente input
                if (window.guiaNav) {
                    if (inputId === 'inputArtCodigo') {
                        const inputLote = document.getElementById('inputArtLote');
                        if (inputLote) inputLote.focus();
                    } else {
                        window.guiaNav.avanzarDesdeCampo(inputId);
                    }
                }
            } else {
                // 0 o más de 1 resultados: abrir modal y filtrar
                this.abrirBuscadorDinamico(inputId);
                const buscarInput = document.getElementById('buscar-transportista');
                if (buscarInput && !config.hideSearch) {
                    buscarInput.value = query;
                    this.cargarDataBuscador(query);
                }
            }
        } catch (error) {
            console.error("Error en autocompletado rápido:", error);
            // Ante cualquier error, abrir el modal por defecto
            this.abrirBuscadorDinamico(inputId);
        }
    }

    async cargarDataBuscador(query = '') {
        const tbody = document.getElementById('tabla-body-transportistas');
        if (!tbody || !this.activeSearchConfig) return;

        tbody.innerHTML = `
            <tr>
                <td colspan="${this.activeSearchConfig.headers.length}" class="text-center py-6 text-slate-400">
                    <i class="fas fa-spinner fa-spin mr-1.5 text-blue-500"></i> Cargando...
                </td>
            </tr>
        `;

        try {
            // Pasamos la instancia del servicio al fetchData de la configuración externa
            const response = await this.activeSearchConfig.fetchData(this.guiaService, query);

            if (response && response.success && Array.isArray(response.data)) {
                tbody.innerHTML = '';
                const items = response.data;

                if (items.length === 0) {
                    tbody.innerHTML = `
                        <tr>
                            <td colspan="${this.activeSearchConfig.headers.length}" class="text-center py-8 text-slate-400">
                                No se encontraron registros
                            </td>
                        </tr>
                    `;
                    return;
                }

                const fragment = document.createDocumentFragment();
                items.forEach((item, index) => {
                    const tr = document.createElement('tr');
                    tr.tabIndex = 0; // Habilitar enfoque por teclado
                    tr.className = 'hover:bg-slate-50 cursor-pointer transition-all focus:bg-blue-50 focus:outline-none focus:ring-2 focus:ring-blue-500/20';

                    tr.addEventListener('click', () => {
                        const inputId = this.activeSearchInputId;
                        this.activeSearchConfig.onSelect(item);
                        if (inputId === 'inputArtCodigo') {
                            this.cerrarBuscadorDinamico(false); // Cerrar sin avanzar
                            const inputLote = document.getElementById('inputArtLote');
                            if (inputLote) inputLote.focus();
                        } else if (inputId === 'clienteOrigen') {
                            this.cerrarBuscadorDinamico(true); // Avanzar
                            this.cargarSeriesAlmacenCliente();
                            this.cargarDireccionClienteOrigen(item.codigo);
                        } else if (inputId === 'clienteDestino') {
                            this.cerrarBuscadorDinamico(true); // Avanzar
                            this.cargarDireccionClienteDestino(item.codigo);
                        } else if (inputId === 'inputArtCencos') {
                            this.cerrarBuscadorDinamico(true); // Avanzar
                            this.procesarGalponesCencos(item.codigo);
                        } else {
                            this.cerrarBuscadorDinamico(true); // Avanzar al siguiente campo
                        }
                    });

                    // Eventos de teclado en la fila
                    tr.addEventListener('keydown', (e) => {
                        if (e.key === 'ArrowDown') {
                            e.preventDefault();
                            const nextRow = tr.nextElementSibling;
                            if (nextRow) nextRow.focus();
                        } else if (e.key === 'ArrowUp') {
                            e.preventDefault();
                            const prevRow = tr.previousElementSibling;
                            if (prevRow) {
                                prevRow.focus();
                            } else {
                                const config = this.activeSearchConfig;
                                if (config && !config.hideSearch) {
                                    const buscarInput = document.getElementById('buscar-transportista');
                                    if (buscarInput) buscarInput.focus();
                                }
                            }
                        } else if (e.key === 'Enter') {
                            e.preventDefault();
                            const inputId = this.activeSearchInputId;
                            this.activeSearchConfig.onSelect(item);
                            if (inputId === 'inputArtCodigo') {
                                this.cerrarBuscadorDinamico(false); // Cerrar sin avanzar
                                const inputLote = document.getElementById('inputArtLote');
                                if (inputLote) inputLote.focus();
                            } else if (inputId === 'clienteOrigen') {
                                this.cerrarBuscadorDinamico(true); // Avanzar
                                this.cargarSeriesAlmacenCliente();
                                this.cargarDireccionClienteOrigen(item.codigo);
                            } else if (inputId === 'clienteDestino') {
                                this.cerrarBuscadorDinamico(true); // Avanzar
                                this.cargarDireccionClienteDestino(item.codigo);
                            } else if (inputId === 'inputArtCencos') {
                                this.cerrarBuscadorDinamico(true); // Avanzar
                                this.procesarGalponesCencos(item.codigo);
                            } else {
                                this.cerrarBuscadorDinamico(true); // Seleccionar y avanzar al siguiente campo
                            }
                        }
                    });

                    tr.innerHTML = this.activeSearchConfig.renderRow(item, index);
                    fragment.appendChild(tr);
                });

                tbody.appendChild(fragment);

                // Si el buscador está oculto, enfocar la primera fila automáticamente después de renderizar
                if (this.activeSearchConfig && this.activeSearchConfig.hideSearch) {
                    setTimeout(() => {
                        const firstRow = tbody.querySelector('tr');
                        if (firstRow) firstRow.focus();
                    }, 50);
                }
            } else {
                tbody.innerHTML = `
                    <tr>
                        <td colspan="${this.activeSearchConfig.headers.length}" class="text-center py-8 text-rose-500">
                            Error al cargar los datos
                        </td>
                    </tr>
                `;
            }
        } catch (error) {
            console.error("Error al buscar en el modal dinámico:", error);
            tbody.innerHTML = `
                <tr>
                    <td colspan="${this.activeSearchConfig.headers.length}" class="text-center py-8 text-rose-500">
                        Error de conexión con el servidor
                    </td>
                </tr>
            `;
        }
    }

    async procesarLotesArticulo(codigoArticulo) {
        const inputLote = document.getElementById('inputArtLote');
        const inputCant = document.getElementById('inputArtCant');

        if (!inputLote) return;

        const almacen = document.getElementById('zonaOrigen')?.value || '';
        const fechaVal = document.getElementById('fechaEmision')?.value;
        const anio = fechaVal ? new Date(fechaVal).getFullYear() : new Date().getFullYear();

        if (!almacen) {
            window.Swal.fire({
                icon: 'warning',
                title: 'Almacén de origen requerido',
                text: 'Por favor, seleccione una Zona de Origen antes de elegir el artículo.'
            });
            inputLote.value = '';
            return;
        }

        const getStockLimit = (lote) => {
            const isKgs = (lote.unidad || document.getElementById('inputArtUnd')?.value || '').toUpperCase() === 'KGS';
            const isAlmPref = almacen.startsWith('M');
            if (isAlmPref && isKgs) {
                return parseFloat(lote.stock_peso) || 0;
            }
            return Math.floor(parseFloat(lote.stock_cantidad)) || 0;
        };

        try {
            const response = await this.guiaService.getLotes(almacen, codigoArticulo, anio);
            if (response && response.success && Array.isArray(response.data)) {
                const lotes = response.data;

                if (lotes.length === 0) {
                    window.Swal.fire({
                        icon: 'warning',
                        title: 'Sin stock',
                        text: 'No hay lotes con stock disponible para este artículo.'
                    });
                    inputLote.value = '';
                    inputLote.disabled = true;
                    this.stockMaximoPermitido = 0;
                }
                else {
                    inputLote.disabled = false;

                    let tableRowsHtml = '';
                    lotes.forEach((l, index) => {
                        const stockLim = getStockLimit(l);
                        tableRowsHtml += `
                            <tr class="hover:bg-slate-50 border-b border-slate-100 text-xs">
                                <td class="px-3 py-2 text-center text-slate-500 font-mono">${index + 1}</td>
                                <td class="px-3 py-2 text-slate-700 font-mono">${codigoArticulo}</td>
                                <td class="px-3 py-2 font-bold text-slate-800 font-mono">${l.lote}</td>
                                <td class="px-3 py-2 text-right text-slate-700 font-mono">${Math.floor(parseFloat(l.stock_cantidad))}</td>
                                <td class="px-3 py-2 text-right text-slate-700 font-mono">${parseFloat(l.stock_peso).toFixed(2)}</td>
                                <td class="px-3 py-2 text-center">
                                    <button type="button" 
                                        class="btn-seleccionar-lote px-3 py-1 bg-indigo-600 hover:bg-indigo-700 text-white font-bold rounded text-[10px] transition-colors"
                                        data-lote="${l.lote}" 
                                        data-stock="${stockLim}">
                                        Seleccionar
                                    </button>
                                </td>
                            </tr>
                        `;
                    });

                    const htmlContent = `
                        <div class="overflow-x-auto w-full mt-3">
                            <table class="w-full text-left border-collapse border border-slate-200">
                                <thead class="bg-blue-700  text-[11px] uppercase font-bold text-white border-b border-slate-200">
                                    <tr>
                                        <th class="px-3 py-2 text-center">N°</th>
                                        <th class="px-3 py-2">Código</th>
                                        <th class="px-3 py-2">Lote</th>
                                        <th class="px-3 py-2 text-right">Cantidad</th>
                                        <th class="px-3 py-2 text-right">Peso</th>
                                        <th class="px-3 py-2 text-center">Acción</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-150">
                                    ${tableRowsHtml}
                                </tbody>
                            </table>
                        </div>
                    `;

                    window.Swal.fire({
                        title: 'Seleccionar Lote',
                        html: htmlContent,
                        showCancelButton: true,
                        showConfirmButton: false,
                        cancelButtonText: 'Cerrar',
                        returnFocus: false,
                        customClass: {
                            cancelButton: 'px-4 py-2 bg-slate-200 text-slate-700 rounded-md font-bold text-xs'
                        },
                        didOpen: (modalElement) => {
                            const firstBtn = modalElement.querySelector('.btn-seleccionar-lote');
                            if (firstBtn) firstBtn.focus();
                            
                            const buttons = modalElement.querySelectorAll('.btn-seleccionar-lote');
                            buttons.forEach(btn => {
                                btn.addEventListener('click', (e) => {
                                    const loteVal = e.currentTarget.getAttribute('data-lote');
                                    const stockVal = parseFloat(e.currentTarget.getAttribute('data-stock')) || 0;

                                    inputLote.value = loteVal;
                                    this.stockMaximoPermitido = stockVal;

                                    window.Swal.close();

                                    if (inputCant) {
                                        inputCant.focus();
                                        inputCant.select();
                                    }
                                });
                            });
                        }
                    });
                }
            } else {
                console.error("Error al obtener los lotes:", response?.message);
            }
        } catch (error) {
            console.error("Error en la petición de lotes:", error);
        }
    }

    async cargarSeriesAlmacenCliente() {
        const selectOrigen = document.getElementById('zonaOrigen');
        const inputClienteOrigen = document.getElementById('clienteOrigen');
        const selectSerie = document.getElementById('serie');
        const inputDescSerie = document.getElementById('descripcionSerie');
        const inputNumeroGuia = document.getElementById('numeroGuia');

        if (!selectOrigen || !inputClienteOrigen || !selectSerie) return;

        const almacen = selectOrigen.value;
        const cliente = inputClienteOrigen.value;

        // Si falta alguno de los dos, limpiamos y salimos
        if (!almacen || !cliente) {
            selectSerie.innerHTML = '<option value="">-- Serie --</option>';
            if (inputDescSerie) inputDescSerie.value = '';
            if (inputNumeroGuia) inputNumeroGuia.value = '0';
            return;
        }

        try {
            const response = await this.guiaService.getSeries(almacen, cliente);
            if (response && response.success && Array.isArray(response.data)) {
                const series = response.data;

                if (series.length === 0) {
                    window.Swal.fire({
                        icon: 'warning',
                        title: 'Sin series asignadas',
                        text: 'El cliente o almacén de origen seleccionado no cuenta con series asignadas.'
                    });
                    selectSerie.innerHTML = '<option value="">-- Sin Series --</option>';
                    if (inputDescSerie) inputDescSerie.value = '';
                    if (inputNumeroGuia) inputNumeroGuia.value = '0';
                } else {
                    let html = '';
                    series.forEach(s => {
                        html += `<option value="${s.serie}" data-descripcion="${s.descripcion}" data-correlativo="${s.correlativo}">${s.serie}</option>`;
                    });
                    selectSerie.innerHTML = html;

                    // Disparar la actualización del primer elemento seleccionado por defecto
                    this.actualizarCamposSerie();
                }
            } else {
                console.error("Error al cargar las series:", response?.message);
            }
        } catch (error) {
            console.error("Error en la petición de series:", error);
        }
    }

    actualizarCamposSerie() {
        const selectSerie = document.getElementById('serie');
        const inputDescSerie = document.getElementById('descripcionSerie');
        const inputNumeroGuia = document.getElementById('numeroGuia');

        if (!selectSerie) return;

        const selectedOption = selectSerie.options[selectSerie.selectedIndex];
        if (selectedOption && selectedOption.value) {
            const descripcion = selectedOption.getAttribute('data-descripcion') || '';
            const correlativo = selectedOption.getAttribute('data-correlativo') || '0';

            if (inputDescSerie) inputDescSerie.value = descripcion;
            if (inputNumeroGuia) inputNumeroGuia.value = correlativo;
        } else {
            if (inputDescSerie) inputDescSerie.value = '';
            if (inputNumeroGuia) inputNumeroGuia.value = '0';
        }
    }

    async cargarDireccionClienteOrigen(codigo) {
        const puntoPartida = document.getElementById('puntoPartida');
        const nombreClienteOrigen = document.getElementById('nombreClienteOrigen');
        if (!puntoPartida) return;

        if (!codigo) {
            puntoPartida.value = '';
            if (nombreClienteOrigen) {
                nombreClienteOrigen.textContent = '';
                nombreClienteOrigen.title = '';
            }
            return;
        }

        try {
            const response = await this.guiaService.getDireccionCliente(codigo);
            if (response && response.success && response.data) {
                puntoPartida.value = response.data.direcc || '';
                this.ubigeoPartidaLocal = response.data.ubigeo || '';
                if (nombreClienteOrigen) {
                    nombreClienteOrigen.textContent = response.data.nombre || '';
                    nombreClienteOrigen.title = response.data.nombre || '';
                }
            } else {
                puntoPartida.value = '';
                if (nombreClienteOrigen) {
                    nombreClienteOrigen.textContent = '';
                    nombreClienteOrigen.title = '';
                }
            }
        } catch (error) {
            console.error("Error al obtener la dirección del cliente de origen:", error);
            puntoPartida.value = '';
            if (nombreClienteOrigen) {
                nombreClienteOrigen.textContent = '';
                nombreClienteOrigen.title = '';
            }
        }
    }

    async cargarDireccionClienteDestino(codigo) {
        const puntoLlegada = document.getElementById('puntoLlegada');
        const nombreClienteDestino = document.getElementById('nombreClienteDestino');
        if (!puntoLlegada) return;

        if (!codigo) {
            puntoLlegada.value = '';
            if (nombreClienteDestino) {
                nombreClienteDestino.textContent = '';
                nombreClienteDestino.title = '';
            }
            return;
        }

        try {
            const response = await this.guiaService.getDireccionCliente(codigo);
            if (response && response.success && response.data) {
                puntoLlegada.value = response.data.direcc || '';
                this.ubigeoLlegadaLocal = response.data.ubigeo || '';
                if (nombreClienteDestino) {
                    nombreClienteDestino.textContent = response.data.nombre || '';
                    nombreClienteDestino.title = response.data.nombre || '';
                }
            } else {
                puntoLlegada.value = '';
                if (nombreClienteDestino) {
                    nombreClienteDestino.textContent = '';
                    nombreClienteDestino.title = '';
                }
            }
        } catch (error) {
            console.error("Error al obtener la dirección del cliente de destino:", error);
            puntoLlegada.value = '';
            if (nombreClienteDestino) {
                nombreClienteDestino.textContent = '';
                nombreClienteDestino.title = '';
            }
        }
    }

    async procesarGalponesCencos(cencos) {
        const inputGalpon = document.getElementById('inputArtGalpon');
        const inputArtCodigo = document.getElementById('inputArtCodigo');

        if (!inputGalpon) return;

        const codigoArticulo = inputArtCodigo?.value || '';

        // Regla especial: Si el código del Artículo actual NO empieza con "PL", forzar galpón a "0"
        if (!codigoArticulo.toUpperCase().startsWith('PL')) {
            inputGalpon.value = '0';
            return;
        }

        if (!cencos) {
            inputGalpon.value = '';
            return;
        }

        try {
            const response = await this.guiaService.getGalpones(cencos);
            if (response && response.success && Array.isArray(response.data)) {
                const galpones = response.data;

                if (galpones.length === 0) {
                    window.Swal.fire({
                        icon: 'warning',
                        title: 'Sin galpones',
                        text: 'No hay galpones registrados para el Centro de Costo (Cencos) seleccionado.'
                    });
                    inputGalpon.value = '';
                } else if (galpones.length === 1) {
                    inputGalpon.value = galpones[0].galpon || '0';
                } else {
                    // Mostrar selector SweetAlert
                    const options = {};
                    galpones.forEach(g => {
                        options[g.galpon] = `Galpón: ${g.galpon}`;
                    });

                    window.Swal.fire({
                        title: 'Seleccionar Galpón',
                        text: 'Elija el galpón correspondiente:',
                        input: 'select',
                        inputOptions: options,
                        inputPlaceholder: '-- Seleccione un Galpón --',
                        showCancelButton: true,
                        confirmButtonText: 'Seleccionar',
                        cancelButtonText: 'Cancelar',
                        inputValidator: (value) => {
                            if (!value) {
                                return 'Debe seleccionar un galpón';
                            }
                        },
                        customClass: {
                            confirmButton: 'btn-primary px-4 py-2 bg-blue-600 text-white rounded-md mr-2',
                            cancelButton: 'btn-secondary px-4 py-2 bg-gray-250 text-gray-700 rounded-md'
                        },
                        buttonsStyling: false
                    }).then((result) => {
                        if (result.isConfirmed && result.value) {
                            inputGalpon.value = result.value;
                        }
                    });
                }
            } else {
                console.error("Error al obtener los galpones:", response?.message);
            }
        } catch (error) {
            console.error("Error en la petición de galpones:", error);
        }
    }

    agregarItemGrid() {
        const inputCodigo = document.getElementById('inputArtCodigo');
        const inputDescri = document.getElementById('inputArtDescri');
        const inputLote = document.getElementById('inputArtLote');
        const inputUnd = document.getElementById('inputArtUnd');
        const inputCant = document.getElementById('inputArtCant');
        const inputPeso = document.getElementById('inputArtPeso');
        const inputCencos = document.getElementById('inputArtCencos');
        const inputGalpon = document.getElementById('inputArtGalpon');
        const inputObservacion = document.getElementById('inputArtObservacion');

        if (!inputCodigo || !inputCant) return;

        const codigo = inputCodigo.value.trim();
        const descripcion = inputDescri ? inputDescri.value.trim() : '';
        const lote = inputLote ? inputLote.value.trim() : '';
        const unidad = inputUnd ? inputUnd.value.trim() : '';
        const cantidadVal = parseFloat(inputCant.value);
        const pesoVal = parseFloat(inputPeso ? inputPeso.value : 0) || 0;
        const cencos = inputCencos ? inputCencos.value.trim() : '';
        const galpon = inputGalpon ? inputGalpon.value.trim() : '';
        const observacion = inputObservacion ? inputObservacion.value.trim() : '';

        if (!codigo) {
            window.Swal.fire({
                icon: 'warning',
                title: 'Artículo requerido',
                text: 'Debe seleccionar un artículo para agregarlo a la grilla.'
            });
            return;
        }

        if (isNaN(cantidadVal) || cantidadVal <= 0) {
            window.Swal.fire({
                icon: 'warning',
                title: 'Cantidad inválida',
                text: 'Debe ingresar una cantidad mayor a cero.'
            });
            return;
        }

        // Validación estricta de peso según unidad
        let finalPesoVal = pesoVal;
        if (unidad.toUpperCase() === 'KGS') {
            if (isNaN(pesoVal) || pesoVal <= 0) {
                window.Swal.fire({
                    icon: 'error',
                    title: 'Peso Requerido',
                    text: 'El peso es obligatorio y debe ser mayor a 0 para la unidad KGS'
                });
                return;
            }
        } 

        // Formato de detalle adicional si empieza con PL
        let detalleAdicional = '';
        if (codigo.toUpperCase().startsWith('PL')) {
            detalleAdicional = `| Galpon: ${galpon} | Lote: ${lote} | Cantidad: ${cantidadVal}`;
        }

        const item = {
            codigo,
            descripcion,
            lote,
            unidad,
            cantidad: cantidadVal,
            peso: finalPesoVal,
            cencos,
            galpon,
            detalleAdicional,
            observacion
        };

        this.detalleItems.push(item);

        // Limpiar inputs
        inputCodigo.value = '';
        if (inputDescri) inputDescri.value = '';
        if (inputLote) {
            inputLote.value = '';
            inputLote.disabled = false; // Desbloquear por si acaso
        }
        if (inputUnd) inputUnd.value = '';
        if (inputCant) inputCant.value = '';
        if (inputPeso) inputPeso.value = '';
        if (inputCencos) inputCencos.value = '';
        if (inputGalpon) inputGalpon.value = '';
        if (inputObservacion) inputObservacion.value = '';

        // Enfocar primer campo
        inputCodigo.focus();

        // Renderizar y calcular
        this.renderizarGrid();
        this.calcularTotales();
    }

    renderizarGrid() {
        const tbody = document.querySelector('#tablaDetalleGuia tbody');
        if (!tbody) return;

        tbody.innerHTML = '';

        this.detalleItems.forEach((item, index) => {
            const tr = document.createElement('tr');
            tr.className = 'hover:bg-slate-50/50 transition-colors';
            tr.innerHTML = `
                <td class="px-3 py-2 text-center border-r border-slate-100 font-mono text-slate-400">${index + 1}</td>
                <td class="px-4 py-2 border-r border-slate-100 font-mono font-semibold text-slate-800">${item.codigo}</td>
                <td class="px-4 py-2 border-r border-slate-100 text-slate-700">${item.descripcion}</td>
                <td class="px-4 py-2 border-r border-slate-100 font-mono text-slate-650">${item.lote}</td>
                <td class="px-3 py-2 text-center border-r border-slate-100 font-mono text-slate-500">${item.unidad}</td>
                <td class="px-4 py-2 text-right border-r border-slate-100 font-mono font-semibold text-slate-800">${item.cantidad.toFixed(2)}</td>
                <td class="px-4 py-2 text-right border-r border-slate-100 font-mono font-semibold text-slate-800">${item.peso.toFixed(2)}</td>
                <td class="px-4 py-2 border-r border-slate-100 font-mono text-slate-600">${item.cencos}</td>
                <td class="px-4 py-2 border-r border-slate-100 font-mono text-slate-600">${item.galpon}</td>
                <td class="px-4 py-2 border-r border-slate-100 text-slate-600 truncate max-w-[120px]" title="${item.observacion || ''}">${item.observacion || ''}</td>
                <td class="px-3 py-2 text-center flex justify-center gap-3">
                    <button type="button" class="btn-editar-item text-amber-500 hover:text-amber-700 transition-colors" title="Editar">
                        <i class="fas fa-edit text-xs"></i>
                    </button>
                    <button type="button" class="btn-eliminar-item text-rose-500 hover:text-rose-700 transition-colors" title="Eliminar">
                        <i class="fas fa-trash-alt text-xs"></i>
                    </button>
                </td>
            `;

            const btnEdit = tr.querySelector('.btn-editar-item');
            if (btnEdit) {
                btnEdit.addEventListener('click', () => {
                    this.editarItemGrid(index);
                });
            }

            const btnDel = tr.querySelector('.btn-eliminar-item');
            if (btnDel) {
                btnDel.addEventListener('click', () => {
                    this.eliminarItemGrid(index);
                });
            }

            tbody.appendChild(tr);
        });
    }

    eliminarItemGrid(index) {
        this.detalleItems.splice(index, 1);
        this.renderizarGrid();
        this.calcularTotales();
    }

    editarItemGrid(index) {
        const item = this.detalleItems[index];
        if (!item) return;

        const inputCodigo = document.getElementById('inputArtCodigo');
        const inputDescri = document.getElementById('inputArtDescri');
        const inputLote = document.getElementById('inputArtLote');
        const inputUnd = document.getElementById('inputArtUnd');
        const inputCant = document.getElementById('inputArtCant');
        const inputPeso = document.getElementById('inputArtPeso');
        const inputCencos = document.getElementById('inputArtCencos');
        const inputGalpon = document.getElementById('inputArtGalpon');
        const inputObservacion = document.getElementById('inputArtObservacion');

        if (inputCodigo) inputCodigo.value = item.codigo;
        if (inputDescri) inputDescri.value = item.descripcion;
        if (inputLote) {
            inputLote.value = item.lote;
            inputLote.disabled = false;
        }
        if (inputUnd) inputUnd.value = item.unidad;
        if (inputCant) {
            inputCant.value = item.cantidad;
            this.stockMaximoPermitido = 999999999;
        }
        if (inputPeso) inputPeso.value = item.peso || '';
        if (inputCencos) inputCencos.value = item.cencos;
        if (inputGalpon) inputGalpon.value = item.galpon;
        if (inputObservacion) inputObservacion.value = item.observacion || '';

        const lblStockCant = document.getElementById('lblStockCantLote');
        const lblStockPeso = document.getElementById('lblStockPesoLote');
        if (lblStockCant) lblStockCant.textContent = '';
        if (lblStockPeso) lblStockPeso.textContent = '';

        this.detalleItems.splice(index, 1);
        this.renderizarGrid();
        this.calcularTotales();

        if (inputCant) {
            inputCant.focus();
            inputCant.select();
        }
    }

    calcularTotales() {
        let totalCantidad = 0;
        let totalPeso = 0;

        this.detalleItems.forEach(item => {
            // REGLA ESTRICTA: NO sumar la cantidad ni el peso si el código del artículo empieza con "M091"
            if (!item.codigo.toUpperCase().startsWith('M091')) {
                totalCantidad += item.cantidad;
                totalPeso += item.peso;
            }
        });

        // Actualizar labels del footer
        const lblTotalCantidad = document.getElementById('lblTotalCantidad');
        if (lblTotalCantidad) {
            lblTotalCantidad.textContent = totalCantidad.toFixed(2);
        }

        const lblTotalPeso = document.getElementById('lblTotalPeso');
        if (lblTotalPeso) {
            lblTotalPeso.textContent = totalPeso.toFixed(2);
        }

        // Actualizar inputs de cabecera de SUNAT
        const numBultos = document.getElementById('numBultos');
        if (numBultos) {
            numBultos.value = totalCantidad > 0 ? Math.round(totalCantidad) : '';
        }

        const pesoBrutoTotal = document.getElementById('pesoBrutoTotal');
        if (pesoBrutoTotal) {
            pesoBrutoTotal.value = totalPeso > 0 ? totalPeso.toFixed(2) : '';
        }
    }

    limpiarCamposGrid() {
        this.stockMaximoPermitido = 0;
        const inputCodigo = document.getElementById('inputArtCodigo');
        const inputDescri = document.getElementById('inputArtDescri');
        const inputLote = document.getElementById('inputArtLote');
        const inputUnd = document.getElementById('inputArtUnd');
        const inputCant = document.getElementById('inputArtCant');
        const inputPeso = document.getElementById('inputArtPeso');
        const inputCencos = document.getElementById('inputArtCencos');
        const inputGalpon = document.getElementById('inputArtGalpon');
        const inputObservacion = document.getElementById('inputArtObservacion');

        if (inputCodigo) inputCodigo.value = '';
        if (inputDescri) inputDescri.value = '';
        if (inputLote) {
            inputLote.value = '';
            inputLote.disabled = false;
        }
        if (inputUnd) inputUnd.value = '';
        if (inputCant) inputCant.value = '';
        if (inputPeso) inputPeso.value = '';
        if (inputCencos) inputCencos.value = '';
        if (inputGalpon) inputGalpon.value = '';
        if (inputObservacion) inputObservacion.value = '';

        const lblStockCant = document.getElementById('lblStockCantLote');
        const lblStockPeso = document.getElementById('lblStockPesoLote');
        if (lblStockCant) lblStockCant.textContent = '';
        if (lblStockPeso) lblStockPeso.textContent = '';

        if (inputCodigo) inputCodigo.focus();
    }

    async guardarDatos() {
        if (this.detalleItems.length === 0) {
            window.Swal.fire({
                icon: 'warning',
                title: 'Detalle vacío',
                text: 'Debe agregar al menos un ítem a la grilla antes de guardar.'
            });
            return;
        }

        // ... (Tu código de recolección de variables y validaciones se mantiene IGUAL) ...
        const valSerie = document.getElementById('serie')?.value?.trim() || '';
        const valClienteRuc = document.getElementById('clienteRuc')?.value?.trim() || '';
        const valMotivoTraslado = document.getElementById('motivoTraslado')?.value?.trim() || '';
        const valCodTransportista = document.getElementById('codTransportista')?.value?.trim() || '';
        const valCodConductor = document.getElementById('codConductor')?.value?.trim() || '';
        const valPlacaP = document.getElementById('placaP')?.value?.trim() || '';
        const valPuntoPartida = document.getElementById('puntoPartida')?.value?.trim() || '';
        const valPuntoLlegada = document.getElementById('puntoLlegada')?.value?.trim() || '';
        const valTipoTransporte = document.getElementById('tipoTransporte')?.value?.trim() || '';

        if (!valSerie || !valClienteRuc || !valMotivoTraslado || !valCodTransportista || !valCodConductor || !valPlacaP || !valPuntoPartida || !valPuntoLlegada || !valTipoTransporte) {
            window.Swal.fire({
                icon: 'warning',
                title: 'Campos Incompletos',
                text: 'Por favor, complete todos los campos obligatorios de la cabecera (Serie, Cliente, Motivo, Datos de Transporte y Direcciones).'
            });
            return;
        }

        const fechaEmisionVal = document.getElementById('fechaEmision')?.value || '';
        const fechaTrasladoVal = document.getElementById('fechaTraslado')?.value || '';
        const tipoTransporteVal = valTipoTransporte;
        const motivoTrasladoVal = valMotivoTraslado;
        const motivoTrasladoOtrosVal = document.getElementById('motivoTrasladoOtros')?.value || '';
        const clienteRucVal = valClienteRuc;

        if (fechaTrasladoVal && fechaEmisionVal && fechaTrasladoVal < fechaEmisionVal) {
            window.Swal.fire({
                icon: 'warning',
                title: 'Fecha inválida',
                text: 'La fecha de inicio de traslado no puede ser menor a la fecha de emisión.'
            });
            return;
        }

        if (tipoTransporteVal === '02') {
            window.Swal.fire({
                icon: 'warning',
                title: 'Transporte privado no permitido',
                text: 'No se permite registrar transporte privado.'
            });
            return;
        }

        if (motivoTrasladoVal === '13' && !motivoTrasladoOtrosVal.trim()) {
            window.Swal.fire({
                icon: 'warning',
                title: 'Especificar Motivo',
                text: 'El campo "Especificar Motivo" es obligatorio cuando el motivo de traslado es "Otros".'
            });
            return;
        }

        if ((motivoTrasladoVal === '18' || motivoTrasladoVal === '09') && clienteRucVal === '20419158462') {
            window.Swal.fire({
                icon: 'warning',
                title: 'Cliente no permitido',
                text: 'Para este motivo de traslado, el cliente no puede ser Granja Rinconada.'
            });
            return;
        }

        const motivosExcluidos = ['01', '14', '18', '09', '13'];
        if (!motivosExcluidos.includes(motivoTrasladoVal) && clienteRucVal !== '20419158462') {
            window.Swal.fire({
                icon: 'warning',
                title: 'Cliente incorrecto',
                text: 'Para el motivo de traslado seleccionado, el cliente debe ser obligatoriamente Granja Rinconada.'
            });
            return;
        }

        const confirm = await window.Swal.fire({
            title: '¿Confirmar guardado?',
            text: 'Seleccione una opción para guardar la guía en la base de datos.',
            icon: 'question',
            showCancelButton: true,
            showDenyButton: true,
            confirmButtonText: 'Guardar e Imprimir',
            denyButtonText: 'Solo Guardar',
            cancelButtonText: 'Cancelar',
            confirmButtonColor: '#3085d6',
            denyButtonColor: '#4f46e5'
        });

        if (confirm.isDismissed) return;
        const imprimirAlGuardar = confirm.isConfirmed;
        // NUEVO: Definir la acción en texto para enviarla al backend
        const accionSeleccionada = imprimirAlGuardar ? 'imprimir' : 'guardar';

        let pdfWindow = null;
        if (imprimirAlGuardar) {
            pdfWindow = window.open('', '_blank');
            if (pdfWindow) {
                pdfWindow.document.write('<html><head><title>Generando PDF...</title></head><body style="display:flex;justify-content:center;align-items:center;height:100vh;font-family:sans-serif;color:#4b5563;background-color:#f9fafb;"><div style="text-align:center;"><h2>Generando PDF de la Guía...</h2><p>Conectando con SUNAT/NubeFact, espere un momento por favor.</p></div></body></html>');
                pdfWindow.document.close();
            }
        }

        const transaccion = document.getElementById('transaccion')?.value || ''; 
        const zonaOrigen = document.getElementById('zonaOrigen')?.value || '';
        const zonaDestino = document.getElementById('zonaDestino')?.value || '';
        const clienteRuc = clienteRucVal;
        const serie = valSerie;
        const numeroGuia = document.getElementById('numeroGuia')?.value || '';
        const fechaEmision = fechaEmisionVal;
        const fechaTraslado = fechaTrasladoVal;
        const observaciones = document.getElementById('observaciones')?.value || '';
        const codTransportista = valCodTransportista;
        const nombreTransportista = document.getElementById('nomTransportista')?.value?.trim() || '';
        const nombreConductor = document.getElementById('nomConductor')?.value?.trim() || '';
        const licenciaConductor = document.getElementById('licenciaCond')?.value?.trim() || '';
        const codConductor = valCodConductor;
        const placaP = valPlacaP;
        const placaR = document.getElementById('placaR')?.value || '';
        const clienteOrigen = document.getElementById('clienteOrigen')?.value || '';
        const clienteDestino = document.getElementById('clienteDestino')?.value || '';
        const tipoTransporte = tipoTransporteVal;
        const motivoTraslado = motivoTrasladoVal;
        const motivoTrasladoOtros = motivoTrasladoOtrosVal;
        
        const lblTotalCantidad = document.getElementById('lblTotalCantidad')?.textContent || '0';
        const lblTotalPeso = document.getElementById('lblTotalPeso')?.textContent || '0';
        const totalCantidad = parseFloat(lblTotalCantidad) || 0;
        const totalPeso = parseFloat(lblTotalPeso) || 0;

        const payload = {
            cabecera: {
                transaccion,
                zonaOrigen,
                zonaDestino,
                clienteRuc,
                serie,
                numeroGuia,
                fechaEmision,
                fechaTraslado,
                observaciones,
                codTransportista,
                nombreTransportista,
                codConductor,
                nombreConductor,     
                licenciaConductor,   
                placaP,
                placaR,
                clienteOrigen,
                clienteDestino,
                tipoTransporte,
                motivoTraslado,
                motivoTrasladoOtros,
                totalCantidad,
                totalPeso,
                puntoPartida: valPuntoPartida,
                puntoLlegada: valPuntoLlegada,
                ubigeoPartida: this.ubigeoPartidaLocal || '',
                ubigeoLlegada: this.ubigeoLlegadaLocal || ''
            },
            detalle: this.detalleItems,
            accion: accionSeleccionada 
        };

        try {
            window.Swal.fire({
                title: 'Procesando Guía',
                html: 'Guardando localmente y conectando con NubeFact...<br><br><small>Esto puede tomar unos segundos.</small>',
                allowOutsideClick: false,
                didOpen: () => {
                    window.Swal.showLoading();
                }
            });

            // Llamada al backend
            const response = await this.guiaService.guardarGuia(payload);

            if (response && response.success) {
                
                // --- NUEVA LÓGICA DE MANEJO DE NUBEFACT ---
                let mensajeExito = 'La guía ha sido guardada correctamente en el sistema local.';
                let urlPdfFinal = null;

                // Analizar la respuesta de NubeFact si existe
                if (response.nubefact) {
                    if (response.nubefact.errors || response.nubefact.sunat_description) {
                         // NubeFact o SUNAT arrojaron un error (Ej: Guía ya existe, RUC inválido, etc.)
                         const errorMsg = response.nubefact.errors || response.nubefact.sunat_description;
                         mensajeExito = `Guardado local OK.<br><br><b style="color:red;">Error SUNAT/NubeFact:</b> ${errorMsg}`;
                    } 
                    else if (response.nubefact.enlace_del_pdf) {
                         // NubeFact aprobó y generó el PDF oficial
                         urlPdfFinal = response.nubefact.enlace_del_pdf;
                         mensajeExito = `Guardado local OK y aceptado por SUNAT.`;
                    }
                    else if (response.nubefact.aceptada_por_sunat === false) {
                         // NubeFact recibió pero SUNAT aún está procesando
                         mensajeExito = `Guardado local OK.<br>Enviado a NubeFact. <b>SUNAT procesando...</b>`;
                    }
                }

                // Manejo de la ventana de impresión
                if (imprimirAlGuardar && pdfWindow && response.treg) {
                    // Si NubeFact dio link, usamos ese. Si no, usamos nuestro FPDF clonado como respaldo.
                    if (urlPdfFinal) {
                        pdfWindow.location.href = urlPdfFinal;
                    } else {
                        const printUrl = `/plantaincubacion/backend/index.php/api/lista-guia-electronica/pdf?treg=${encodeURIComponent(response.treg)}`;
                        pdfWindow.location.href = printUrl;
                    }
                } else if (pdfWindow) {
                    pdfWindow.close();
                }

                // Mostrar la alerta final al usuario
                await window.Swal.fire({
                    icon: (response.nubefact && (response.nubefact.errors || response.nubefact.sunat_description)) ? 'warning' : 'success',
                    title: 'Resultado del Proceso',
                    html: mensajeExito
                });
                
                // ... (El código de limpieza de campos se mantiene IGUAL) ...
                this.detalleItems = [];
                this.renderizarGrid();
                this.calcularTotales();
                
                const inputsToClear = [
                    'codTransportista', 'nomTransportista',
                    'codConductor', 'nomConductor', 'licenciaCond', 'placaP', 'placaR',
                    'observaciones', 'clienteOrigen', 'clienteDestino', 'puntoPartida', 'puntoLlegada',
                    'tipoTransporte', 'zonaOrigen', 'zonaDestino'
                ];
                inputsToClear.forEach(id => {
                    const el = document.getElementById(id);
                    if (el) el.value = '';
                });

                const labelsToClear = [
                    'nombreClienteOrigen', 'nombreClienteDestino',
                    'lblStockCantLote', 'lblStockPesoLote'
                ];
                labelsToClear.forEach(id => {
                    const el = document.getElementById(id);
                    if (el) {
                        el.textContent = '';
                        el.title = '';
                    }
                });

                const selectSerie = document.getElementById('serie');
                if (selectSerie) selectSerie.value = '';
                this.actualizarCamposSerie();

                const selectMotivo = document.getElementById('motivoTraslado');
                if (selectMotivo) {
                    selectMotivo.value = '';
                    selectMotivo.dispatchEvent(new Event('change'));
                }

                document.getElementById('tipoEnvioAlmacen')?.focus();
            } else {
                if (pdfWindow) pdfWindow.close();
                window.Swal.fire({
                    icon: 'error',
                    title: 'Error al guardar',
                    text: response?.message || 'Ocurrió un error inesperado al guardar la guía.'
                });
            }
        } catch (error) {
            if (pdfWindow) pdfWindow.close();
            console.error("Error al guardar la guía:", error);
            window.Swal.fire({
                icon: 'error',
                title: 'Error de red',
                text: 'No se pudo conectar con el servidor para guardar la guía.'
            });
        }
    }
}

window.GuiaElectronicaController = GuiaElectronicaController;
