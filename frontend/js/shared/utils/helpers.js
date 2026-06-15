const Helpers = {
    formatearNumero(numero) {
        return numero.toLocaleString('es-PE');
    },

    formatearFecha(fecha) {
        const f = typeof fecha === 'string' ? new Date(fecha) : fecha;
        return f.toLocaleDateString('es-PE');
    },

    debounce(func, wait = 300) {
        let timeout;
        return function executedFunction(...args) {
            const later = () => {
                clearTimeout(timeout);
                func(...args);
            };
            clearTimeout(timeout);
            timeout = setTimeout(later, wait);
        };
    },

    estaVacio(obj) {
        return Object.keys(obj).length === 0;
    },

    clonarObjeto(obj) {
        return JSON.parse(JSON.stringify(obj));
    },

    capitalizar(str) {
        return str.charAt(0).toUpperCase() + str.slice(1).toLowerCase();
    },

    generarId() {
        return `${Date.now()}-${Math.random().toString(36).substr(2, 9)}`;
    },

    descargarArchivo(url, nombreArchivo) {
        const link = document.createElement('a');
        link.href = url;
        link.download = nombreArchivo;
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
    },

    esNumerico(valor) {
        return !isNaN(parseFloat(valor)) && isFinite(valor);
    },

    truncar(texto, longitud = 50) {
        if (texto.length <= longitud) return texto;
        return texto.substring(0, longitud) + '...';
    }
};

window.Helpers = Helpers;
