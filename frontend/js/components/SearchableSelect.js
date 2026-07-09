/**
 * SearchableSelect - Componente puro en Tailwind CSS para convertir <select> en campos con búsqueda
 *
 * Características:
 * - Hereda las clases Tailwind de estilo, bordes, altura y focus del select original.
 * - Barra de búsqueda integrada flotante de Tailwind.
 * - Navegación con teclado (Flechas Arriba/Abajo, Enter, Esc, Tab).
 * - Posicionamiento viewport-fixed inmune a recortes de contenedores con scroll.
 */
class SearchableSelect {
    constructor(selectElement, options = {}) {
        this.originalSelect = selectElement;
        this.options = {
            placeholder: 'Buscar...',
            noResults: 'Sin resultados',
            autoFocus: true,
            moveToNextOnSelect: true,
            ...options
        };

        this.isOpen = false;
        this.selectedIndex = -1;
        this.filteredOptions = [];
        this.allOptions = [];

        this.init();
    }

    init() {
        // Ocultar select original
        this.originalSelect.style.display = 'none';

        // Contenedor relativo
        this.container = document.createElement('div');
        this.container.className = 'searchable-select relative w-full';
        this.originalSelect.parentNode.insertBefore(this.container, this.originalSelect);

        // Display field (copiar clases del select original para conservar la estética exacta)
        this.displayField = document.createElement('div');
        const originalClasses = Array.from(this.originalSelect.classList).join(' ');
        
        // Quitar clases de padding lateral nativo del select si las tuviera, y poner flex
        this.displayField.className = `${originalClasses} searchable-select-display flex items-center justify-between cursor-pointer select-none focus:outline-none focus:ring-4 focus:ring-indigo-500/10 focus:border-indigo-500 transition-all duration-200`;
        this.displayField.tabIndex = this.originalSelect.tabIndex >= 0 ? this.originalSelect.tabIndex : 0;
        
        // Contenido del display (texto + chevron)
        this.displayField.innerHTML = `
            <span class="truncate pr-2 text-left"></span>
            <i class="fa-solid fa-chevron-down text-[10px] text-slate-400 shrink-0 transition-transform duration-200"></i>
        `;
        this.container.appendChild(this.displayField);

        // Guardar referencia inversa para que FormNavigation pueda acceder a la instancia
        this.displayField._searchableSelectInstance = this;

        // Dropdown (floating overlay)
        this.dropdown = document.createElement('div');
        this.dropdown.className = 'searchable-select-dropdown fixed bg-white border border-slate-200/80 rounded-xl shadow-[0_10px_30px_-5px_rgba(0,0,0,0.1)] z-[999999] p-1.5 flex flex-col gap-1.5 transition-all duration-150 ease-out transform scale-95 opacity-0 pointer-events-none';
        this.dropdown.style.display = 'none';

        // Campo de búsqueda
        this.searchInput = document.createElement('input');
        this.searchInput.type = 'text';
        this.searchInput.className = 'w-full h-8 px-2.5 bg-slate-50 border border-slate-200 rounded-lg text-xs focus:outline-none focus:ring-4 focus:ring-indigo-500/10 focus:border-indigo-500 transition-all duration-150 placeholder:text-slate-400';
        this.searchInput.placeholder = this.options.placeholder;
        this.searchInput.autocomplete = 'off';

        // Lista de opciones
        this.optionsList = document.createElement('div');
        this.optionsList.className = 'flex-1 overflow-y-auto max-h-48 space-y-0.5 pr-0.5 custom-scrollbar';
        
        // Agregar scrollbar personalizado en head si no existe
        const styleId = 'searchable-select-scrollbar-styles';
        if (!document.getElementById(styleId)) {
            const style = document.createElement('style');
            style.id = styleId;
            style.textContent = `
                .custom-scrollbar::-webkit-scrollbar {
                    width: 4px;
                    height: 4px;
                }
                .custom-scrollbar::-webkit-scrollbar-track {
                    background: transparent;
                }
                .custom-scrollbar::-webkit-scrollbar-thumb {
                    background: #cbd5e1;
                    border-radius: 9999px;
                }
                .custom-scrollbar::-webkit-scrollbar-thumb:hover {
                    background: #94a3b8;
                }
            `;
            document.head.appendChild(style);
        }

        this.dropdown.appendChild(this.searchInput);
        this.dropdown.appendChild(this.optionsList);
        document.body.appendChild(this.dropdown);

        this.attachEvents();
        this.loadOptions();
        this.updateDisplayText();
    }

