class MultiSelectBuscador {
    constructor(selectId, placeholder = "Buscar...") {
        this.selectNativo = document.getElementById(selectId);
        if (!this.selectNativo) return;

        // 🔥 BLINDAJE 1: Forzar de forma dinámica que el select nativo soporte selección múltiple
        this.selectNativo.multiple = true;

        this.placeholder = placeholder;
        this.opciones = []; 
        this.valoresSeleccionados = [];

        this.initUI();
        this.configurarEventos();
    }

    initUI() {
        this.selectNativo.classList.add('hidden');

        this.wrapper = document.createElement('div');
        this.wrapper.className = "relative w-full text-sm";

        this.btn = document.createElement('div');
        this.btn.className = "w-full h-10 px-3 border border-gray-300 rounded-lg flex items-center justify-between bg-white cursor-pointer select-none focus:ring-2 focus:ring-purple-500 overflow-hidden";
        
        this.textoBtn = document.createElement('span');
        this.textoBtn.className = "text-gray-500 truncate pr-2";
        this.textoBtn.textContent = "Todas";
        this.btn.appendChild(this.textoBtn);

        this.flecha = document.createElement('i');
        this.flecha.className = "fas fa-chevron-down text-gray-400 text-xs transition-transform duration-200 flex-shrink-0";
        this.btn.appendChild(this.flecha);

        this.wrapper.appendChild(this.btn);

        this.dropdown = document.createElement('div');
        this.dropdown.className = "hidden fixed bg-white border border-gray-200 rounded-xl shadow-2xl p-2 max-h-64 flex flex-col gap-2";
        this.dropdown.style.zIndex = '999999';

        const divBuscador = document.createElement('div');
        divBuscador.className = "relative flex items-center flex-shrink-0";
        divBuscador.innerHTML = `<i class="fas fa-search absolute left-3 text-gray-400 text-xs"></i>`;
        
        this.inputBuscar = document.createElement('input');
        this.inputBuscar.type = "text";
        this.inputBuscar.placeholder = this.placeholder;
        this.inputBuscar.className = "w-full h-8 pl-8 pr-3 border border-purple-300 rounded-lg text-xs focus:outline-none focus:border-purple-500";
        divBuscador.appendChild(this.inputBuscar);
        this.dropdown.appendChild(divBuscador);

        this.listaContenedor = document.createElement('div');
        this.listaContenedor.className = "overflow-y-auto flex-1 text-xs divide-y divide-gray-50 max-h-44 pr-1";
        this.dropdown.appendChild(this.listaContenedor);

        document.body.appendChild(this.dropdown);
        this.selectNativo.parentNode.insertBefore(this.wrapper, this.selectNativo.nextSibling);
    }

    actualizarOpcionesDesdeSelect() {
        this.opciones = Array.from(this.selectNativo.options)
            .filter(opt => opt.value !== '') 
            .map(opt => ({ valor: opt.value, texto: opt.textContent }));
        
        this.valoresSeleccionados = [];
        this.organizarYRenderizar();
        this.actualizarTextoBtn();
    }

    // 🚀 BLINDAJE 2: Algoritmo para empujar lo seleccionado al inicio sin recargar de más el DOM
    organizarYRenderizar(listaFiltrada = null) {
        let opcionesAMostrar = listaFiltrada || this.opciones;

        // Si no se está buscando nada, ordenamos: seleccionados primero
        if (!listaFiltrada) {
            const seleccionados = opcionesAMostrar.filter(o => this.valoresSeleccionados.includes(o.valor));
            const noSeleccionados = opcionesAMostrar.filter(o => !this.valoresSeleccionados.includes(o.valor));
            opcionesAMostrar = [...seleccionados, ...noSeleccionados];
        }

        // 🔥 OPTIMIZACIÓN RENDIMIENTO: Solo volcamos al DOM los primeros 60 elementos para que vuele la app
        this.renderizarOpciones(opcionesAMostrar.slice(0, 60));
    }

