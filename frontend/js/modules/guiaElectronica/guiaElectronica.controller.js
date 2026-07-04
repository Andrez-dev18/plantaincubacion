// guiaElectronica.controller.js
class GuiaElectronicaController {
    constructor() {
        this.guiaService = new window.GuiaElectronicaService();
    }

    async init() {
        this.setupEventListeners();
        await this.cargarZonas();
        await this.cargarTiposTransporte();
    }

    setupEventListeners() {
        // En esta etapa preliminar dejamos listos los listeners básicos
        const form = document.getElementById('formGuiaRemision');
        if (form) {
            form.addEventListener('submit', (e) => e.preventDefault());
        }
    }

    async cargarZonas() {
        const selectOrigen = document.getElementById('zonaOrigen');
        const selectDestino = document.getElementById('zonaDestino');

        if (!selectOrigen && !selectDestino) return;

        try {
            const response = await this.guiaService.getZonas();

            if (response && response.success && Array.isArray(response.data)) {
                const fragmentOrigen = document.createDocumentFragment();
                const fragmentDestino = document.createDocumentFragment();

                // Limpiar opciones anteriores
                if (selectOrigen) selectOrigen.innerHTML = '<option value="">-- Seleccione Zona Origen --</option>';
                if (selectDestino) selectDestino.innerHTML = '<option value="">-- Seleccione Zona Destino --</option>';

                response.data.forEach(item => {
                    const codigo = item.codigo || item.tzona;
                    const descripcion = item.descri || item.descripcion;

                    if (selectOrigen) {
                        const opt = document.createElement('option');
                        opt.value = codigo;
                        opt.textContent = `${codigo} | ${descripcion}`;
                        fragmentOrigen.appendChild(opt);
                    }

                    if (selectDestino) {
                        const opt = document.createElement('option');
                        opt.value = codigo;
                        opt.textContent = `${codigo} | ${descripcion}`;
                        fragmentDestino.appendChild(opt);
                    }
                });

                if (selectOrigen) selectOrigen.appendChild(fragmentOrigen);
                if (selectDestino) selectDestino.appendChild(fragmentDestino);
            }
        } catch (error) {
            console.error("Error al cargar zonas de origen/destino:", error);
        }
    }

    async cargarTiposTransporte() {
        const selectTransporte = document.getElementById('tipoTransporte');
        if (!selectTransporte) return;

        try {
            const response = await this.guiaService.getTransporte();

            if (response && response.success && Array.isArray(response.data)) {
                const fragment = document.createDocumentFragment();

                // Limpiar opciones anteriores
                selectTransporte.innerHTML = '<option value="">-- Seleccione Tipo Transporte --</option>';

                response.data.forEach(item => {
                    const codigo = item.codigo || item.cod;
                    const descripcion = item.descripcion || item.nom;

                    const opt = document.createElement('option');
                    opt.value = codigo;
                    opt.textContent = `${codigo} | ${descripcion}`;
                    fragment.appendChild(opt);
                });

                selectTransporte.appendChild(fragment);
            }
        } catch (error) {
            console.error("Error al cargar tipos de transporte:", error);
        }
    }
}

window.GuiaElectronicaController = GuiaElectronicaController;
