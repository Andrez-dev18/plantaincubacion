/**
 * FormNavigation - Utilidad para navegación automática con Enter en formularios
 *
 * Características:
 * - Al presionar Enter en cualquier campo, pasa al siguiente campo modificable
 * - Salta campos readonly, disabled y ocultos
 * - Compatible con SearchableSelect
 * - Manejo especial para textarea (Enter agrega línea, Ctrl+Enter avanza)
 */
class FormNavigation {
    constructor(formElement, options = {}) {
        this.form = formElement;
        this.options = {
            enterToNextField: true,
            skipReadonly: true,
            skipDisabled: true,
            skipHidden: true,
            textareaRequiresCtrl: true, // En textarea, requiere Ctrl+Enter para avanzar
            autoOpenSearchableSelect: true, // Auto-abrir SearchableSelect al recibir foco
            ...options
        };

        this.init();
    }

    init() {
        if (!this.form) {
            console.error('FormNavigation: No se proporcionó un formulario válido');
            return;
        }

        // Guardar instancia en el formulario para que otros componentes puedan acceder
        this.form.formNavigationInstance = this;

        // Agregar listener para todos los campos focalizables
        this.form.addEventListener('keydown', (e) => this.handleKeyDown(e));

        // Auto-abrir SearchableSelect al recibir foco con Enter
        if (this.options.autoOpenSearchableSelect) {
            this.form.addEventListener('focus', (e) => {
                if (e.target.classList.contains('searchable-select-display')) {
                    // Pequeño delay para evitar que se abra y cierre inmediatamente
                    setTimeout(() => {
                        const select = e.target.parentElement?.querySelector('select');
                        if (select?.searchableSelectInstance) {
                            select.searchableSelectInstance.open();
                        }
                    }, 50);
                }
            }, true);
        }
    }

    handleKeyDown(e) {
        // Solo procesar Enter
        if (e.key !== 'Enter') return;

        const target = e.target;

        // Si es un textarea, solo avanzar con Ctrl+Enter
        if (target.tagName === 'TEXTAREA') {
            if (this.options.textareaRequiresCtrl && !e.ctrlKey) {
                return; // Permitir Enter normal en textarea
            }
            e.preventDefault();
            this.moveToNextField(target);
            return;
        }

        // Si es un botón, permitir comportamiento normal (submit, click)
        if (target.tagName === 'BUTTON' || target.type === 'submit') {
            return;
        }

        // Si es un SearchableSelect abierto, no hacer nada (el componente maneja Enter)
        if (target.closest('.searchable-select-dropdown')) {
            return;
        }

        // Para todos los demás campos, prevenir submit y avanzar
        if (this.isFocusableField(target)) {
            e.preventDefault();
            this.moveToNextField(target);
        }
    }

    isFocusableField(element) {
        const tag = element.tagName;
        const type = element.type;

        // Lista de campos focalizables
        const focusableTags = ['INPUT', 'SELECT', 'TEXTAREA'];
        const ignoredTypes = ['hidden', 'submit', 'button', 'reset', 'radio', 'checkbox'];

        if (!focusableTags.includes(tag)) {
            // También considerar elementos con clase searchable-select-display
            return element.classList.contains('searchable-select-display');
        }

        if (ignoredTypes.includes(type)) {
            return false;
        }

        return true;
    }

    isFieldVisible(element) {
        if (!this.options.skipHidden) return true;

        // Verificar si el elemento o algún padre está oculto
        let el = element;
        while (el && el !== this.form) {
            const style = window.getComputedStyle(el);
            if (style.display === 'none' || style.visibility === 'hidden') {
                return false;
            }
            el = el.parentElement;
        }
        return true;
    }

    isFieldFocusable(element) {
        // Campos readonly
        if (this.options.skipReadonly && element.readOnly) {
            return false;
        }

        // Campos disabled
        if (this.options.skipDisabled && element.disabled) {
            return false;
        }

        // Para SearchableSelect display, verificar si tiene atributo disabled
        if (element.classList.contains('searchable-select-display')) {
            if (this.options.skipDisabled && element.hasAttribute('disabled')) {
                return false;
            }
        }

        // Campos ocultos
        if (!this.isFieldVisible(element)) {
            return false;
        }

        // Verificar tabIndex negativo
        if (element.tabIndex < 0) {
            return false;
        }

        return true;
    }

    getAllFocusableFields() {
        // Seleccionar todos los campos potencialmente focalizables
        const fields = this.form.querySelectorAll(
            'input:not([type="hidden"]):not([type="submit"]):not([type="button"]):not([type="reset"]), ' +
            'select, ' +
            'textarea, ' +
            '.searchable-select-display'
        );

        // Filtrar solo los que son realmente focalizables
        return Array.from(fields).filter(field => this.isFieldFocusable(field));
    }

