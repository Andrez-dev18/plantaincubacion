// guiaElectronica.service.js
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
}

window.GuiaElectronicaService = GuiaElectronicaService;
