class FormView {
    constructor() {
        this.formContainer = document.getElementById('registroForm');
    }

    generarFormulario(datos = null) {
        if (!this.formContainer) return;

        const esVivo = dataController.esVivo();
        const esProvincia = dataController.esProvincia();
        const tipoProcValue = Constants.TIPOS_PROCESAMIENTO[dataController.tipoActual];

        const html = `
            <div class="space-y-6">
                ${this.generarSeccionGeneral(datos)}
                ${this.generarSeccionCliente(datos, esVivo)}
                ${this.generarSeccionProveedores(datos, esVivo, esProvincia)}
                ${this.generarSeccionPotencial(datos, esVivo)}
                ${this.generarSeccionObservaciones(datos)}
                
                <input type="hidden" name="tipo_proc" value="${tipoProcValue}">
                <input type="hidden" name="id" value="${datos?.id || 0}">
            </div>
        `;

        this.formContainer.innerHTML = html;
    }

    generarSeccionGeneral(datos) {
        return `
            <div class="bg-gradient-to-r from-blue-50 to-indigo-50 p-4 rounded-lg">
                <h4 class="font-bold text-gray-700 mb-3 flex items-center gap-2">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                    Información General
                </h4>
                <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-1">Año</label>
                        <input type="number" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent" name="ano" value="${datos?.ano || Constants.OPCIONES.ANO_DEFAULT}" required>
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-1">Mes</label>
                        <input type="text" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent" name="mes" value="${datos?.mes || ''}" required>
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-1">Provincia</label>
                        <input type="text" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent" name="provincia" value="${datos?.provincia || ''}" required>
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-1">Zona</label>
                        <input type="text" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent" name="zona" value="${datos?.zona || ''}" required>
                    </div>
                </div>
            </div>
        `;
    }

    generarSeccionCliente(datos, esVivo) {
        return `
            <div class="bg-gradient-to-r from-green-50 to-emerald-50 p-4 rounded-lg">
                <h4 class="font-bold text-gray-700 mb-3 flex items-center gap-2">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                    </svg>
                    Datos del Cliente
                </h4>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-1">Compra${esVivo ? '' : ' GRS'}</label>
                        <select class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent" name="${esVivo ? 'compra' : 'compraGrs'}" required>
                            <option value="SI" ${(datos?.compra || datos?.compraGrs) === 'SI' ? 'selected' : ''}>SI</option>
                            <option value="NO" ${(datos?.compra || datos?.compraGrs) === 'NO' ? 'selected' : ''}>NO</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-1">Tipo Cliente</label>
                        <input type="text" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent" name="${esVivo ? 'tipo_cliente' : 'tipoCliente'}" value="${datos?.tipo_cliente || datos?.tipoCliente || ''}" required>
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-1">Nombre</label>
                        <input type="text" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent" name="nombre" value="${datos?.nombre || ''}" required>
                    </div>
                </div>
            </div>
        `;
    }

    generarSeccionProveedores(datos, esVivo, esProvincia) {
        const proveedores = this.obtenerProveedores(esVivo, esProvincia);
        
        return `
            <div class="bg-gradient-to-r from-yellow-50 to-orange-50 p-4 rounded-lg">
                <h4 class="font-bold text-gray-700 mb-3 flex items-center gap-2">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path>
                    </svg>
                    Proveedores
                </h4>
                <div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-6 gap-3">
                    ${proveedores.map(p => this.generarCampoProveedor(p, datos, esVivo)).join('')}
                </div>
            </div>
        `;
    }

    generarCampoProveedor(proveedor, datos, esVivo) {
        const label = this.obtenerLabelProveedor(proveedor, esVivo);
        const valor = datos?.[proveedor] || 0;

        return `
            <div>
                <label class="block text-xs font-semibold text-gray-700 mb-1">${label}</label>
                <input type="number" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent text-sm" name="${proveedor}" value="${valor}">
            </div>
        `;
    }

