class MenuNavController {
    constructor() {
        this.navContainer = document.getElementById('sidebarMenu') || document.getElementById('navMenuPrincipal');
        this.initialized = false;
    }

    async init() {
        if (!this.navContainer || this.initialized) return;
        this.initialized = true;

        console.log('🔄 Inicializando MenuNavController (Nuevo)...');
        try {
            const authService = new AuthService();
            const response = await authService.obtenerMenu();

            if (response.success && response.data && response.data.length > 0) {
                this.renderizarMenu(response.data);
                this.restaurarMenuActivo();
            } else {
                this.navContainer.innerHTML = `
                    <div class="text-center p-4 text-gray-400 text-sm">
                        No tienes módulos asignados.
                    </div>
                `;
            }
        } catch (error) {
            console.error('❌ Error al inicializar el menú:', error);
            this.navContainer.innerHTML = `
                <div class="text-center p-4 text-red-400 text-sm">
                    Error al cargar el menú.
                </div>
            `;
        }
    }

    renderizarMenu(menuData) {
        // Ponemos el título base
        let html = `
            <div class="mb-4">
                <p class="text-blue-300 text-xs uppercase font-semibold mb-2 px-3">Dashboards</p>
            </div>
        `;

        // Recorremos el Nivel 1 (Grupos principales como Oferta, Demanda, etc.)
        menuData.forEach((nodoPrincipal, index) => {
            html += this.crearGrupoPrincipal(nodoPrincipal, index + 1);
        });

        // Inyectamos todo al DOM
        this.navContainer.innerHTML = html;

        // Llamamos a la función de numeración si existe
        if (typeof autoNumerarMenus === 'function') {
            autoNumerarMenus();
        }
    }

    restaurarMenuActivo() {
        const lastUrl = sessionStorage.getItem('last_dashboard_url');
        if (!lastUrl) return; // Si no hay nada guardado, no hacemos nada

        // 1. Buscamos el enlace exacto que coincida con la URL guardada
        const links = document.querySelectorAll('.menu-link');
        let activeLink = null;

        for (const link of links) {
            const onclickAttr = link.getAttribute('onclick');
            if (onclickAttr && onclickAttr.includes(lastUrl)) {
                activeLink = link;
                break;
            }
        }

        // 2. Si encontramos el enlace, lo activamos y abrimos sus padres
        if (activeLink) {
            // Pintamos el botón (usando la función global que ya está en index.html)
            if (typeof selectMenuItem === 'function') {
                selectMenuItem(activeLink);
            }

            // Escalamos hacia arriba en el HTML para encontrar todas las carpetas cerradas y abrirlas
            let currentNode = activeLink;
            while (currentNode) {
                // Si el nodo actual es un submenú oculto
                if (currentNode.classList && currentNode.classList.contains('submenu')) {
                    currentNode.classList.remove('hidden'); // Quitamos el hidden para que se vea

                    // Buscamos el botón que abre este submenú para rotar su flechita
                    const btnPadre = currentNode.previousElementSibling;
                    if (btnPadre && (btnPadre.tagName === 'BUTTON')) {
                        const flechaAbajo = btnPadre.querySelector('.fa-chevron-down');
                        const flechaDerecha = btnPadre.querySelector('.fa-chevron-right');

                        if (flechaAbajo) flechaAbajo.classList.add('rotate-180');
                        if (flechaDerecha) flechaDerecha.classList.add('rotate-90');
                    }
                }

                // Si llegamos a la caja principal del menú, detenemos la búsqueda
                if (currentNode === this.navContainer) break;

                // Subimos un nivel en el árbol HTML
                currentNode = currentNode.parentElement;
            }

            // Opcional: Hacer scroll automático en el menú para que el botón visible quede centrado
            setTimeout(() => {
                activeLink.scrollIntoView({ behavior: 'smooth', block: 'center' });
            }, 300);
        }
    }

