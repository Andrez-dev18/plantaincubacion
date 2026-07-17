// movimiento-almacen.service.js
class MovimientoAlmacenService extends Service {
    constructor() {
        super();
        this.base = '/plantaincubacion/backend/index.php/api/movimiento-almacen';
    }

    // ── Maestros ──────────────────────────────────────────────────────────────
    getAlmacenes()               { return Http.get(`${this.base}/almacenes`); }
    getTransacciones()           { return Http.get(`${this.base}/transacciones`); }
    getTiposDocumento()          { return Http.get(`${this.base}/tipos-documento`); }
    getCentrosCosto()            { return Http.get(`${this.base}/centros-costo`); }
    getClientesProveedores(q = '') { return Http.get(`${this.base}/clientes-proveedores${q ? `?q=${encodeURIComponent(q)}` : ''}`); }
    getProductos(filtros = {}) {
        const q = new URLSearchParams(filtros).toString();
        return Http.get(`${this.base}/productos${q ? `?${q}` : ''}`);
    }
    getLotes(alma = '', codigo = '', fecha = '') {
        const params = new URLSearchParams();
        if (alma) params.set('alma', alma);
        if (codigo) params.set('codigo', codigo);
        if (fecha) params.set('fecha', fecha);
        const q = params.toString();
        return Http.get(`${this.base}/lotes${q ? `?${q}` : ''}`);
    }
    getTipoCambio(fecha)          { return Http.get(`${this.base}/tipo-cambio?fecha=${fecha}`); }
    getCorrelativo(tdoc, tserie) {
        return Http.get(`${this.base}/correlativo?tdoc=${encodeURIComponent(tdoc)}&tserie=${encodeURIComponent(tserie)}`);
    }

    // ── ABC Costing (cascada) ─────────────────────────────────────────────────
    getProcesos()                         { return Http.get(`${this.base}/abc/procesos`); }
    getSubprocesos(proc)                  { return Http.get(`${this.base}/abc/subprocesos?proc=${proc}`); }
    getActividades(proc, subp)            { return Http.get(`${this.base}/abc/actividades?proc=${proc}&subp=${subp}`); }
    getTareas(proc, subp, acti)           { return Http.get(`${this.base}/abc/tareas?proc=${proc}&subp=${subp}&acti=${acti}`); }

    // ── Validaciones ──────────────────────────────────────────────────────────
    verificarFecha(fecha)        { return Http.get(`${this.base}/verificar-fecha?fecha=${fecha}`); }
    verificarMes(fecha)          { return Http.get(`${this.base}/verificar-mes?fecha=${fecha}`); }
    getNuevoReg()                { return Http.get(`${this.base}/nuevo-reg`); }

    // ── Movimientos ───────────────────────────────────────────────────────────
    listar(filtros = {}) {
        const q = new URLSearchParams(filtros).toString();
        return Http.get(`${this.base}/cabecera?${q}`);
    }
    getMovimiento(treg)          { return Http.get(`${this.base}/cabecera/${treg}`); }
    crear(payload)               { return Http.post(`${this.base}/cabecera`, payload); }
    actualizar(treg, payload)    { return Http.put(`${this.base}/cabecera/${treg}`, payload); }
    eliminar(treg)               { return Http.delete(`${this.base}/cabecera/${treg}`); }

    getMovimientoPdfUrl(treg, opciones = {}) {
        const params = new URLSearchParams(opciones).toString();
        return `${this.base}/comprobante-pdf/${encodeURIComponent(treg)}${params ? `?${params}` : ''}`;
    }

    // ── Kardex / Stock ────────────────────────────────────────────────────────
    getKardex(codigo, lote, alma, filtros = {}) {
        const q = new URLSearchParams(filtros).toString();
        const suffix = q ? `?${q}` : '';
        return Http.get(`${this.base}/kardex/${codigo}/${lote}/${alma}${suffix}`);
    }
    getStockAlmacen(alma)        { return Http.get(`${this.base}/stock/${alma}`); }
    getReporteKardex(filtros = {}) {
        const q = new URLSearchParams(filtros).toString();
        return Http.get(`${this.base}/reporte-kardex?${q}`);
    }

    getReporteKardexPdfUrl(filtros = {}) {
        const q = new URLSearchParams(filtros).toString();
        return `${this.base}/reporte-kardex-pdf?${q}`;
    }

    // ── Borradores (Base de Datos) ─────────────────────────────────────────────
    obtenerBorrador(formulario, usuario) {
        const params = new URLSearchParams({ id_programa: '1', formulario, usuario });
        return Http.get(`/plantaincubacion/backend/index.php/api/borradores/obtener?${params.toString()}`);
    }

    guardarBorrador(payload) {
        return Http.post(`/plantaincubacion/backend/index.php/api/borradores/guardar`, payload);
    }

    eliminarBorrador(formulario, usuario) {
        const payload = { id_programa: '1', formulario, usuario };
        return Http.post(`/plantaincubacion/backend/index.php/api/borradores/eliminar`, payload);
    }
}
