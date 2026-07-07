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
        setInterval(() => {
            const hoy = getHoy();
            if (hoy !== fechaActual) {
                fechaActual = hoy;
                const inputFechaEmision = document.getElementById('fechaEmision');
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

        // Configurar los triggers dinámicos de búsqueda
        Object.keys(this.busquedasConfig).forEach(inputId => {
            const inputElement = document.getElementById(inputId);
            if (inputElement) {
                inputElement.addEventListener('keydown', (e) => {
                    if (e.key === 'Enter') {
                        e.preventDefault();
                        this.abrirBuscadorDinamico(inputId);
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

                // Lógica de auto-completado de Cliente
                const motivosExcluidos = ['01', '14', '18', '09', '13'];
                const inputClienteRuc = document.getElementById('clienteRuc');
                const inputClienteNombre = document.getElementById('clienteNombre');

                if (motivoVal && !motivosExcluidos.includes(motivoVal)) {
                    if (inputClienteRuc) inputClienteRuc.value = '20419158462';
                    if (inputClienteNombre) inputClienteNombre.value = 'GRANJA RINCONADA DEL SUR S.A.';
                } else if (motivosExcluidos.includes(motivoVal)) {
                    if (inputClienteRuc) inputClienteRuc.value = '';
                    if (inputClienteNombre) inputClienteNombre.value = '';
                }
            });
        }

        // Listener para guardar los datos
        const btnGuardar = document.getElementById('btn-guardar');
        if (btnGuardar) {
            btnGuardar.addEventListener('click', () => this.guardarDatos());
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

        // Actualizar placeholder del input
        const buscarInput = document.getElementById('buscar-transportista');
        if (buscarInput) {
            buscarInput.placeholder = config.placeholder;
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
        if (buscarInput) buscarInput.focus();

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
                            this.procesarLotesArticulo(item.codigo);
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
                                const buscarInput = document.getElementById('buscar-transportista');
                                if (buscarInput) buscarInput.focus();
                            }
                        } else if (e.key === 'Enter') {
                            e.preventDefault();
                            const inputId = this.activeSearchInputId;
                            this.activeSearchConfig.onSelect(item);
                            if (inputId === 'inputArtCodigo') {
                                this.cerrarBuscadorDinamico(false); // Cerrar sin avanzar
                                this.procesarLotesArticulo(item.codigo);
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
                }
                else if (lotes.length === 1) {
                    inputLote.disabled = false;
                    inputLote.value = lotes[0].lote || '';
                    if (inputCant) {
                        inputCant.focus();
                        inputCant.select();
                    }
                }
                else {
                    inputLote.disabled = false;

                    const options = {};
                    lotes.forEach(l => {
                        options[l.lote] = `Lote: ${l.lote} (Stock: ${l.stock_cantidad} | Peso: ${l.stock_peso})`;
                    });

                    window.Swal.fire({
                        title: 'Seleccionar Lote',
                        text: 'Múltiples lotes disponibles. Elija uno:',
                        input: 'select',
                        inputOptions: options,
                        inputPlaceholder: '-- Seleccione un Lote --',
                        showCancelButton: true,
                        confirmButtonText: 'Seleccionar',
                        cancelButtonText: 'Cancelar',
                        inputValidator: (value) => {
                            if (!value) {
                                return 'Debe seleccionar un lote';
                            }
                        },
                        customClass: {
                            confirmButton: 'btn-primary px-4 py-2 bg-blue-600 text-white rounded-md mr-2',
                            cancelButton: 'btn-secondary px-4 py-2 bg-gray-250 text-gray-700 rounded-md'
                        },
                        buttonsStyling: false
                    }).then((result) => {
                        if (result.isConfirmed && result.value) {
                            inputLote.value = result.value;
                            if (inputCant) {
                                inputCant.focus();
                                inputCant.select();
                            }
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
        if (!puntoPartida) return;

        if (!codigo) {
            puntoPartida.value = '';
            return;
        }

        try {
            const response = await this.guiaService.getDireccionCliente(codigo);
            if (response && response.success && response.data) {
                puntoPartida.value = response.data.direcc || '';
            } else {
                puntoPartida.value = '';
            }
        } catch (error) {
            console.error("Error al obtener la dirección del cliente de origen:", error);
            puntoPartida.value = '';
        }
    }

    async cargarDireccionClienteDestino(codigo) {
        const puntoLlegada = document.getElementById('puntoLlegada');
        if (!puntoLlegada) return;

        if (!codigo) {
            puntoLlegada.value = '';
            return;
        }

        try {
            const response = await this.guiaService.getDireccionCliente(codigo);
            if (response && response.success && response.data) {
                puntoLlegada.value = response.data.direcc || '';
            } else {
                puntoLlegada.value = '';
            }
        } catch (error) {
            console.error("Error al obtener la dirección del cliente de destino:", error);
            puntoLlegada.value = '';
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

        if (!inputCodigo || !inputCant) return;

        const codigo = inputCodigo.value.trim();
        const descripcion = inputDescri ? inputDescri.value.trim() : '';
        const lote = inputLote ? inputLote.value.trim() : '';
        const unidad = inputUnd ? inputUnd.value.trim() : '';
        const cantidadVal = parseFloat(inputCant.value);
        const pesoVal = parseFloat(inputPeso ? inputPeso.value : 0) || 0;
        const cencos = inputCencos ? inputCencos.value.trim() : '';
        const galpon = inputGalpon ? inputGalpon.value.trim() : '';

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
            peso: pesoVal,
            cencos,
            galpon,
            detalleAdicional
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
                <td class="px-3 py-2 text-center">
                    <button type="button" class="btn-eliminar-item text-rose-500 hover:text-rose-700 transition-colors">
                        <i class="fas fa-trash-alt text-xs"></i>
                    </button>
                </td>
            `;

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
            numBultos.value = Math.round(totalCantidad);
        }

        const pesoBrutoTotal = document.getElementById('pesoBrutoTotal');
        if (pesoBrutoTotal) {
            pesoBrutoTotal.value = totalPeso.toFixed(2);
        }
    }

    limpiarCamposGrid() {
        const inputCodigo = document.getElementById('inputArtCodigo');
        const inputDescri = document.getElementById('inputArtDescri');
        const inputLote = document.getElementById('inputArtLote');
        const inputUnd = document.getElementById('inputArtUnd');
        const inputCant = document.getElementById('inputArtCant');
        const inputPeso = document.getElementById('inputArtPeso');
        const inputCencos = document.getElementById('inputArtCencos');
        const inputGalpon = document.getElementById('inputArtGalpon');

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

        // Recolectar valores de los campos obligatorios de cabecera
        const valSerie = document.getElementById('serie')?.value?.trim() || '';
        const valClienteRuc = document.getElementById('clienteRuc')?.value?.trim() || '';
        const valMotivoTraslado = document.getElementById('motivoTraslado')?.value?.trim() || '';
        const valCodTransportista = document.getElementById('codTransportista')?.value?.trim() || '';
        const valCodConductor = document.getElementById('codConductor')?.value?.trim() || '';
        const valPlacaP = document.getElementById('placaP')?.value?.trim() || '';
        const valPuntoPartida = document.getElementById('puntoPartida')?.value?.trim() || '';
        const valPuntoLlegada = document.getElementById('puntoLlegada')?.value?.trim() || '';
        const valTipoTransporte = document.getElementById('tipoTransporte')?.value?.trim() || '';

        // Validación de campos obligatorios
        if (!valSerie || !valClienteRuc || !valMotivoTraslado || !valCodTransportista || !valCodConductor || !valPlacaP || !valPuntoPartida || !valPuntoLlegada || !valTipoTransporte) {
            window.Swal.fire({
                icon: 'warning',
                title: 'Campos Incompletos',
                text: 'Por favor, complete todos los campos obligatorios de la cabecera (Serie, Cliente, Motivo, Datos de Transporte y Direcciones).'
            });
            return;
        }

        // Obtener valores para las demás validaciones
        const fechaEmisionVal = document.getElementById('fechaEmision')?.value || '';
        const fechaTrasladoVal = document.getElementById('fechaTraslado')?.value || '';
        const tipoTransporteVal = valTipoTransporte;
        const motivoTrasladoVal = valMotivoTraslado;
        const motivoTrasladoOtrosVal = document.getElementById('motivoTrasladoOtros')?.value || '';
        const clienteRucVal = valClienteRuc;

        // Validaciones de Guardado (usar Swal.fire y hacer return si fallan)
        // 1. Fechas: Validar que fechaTraslado NO sea menor a fechaEmision.
        if (fechaTrasladoVal && fechaEmisionVal && fechaTrasladoVal < fechaEmisionVal) {
            window.Swal.fire({
                icon: 'warning',
                title: 'Fecha inválida',
                text: 'La fecha de inicio de traslado no puede ser menor a la fecha de emisión.'
            });
            return;
        }

        // 2. Tipo Transporte: Si tipoTransporte es '02' (Privado), detener y mostrar alerta
        if (tipoTransporteVal === '02') {
            window.Swal.fire({
                icon: 'warning',
                title: 'Transporte privado no permitido',
                text: 'No se permite registrar transporte privado.'
            });
            return;
        }

        // 3. Obligatoriedad Motivo '13': Si motivoTraslado es '13', validar que motivoTrasladoOtros no esté vacío.
        if (motivoTrasladoVal === '13' && !motivoTrasladoOtrosVal.trim()) {
            window.Swal.fire({
                icon: 'warning',
                title: 'Especificar Motivo',
                text: 'El campo "Especificar Motivo" es obligatorio cuando el motivo de traslado es "Otros".'
            });
            return;
        }

        // 4. Lógica de Cliente (Granja Rinconada = '20419158462')
        // - Si el motivo es '18' o '09': El clienteRuc NO puede ser '20419158462'.
        if ((motivoTrasladoVal === '18' || motivoTrasladoVal === '09') && clienteRucVal === '20419158462') {
            window.Swal.fire({
                icon: 'warning',
                title: 'Cliente no permitido',
                text: 'Para este motivo de traslado, el cliente no puede ser Granja Rinconada.'
            });
            return;
        }

        // - Si el motivo NO es '01', '14', '18', '09' ni '13': El clienteRuc DEBE ser obligatoriamente '20419158462'.
        const motivosExcluidos = ['01', '14', '18', '09', '13'];
        if (!motivosExcluidos.includes(motivoTrasladoVal) && clienteRucVal !== '20419158462') {
            window.Swal.fire({
                icon: 'warning',
                title: 'Cliente incorrecto',
                text: 'Para el motivo de traslado seleccionado, el cliente debe ser obligatoriamente Granja Rinconada.'
            });
            return;
        }

        // Confirmación
        const confirm = await window.Swal.fire({
            title: '¿Confirmar guardado?',
            text: 'Se procederá a guardar la cabecera y el detalle en la base de datos.',
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: 'Sí, guardar',
            cancelButtonText: 'Cancelar'
        });

        if (!confirm.isConfirmed) return;

        // Recolectar datos de cabecera
        const transaccion = document.getElementById('transaccion')?.value || ''; // S440 o S400
        const zonaOrigen = document.getElementById('zonaOrigen')?.value || '';
        const zonaDestino = document.getElementById('zonaDestino')?.value || '';
        const clienteRuc = clienteRucVal;
        const serie = valSerie;
        const numeroGuia = document.getElementById('numeroGuia')?.value || '';
        const fechaEmision = fechaEmisionVal;
        const fechaTraslado = fechaTrasladoVal;
        const observaciones = document.getElementById('observaciones')?.value || '';
        const codTransportista = valCodTransportista;
        const codConductor = valCodConductor;
        const placaP = valPlacaP;
        const placaR = document.getElementById('placaR')?.value || '';
        const clienteOrigen = document.getElementById('clienteOrigen')?.value || '';
        const clienteDestino = document.getElementById('clienteDestino')?.value || '';
        const tipoTransporte = tipoTransporteVal;
        const motivoTraslado = motivoTrasladoVal;
        const motivoTrasladoOtros = motivoTrasladoOtrosVal;
        
        // Totales calculados en la grilla
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
                codConductor,
                placaP,
                placaR,
                clienteOrigen,
                clienteDestino,
                tipoTransporte,
                motivoTraslado,
                motivoTrasladoOtros,
                totalCantidad,
                totalPeso
            },
            detalle: this.detalleItems
        };

        try {
            window.Swal.fire({
                title: 'Guardando...',
                text: 'Espere por favor',
                allowOutsideClick: false,
                didOpen: () => {
                    window.Swal.showLoading();
                }
            });

            const response = await this.guiaService.guardarGuia(payload);

            if (response && response.success) {
                await window.Swal.fire({
                    icon: 'success',
                    title: '¡Guardado!',
                    text: response.message || 'La guía ha sido guardada correctamente.'
                });
                
                // Limpiar todo tras guardar exitosamente
                this.detalleItems = [];
                this.renderizarGrid();
                this.calcularTotales();
                
                // Limpiar cabecera
                const inputsToClear = [
                    'clienteRuc', 'clienteNombre', 'codTransportista', 'nomTransportista',
                    'codConductor', 'nomConductor', 'licenciaCond', 'placaP', 'placaR',
                    'observaciones', 'clienteOrigen', 'clienteDestino', 'puntoPartida', 'puntoLlegada'
                ];
                inputsToClear.forEach(id => {
                    const el = document.getElementById(id);
                    if (el) el.value = '';
                });

                // Reset de series/correlativo
                const selectSerie = document.getElementById('serie');
                if (selectSerie) selectSerie.value = '';
                this.actualizarCamposSerie();

                // Limpiar motivo traslado
                const selectMotivo = document.getElementById('motivoTraslado');
                if (selectMotivo) {
                    selectMotivo.value = '';
                    selectMotivo.dispatchEvent(new Event('change'));
                }

                // Foco al inicio
                document.getElementById('tipoEnvioAlmacen')?.focus();
            } else {
                window.Swal.fire({
                    icon: 'error',
                    title: 'Error al guardar',
                    text: response?.message || 'Ocurrió un error inesperado al guardar la guía.'
                });
            }
        } catch (error) {
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
