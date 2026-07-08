// guiaElectronica.config.js
// Configuración de búsquedas dinámicas para el módulo de Guía de Remisión Electrónica
const BUSQUEDAS_CONFIG = {
    'codTransportista': {
        title: 'Buscar Transportista',
        iconClass: 'fa-solid fa-truck',
        placeholder: 'Escriba RUC o Razón Social para buscar...',
        headers: ['N°', 'RUC', 'Nombre / Razón Social', 'TUC', 'Estado'],
        fetchData: (service, query) => service.getTransportistas(query),
        renderRow: (item, index) => {
            const estado = (item.testado || item.estado || 'A').toUpperCase();
            const isActivo = (estado === 'A' || estado === 'ACTIVO');
            const estadoTexto = isActivo ? 'ACTIVO' : 'INACTIVO';
            const badgeClass = isActivo 
                ? 'bg-emerald-50 text-emerald-700 border-emerald-250/65' 
                : 'bg-slate-100 text-slate-650 border-slate-200';

            return `
                <td class="px-4 py-2.5 text-center w-12 border-r border-slate-100 font-mono text-slate-400">${index + 1}</td>
                <td class="px-4 py-2.5 border-r border-slate-100 font-semibold text-slate-800 font-mono">${item.ruc}</td>
                <td class="px-4 py-2.5 border-r border-slate-100 font-medium text-slate-700">${item.nombre}</td>
                <td class="px-4 py-2.5 border-r border-slate-100 text-slate-500 font-mono">${item.tuc || '-'}</td>
                <td class="px-4 py-2.5 text-center">
                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold border ${badgeClass}">
                        ${estadoTexto}
                    </span>
                </td>
            `;
        },
        onSelect: (item) => {
            const codInput = document.getElementById('codTransportista');
            const nomInput = document.getElementById('nomTransportista');
            if (codInput) codInput.value = item.ruc;
            if (nomInput) nomInput.value = item.nombre;
        }
    },
    'codConductor': {
        title: 'Buscar Conductor',
        iconClass: 'fa-solid fa-id-card',
        placeholder: 'Escriba Código, Nombre o Licencia para buscar...',
        headers: ['N°', 'DNI', 'Nombre Conductor', 'Licencia', 'Estado'],
        fetchData: (service, query) => {
            const transportista = document.getElementById('codTransportista')?.value || '';
            const chkMostrarTodos = document.getElementById('chk-mostrar-todos-conductores');
            const mostrarTodos = chkMostrarTodos ? chkMostrarTodos.checked : false;
            return service.getConductores(query, transportista, mostrarTodos);
        },
        renderRow: (item, index) => {
            const estado = (item.testado || item.estado || 'A').toUpperCase();
            const isActivo = (estado === 'A' || estado === 'ACTIVO');
            const estadoTexto = isActivo ? 'ACTIVO' : 'INACTIVO';
            const badgeClass = isActivo 
                ? 'bg-emerald-50 text-emerald-700 border-emerald-250/65' 
                : 'bg-slate-100 text-slate-650 border-slate-200';

            return `
                <td class="px-4 py-2.5 text-center w-12 border-r border-slate-100 font-mono text-slate-400">${index + 1}</td>
                <td class="px-4 py-2.5 border-r border-slate-100 font-semibold text-slate-800 font-mono">${item.dni || '-'}</td>
                <td class="px-4 py-2.5 border-r border-slate-100 font-medium text-slate-700">${item.nombre || ''}</td>
                <td class="px-4 py-2.5 border-r border-slate-100 text-slate-500 font-mono">${item.licencia || '-'}</td>
                <td class="px-4 py-2.5 text-center">
                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold border ${badgeClass}">
                        ${estadoTexto}
                    </span>
                </td>
            `;
        },
        onSelect: (item) => {
            const codInput = document.getElementById('codConductor');
            const nomInput = document.getElementById('nomConductor');
            const licInput = document.getElementById('licenciaCond');
            if (codInput) codInput.value = item.dni || '';
            if (nomInput) nomInput.value = item.nombre || '';
            if (licInput) licInput.value = item.licencia || '';
        }
    },
    'placaP': {
        title: 'Buscar Placa Vehículo P',
        iconClass: 'fa-solid fa-truck-pickup',
        placeholder: 'Escriba Placa o Marca para buscar...',
        headers: ['N°', 'Placa', 'Marca', 'C. Inscripción', 'Conf. Vehicular', 'SOAT', 'F. Venc. SOAT'],
        fetchData: (service, query) => {
            const transportista = document.getElementById('codTransportista')?.value || '';
            if (!transportista.trim()) {
                window.Swal.fire({
                    icon: 'warning',
                    title: 'Transportista Requerido',
                    text: 'Debe seleccionar un transportista antes de buscar la placa.'
                });
                const modal = document.getElementById('modal-transportistas');
                if (modal) {
                    modal.style.display = 'none';
                    modal.classList.remove('show');
                }
                return { success: true, data: [] };
            }
            return service.getCamiones(query, transportista);
        },
        renderRow: (item, index) => {
            return `
                <td class="px-4 py-2.5 text-center w-12 border-r border-slate-100 font-mono text-slate-400">${index + 1}</td>
                <td class="px-4 py-2.5 border-r border-slate-100 font-semibold text-slate-800 font-mono">${item.placa}</td>
                <td class="px-4 py-2.5 border-r border-slate-100 font-medium text-slate-700">${item.marca || '-'}</td>
                <td class="px-4 py-2.5 border-r border-slate-100 text-slate-500 font-mono">${item.cinscripcion || '-'}</td>
                <td class="px-4 py-2.5 border-r border-slate-100 text-slate-500 font-mono">${item.confvehicular || '-'}</td>
                <td class="px-4 py-2.5 border-r border-slate-100 text-slate-500 font-mono">${item.soat || '-'}</td>
                <td class="px-4 py-2.5 text-center font-mono text-slate-500">${item.fechaisoat || '-'}</td>
            `;
        },
        onSelect: (item) => {
            const placaInput = document.getElementById('placaP');
            if (placaInput) {
                placaInput.value = item.placa;
                placaInput.dispatchEvent(new Event('change'));
                placaInput.dispatchEvent(new Event('blur'));
            }
        }
    },
    'placaR': {
        title: 'Buscar Placa R',
        iconClass: 'fa-solid fa-trailer',
        placeholder: 'Escriba Placa o Marca para buscar...',
        headers: ['N°', 'Placa', 'Marca', 'C. Inscripción', 'Conf. Vehicular', 'SOAT', 'F. Venc. SOAT'],
        fetchData: (service, query) => {
            const transportista = document.getElementById('codTransportista')?.value || '';
            if (!transportista.trim()) {
                window.Swal.fire({
                    icon: 'warning',
                    title: 'Transportista Requerido',
                    text: 'Debe seleccionar un transportista antes de buscar la placa.'
                });
                const modal = document.getElementById('modal-transportistas');
                if (modal) {
                    modal.style.display = 'none';
                    modal.classList.remove('show');
                }
                return { success: true, data: [] };
            }
            return service.getCamiones(query, transportista);
        },
        renderRow: (item, index) => {
            return `
                <td class="px-4 py-2.5 text-center w-12 border-r border-slate-100 font-mono text-slate-400">${index + 1}</td>
                <td class="px-4 py-2.5 border-r border-slate-100 font-semibold text-slate-800 font-mono">${item.placa}</td>
                <td class="px-4 py-2.5 border-r border-slate-100 font-medium text-slate-700">${item.marca || '-'}</td>
                <td class="px-4 py-2.5 border-r border-slate-100 text-slate-500 font-mono">${item.cinscripcion || '-'}</td>
                <td class="px-4 py-2.5 border-r border-slate-100 text-slate-500 font-mono">${item.confvehicular || '-'}</td>
                <td class="px-4 py-2.5 border-r border-slate-100 text-slate-500 font-mono">${item.soat || '-'}</td>
                <td class="px-4 py-2.5 text-center font-mono text-slate-500">${item.fechaisoat || '-'}</td>
            `;
        },
        onSelect: (item) => {
            const placaInput = document.getElementById('placaR');
            if (placaInput) {
                placaInput.value = item.placa;
                placaInput.dispatchEvent(new Event('change'));
                placaInput.dispatchEvent(new Event('blur'));
            }
        }
    },
    'clienteOrigen': {
        title: 'Buscar Cliente Origen',
        iconClass: 'fa-solid fa-user-tag',
        placeholder: 'Escribe codigo o nombre para buscar...',
        headers: ['N°', 'Codigo', 'Nombre', 'Direccion'],
        fetchData: (service, query) => service.getClientes(query),
        renderRow: (item, index) => {
            return `
                <td class="px-4 py-2.5 text-center w-12 border-r border-slate-100 font-mono text-slate-400">${index + 1}</td>
                <td class="px-4 py-2.5 border-r border-slate-100 font-semibold text-slate-800 font-mono">${item.codigo}</td>
                <td class="px-4 py-2.5 border-r border-slate-100 font-medium text-slate-700">${item.nombre || '-'}</td>
                <td class="px-4 py-2.5 border-r border-slate-100 text-slate-500 font-mono">${item.direccion || '-'}</td>
            `;
        },
        onSelect: (item) => {
            const clienteOrigen = document.getElementById('clienteOrigen');
            if (clienteOrigen) clienteOrigen.value = item.codigo;
            const nombreEl = document.getElementById('nombreClienteOrigen');
            if (nombreEl) {
                nombreEl.textContent = item.nombre || '';
                nombreEl.title = item.nombre || '';
            }
        }
    },
    'clienteDestino': {
        title: 'Buscar Cliente Destino',
        iconClass: 'fa-solid fa-user-tag',
        placeholder: 'Escribe codigo o nombre para buscar...',
        headers: ['N°', 'Codigo', 'Nombre', 'Direccion'],
        fetchData: (service, query) => service.getClientes(query),
        renderRow: (item, index) => {
            return `
                <td class="px-4 py-2.5 text-center w-12 border-r border-slate-100 font-mono text-slate-400">${index + 1}</td>
                <td class="px-4 py-2.5 border-r border-slate-100 font-semibold text-slate-800 font-mono">${item.codigo}</td>
                <td class="px-4 py-2.5 border-r border-slate-100 font-medium text-slate-700">${item.nombre || '-'}</td>
                <td class="px-4 py-2.5 border-r border-slate-100 text-slate-500 font-mono">${item.direccion || '-'}</td>
            `;
        },
        onSelect: (item) => {
            const clienteDestino = document.getElementById('clienteDestino');
            if (clienteDestino) clienteDestino.value = item.codigo;
            const nombreEl = document.getElementById('nombreClienteDestino');
            if (nombreEl) {
                nombreEl.textContent = item.nombre || '';
                nombreEl.title = item.nombre || '';
            }
        }

    },
    'clienteRuc': {
        title: 'Buscar Cliente',
        iconClass: 'fa-solid fa-user-tag',
        placeholder: 'Escribe codigo o nombre para buscar...',
        headers: ['N°', 'Codigo', 'Nombre', 'Direccion'],
        fetchData: (service, query) => service.getClientes(query),
        renderRow: (item, index) => {
            return `
                <td class="px-4 py-2.5 text-center w-12 border-r border-slate-100 font-mono text-slate-400">${index + 1}</td>
                <td class="px-4 py-2.5 border-r border-slate-100 font-semibold text-slate-800 font-mono">${item.codigo}</td>
                <td class="px-4 py-2.5 border-r border-slate-100 font-medium text-slate-700">${item.nombre || '-'}</td>
                <td class="px-4 py-2.5 border-r border-slate-100 text-slate-500 font-mono">${item.direccion || '-'}</td>
            `;
        },
        onSelect: (item) => {
            const clienteRuc = document.getElementById('clienteRuc');
            if (clienteRuc) clienteRuc.value = item.codigo;
            const clienteNombre = document.getElementById('clienteNombre');
            if (clienteNombre) clienteNombre.value = item.nombre;
        }

    },
    'inputArtCodigo': {
        title: 'Buscar Artículo por Código',
        iconClass: 'fa-solid fa-cart-shopping',
        placeholder: 'Escribe codigo o nombre para buscar...',
        headers: ['N°', 'Codigo', 'Nombre', 'UNIDAD'],
        fetchData: (service, query) => {
            const almacen = document.getElementById('zonaOrigen')?.value || '';
            const anio = new Date().getFullYear();

            if (!almacen) {
                window.Swal.fire({
                    icon: 'warning',
                    title: 'Seleccione Almacén',
                    text: 'Debe seleccionar una Zona de Origen antes de buscar el artículo.'
                });
                const modal = document.getElementById('modal-transportistas');
                if (modal) {
                    modal.style.display = 'none';
                    modal.classList.remove('show');
                }
                return [];
            }
            return service.getArticulos(query, almacen, anio);
        },
        renderRow: (item, index) => {
            return `
                <td class="px-4 py-2.5 text-center w-12 border-r border-slate-100 font-mono text-slate-400">${index + 1}</td>
                <td class="px-4 py-2.5 border-r border-slate-100 font-semibold text-slate-800 font-mono">${item.codigo}</td>
                <td class="px-4 py-2.5 border-r border-slate-100 font-medium text-slate-700">${item.descri || '-'}</td>
                <td class="px-4 py-2.5 border-r border-slate-100 text-slate-500 font-mono">${item.unidad || '-'}</td>
            `;
        },
        onSelect: (item) => {
            const inputArtCodigo = document.getElementById('inputArtCodigo');
            if (inputArtCodigo) inputArtCodigo.value = item.codigo;
            const inputArtDescri = document.getElementById('inputArtDescri');
            if (inputArtDescri) inputArtDescri.value = item.descri;
            const inputArtUnd = document.getElementById('inputArtUnd');
            if (inputArtUnd) inputArtUnd.value = item.unidad || '';

            // Lógica para autocompletar lote si es único
            const inputLote = document.getElementById('inputArtLote');
            if (inputLote) {
                // Limpiar lote previo
                inputLote.value = '';
                if (window.guiaController) {
                    window.guiaController.stockMaximoPermitido = 0;
                }

                const almacen = document.getElementById('zonaOrigen')?.value || '';
                const fechaVal = document.getElementById('fechaEmision')?.value;
                const anio = fechaVal ? new Date(fechaVal).getFullYear() : new Date().getFullYear();

                if (almacen && item.codigo) {
                    const service = new window.GuiaElectronicaService();
                    service.getLotes(almacen, item.codigo, anio).then(response => {
                        if (response && response.success && Array.isArray(response.data)) {
                            const lotes = response.data;
                            if (lotes.length === 1) {
                                // Auto-completar lote
                                const loteUnico = lotes[0];
                                inputLote.value = loteUnico.lote;

                                // Guardar stock máximo
                                const isKgs = (loteUnico.unidad || item.unidad || '').toUpperCase() === 'KGS';
                                const isAlmPref = almacen.startsWith('M');
                                let stockLimit = 0;
                                if (isAlmPref && isKgs) {
                                    stockLimit = parseFloat(loteUnico.stock_peso) || 0;
                                } else {
                                    stockLimit = Math.floor(parseFloat(loteUnico.stock_cantidad)) || 0;
                                }
                                if (window.guiaController) {
                                    window.guiaController.stockMaximoPermitido = stockLimit;
                                }

                                // Actualizar leyendas de stock
                                const lblStockCant = document.getElementById('lblStockCantLote');
                                const lblStockPeso = document.getElementById('lblStockPesoLote');
                                if (lblStockCant) {
                                    const cantVal = Math.floor(parseFloat(loteUnico.stock_cantidad)) || 0;
                                    lblStockCant.textContent = `Stock: ${cantVal}`;
                                    lblStockCant.title = `Stock Disponible: ${cantVal}`;
                                }
                                if (lblStockPeso) {
                                    const pesoVal = parseFloat(loteUnico.stock_peso).toFixed(2) || '0.00';
                                    lblStockPeso.textContent = `Peso: ${pesoVal}`;
                                    lblStockPeso.title = `Peso Disponible: ${pesoVal}`;
                                }
                            } else {
                                const lblStockCant = document.getElementById('lblStockCantLote');
                                const lblStockPeso = document.getElementById('lblStockPesoLote');
                                if (lblStockCant) lblStockCant.textContent = '';
                                if (lblStockPeso) lblStockPeso.textContent = '';
                            }
                        }
                    }).catch(err => {
                        console.error("Error al autocompletar lote:", err);
                    });
                }
            }
        }
    },
    'inputArtLote': {
        title: 'Seleccionar Lote',
        iconClass: 'fa-solid fa-boxes-stacked',
        placeholder: '',
        hideSearch: true,
        headers: ['N°', 'Lote', 'Stock Cant.', 'Stock Peso'],
        fetchData: (service, query) => {
            const inputCodigo = document.getElementById('inputArtCodigo');
            const codigoArticulo = inputCodigo ? inputCodigo.value.trim() : '';
            if (!codigoArticulo) {
                window.Swal.fire({
                    icon: 'warning',
                    title: 'Artículo Requerido',
                    text: 'Por favor, ingrese o seleccione un código de artículo antes de buscar el lote.'
                }).then(() => {
                    if (inputCodigo) inputCodigo.focus();
                });
                const modal = document.getElementById('modal-transportistas');
                if (modal) {
                    modal.style.display = 'none';
                    modal.classList.remove('show');
                }
                return { success: true, data: [] };
            }

            const almacen = document.getElementById('zonaOrigen')?.value || '';
            const fechaVal = document.getElementById('fechaEmision')?.value;
            const anio = fechaVal ? new Date(fechaVal).getFullYear() : new Date().getFullYear();

            if (!almacen) {
                window.Swal.fire({
                    icon: 'warning',
                    title: 'Almacén de origen requerido',
                    text: 'Por favor, seleccione una Zona de Origen antes de elegir el artículo.'
                });
                const modal = document.getElementById('modal-transportistas');
                if (modal) {
                    modal.style.display = 'none';
                    modal.classList.remove('show');
                }
                return { success: true, data: [] };
            }

            return service.getLotes(almacen, codigoArticulo, anio);
        },
        renderRow: (item, index) => {
            return `
                <td class="px-4 py-2.5 text-center w-12 border-r border-slate-100 font-mono text-slate-400">${index + 1}</td>
                <td class="px-4 py-2.5 border-r border-slate-100 font-semibold text-slate-800 font-mono">${item.lote}</td>
                <td class="px-4 py-2.5 border-r border-slate-100 text-right text-slate-700 font-mono">${Math.floor(parseFloat(item.stock_cantidad))}</td>
                <td class="px-4 py-2.5 text-right font-mono text-slate-700">${parseFloat(item.stock_peso).toFixed(2)}</td>
            `;
        },
        onSelect: (item) => {
            const inputLote = document.getElementById('inputArtLote');
            if (inputLote) inputLote.value = item.lote;

            const almacen = document.getElementById('zonaOrigen')?.value || '';
            const isKgs = (item.unidad || document.getElementById('inputArtUnd')?.value || '').toUpperCase() === 'KGS';
            const isAlmPref = almacen.startsWith('M');
            
            let stockLimit = 0;
            if (isAlmPref && isKgs) {
                stockLimit = parseFloat(item.stock_peso) || 0;
            } else {
                stockLimit = Math.floor(parseFloat(item.stock_cantidad)) || 0;
            }

            if (window.guiaController) {
                window.guiaController.stockMaximoPermitido = stockLimit;
            }

            // Actualizar leyendas de stock
            const lblStockCant = document.getElementById('lblStockCantLote');
            const lblStockPeso = document.getElementById('lblStockPesoLote');
            if (lblStockCant) {
                const cantVal = Math.floor(parseFloat(item.stock_cantidad)) || 0;
                lblStockCant.textContent = `Stock: ${cantVal}`;
                lblStockCant.title = `Stock Disponible: ${cantVal}`;
            }
            if (lblStockPeso) {
                const pesoVal = parseFloat(item.stock_peso).toFixed(2) || '0.00';
                lblStockPeso.textContent = `Peso: ${pesoVal}`;
                lblStockPeso.title = `Peso Disponible: ${pesoVal}`;
            }
        }
    },
    'inputArtCencos': {
        title: 'Buscar Centro de Costo (Cencos)',
        iconClass: 'fa-solid fa-store',
        placeholder: 'Escribe codigo o nombre para buscar...',
        headers: ['N°', 'Código', 'Descripción'],
        fetchData: (service, query) => service.getCencos(query),
        renderRow: (item, index) => {
            return `
                <td class="px-4 py-2.5 text-center w-12 border-r border-slate-100 font-mono text-slate-400">${index + 1}</td>
                <td class="px-4 py-2.5 border-r border-slate-100 font-semibold text-slate-800 font-mono">${item.codigo}</td>
                <td class="px-4 py-2.5 border-r border-slate-100 font-medium text-slate-700">${item.descripcion || '-'}</td>
            `;
        },
        onSelect: (item) => {
            const cencosInput = document.getElementById('inputArtCencos');
            if (cencosInput) cencosInput.value = item.codigo;
        }
    }
};

window.GuiaElectronicaConfig = BUSQUEDAS_CONFIG;
