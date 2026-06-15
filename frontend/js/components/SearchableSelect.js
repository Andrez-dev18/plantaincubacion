/**
 * SearchableSelect - Componente para convertir <select> en campos con búsqueda
 *
 * Características:
 * - Campo de búsqueda integrado al desplegar
 * - Filtrado en tiempo real escribiendo
 * - Al seleccionar o presionar Enter, pasa al siguiente campo
 * - Navegación con teclado (flechas arriba/abajo, Enter, Esc)
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

        this.init();
    }

    init() {
        // Ocultar el select original
        this.originalSelect.style.display = 'none';

        // Crear contenedor principal
        this.container = document.createElement('div');
        this.container.className = 'searchable-select';
        this.originalSelect.parentNode.insertBefore(this.container, this.originalSelect);

        // Crear campo de visualización
        this.displayField = document.createElement('div');
        this.displayField.className = 'searchable-select-display input-field cursor-pointer';
        this.displayField.tabIndex = 0;
        this.updateDisplayText();

        // Crear dropdown (se agregará al body para evitar problemas de z-index)
        this.dropdown = document.createElement('div');
        this.dropdown.className = 'searchable-select-dropdown';
        this.dropdown.style.display = 'none';
        this.dropdown.style.position = 'fixed';

        // Crear campo de búsqueda
        this.searchInput = document.createElement('input');
        this.searchInput.type = 'text';
        this.searchInput.className = 'searchable-select-search';
        this.searchInput.placeholder = this.options.placeholder;

        // Crear lista de opciones
        this.optionsList = document.createElement('div');
        this.optionsList.className = 'searchable-select-options';

        // Ensamblar
        this.dropdown.appendChild(this.searchInput);
        this.dropdown.appendChild(this.optionsList);
        this.container.appendChild(this.displayField);
        document.body.appendChild(this.dropdown); // Agregar dropdown al body

        // Event listeners
        this.attachEvents();

        // Cargar opciones iniciales
        this.loadOptions();
    }

    attachEvents() {
        // Click en el campo de visualización
        this.displayField.addEventListener('click', () => this.toggle());

        // Enter en el campo de visualización
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
            }
        });

        // Click fuera para cerrar
        document.addEventListener('click', (e) => {
            if (!this.container.contains(e.target) && !this.dropdown.contains(e.target)) {
                this.close();
            }
        });

        // Observar cambios en el select original (por si se actualiza dinámicamente)
        this.observer = new MutationObserver(() => this.loadOptions());
        this.observer.observe(this.originalSelect, { childList: true, subtree: true });
    }

    loadOptions() {
        this.allOptions = Array.from(this.originalSelect.options).map(opt => ({
            value: opt.value,
            text: opt.textContent.trim(),
            element: opt
        }));
        this.filterOptions();
    }

    filterOptions() {
        const searchTerm = this.searchInput.value.toLowerCase();

        this.filteredOptions = this.allOptions.filter(opt => {
            // No filtrar la opción vacía/placeholder si no hay búsqueda
            if (!searchTerm && opt.value === '') return true;
            // Filtrar opción vacía cuando hay búsqueda
            if (opt.value === '') return false;

            return opt.text.toLowerCase().includes(searchTerm) ||
                   opt.value.toLowerCase().includes(searchTerm);
        });

        this.renderOptions();
    }

    renderOptions() {
        this.optionsList.innerHTML = '';
        this.selectedIndex = -1;

        if (this.filteredOptions.length === 0) {
            const noResults = document.createElement('div');
            noResults.className = 'searchable-select-option-item no-results';
            noResults.textContent = this.options.noResults;
            this.optionsList.appendChild(noResults);
            return;
        }

        this.filteredOptions.forEach((opt, index) => {
            const optionDiv = document.createElement('div');
            optionDiv.className = 'searchable-select-option-item';
            optionDiv.textContent = opt.text;
            optionDiv.dataset.value = opt.value;
            optionDiv.dataset.index = index;

            // Marcar si está seleccionado actualmente
            if (opt.value === this.originalSelect.value) {
                optionDiv.classList.add('selected');
            }

            // Click en opción
            optionDiv.addEventListener('click', () => {
                this.selectOption(opt);
            });

            // Hover
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

        // Circular
        if (this.selectedIndex < 0) {
            this.selectedIndex = this.filteredOptions.length - 1;
        } else if (this.selectedIndex >= this.filteredOptions.length) {
            this.selectedIndex = 0;
        }

        this.highlightOption();
        this.scrollToHighlighted();
    }

    highlightOption() {
        const items = this.optionsList.querySelectorAll('.searchable-select-option-item');
        items.forEach((item, index) => {
            item.classList.toggle('highlighted', index === this.selectedIndex);
        });
    }

    scrollToHighlighted() {
        const highlighted = this.optionsList.querySelector('.highlighted');
        if (highlighted) {
            highlighted.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
        }
    }

    selectCurrentOption() {
        if (this.selectedIndex >= 0 && this.filteredOptions[this.selectedIndex]) {
            this.selectOption(this.filteredOptions[this.selectedIndex]);
        } else if (this.filteredOptions.length === 1) {
            // Si solo hay una opción filtrada, seleccionarla
            this.selectOption(this.filteredOptions[0]);
        }
    }

    selectOption(option) {
        // Actualizar el select original
        this.originalSelect.value = option.value;

        // Disparar evento change en el select original
        const event = new Event('change', { bubbles: true });
        this.originalSelect.dispatchEvent(event);

        // Actualizar visualización
        this.updateDisplayText();

        // Cerrar dropdown
        this.close();

        // Mover al siguiente campo si está habilitado
        if (this.options.moveToNextOnSelect) {
            this.moveToNextField();
        }
    }

    updateDisplayText() {
        const selectedOption = this.originalSelect.options[this.originalSelect.selectedIndex];
        const displayText = selectedOption
            ? (selectedOption.dataset.display?.trim() || selectedOption.textContent.trim())
            : '';

        if (displayText && this.originalSelect.value !== '') {
            this.displayField.textContent = displayText;
            this.displayField.classList.remove('placeholder');
        } else {
            this.displayField.textContent = this.originalSelect.querySelector('option[value=""]')?.textContent || 'Seleccionar...';
            this.displayField.classList.add('placeholder');
        }
    }

    moveToNextField() {
        // Si hay un FormNavigation activo en el formulario, usarlo
        const form = this.originalSelect.form;
        if (form && form.formNavigationInstance) {
            form.formNavigationInstance.moveToNextField(this.displayField);
            return;
        }

        // Fallback: lógica propia
        // Obtener todos los campos focalizables del formulario
        const formElement = this.originalSelect.form || document;
        const focusable = Array.from(formElement.querySelectorAll(
            'input:not([readonly]):not([disabled]):not([type="hidden"]), ' +
            'select:not([disabled]), ' +
            'textarea:not([disabled]), ' +
            '.searchable-select-display'
        )).filter(el => {
            // Filtrar elementos visibles
            const style = window.getComputedStyle(el);
            return style.display !== 'none' && style.visibility !== 'hidden';
        });

        const currentIndex = focusable.indexOf(this.displayField);
        if (currentIndex >= 0 && currentIndex < focusable.length - 1) {
            const nextField = focusable[currentIndex + 1];
            setTimeout(() => {
                nextField.focus();
                // Si el siguiente es otro searchable select, abrirlo automáticamente
                if (nextField.classList.contains('searchable-select-display')) {
                    nextField.click();
                }
                // Si es un input, seleccionar el contenido
                if (nextField.tagName === 'INPUT' &&
                    (nextField.type === 'text' || nextField.type === 'number')) {
                    nextField.select();
                }
            }, 100);
        }
    }

    open() {
        if (this.isOpen) return;

        this.isOpen = true;
        this.container.classList.add('open');

        // Calcular posición del dropdown basándose en el displayField
        const rect = this.displayField.getBoundingClientRect();
        this.dropdown.style.top = `${rect.bottom + 4}px`; // 4px de margen
        this.dropdown.style.left = `${rect.left}px`;
        this.dropdown.style.width = `${Math.max(rect.width, 350)}px`; // Mínimo 350px

        this.dropdown.style.display = 'block';
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

        // Actualizar posición al hacer scroll
        this._updatePositionOnScroll = () => this.updateDropdownPosition();
        window.addEventListener('scroll', this._updatePositionOnScroll, true);
        window.addEventListener('resize', this._updatePositionOnScroll);
    }

    updateDropdownPosition() {
        if (!this.isOpen) return;
        const rect = this.displayField.getBoundingClientRect();
        this.dropdown.style.top = `${rect.bottom + 4}px`;
        this.dropdown.style.left = `${rect.left}px`;
    }

    close() {
        this.isOpen = false;
        this.container.classList.remove('open');
        this.dropdown.style.display = 'none';
        this.searchInput.value = '';

        // Remover listeners de posición
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
        this.container.remove();
        this.dropdown.remove(); // Remover dropdown del body
        this.originalSelect.style.display = '';
    }
}

// Función helper para inicializar todos los selects en un contenedor
function initSearchableSelects(container = document, options = {}) {
    const selects = container.querySelectorAll('select:not([data-no-search])');
    const instances = [];

    selects.forEach(select => {
        // Evitar inicializar dos veces
        if (select.searchableSelectInstance) return;

        const instance = new SearchableSelect(select, options);
        select.searchableSelectInstance = instance;
        instances.push(instance);
    });

    return instances;
}

// Exportar para uso global
if (typeof module !== 'undefined' && module.exports) {
    module.exports = { SearchableSelect, initSearchableSelects };
}
