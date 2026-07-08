// guiaElectronica.navigation.js
// Lógica de navegabilidad exclusiva para la ventana de Guía de Remisión Electrónica

class GuiaElectronicaNavigation {
    constructor(formSelector) {
        this.form = document.querySelector(formSelector);
        if (!this.form) return;
        this.init();
    }

    init() {
        this.form.addEventListener('keydown', (e) => this.handleKeyDown(e));

        // Escuchar la tecla Escape a nivel de documento para retroceder de forma robusta
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') {
                // Si hay un buscador/modal dinámico abierto o Swal, dejar que ellos manejen Escape
                const modal = document.getElementById('modal-transportistas');
                if ((modal && modal.style.display === 'flex') || document.querySelector('.swal2-container')) {
                    return;
                }

                const campos = this.obtenerCampos();
                let currentEl = document.activeElement;
                
                // Si el activeElement no es un campo válido, usar el último campo focalizado en memoria
                if (!currentEl || !campos.includes(currentEl)) {
                    currentEl = this.ultimoCampoFocalizado;
                }

                const index = campos.indexOf(currentEl);
                if (index !== -1) {
                    e.preventDefault();
                    this.retrocederAnterior(campos, index);
                }
            }
        });

        // Seguimiento del último campo enfocado
        this.ultimoCampoFocalizado = null;
        this.form.addEventListener('focusin', (e) => {
            const target = e.target;
            const campos = this.obtenerCampos();
            if (campos.includes(target)) {
                this.ultimoCampoFocalizado = target;
            }
        });

        // Restaurar el foco cuando la ventana vuelve a tener el foco principal (ej. desde devtools)
        window.addEventListener('focus', () => {
            setTimeout(() => {
                const modal = document.getElementById('modal-transportistas');
                if ((modal && modal.style.display === 'flex') || document.querySelector('.swal2-container')) {
                    return;
                }
                const active = document.activeElement;
                if ((!active || active === document.body || active.tagName === 'HTML') && this.ultimoCampoFocalizado) {
                    this.ultimoCampoFocalizado.focus();
                    if (typeof this.ultimoCampoFocalizado.select === 'function' && 
                        (this.ultimoCampoFocalizado.type === 'text' || this.ultimoCampoFocalizado.type === 'number')) {
                        this.ultimoCampoFocalizado.select();
                    }
                }
            }, 50);
        });

        // Restaurar el foco cuando se hace click en el fondo o zonas no interactivas de la página
        document.addEventListener('click', (e) => {
            setTimeout(() => {
                const modal = document.getElementById('modal-transportistas');
                if ((modal && modal.style.display === 'flex') || document.querySelector('.swal2-container')) {
                    return;
                }
                const active = document.activeElement;
                if ((!active || active === document.body || active.tagName === 'HTML') && this.ultimoCampoFocalizado) {
                    // Evitar re-enfocar si el click fue sobre un elemento interactivo (botones, enlaces, etc.)
                    if (this.esElementoInteractivo(e.target)) {
                        return;
                    }
                    this.ultimoCampoFocalizado.focus();
                    if (typeof this.ultimoCampoFocalizado.select === 'function' && 
                        (this.ultimoCampoFocalizado.type === 'text' || this.ultimoCampoFocalizado.type === 'number')) {
                        this.ultimoCampoFocalizado.select();
                    }
                }
            }, 50);
        });
    }

    // Helper para verificar si un elemento es interactivo y no debe ser interrumpido
    esElementoInteractivo(el) {
        if (!el) return false;
        const tagName = el.tagName.toLowerCase();
        if (['input', 'select', 'textarea', 'button', 'a'].includes(tagName)) return true;
        if (el.tabIndex >= 0) return true;
        if (el.closest('button') || el.closest('a') || el.closest('tr') || el.closest('label')) return true;
        if (el.closest('.swal2-container') || el.closest('#modal-transportistas')) return true;
        return false;
    }

    // Retorna todos los campos focalizables y visibles de este formulario en orden de tabulación
    obtenerCampos() {
        const fields = this.form.querySelectorAll(
            'input:not([type="hidden"]):not([type="submit"]):not([type="button"]):not([type="reset"]), ' +
            'select, ' +
            'textarea'
        );
        return Array.from(fields).filter(el => {
            if (el.disabled || el.readOnly || el.tabIndex < 0) return false;
            
            // Si es un radio button, solo permitir el que esté seleccionado (checked)
            if (el.type === 'radio') {
                if (!el.checked) {
                    const name = el.name;
                    if (name) {
                        const form = el.form || document;
                        const checkedRadio = form.querySelector(`input[type="radio"][name="${name}"]:checked`);
                        if (checkedRadio) return false;
                        const allRadios = Array.from(form.querySelectorAll(`input[type="radio"][name="${name}"]`));
                        if (allRadios.indexOf(el) !== 0) return false;
                    }
                }
            }
            
            // Verificar visibilidad
            const style = window.getComputedStyle(el);
            if (style.display === 'none' || style.visibility === 'hidden') return false;
            
            // Verificar si el elemento o alguno de sus padres está oculto
            if (el.offsetParent === null && style.position !== 'fixed') return false;
            
            return true;
        });
    }

    // Indica si el campo es de texto libre y permite espacios normales al escribir
    permiteEspacio(el) {
        const id = el.id;
        const textFieldsWithSpaces = [
            'puntoPartida', 'puntoLlegada', 'observaciones', 
            'pedidosRef', 'nomConductor', 'nomTransportista', 
            'clienteNombre', 'inputArtDescri'
        ];
        if (textFieldsWithSpaces.includes(id)) {
            return true;
        }

        const lowerId = id.toLowerCase();
        if (lowerId.includes('nombre') || 
            lowerId.includes('observ') || 
            lowerId.includes('partida') || 
            lowerId.includes('llegada') ||
            lowerId.includes('descri')) {
            return true;
        }
        return false;
    }

    handleKeyDown(e) {
        // Si hay una alerta SweetAlert abierta, dejar que interactúe allí
        if (document.querySelector('.swal2-container')) {
            return;
        }

        // Si hay un buscador/modal dinámico abierto, no intervenir
        const modal = document.getElementById('modal-transportistas');
        if (modal && modal.style.display === 'flex') {
            return;
        }

        // Solo procesar Enter y Espacio (Escape se maneja a nivel de documento)
        if (e.key !== 'Enter' && e.key !== ' ') return;

        const target = e.target;
        const campos = this.obtenerCampos();
        const index = campos.indexOf(target);

        if (index === -1) return;

        // 1. ESPACIO -> Avanzar (solo si el campo no es de texto libre)
        if (e.key === ' ') {
            if (!this.permiteEspacio(target)) {
                e.preventDefault();
                this.avanzarSiguiente(campos, index);
            }
            return;
        }

        // 2. ENTER -> Avanzar
        if (e.key === 'Enter') {
            e.preventDefault();
            this.avanzarSiguiente(campos, index);
        }
    }

    avanzarSiguiente(campos, index) {
        const current = campos[index];
        if (current && (current.id === 'placaP' || current.id === 'placaR')) {
            const placaP = document.getElementById('placaP');
            const placaR = document.getElementById('placaR');
            if (placaP && placaR) {
                const valP = placaP.value.trim().toUpperCase();
                const valR = placaR.value.trim().toUpperCase();
                if (valP !== '' && valR !== '' && valP === valR) {
                    current.blur();
                    return;
                }
            }
        }
        if (index < campos.length - 1) {
            const next = campos[index + 1];
            next.focus();
            
            // Si el siguiente campo es un select nativo, intentar abrir el menú de opciones
            if (next.tagName === 'SELECT') {
                try {
                    if (typeof next.showPicker === 'function') {
                        next.showPicker();
                    }
                } catch (err) {
                    console.warn('showPicker no soportado en select:', err);
                }
            } else if (next.tagName === 'INPUT' && (next.type === 'text' || next.type === 'number')) {
                next.select();
            }
        }
    }

    retrocederAnterior(campos, index) {
        const current = campos[index];
        if (current && (current.id === 'placaP' || current.id === 'placaR')) {
            const placaP = document.getElementById('placaP');
            const placaR = document.getElementById('placaR');
            if (placaP && placaR) {
                const valP = placaP.value.trim().toUpperCase();
                const valR = placaR.value.trim().toUpperCase();
                if (valP !== '' && valR !== '' && valP === valR) {
                    current.blur();
                    return;
                }
            }
        }
        if (index > 0) {
            const prev = campos[index - 1];
            prev.focus();

            // Si el anterior campo es un select nativo, abrirlo
            if (prev.tagName === 'SELECT') {
                try {
                    if (typeof prev.showPicker === 'function') {
                        prev.showPicker();
                    }
                } catch (err) {
                    console.warn('showPicker no soportado en select:', err);
                }
            } else if (prev.tagName === 'INPUT' && (prev.type === 'text' || prev.type === 'number')) {
                prev.select();
            }
        }
    }

    avanzarDesdeCampo(fieldId) {
        const campos = this.obtenerCampos();
        const el = document.getElementById(fieldId);
        const index = campos.indexOf(el);
        if (index !== -1) {
            this.avanzarSiguiente(campos, index);
        }
    }
}