    crearGrupoPrincipal(nodo, numero) {
        const icono = nodo.icono || 'fa-solid fa-folder';
        
        // Limpiar numeración previa si existe en el nombre (ej: "1. Programa" -> "Programa")
        const cleanName = (nodo.nom_mod || '').replace(/^\d+(\.\d+)*\.\s*/, '');
        const displayName = `${numero}. ${cleanName}`;

        let hijosHtml = '';
        if (nodo.children && nodo.children.length > 0) {
            nodo.children.forEach((hijo, index) => {
                hijosHtml += this.crearSubnivel(hijo, `${numero}.${index + 1}`);
            });
        }

        return `
        <div id="${nodo.cod_mod}" class="menu-group">
            <button class="menu-item flex items-center justify-between w-full px-4 py-3 text-white rounded-lg"
                onclick="toggleSubmenu('submenu-${nodo.cod_mod}', this)">
                <span class="flex items-center gap-3">
                    <i class="${icono} w-5"></i>
                    <span class="font-medium">${displayName}</span>
                </span>
                <i class="fas fa-chevron-down text-sm"></i>
            </button>
            <div id="submenu-${nodo.cod_mod}" class="submenu hidden pl-10 mt-2 space-y-4">
                ${hijosHtml}
            </div>
        </div>
        `;
    }

    crearSubnivel(nodo, prefix) {
        // Limpiar numeración previa si existe en el nombre
        const cleanName = (nodo.nom_mod || '').replace(/^\d+(\.\d+)*\.\s*/, '');
        const displayName = `${prefix}. ${cleanName}`;

        if (nodo.tipo === 'group') {
            const icono = nodo.icono ? `<i class="${nodo.icono}"></i>` : '<i class="fa-solid fa-folder"></i>';
            let hijosHtml = '';

            // Recorremos los hijos y volvemos a llamar a crearSubnivel de forma recursiva
            if (nodo.children && nodo.children.length > 0) {
                nodo.children.forEach((hijo, index) => {
                    hijosHtml += this.crearSubnivel(hijo, `${prefix}.${index + 1}`);
                });
            }

            return `
            <div id="${nodo.cod_mod}" class="text-sm menu-sub-control mt-2">
                <button class="submenu-toggle flex items-center justify-between w-full text-gray-300 hover:text-white py-1"
                    onclick="toggleSubmenu('submenu-${nodo.cod_mod}', this); event.stopPropagation();">
                    <span>${icono} ${displayName}</span>
                    <i class="fas fa-chevron-right text-xs"></i>
                </button>
                <div id="submenu-${nodo.cod_mod}" class="submenu hidden pl-4 mt-2 space-y-2 border-l border-white-600">
                    ${hijosHtml}
                </div>
            </div>
            `;
        }
        // Si el nodo es directamente un enlace final (Sin importar en qué nivel esté)
        else {
            return this.crearEnlaceFinal(nodo, prefix);
        }
    }

    crearEnlaceFinal(nodo, prefix) {
        // Renderiza el enlace puro de forma limpia con soporte para IDs y parámetros
        const url = nodo.url || '#';
        const param = nodo.tipo_param || '';
        const cleanName = (nodo.nom_mod || '').replace(/^\d+(\.\d+)*\.\s*/, '');
        const displayName = `${prefix}. ${cleanName}`;
        const titulo = nodo.titulo || cleanName;
        const icono = nodo.icono ? `<i class="${nodo.icono}"></i>` : '<i class="fa-link fa-solid"></i>';

        return `
        <div id="${nodo.cod_mod}" class="text-sm menu-sub-control">
            <a href="#"
                onclick="selectMenuItem(this); loadDashboardAndData('${url}', '${param}', '${titulo}'); event.stopPropagation();"
                class="menu-link flex items-center justify-between w-full text-gray-400 hover:text-white py-1">
                <span>${icono} ${displayName}</span>
            </a>
        </div>
        `;
    }
}

// Exponer de forma global con alias compatibles
window.MenuNavController = MenuNavController;
window.MenuController = MenuNavController;
window.menuPrincipal = new MenuNavController();

// Inicialización automática
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => {
        window.menuPrincipal.init();
    });
} else {
    window.menuPrincipal.init();
}