    moveToNextField(currentField) {
        // Primero, obtener todos los campos actualmente visibles y focalizables
        let focusableFields = this.getAllFocusableFields();
        let currentIndex = focusableFields.indexOf(currentField);

        if (currentIndex === -1) {
            console.warn('FormNavigation: Campo actual no encontrado en la lista de campos focalizables');
            return;
        }

        // Buscar el siguiente campo
        let nextIndex = currentIndex + 1;

        const formularioItems = document.getElementById('formulario-agregar-item');

        // Si el bloque de items está oculto y el siguiente foco saltaría a campos
        // posteriores (por ejemplo formato/guardar), primero mostrar items.
        if (formularioItems && !this.isFieldVisible(formularioItems) && nextIndex < focusableFields.length) {
            const currentDentroItems = !!currentField.closest?.('#formulario-agregar-item');
            const nextFieldTentativo = focusableFields[nextIndex];
            const nextDespuesDeItems = !!nextFieldTentativo &&
                !!(formularioItems.compareDocumentPosition(nextFieldTentativo) & Node.DOCUMENT_POSITION_FOLLOWING);

            if (!currentDentroItems && nextDespuesDeItems) {
                const event = new CustomEvent('showItemsForm');
                document.dispatchEvent(event);

                setTimeout(() => {
                    const primerCampo = document.getElementById('grid-tcencos');
                    if (primerCampo?.searchableSelectInstance?.displayField) {
                        primerCampo.searchableSelectInstance.displayField.focus();
                        setTimeout(() => primerCampo.searchableSelectInstance.open(), 100);
                    } else if (primerCampo) {
                        primerCampo.focus();
                    }
                }, 350);

                return;
            }
        }

        // Si llegamos al final de los campos visibles, verificar si hay campos ocultos que deberían mostrarse
        if (nextIndex >= focusableFields.length) {
            // Verificar si el formulario de agregar items está oculto
            if (formularioItems && formularioItems.style.display === 'none') {
                // Disparar evento para mostrar el formulario de items
                const event = new CustomEvent('showItemsForm');
                document.dispatchEvent(event);

                // Esperar a que se muestre y luego re-calcular campos focalizables
                setTimeout(() => {
                    focusableFields = this.getAllFocusableFields();

                    const primerCampo = document.getElementById('grid-tcencos');
                    if (primerCampo?.searchableSelectInstance?.displayField) {
                        primerCampo.searchableSelectInstance.displayField.focus();
                        setTimeout(() => primerCampo.searchableSelectInstance.open(), 100);
                    } else if (primerCampo) {
                        primerCampo.focus();
                    }
                }, 350); // Esperar a que termine la animación
                return;
            }

            // Si no hay más campos ocultos, no hacer nada (mantener foco en el último)
            return;
        }

        const nextField = focusableFields[nextIndex];

        if (nextField) {
            // Enfocar el siguiente campo
            nextField.focus();

            // Si es un SearchableSelect, abrirlo automáticamente
            if (nextField.classList.contains('searchable-select-display')) {
                setTimeout(() => {
                    nextField.click();
                }, 100);
            }

            // Si es un input de tipo text o number, seleccionar todo el contenido
            if (nextField.tagName === 'INPUT' &&
                (nextField.type === 'text' || nextField.type === 'number')) {
                nextField.select();
            }
        }
    }

    moveToPreviousField(currentField) {
        const focusableFields = this.getAllFocusableFields();
        const currentIndex = focusableFields.indexOf(currentField);

        if (currentIndex === -1) return;

        let prevIndex = currentIndex - 1;

        if (prevIndex < 0) {
            // prevIndex = focusableFields.length - 1; // Circular
            return; // O no hacer nada
        }

        const prevField = focusableFields[prevIndex];
        if (prevField) {
            prevField.focus();
            if (prevField.tagName === 'INPUT') {
                prevField.select();
            }
        }
    }

    // Navegar a un campo específico por ID
    focusField(fieldId) {
        const field = this.form.querySelector(`#${fieldId}`);
        if (!field) {
            console.warn(`FormNavigation: Campo con id="${fieldId}" no encontrado`);
            return;
        }

        // Si es un select con SearchableSelect, enfocar el display
        if (field.searchableSelectInstance) {
            const display = field.searchableSelectInstance.displayField;
            if (display) {
                display.focus();
                return;
            }
        }

        field.focus();
        if (field.tagName === 'INPUT') {
            field.select();
        }
    }

    // Obtener el campo siguiente
    getNextField(currentField) {
        const focusableFields = this.getAllFocusableFields();
        const currentIndex = focusableFields.indexOf(currentField);

        if (currentIndex === -1 || currentIndex >= focusableFields.length - 1) {
            return null;
        }

        return focusableFields[currentIndex + 1];
    }

    // Obtener el campo anterior
    getPreviousField(currentField) {
        const focusableFields = this.getAllFocusableFields();
        const currentIndex = focusableFields.indexOf(currentField);

        if (currentIndex <= 0) {
            return null;
        }

        return focusableFields[currentIndex - 1];
    }

    // Destruir la instancia
    destroy() {
        // Remover listeners si es necesario
        // (En este caso, como usamos event delegation, no es necesario)
    }
}

// Función helper para inicializar en un formulario
function initFormNavigation(formSelector, options = {}) {
    const form = typeof formSelector === 'string'
        ? document.querySelector(formSelector)
        : formSelector;

    if (!form) {
        console.error('initFormNavigation: Formulario no encontrado');
        return null;
    }

    return new FormNavigation(form, options);
}

// Exportar para uso global
if (typeof module !== 'undefined' && module.exports) {
    module.exports = { FormNavigation, initFormNavigation };
}