    obtenerProveedores(esVivo, esProvincia) {
        if (esVivo) {
            let proveedores = [...Constants.PROVEEDORES_VIVO.COMUNES];
            if (esProvincia) {
                proveedores.push(...Constants.PROVEEDORES_VIVO.PROVINCIA);
            } else {
                proveedores.push(...Constants.PROVEEDORES_VIVO.AREQUIPA);
            }
            proveedores.push('otras_granjas_chicas');
            return proveedores;
        } else {
            let proveedores = [...Constants.PROVEEDORES_BENEFICIADO.COMUNES];
            proveedores.push(...Constants.PROVEEDORES_BENEFICIADO.AREQUIPA);
            if (esProvincia) {
                proveedores.push(...Constants.PROVEEDORES_BENEFICIADO.PROVINCIA);
            }
            proveedores.push('otros');
            return proveedores;
        }
    }

    obtenerLabelProveedor(proveedor, esVivo) {
        const labels = {
            'grs': 'GRS', 'rp': 'RP', 'renzo': 'Renzo', 'avicola_renzo': 'Avícola Renzo',
            'fafo': 'Fafo', 'santa_angela': 'Santa Angela', 'rosario': 'Rosario',
            'pollo_lima': 'Pollo Lima', 'jorge_pan': 'Jorge Pan', 'mirian_g': 'Mirian G',
            'vasquez': 'Vasquez', 'san_joaquin': 'San Joaquin', 'fortunato': 'Fortunato',
            'perca': 'Perca', 'gamboa': 'Gamboa', 'asoc_sondor': 'Asoc Sondor',
            'otras_granjas_chicas': 'Otras Granjas', 'avelino': 'Avelino',
            'peladores': 'Peladores', 'avicruz': 'Avicruz', 'rafael': 'Rafael',
            'matilde': 'Matilde', 'avirox': 'Avirox', 'julia': 'Julia',
            'simon': 'Simón', 'yesica': 'Yesica', 'gabriel': 'Gabriel',
            'arturo': 'Arturo', 'nicolas': 'Nicolás', 'luis_f': 'Luis F',
            'mirella': 'Mirella', 'grs_vivo': 'GRS Vivo', 'santa_elena': 'Santa Elena',
            'granjas_chicas': 'Granjas Chicas', 'sanfern_lima': 'Sanfern Lima',
            'otros': 'Otros'
        };

        return labels[proveedor] || proveedor;
    }

    generarSeccionPotencial(datos, esVivo) {
        return `
            <div class="bg-gradient-to-r from-purple-50 to-pink-50 p-4 rounded-lg">
                <h4 class="font-bold text-gray-700 mb-3 flex items-center gap-2">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
                    </svg>
                    Potencial y Condiciones
                </h4>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-1">Potencial Mínimo</label>
                        <input type="number" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent" name="${esVivo ? 'potencial_minimo' : 'potencialMinimo'}" value="${datos?.potencial_minimo || datos?.potencialMinimo || 0}">
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-1">Potencial Máximo</label>
                        <input type="number" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent" name="${esVivo ? 'potencial_maximo' : 'potencialMaximo'}" value="${datos?.potencial_maximo || datos?.potencialMaximo || 0}">
                    </div>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-1">Condición PT Min</label>
                        <textarea class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent" name="${esVivo ? 'condicion_ptmin' : 'condicionPtmin'}" rows="3">${datos?.condicion_ptmin || datos?.condicionPtmin || ''}</textarea>
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-1">Condición PT Max</label>
                        <textarea class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent" name="${esVivo ? 'condicion_ptmax' : 'condicionPtmax'}" rows="3">${datos?.condicion_ptmax || datos?.condicionPtmax || ''}</textarea>
                    </div>
                </div>
            </div>
        `;
    }

    generarSeccionObservaciones(datos) {
        return `
            <div class="bg-gradient-to-r from-gray-50 to-slate-50 p-4 rounded-lg">
                <h4 class="font-bold text-gray-700 mb-3 flex items-center gap-2">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                    </svg>
                    Observaciones
                </h4>
                <textarea class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent" name="observaciones" rows="3" placeholder="Escribe aquí las observaciones adicionales...">${datos?.observaciones || ''}</textarea>
            </div>
        `;
    }
}

window.formView = new FormView();
