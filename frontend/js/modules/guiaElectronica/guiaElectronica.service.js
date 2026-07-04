class GuiaElectronicaService extends Service {
    constructor() {
        super();
        this.base = '/plantaincubacion/backend/index.php/api/guia-electronica';
    }

    // ── Zonas ──────────────────────────────────────────────────────────────
    getZonas() {
        return Http.get(`${this.base}/zonas`);
    }

    // ── Transporte ──────────────────────────────────────────────────────────────
    getTransporte() {
        return Http.get(`${this.base}/tipos-transporte`);
    }

    // ── Transportistas ──────────────────────────────────────────────────────────
    getTransportistas(q = '') {
        return Http.get(`${this.base}/transportistas${q ? `?q=${encodeURIComponent(q)}` : ''}`);
    }

    // ── Conductores ─────────────────────────────────────────────────────────────
    getConductores(q = '') {
        return Http.get(`${this.base}/conductores${q ? `?q=${encodeURIComponent(q)}` : ''}`);
    }

    // ── Camiones ────────────────────────────────────────────────────────────────
    getCamiones(q = '') {
        return Http.get(`${this.base}/camiones${q ? `?q=${encodeURIComponent(q)}` : ''}`);
    }

    // ── clientes ────────────────────────────────────────────────────────────────
    getClientes(q = '') {
        return Http.get(`${this.base}/clientes${q ? `?q=${encodeURIComponent(q)}` : ''}`);
    }
}

window.GuiaElectronicaService = GuiaElectronicaService;
