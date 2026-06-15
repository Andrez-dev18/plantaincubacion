/**
 * SimulacionEscenariosService
 * 
 * Servicio para comunicación con el backend del módulo de Simulación de Escenarios
 */
class SimulacionEscenariosService {
    
    constructor() {
        this.baseURL = '/plantaincubacion/backend/index.php/api/simulacion-escenarios';
    }

    /**
     * Obtener lista de proyecciones disponibles
     */
    async obtenerProyecciones() {
        try {
            const response = await fetch('/plantaincubacion/backend/index.php/api/secuencia/proyecciones');
            
            if (!response.ok) {
                throw new Error(`Error HTTP: ${response.status}`);
            }
            
            const data = await response.json();
            
            if (data.success) {
                return data.data || [];
            } else {
                throw new Error(data.message || 'Error al obtener proyecciones');
            }
        } catch (error) {
            console.error('Error en obtenerProyecciones:', error);
            throw error;
        }
    }

    /**
     * Listar escenarios de una proyección
     */
    async listarEscenarios(proyeccion) {
        try {
            const response = await fetch(`${this.baseURL}/listar?proyeccion=${encodeURIComponent(proyeccion)}`);
            
            if (!response.ok) {
                throw new Error(`Error HTTP: ${response.status}`);
            }
            
            const data = await response.json();
            
            if (data.success) {
                return data.data || [];
            } else {
                throw new Error(data.message || 'Error al listar escenarios');
            }
        } catch (error) {
            console.error('Error en listarEscenarios:', error);
            throw error;
        }
    }

    /**
     * Guardar un escenario (con auto-cálculo)
     */
    async guardarEscenario(escenario) {
        try {
            const response = await fetch(`${this.baseURL}/guardar`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify(escenario)
            });
            
            if (!response.ok) {
                throw new Error(`Error HTTP: ${response.status}`);
            }
            
            const data = await response.json();
            
            if (data.success) {
                return data;
            } else {
                throw new Error(data.message || 'Error al guardar escenario');
            }
        } catch (error) {
            console.error('Error en guardarEscenario:', error);
            throw error;
        }
    }

    /**
     * Calcular un escenario
     */
    async calcularEscenario(idEscenario) {
        try {
            const response = await fetch(`${this.baseURL}/calcular`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({ id_escenario: idEscenario })
            });
            
            if (!response.ok) {
                throw new Error(`Error HTTP: ${response.status}`);
            }
            
            const data = await response.json();
            
            if (data.success) {
                return data;
            } else {
                throw new Error(data.message || 'Error al calcular escenario');
            }
        } catch (error) {
            console.error('Error en calcularEscenario:', error);
            throw error;
        }
    }

    /**
     * Obtener un escenario por ID
     */
    async obtenerEscenario(id) {
        try {
            const response = await fetch(`${this.baseURL}/obtener/${id}`);
            
            if (!response.ok) {
                throw new Error(`Error HTTP: ${response.status}`);
            }
            
            const data = await response.json();
            
            if (data.success) {
                return data.data;
            } else {
                throw new Error(data.message || 'Error al obtener escenario');
            }
        } catch (error) {
            console.error('Error en obtenerEscenario:', error);
            throw error;
        }
    }

    /**
     * Eliminar un escenario
     */
    async eliminarEscenario(id) {
        try {
            const response = await fetch(`${this.baseURL}/eliminar/${id}`, {
                method: 'DELETE'
            });
            
            if (!response.ok) {
                throw new Error(`Error HTTP: ${response.status}`);
            }
            
            const data = await response.json();
            
            if (data.success) {
                return data;
            } else {
                throw new Error(data.message || 'Error al eliminar escenario');
            }
        } catch (error) {
            console.error('Error en eliminarEscenario:', error);
            throw error;
        }
    }
}