    attachEvents() {
        // Toggle click
        this.displayField.addEventListener('click', (e) => {
            e.preventDefault();
            this.toggle();
        });

        // Enter/Space abre
        this.displayField.addEventListener('keydown', (e) => {
            if (e.key === 'Enter' || e.key === ' ') {
                e.preventDefault();
                this.open();
            }
        });

        // Búsqueda
        this.searchInput.addEventListener('input', () => this.filterOptions());

        // Teclas en búsqueda
        this.searchInput.addEventListener('keydown', (e) => {
            switch(e.key) {
                case 'ArrowDown':
                    e.preventDefault();
                    this.moveSelection(1);
                    break;
                case 'ArrowUp':
                    e.preventDefault();
                    this.moveSelection(-1);
                    break;
                case 'Enter':
                    e.preventDefault();
                    this.selectCurrentOption();
                    break;
                case 'Escape':
                    e.preventDefault();
                    this.close();
                    this.displayField.focus();
                    break;
                case 'Tab':
                    this.close();
                    break;
            }
        });

        // Click fuera para cerrar
        this.clickOutsideHandler = (e) => {
            if (!this.container.contains(e.target) && !this.dropdown.contains(e.target)) {
                this.close();
            }
        };
        document.addEventListener('click', this.clickOutsideHandler);

        // Observar cambios en el select original (por si se actualiza dinámicamente)
        this.observer = new MutationObserver(() => {
            this.loadOptions();
            this.updateDisplayText();
        });
        this.observer.observe(this.originalSelect, { childList: true, subtree: true });
    }

    loadOptions() {
        this.allOptions = Array.from(this.originalSelect.options).map(opt => ({
            value: opt.value,
            text: opt.textContent.trim(),
            display: opt.getAttribute('data-display')?.trim() || opt.textContent.trim(),
            element: opt
        }));
        this.filterOptions();
    }

    filterOptions() {
        const searchTerm = this.searchInput.value.toLowerCase();

        this.filteredOptions = this.allOptions.filter(opt => {
            if (!searchTerm && opt.value === '') return true;
            if (opt.value === '') return false;

            return opt.text.toLowerCase().includes(searchTerm) ||
                   opt.value.toLowerCase().includes(searchTerm) ||
                   opt.display.toLowerCase().includes(searchTerm);
        });

        this.renderOptions();
    }

    renderOptions() {
        this.optionsList.innerHTML = '';
        this.selectedIndex = -1;

        if (this.filteredOptions.length === 0) {
            const noResults = document.createElement('div');
            noResults.className = 'px-3 py-2 text-xs text-slate-400 italic text-center select-none';
            noResults.textContent = this.options.noResults;
            this.optionsList.appendChild(noResults);
            return;
        }

        const currentValue = this.originalSelect.value;

        this.filteredOptions.forEach((opt, index) => {
            const optionDiv = document.createElement('div');
            
            // Estilos base de las opciones
            let optionClasses = 'px-2.5 py-1.5 text-xs rounded-md cursor-pointer transition-colors duration-150 select-none ';
            
            if (opt.value === '') {
                // Opción placeholder
                optionClasses += 'text-slate-400 font-medium italic hover:bg-slate-50';
            } else if (opt.value === currentValue) {
                // Opción seleccionada
                optionClasses += 'bg-indigo-600 text-white font-semibold';
            } else {
                // Opción normal
                optionClasses += 'text-slate-700 hover:bg-indigo-550/10 hover:text-indigo-700';
            }

            optionDiv.className = optionClasses;
            optionDiv.textContent = opt.display;
            optionDiv.dataset.value = opt.value;
            optionDiv.dataset.index = index;

            optionDiv.addEventListener('click', () => {
                this.selectOption(opt);
            });

            optionDiv.addEventListener('mouseenter', () => {
                this.selectedIndex = index;
                this.highlightOption();
            });

            this.optionsList.appendChild(optionDiv);
        });
    }

