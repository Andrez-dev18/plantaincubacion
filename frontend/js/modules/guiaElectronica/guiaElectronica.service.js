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

    // ── artículos ────────────────────────────────────────────────────────────────
    getArticulos(q = '') {
        return Http.get(`${this.base}/articulos${q ? `?q=${encodeURIComponent(q)}` : ''}`);
    }

    // ── lotes ──────────────────────────────────────────────────────────────────
    getLotes(almacen, codigoArticulo, anio) {
        const query = `?almacen=${encodeURIComponent(almacen)}&articulo=${encodeURIComponent(codigoArticulo)}&anio=${encodeURIComponent(anio)}`;
        return Http.get(`${this.base}/lotes${query}`);
    }

    // ── series ─────────────────────────────────────────────────────────────────
    getSeries(almacen, cliente) {
        const query = `?almacen=${encodeURIComponent(almacen)}&cliente=${encodeURIComponent(cliente)}`;
        return Http.get(`${this.base}/series${query}`);
    }

    // ── motivos de traslado ──────────────────────────────────────────────────────
    getMotivosTraslado() {
        return Http.get(`${this.base}/motivos-traslado`);
    }
}

window.GuiaElectronicaService = GuiaElectronicaService;