// Iniciar e inyectar el listener de Enter en los selects nativos para confirmar la opción y avanzar
document.addEventListener('DOMContentLoaded', () => {
    window.guiaNav = new GuiaElectronicaNavigation('#formGuiaRemision');
    
    // Interceptar SweetAlert2 para restaurar el foco al input anterior al cerrarse
    if (window.Swal) {
        const originalFire = window.Swal.fire;
        window.Swal.fire = function (...args) {
            return originalFire.apply(this, args).then((result) => {
                if (window.guiaNav && window.guiaNav.ultimoCampoFocalizado) {
                    const elementToFocus = window.guiaNav.ultimoCampoFocalizado;
                    if (typeof elementToFocus.focus === 'function' && document.body.contains(elementToFocus)) {
                        setTimeout(() => {
                            // Si ya hay otra alerta de SweetAlert2 abierta en este momento, no robar el foco
                            if (document.querySelector('.swal2-container')) {
                                return;
                            }
                            elementToFocus.focus();
                            if (typeof elementToFocus.select === 'function' && 
                                (elementToFocus.type === 'text' || elementToFocus.type === 'number')) {
                                elementToFocus.select();
                            }
                        }, 150); // Dar suficiente tiempo para que finalice la animación de cierre de SweetAlert2
                    }
                }
                return result;
            });
        };
    }
    
    // Escuchar Enter en todos los selectores nativos para proceder al siguiente campo
    document.querySelectorAll('#formGuiaRemision select').forEach(sel => {
        sel.addEventListener('keydown', (e) => {
            if (e.key === 'Enter') {
                e.preventDefault();
                e.stopPropagation();
                
                const nav = window.guiaNav;
                if (nav) {
                    const campos = nav.obtenerCampos();
                    const index = campos.indexOf(sel);
                    if (index !== -1) {
                        nav.avanzarSiguiente(campos, index);
                    }
                }
            }
        });
    });
});