    moveSelection(direction) {
        if (this.filteredOptions.length === 0) return;

        this.selectedIndex += direction;

        if (this.selectedIndex < 0) {
            this.selectedIndex = this.filteredOptions.length - 1;
        } else if (this.selectedIndex >= this.filteredOptions.length) {
            this.selectedIndex = 0;
        }

        this.highlightOption();
        this.scrollToHighlighted();
    }

    highlightOption() {
        const items = this.optionsList.children;
        Array.from(items).forEach((item, index) => {
            if (item.classList.contains('no-results')) return;
            const isHighlighted = index === this.selectedIndex;
            
            if (isHighlighted) {
                if (item.dataset.value === this.originalSelect.value) {
                    item.className = 'px-2.5 py-1.5 text-xs rounded-md cursor-pointer transition-colors duration-150 select-none bg-indigo-700 text-white font-semibold';
                } else {
                    item.className = 'px-2.5 py-1.5 text-xs rounded-md cursor-pointer transition-colors duration-150 select-none bg-indigo-50 text-indigo-700 font-medium';
                }
            } else {
                if (item.dataset.value === this.originalSelect.value) {
                    item.className = 'px-2.5 py-1.5 text-xs rounded-md cursor-pointer transition-colors duration-150 select-none bg-indigo-600 text-white font-semibold';
                } else {
                    item.className = 'px-2.5 py-1.5 text-xs rounded-md cursor-pointer transition-colors duration-150 select-none text-slate-700 hover:bg-indigo-50 hover:text-indigo-700';
                }
            }
        });
    }

    scrollToHighlighted() {
        const items = this.optionsList.children;
        const highlighted = items[this.selectedIndex];
        if (highlighted) {
            highlighted.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
        }
    }

    selectCurrentOption() {
        if (this.selectedIndex >= 0 && this.filteredOptions[this.selectedIndex]) {
            this.selectOption(this.filteredOptions[this.selectedIndex]);
        } else if (this.filteredOptions.length === 1) {
            this.selectOption(this.filteredOptions[0]);
        }
    }

    selectOption(option) {
        this.originalSelect.value = option.value;

        // Disparar evento change en el select original
        const event = new Event('change', { bubbles: true });
        this.originalSelect.dispatchEvent(event);

        this.updateDisplayText();
        this.close();

        // Si se selecciona la transacción S003, enfocar directamente Serie (tserie) y detener navegación automática
        if (this.originalSelect.id === 'tcodtra' && option.value === 'S003') {
            setTimeout(() => {
                const tserie = document.getElementById('tserie');
                if (tserie) {
                    tserie.focus();
                    if (typeof tserie.select === 'function') tserie.select();
                }
            }, 50);
            return;
        }

        this.displayField.focus();

        if (this.options.moveToNextOnSelect) {
            this.moveToNextField();
        }
    }

    updateDisplayText() {
        const selectedIndex = this.originalSelect.selectedIndex;
        const selectedOption = selectedIndex >= 0 ? this.originalSelect.options[selectedIndex] : null;
        
        const displayText = selectedOption
            ? (selectedOption.getAttribute('data-display')?.trim() || selectedOption.textContent.trim())
            : '';

        const span = this.displayField.querySelector('span');
        if (span) {
            if (displayText && this.originalSelect.value !== '') {
                span.textContent = displayText;
                span.classList.remove('text-slate-400', 'italic');
                span.classList.add('text-slate-700');
            } else {
                const placeholderText = this.originalSelect.querySelector('option[value=""]')?.textContent || 'Seleccionar...';
                span.textContent = placeholderText;
                span.classList.remove('text-slate-700');
                span.classList.add('text-slate-400', 'italic');
            }
        }
    }

