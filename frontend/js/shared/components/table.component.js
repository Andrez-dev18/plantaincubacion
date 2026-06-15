class TableView {
    constructor() {
        this.tableHeaders = document.getElementById('tableHeaders');
        this.tableBody = document.getElementById('tableBody');
        this.dataTable = null;
        this.columnasVisibles = {};
    }

    render(datos) {
        if (this.dataTable) {
            this.dataTable.destroy();
            this.dataTable = null;
        }

        if (!datos || datos.length === 0) {
            this.renderizarVacia();
            return;
        }

        const headers = Object.keys(datos[0]);
        this.renderizarEncabezados(headers);
        this.renderizarFilas(datos, headers);
        
        this.generarCheckboxesColumnas(headers);

        setTimeout(() => this.inicializarDataTable(), 0);
    }

    generarCheckboxesColumnas(headers) {
        const container = document.getElementById('columnCheckboxContainer');
        
        console.log('🔍 DEBUG generarCheckboxesColumnas:');
        console.log('- Container encontrado:', !!container);
        console.log('- Headers recibidos:', headers);
        
        if (!container) {
            console.error('❌ Container "columnCheckboxContainer" NO encontrado');
            console.log('Elementos con ID en el documento:', 
                Array.from(document.querySelectorAll('[id]')).map(el => el.id)
            );
            return;
        }

        container.innerHTML = '';
        this.columnasVisibles = {};

        const columnasConOpciones = [...headers, 'Opciones'];

        columnasConOpciones.forEach((col, index) => {
            const label = document.createElement('label');
            label.className = 'column-toggle';
            
            const checkbox = document.createElement('input');
            checkbox.type = 'checkbox';
            checkbox.className = 'column-checkbox';
            checkbox.dataset.column = index;
            checkbox.checked = true;
            
            if (index === columnasConOpciones.length - 1) {
                checkbox.disabled = true;
            }
            
            this.columnasVisibles[index] = true;
            
            checkbox.addEventListener('change', (e) => {
                e.stopPropagation();
                const columnIndex = parseInt(e.target.dataset.column);
                this.columnasVisibles[columnIndex] = e.target.checked;
                
                if (this.dataTable) {
                    const column = this.dataTable.column(columnIndex);
                    column.visible(e.target.checked);
                }
            });
            
            const span = document.createElement('span');
            span.textContent = this.formatearNombreColumna(col);
            
            label.appendChild(checkbox);
            label.appendChild(span);
            container.appendChild(label);
        });

        console.log('✅ Checkboxes generados:', container.children.length);
        console.log('✅ Contenido del container:', container.innerHTML.substring(0, 200));

        this.setupColumnToggle();
    }

    setupColumnToggle() {
        const btnToggle = document.getElementById('btnToggleColumns');
        const dropdown = document.getElementById('columnDropdown');
        const btnClose = document.getElementById('btnCloseDropdown');

        if (!btnToggle || !dropdown) {
            console.warn('⚠️ Botón o dropdown no encontrado');
            return;
        }

        btnToggle.onclick = (e) => {
            e.preventDefault();
            e.stopPropagation();
            dropdown.classList.toggle('show');
            console.log('🔄 Toggle dropdown:', dropdown.classList.contains('show'));
        };

        if (btnClose) {
            btnClose.onclick = (e) => {
                e.preventDefault();
                e.stopPropagation();
                dropdown.classList.remove('show');
            };
        }

        document.addEventListener('click', (e) => {
            if (!e.target.closest('.dropdown-columns')) {
                dropdown.classList.remove('show');
            }
        });
    }

    renderizarVacia() {
        if (!this.tableBody) return;

        if (this.dataTable) {
            this.dataTable.destroy();
            this.dataTable = null;
        }

        this.tableBody.innerHTML = `
            <tr>
                <td colspan="100" class="px-6 py-12 text-center text-gray-500">
                    <svg class="w-16 h-16 mx-auto mb-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4"></path>
                    </svg>
                    <p class="text-lg font-semibold">No hay datos disponibles</p>
                </td>
            </tr>
        `;
    }

    renderizarEncabezados(headers) {
        if (!this.tableHeaders) return;

        const headerHTML = `
            ${headers.map(h => `
                <th class="px-4 py-3 text-left text-sm font-semibold">${this.formatearNombreColumna(h)}</th>
            `).join('')}
            <th class="px-4 py-3 text-center text-sm font-semibold w-32">Opciones</th>
        `;

        this.tableHeaders.innerHTML = headerHTML;
    }

    renderizarFilas(datos, headers) {
        if (!this.tableBody) return;

        const filasHTML = datos.map((item, index) => {
            const celdas = headers.map(h => {
                const valor = item[h];
                const valorFormateado = this.formatearValor(valor, h);
                return `<td class="px-4 py-3 text-sm text-gray-700">${valorFormateado}</td>`;
            }).join('');

            return `
                <tr class="hover:bg-blue-50 transition-colors">
                    ${celdas}
                    <td class="px-4 py-3 text-center">
                        <div class="flex gap-2 justify-center">
                            <button class="bg-yellow-400 hover:bg-yellow-500 text-white px-3 py-1.5 rounded-lg transition btn-hover-scale"
                                    onclick="window.editarRegistro(${index})"
                                    title="Editar">
                                <i class="fas fa-edit"></i>
                            </button>
                            <button class="bg-red-500 hover:bg-red-600 text-white px-3 py-1.5 rounded-lg transition btn-hover-scale"
                                    onclick="window.eliminarRegistro(${index})"
                                    title="Eliminar">
                                <i class="fas fa-trash-alt"></i>
                            </button>
                        </div>
                    </td>
                </tr>
            `;
        }).join('');

        this.tableBody.innerHTML = filasHTML;
    }

    inicializarDataTable() {
        const tabla = $('#dataTable');

        if (tabla.length) {
            this.dataTable = tabla.DataTable({
                pageLength: 10,
                lengthMenu: [5, 10, 20, 50],
                responsive: true,
                order: [[0, 'desc']],
                language: {
                    url: 'https://cdn.datatables.net/plug-ins/1.13.6/i18n/es-ES.json'
                },
                columnDefs: [
                    { orderable: false, targets: -1 }
                ],
                initComplete: () => {
                    console.log('✅ DataTable inicializado');
                    Object.keys(this.columnasVisibles).forEach(columnIndex => {
                        const column = this.dataTable.column(parseInt(columnIndex));
                        column.visible(this.columnasVisibles[columnIndex]);
                    });
                },
                drawCallback: () => {
                    Object.keys(this.columnasVisibles).forEach(columnIndex => {
                        const column = this.dataTable.column(parseInt(columnIndex));
                        column.visible(this.columnasVisibles[columnIndex]);
                    });
                }
            });
        }
    }

    formatearNombreColumna(nombre) {
        return nombre
            .replace(/_/g, ' ')
            .split(' ')
            .map(palabra => palabra.charAt(0).toUpperCase() + palabra.slice(1))
            .join(' ');
    }

    formatearValor(valor, nombreColumna) {
        if (valor === null || valor === undefined) {
            return '<span class="text-gray-400">—</span>';
        }

        if (typeof valor === 'number' && nombreColumna !== 'ano') {
            if (valor === 0) return '<span class="text-gray-400">0</span>';
            return valor.toLocaleString('es-PE');
        }

        if (typeof valor === 'boolean') {
            return valor
                ? '<span class="text-green-600 font-semibold">✓</span>'
                : '<span class="text-red-600 font-semibold">✗</span>';
        }

        if (valor === 'SI' || valor === 'NO') {
            return valor === 'SI'
                ? '<span class="bg-green-100 text-green-800 px-2 py-1 rounded text-xs font-semibold">SI</span>'
                : '<span class="bg-red-100 text-red-800 px-2 py-1 rounded text-xs font-semibold">NO</span>';
        }

        if (typeof valor === 'string' && valor.length > 50) {
            return `<span class="text-sm" title="${valor}">${valor.substring(0, 50)}...</span>`;
        }

        return valor;
    }

    marcarFilaSeleccionada(index) {
        // Implementación si es necesaria
    }
}

window.tableView = new TableView();
