class ReporteTransaccionService extends Service {
    constructor() {
        super();
        // Usamos la ruta limpia hacia tu nuevo módulo
        this.base = '/plantaincubacion/backend/api/reporte/transacciones';
    }

    // ── Filtros ──────────────────────────────────────────────────────────────
    getTransaccionesSelect() { 
        // Llama a /plantaincubacion/backend/api/reporte/transacciones/select
        return Http.get(`${this.base}/select`); 
    }

    getAlmacenesSelect() { 
        return Http.get(`${this.base.replace('/transacciones', '')}/almacenes/select`); 
    }

    getCencosSelect() { 
        return Http.get(`${this.base.replace('/transacciones', '')}/cencos/select`); 
    }

    getCuentasCorrientes() { 
        return Http.get(`${this.base.replace('/transacciones', '')}/cuentas-corrientes/select`); 
    }

    getLineas() { 
        return Http.get(`${this.base.replace('/transacciones', '')}/lineas/select`); 
    }

    getCodigos() { 
        return Http.get(`${this.base.replace('/transacciones', '')}/articulos/select`); 
    }

    //REPORTE
    getReporteTransacciones(filtros = {}) {
        const q = new URLSearchParams(filtros).toString();
        return Http.get(`${this.base}/generar${q ? `?${q}` : ''}`);
    }

    exportarReportePdf(filtros = {}) {
        // Creamos un formulario dinámico para enviar por POST (evita límites de URL larga)
        const form = document.createElement('form');
        form.method = 'POST';
        
        // Magia de rutas: Reemplaza '/transacciones' por '/exportar' manteniendo la base
        // Resultado: /plantaincubacion/backend/api/reporte/exportar
        form.action = `${this.base.replace('/transacciones', '')}/exportar`; 
        form.target = '_blank'; // Abre el PDF en nueva pestaña

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
        // Creamos un formulario dinámico para enviar por POST (evita límites de URL larga)
        const form = document.createElement('form');
        form.method = 'POST';
        
        // Magia de rutas: Reemplaza '/transacciones' por '/exportar-excel' manteniendo la base
        // Resultado: /plantaincubacion/backend/api/reporte/exportar-excel
        form.action = `${this.base.replace('/transacciones', '')}/exportar-excel`; 
        form.target = '_blank'; // Abre el PDF en nueva pestaña (para descarga)

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

window.ReporteTransaccionService = ReporteTransaccionService;