    moveToNextField() {
        const form = this.originalSelect.form;
        if (form && form.formNavigationInstance) {
            // Evitar interferir si hay una redirección manual de foco en curso (ej: autocompletado S003)
            if (window.movAlmCtrl?._inS003Autocomplete) {
                return;
            }
            form.formNavigationInstance.moveToNextField(this.displayField);
        }
    }

    open() {
        if (this.isOpen) return;

        this.isOpen = true;
        this.container.classList.add('open');
        
        // Rotar chevron
        const chevron = this.displayField.querySelector('i');
        if (chevron) chevron.classList.add('rotate-180');

        // Calcular posición del dropdown basándose en el displayField
        this.dropdown.style.display = 'flex';
        this.updateDropdownPosition();

        // Mostrar con transición
        setTimeout(() => {
            this.dropdown.classList.remove('scale-95', 'opacity-0', 'pointer-events-none');
            this.dropdown.classList.add('scale-100', 'opacity-100');
        }, 10);

        this.searchInput.value = '';
        this.filterOptions();

        if (this.options.autoFocus) {
            setTimeout(() => this.searchInput.focus(), 50);
        }

        // Pre-seleccionar la opción actual si existe
        const currentValue = this.originalSelect.value;
        if (currentValue) {
            const currentIndex = this.filteredOptions.findIndex(opt => opt.value === currentValue);
            if (currentIndex >= 0) {
                this.selectedIndex = currentIndex;
                this.highlightOption();
                this.scrollToHighlighted();
            }
        }

        this._updatePositionOnScroll = () => this.updateDropdownPosition();
        window.addEventListener('scroll', this._updatePositionOnScroll, true);
        window.addEventListener('resize', this._updatePositionOnScroll);
    }

    updateDropdownPosition() {
        if (!this.isOpen) return;
        const rect = this.displayField.getBoundingClientRect();
        this.dropdown.style.top = `${rect.bottom + 4}px`;
        this.dropdown.style.left = `${rect.left}px`;
        this.dropdown.style.width = `${Math.max(rect.width, 240)}px`;
    }

    close() {
        if (!this.isOpen) return;
        this.isOpen = false;
        this.container.classList.remove('open');

        // Rotar chevron de vuelta
        const chevron = this.displayField.querySelector('i');
        if (chevron) chevron.classList.remove('rotate-180');

        this.dropdown.classList.remove('scale-100', 'opacity-100');
        this.dropdown.classList.add('scale-95', 'opacity-0', 'pointer-events-none');
        
        setTimeout(() => {
            if (!this.isOpen) this.dropdown.style.display = 'none';
        }, 150);

        if (this._updatePositionOnScroll) {
            window.removeEventListener('scroll', this._updatePositionOnScroll, true);
            window.removeEventListener('resize', this._updatePositionOnScroll);
        }
    }

    toggle() {
        if (this.isOpen) {
            this.close();
        } else {
            this.open();
        }
    }

    destroy() {
        this.observer.disconnect();
        document.removeEventListener('click', this.clickOutsideHandler);
        this.container.remove();
        this.dropdown.remove();
        this.originalSelect.style.display = '';
    }
}

function initSearchableSelects(container = document, options = {}) {
    const selects = container.querySelectorAll('select:not([data-no-search])');
    const instances = [];

    selects.forEach(select => {
        if (select.searchableSelectInstance) return;

        const instance = new SearchableSelect(select, options);
        select.searchableSelectInstance = instance;
        instances.push(instance);
    });

    return instances;
}

if (typeof module !== 'undefined' && module.exports) {
    module.exports = { SearchableSelect, initSearchableSelects };
}
