/**
 * Theme Sync Utility
 * Sistema de sincronización de tema entre index.html y los iframes
 */

(function() {
    const THEME_STORAGE_KEY = 'appTheme';
    const THEME_MODE_STORAGE_KEY = 'appThemeMode';

    function getSystemTheme() {
        try {
            return window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches
                ? 'dark'
                : 'light';
        } catch (error) {
            return 'light';
        }
    }

    function isManualThemeEnabled() {
        return localStorage.getItem(THEME_MODE_STORAGE_KEY) === 'manual';
    }

    /**
     * Intenta obtener el tema desde el contenedor padre si estamos dentro de un iframe.
     * @returns {string|null} 'dark', 'light' o null si no se puede determinar
     */
    function getParentTheme() {
        try {
            if (!window.parent || window.parent === window) {
                return null;
            }

            const parentBody = window.parent.document && window.parent.document.body;
            if (!parentBody) {
                return null;
            }

            const parentDoc = window.parent.document;
            const htmlTheme = parentDoc && parentDoc.documentElement
                ? parentDoc.documentElement.getAttribute('data-theme')
                : null;
            const bodyTheme = parentBody.getAttribute('data-theme');

            if (htmlTheme === 'dark' || bodyTheme === 'dark') {
                return 'dark';
            }

            if (htmlTheme === 'light' || bodyTheme === 'light') {
                return 'light';
            }

            return parentBody.classList.contains('dark-mode') ? 'dark' : 'light';
        } catch (error) {
            // Si no hay acceso al parent por cualquier motivo, continuar con localStorage.
            return null;
        }
    }

    /**
     * Aplica el tema al documento actual
     * @param {string} theme - 'light' o 'dark'
     */
    function applyTheme(theme, options) {
        const persist = !(options && options.persist === false);
        const isDark = theme === 'dark';
        document.body.classList.toggle('dark-mode', isDark);
        document.body.setAttribute('data-theme', isDark ? 'dark' : 'light');

        if (document.documentElement) {
            document.documentElement.setAttribute('data-theme', isDark ? 'dark' : 'light');
        }

        // Actualizar botón de tema si existe en el iframe
        const themeIcon = document.getElementById('theme-icon');
        const themeText = document.getElementById('theme-text');

        if (themeIcon) {
            themeIcon.textContent = isDark ? '☀️' : '🌙';
        }
        if (themeText) {
            themeText.textContent = isDark ? 'Claro' : 'Oscuro';
        }

        // Guardar en localStorage solo cuando sea una eleccion manual.
        if (persist) {
            localStorage.setItem(THEME_STORAGE_KEY, isDark ? 'dark' : 'light');
            localStorage.setItem(THEME_MODE_STORAGE_KEY, 'manual');
        }
    }

    /**
     * Obtiene el tema actual desde localStorage
     * @returns {string} 'light' o 'dark'
     */
    function getCurrentTheme() {
        const storedTheme = localStorage.getItem(THEME_STORAGE_KEY);
        if (isManualThemeEnabled() && (storedTheme === 'dark' || storedTheme === 'light')) {
            return storedTheme;
        }

        return getSystemTheme();
    }

    /**
     * Resuelve el tema inicial priorizando el estado del contenedor padre.
     * @returns {string} 'light' o 'dark'
     */
    function resolveInitialTheme() {
        const parentTheme = getParentTheme();
        return parentTheme || getCurrentTheme();
    }

    /**
     * Inicializa la sincronización de tema para un iframe
     */
    function initThemeSync() {
        // Aplicar tema inicial (prioriza el estado del parent cuando es iframe)
        applyTheme(resolveInitialTheme(), { persist: false });

        // Escuchar mensajes del parent window
        window.addEventListener('message', function(event) {
            if (event.data && event.data.type === 'THEME_CHANGE') {
                applyTheme(event.data.theme, { persist: false });
            }
        });

        // Fallback para iframes: sincroniza cambios del classList del body del parent.
        try {
            if (window.parent && window.parent !== window && window.parent.document && window.parent.document.body) {
                const parentBody = window.parent.document.body;
                const observer = new MutationObserver(function(mutations) {
                    for (const mutation of mutations) {
                        if (mutation.type === 'attributes' && mutation.attributeName === 'class') {
                            const nextTheme = parentBody.classList.contains('dark-mode') ? 'dark' : 'light';
                            applyTheme(nextTheme, { persist: false });
                        }
                    }
                });

                observer.observe(parentBody, {
                    attributes: true,
                    attributeFilter: ['class']
                });
            }
        } catch (error) {
            // Mantener flujo normal si no se puede observar el parent.
        }

        // Si hay un botón de tema en el iframe, agregar funcionalidad
        const themeToggleBtn = document.getElementById('btn-theme-toggle');
        if (themeToggleBtn) {
            themeToggleBtn.addEventListener('click', function() {
                const currentTheme = document.body.classList.contains('dark-mode') ? 'dark' : 'light';
                const newTheme = currentTheme === 'dark' ? 'light' : 'dark';

                // Aplicar tema localmente
                applyTheme(newTheme, { persist: true });

                // Notificar al parent window si existe
                if (window.parent && window.parent !== window) {
                    window.parent.postMessage({
                        type: 'THEME_CHANGE_FROM_IFRAME',
                        theme: newTheme
                    }, '*');
                }
            });
        }

        // Seguir cambios del tema del sistema operativo mientras no exista override manual.
        if (window.matchMedia) {
            const mediaQuery = window.matchMedia('(prefers-color-scheme: dark)');
            const handleSystemThemeChange = function(event) {
                // Si el usuario ya eligio manualmente un tema, no sobrescribirlo.
                if (isManualThemeEnabled()) {
                    return;
                }

                applyTheme(event.matches ? 'dark' : 'light', { persist: false });
            };

            if (typeof mediaQuery.addEventListener === 'function') {
                mediaQuery.addEventListener('change', handleSystemThemeChange);
            } else if (typeof mediaQuery.addListener === 'function') {
                mediaQuery.addListener(handleSystemThemeChange);
            }
        }
    }

    // Auto-inicializar cuando el DOM esté listo
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initThemeSync);
    } else {
        initThemeSync();
    }

    // Exponer funciones globalmente si es necesario
    window.ThemeSync = {
        applyTheme,
        getCurrentTheme,
        getParentTheme,
        init: initThemeSync
    };
})();