    renderizarOpciones(lista) {
        this.listaContenedor.innerHTML = '';
        const fragment = document.createDocumentFragment();

        lista.forEach(item => {
            const label = document.createElement('label');
            label.className = "flex items-center gap-3 px-3 py-2 hover:bg-purple-50 rounded-lg cursor-pointer text-gray-700 transition-colors select-none";
            
            const checkbox = document.createElement('input');
            checkbox.type = "checkbox";
            checkbox.className = "rounded text-purple-600 focus:ring-purple-500 h-4 w-4 cursor-pointer";
            checkbox.value = item.valor;
            checkbox.checked = this.valoresSeleccionados.includes(item.valor);

            checkbox.addEventListener('change', () => {
                if (checkbox.checked) {
                    if (!this.valoresSeleccionados.includes(item.valor)) this.valoresSeleccionados.push(item.valor);
                } else {
                    this.valoresSeleccionados = this.valoresSeleccionados.filter(v => v !== item.valor);
                }
                this.sincronizarSelectNativo();
                this.actualizarTextoBtn();
            });

            const span = document.createElement('span');
            span.className = "truncate";
            span.textContent = item.texto;

            label.appendChild(checkbox);
            label.appendChild(span);
            fragment.appendChild(label);
        });

        this.listaContenedor.appendChild(fragment);
    }

    sincronizarSelectNativo() {
        Array.from(this.selectNativo.options).forEach(opt => {
            opt.selected = this.valoresSeleccionados.includes(opt.value);
        });
        this.selectNativo.dispatchEvent(new Event('change'));
    }

    actualizarTextoBtn() {
        const cant = this.valoresSeleccionados.length;
        if (cant === 0) {
            this.textoBtn.textContent = "Todas";
            this.textoBtn.className = "text-gray-500 truncate pr-2";
        } else if (cant === 1) {
            const opt = this.opciones.find(o => o.valor === this.valoresSeleccionados[0]);
            this.textoBtn.textContent = opt ? opt.texto : `${cant} seleccionado(s)`;
            this.textoBtn.className = "text-gray-900 font-medium truncate pr-2";
        } else {
            this.textoBtn.textContent = `${cant} seleccionadas`;
            this.textoBtn.className = "text-gray-900 font-medium truncate pr-2";
        }
    }

    configurarEventos() {
        this.btn.addEventListener('click', (e) => {
            e.stopPropagation();
            const abrir = this.dropdown.classList.contains('hidden');
            this.toggleDropdown(abrir);
        });

        this.inputBuscar.addEventListener('input', (e) => {
            const query = e.target.value.toLowerCase().trim();
            if (query === '') {
                this.organizarYRenderizar();
            } else {
                const filtradas = this.opciones.filter(opt => opt.texto.toLowerCase().includes(query));
                this.organizarYRenderizar(filtradas);
            }
        });

        this.dropdown.addEventListener('click', (e) => e.stopPropagation());
        document.addEventListener('click', () => this.toggleDropdown(false));

        window.addEventListener('scroll', (e) => {
            if (this.dropdown.contains(e.target)) return;
            this.toggleDropdown(false);
        }, true);
    }

    toggleDropdown(abrir) {
        if (abrir) {
            document.querySelectorAll('[id$="Dropdown"]').forEach(d => d.classList.add('hidden'));

            // Reorganizamos la lista para poner lo seleccionado arriba justo al abrir el menú
            this.organizarYRenderizar();

            const rect = this.btn.getBoundingClientRect();
            this.dropdown.style.top = `${rect.bottom + 4}px`;
            this.dropdown.style.left = `${rect.left}px`;
            this.dropdown.style.width = `${Math.max(rect.width, 240)}px`;
            this.dropdown.classList.remove('hidden');
            this.flecha.classList.add('rotate-180');
            setTimeout(() => this.inputBuscar.focus(), 50);
        } else {
            this.dropdown.classList.add('hidden');
            this.flecha.classList.remove('rotate-180');
            this.inputBuscar.value = '';
        }
    }

    reset() {
        this.valoresSeleccionados = [];
        this.sincronizarSelectNativo();
        this.actualizarTextoBtn();
    }
}
window.MultiSelectBuscador = MultiSelectBuscador;