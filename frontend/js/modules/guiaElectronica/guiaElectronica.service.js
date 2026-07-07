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
    getConductores(q = '', rucTransportista = '', mostrarTodos = false) {
        const queryParams = [];
        if (q) queryParams.push(`q=${encodeURIComponent(q)}`);
        if (rucTransportista) queryParams.push(`rucTransportista=${encodeURIComponent(rucTransportista)}`);
        if (mostrarTodos) queryParams.push(`mostrarTodos=true`);
        const queryStr = queryParams.length > 0 ? `?${queryParams.join('&')}` : '';
        return Http.get(`${this.base}/conductores${queryStr}`);
    }

    // ── Camiones ────────────────────────────────────────────────────────────────
    getCamiones(q = '', rucTransportista = '') {
        const queryParams = [];
        if (q) queryParams.push(`q=${encodeURIComponent(q)}`);
        if (rucTransportista) queryParams.push(`rucTransportista=${encodeURIComponent(rucTransportista)}`);
        const queryStr = queryParams.length > 0 ? `?${queryParams.join('&')}` : '';
        return Http.get(`${this.base}/camiones${queryStr}`);
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

    // ── dirección de cliente ─────────────────────────────────────────────────────
    getDireccionCliente(codigoCliente) {
        return Http.get(`${this.base}/clientes/direccion?codigo=${encodeURIComponent(codigoCliente)}`);
    }

    // ── cencos ───────────────────────────────────────────────────────────────────
    getCencos(q = '') {
        return Http.get(`${this.base}/cencos${q ? `?q=${encodeURIComponent(q)}` : ''}`);
    }

    // ── galpones ─────────────────────────────────────────────────────────────────
    getGalpones(cencos) {
        return Http.get(`${this.base}/galpones?cencos=${encodeURIComponent(cencos)}`);
    }

    // ── guardar guía ─────────────────────────────────────────────────────────────
    guardarGuia(payload) {
        return Http.post(`${this.base}/guardar`, payload);
    }
}

window.GuiaElectronicaService = GuiaElectronicaService;
