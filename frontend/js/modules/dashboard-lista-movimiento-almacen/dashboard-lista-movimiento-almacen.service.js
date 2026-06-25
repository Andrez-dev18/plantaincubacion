class DashboardListaMovimientoAlmacenService {
    constructor() {
        this.base = '/plantaincubacion/backend/index.php/api/movimiento-almacen';
    }

    getAlmacenes() {
        return Http.get(`${this.base}/almacenes`);
    }

    getTransacciones() {
        return Http.get(`${this.base}/transacciones`);
    }

    listarMovimientos(filtros = {}) {
        return Http.get(`${this.base}/dashboard-lista`, filtros);
    }

    getMovimiento(treg, queryString = '') {
        return Http.get(`${this.base}/cabecera/${encodeURIComponent(treg)}${queryString}`);
    }

    actualizarMovimiento(treg, payload) {
        return Http.put(`${this.base}/cabecera/${encodeURIComponent(treg)}`, payload);
    }

    getComprobantePdfUrl(treg, formato = 'a4', forzarDescarga = true) {
        const params = new URLSearchParams({
            formato: formato === '80mm' ? '80mm' : 'a4',
            download: forzarDescarga ? '1' : '0'
        });

        return `${this.base}/comprobante-pdf/${encodeURIComponent(treg)}?${params.toString()}`;
    }
}
