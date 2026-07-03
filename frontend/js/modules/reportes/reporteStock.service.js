class ReporteStockService extends Service {
    constructor() {
        super();
        // Ruta limpia y base hacia el nuevo módulo de stock
        this.base = '/plantaincubacion/backend/api/reporte/stock';
    }

    // ── FILTROS SELECTS (Alineados con tus nuevas rutas de stock) ─────────────────
    getAlmacenesSelect() { 
        return Http.get(`${this.base}/almacenes/select`); 
    }

    getLineas() { 
        return Http.get(`${this.base}/lineas/select`); 
    }

    getCodigos() { 
        return Http.get(`${this.base}/articulos/select`); 
    }

    // ── DATA DE LA GRILLA PRINCIPAL ──────────────────────────────────────────────
    getReporteStock(filtros = {}) {
        const q = new URLSearchParams(filtros).toString();
        return Http.get(`${this.base}/generar${q ? `?${q}` : ''}`);
    }

    // ── EXPORTACIÓN PDF POST ─────────────────────────────────────────────────────
    exportarReportePdf(filtros = {}) {
        const form = document.createElement('form');
        form.method = 'POST';
        
        // Apunta directo a /plantaincubacion/backend/api/reporte/stock/exportar
        form.action = `${this.base}/exportar`; 
        form.target = '_blank'; 

        Object.keys(filtros).forEach(key => {
            const input = document.createElement('input');
            input.type = 'hidden'; 
            input.name = key; 
            input.value = filtros[key];
            form.appendChild(input);
        });

        document.body.appendChild(form);
        form.submit();
        document.body.removeChild(form);
    }

    exportarReporteExcel(filtros = {}) {
        const form = document.createElement('form');
        form.method = 'POST';
        
        // Apunta directo a /plantaincubacion/backend/api/reporte/stock/exportar-excel
        form.action = `${this.base}/exportar-excel`; 
        form.target = '_blank'; 

        Object.keys(filtros).forEach(key => {
            const input = document.createElement('input');
            input.type = 'hidden'; 
            input.name = key; 
            input.value = filtros[key];
            form.appendChild(input);
        });

        document.body.appendChild(form);
        form.submit();
        document.body.removeChild(form);
    }
}

window.ReporteStockService = ReporteStockService;