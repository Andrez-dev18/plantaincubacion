/**
 * Controlador de Galpones
 * Gestiona la lógica del módulo de galpones
 */
class GalponesController {
    constructor() {
        this.service = new GalponesService();
        this.table = null;
        this.galpones = [];
        this.galponesOriginal = [];
        this.caracteristicas = [];
        this.granjas = []; // Agregar array de granjas
        this.modal = null;
        
        this.init();
    }

    async init() {
        // Cargar características primero
        await this.cargarCaracteristicas();
        
        // Cargar granjas para los dropdowns
        await this.cargarGranjas();
        
        // Cargar galpones
        await this.cargarGalpones();

        // Inicializar tabla DESPUÉS de que los datos estén cargados
        setTimeout(() => {
            if (!this.table && this.galpones.length > 0) {
                this.table = new GalponTable('galponesTable', {
                    galpones: this.galpones,
                    caracteristicas: this.caracteristicas,
                    onView: (id) => this.verGalpon(id),
                    onEdit: (id) => this.editarGalpon(id),
                    onDelete: (id) => this.eliminarGalpon(id)
                });
                this.table.render();
            }
        }, 500);
    }

    async cargarCaracteristicas() {
        try {
            const response = await this.service.obtenerCaracteristicas();
            
            console.log('📋 CARACTERÍSTICAS:', response.success ? '✅' : '❌');
            console.log('   Total características:', response.data?.length || 0);
            
            if (response.success) {
                this.caracteristicas = response.data;
                console.log('   Primeras 3:', response.data?.slice(0, 3));
            } else {
                Notification.warning('No se pudieron cargar las características');
            }
        } catch (error) {
            console.error('Error al cargar características:', error);
        }
    }

    async cargarGranjas() {
        try {
            const response = await this.service.obtenerGranjas();
            
            console.log('🌾 CARGANDO GRANJAS:', response.success ? '✅' : '❌');
            console.log('   Total:', response.data?.length || 0);
            
            if (response.success && response.data && response.data.length > 0) {
                // Guardar granjas en memoria
                this.granjas = response.data;
                console.log('   Primeras 3:', response.data.slice(0, 3));
                
                // Llenar dropdown de filtros
                const selectGranja = document.getElementById('filterGranja');
                if (selectGranja) {
                    console.log('   Llenando combo filterGranja...');
                    response.data.forEach(granja => {
                        const option = document.createElement('option');
                        option.value = granja.id;
                        option.textContent = granja.nombre;
                        selectGranja.appendChild(option);
                    });
                    console.log('   ✅ Combo filterGranja llenado:', selectGranja.options.length, 'opciones');
                } else {
                    console.error('   ❌ No se encontró el elemento #filterGranja');
                }
            } else {
                console.error('   ❌ Sin datos:', response.message || 'Empty data');
            }
        } catch (error) {
            console.error('❌ Error al cargar granjas:', error);
        }
    }

    async cargarGalpones() {
        try {
            const response = await this.service.listar();
            console.log('Response de galpones:', response);

            if (response.success) {
                this.galpones = response.data || [];
                this.galponesOriginal = [...this.galpones];
                console.log('Galpones cargados:', this.galpones.length);
                console.log('Primer galpón:', this.galponesOriginal[0]);
                console.log('Campos disponibles:', this.galponesOriginal[0] ? Object.keys(this.galponesOriginal[0]) : 'sin datos');
                console.log('📊 RENDERIZANDO TABLA con', this.caracteristicas.length, 'características');
                this.renderTabla();
            } else {
                console.error('Error en respuesta:', response);
                Notification.error(response.message || response.mensaje || 'Error al cargar galpones');
            }
        } catch (error) {
            console.error('Error al cargar galpones:', error);
            Notification.error('Error de conexión al cargar galpones');
        }
    }

    renderTabla() {
        if (!this.table) {
            this.table = new GalponTable('galponesTable', {
                galpones: this.galpones,
                caracteristicas: this.caracteristicas,
                onView: (id) => this.verGalpon(id),
                onEdit: (id) => this.editarGalpon(id),
                onDelete: (id) => this.eliminarGalpon(id)
            });
        }
        
        this.table.galpones = this.galpones;
        this.table.caracteristicas = this.caracteristicas;
        this.table.render();
    }

    aplicarFiltros() {
        const searchTerm = document.getElementById('searchInput').value.trim().toLowerCase();
        const granja = document.getElementById('filterGranja').value;
        const fechaDesde = document.getElementById('filterFechaDesde').value;
        const fechaHasta = document.getElementById('filterFechaHasta').value;

        console.log('=== APLICANDO FILTROS ===');
        console.log('searchTerm:', searchTerm);
        console.log('granja seleccionada:', granja);
        console.log('Total galpones:', this.galponesOriginal.length);

        this.galpones = this.galponesOriginal.filter(galpon => {
            // Filtro por búsqueda de texto
            let cumpleBusqueda = true;
            if (searchTerm) {
                const id = (galpon.id || '').toString().toLowerCase();
                const nombre = (galpon.nombre || '').toLowerCase();
                const numGalpon = (galpon.galpon || '').toString().toLowerCase();
                cumpleBusqueda = id.includes(searchTerm) || nombre.includes(searchTerm) || numGalpon.includes(searchTerm);
            }

            // Filtro por granja
            let cumpleGranja = true;
            if (granja) {
                cumpleGranja = galpon.granja == granja;
                if (!cumpleGranja) {
                    console.log('No cumple granja:', galpon.id, 'galpon.granja:', galpon.granja, 'filtro:', granja);
                }
            }

            // Filtro por fecha desde
            let cumpleFechaDesde = true;
            if (fechaDesde && galpon.fecha_hora_crea) {
                const fechaGalpon = new Date(galpon.fecha_hora_crea).toISOString().split('T')[0];
                cumpleFechaDesde = fechaGalpon >= fechaDesde;
            }

            // Filtro por fecha hasta
            let cumpleFechaHasta = true;
            if (fechaHasta && galpon.fecha_hora_crea) {
                const fechaGalpon = new Date(galpon.fecha_hora_crea).toISOString().split('T')[0];
                cumpleFechaHasta = fechaGalpon <= fechaHasta;
            }

            return cumpleBusqueda && cumpleGranja && cumpleFechaDesde && cumpleFechaHasta;
        });

        console.log('Galpones después de filtrar:', this.galpones.length);
        this.renderTabla();
    }

    filtrarGalpones(searchTerm) {
        if (!searchTerm || searchTerm.trim() === '') {
            this.galpones = [...this.galponesOriginal];
        } else {
            const termLower = searchTerm.toLowerCase().trim();
            this.galpones = this.galponesOriginal.filter(galpon => {
                const id = (galpon.id || '').toString().toLowerCase();
                const nombre = (galpon.nombre || '').toLowerCase();
                const numGalpon = (galpon.galpon || '').toString().toLowerCase();
                return id.includes(termLower) || nombre.includes(termLower) || numGalpon.includes(termLower);
            });
        }
        
        this.renderTabla();
    }

    mostrarFormularioNuevo() {
        // Destruir modal anterior si existe
        if (this.modal) {
            // IMPORTANTE: Desmontar formulario ANTES de destruir el modal
            if (this.currentForm) {
                this.currentForm.unmount();
                this.currentForm = null;
            }
            this.modal.destroy();
            this.modal = null;
        }

        // Limpiar cualquier modal huérfano que pueda quedar en el DOM
        const modalOverlays = document.querySelectorAll('.modal-overlay');
        modalOverlays.forEach(overlay => {
            if (overlay && overlay.parentElement) {
                overlay.remove();
            }
        });

        // Restaurar scroll del body por si quedó bloqueado
        document.body.style.overflow = '';

        this.modal = new Modal({
            title: 'Nueva característica',
            content: '<div id="formContainer"></div>',
            size: 'xlarge',
            onClose: () => {
                if (this.currentForm) {
                    this.currentForm.unmount();
                    this.currentForm = null;
                }
                this.modal = null;
            }
        });

        this.modal.open();

        // Esperar a que el modal esté completamente en el DOM
        setTimeout(() => {
            const formContainer = this.modal.container?.querySelector('#formContainer');
            if (formContainer) {
                this.currentForm = new GalponForm(formContainer, {
                    mode: 'create',
                    caracteristicas: this.caracteristicas,
                    granjas: this.granjas, // Pasar granjas para el dropdown
                    galponesDisponibles: [], // Inicialmente vacío
                    onGranjaChange: async (idGranja) => await this.cargarGalponesPorGranja(this.currentForm, idGranja),
                    onSubmit: (data) => this.crearGalpon(data),
                    onCancel: () => {
                        if (this.modal) {
                            this.modal.close();
                        }
                    }
                });

                this.currentForm.mount();
            }
        }, 100);
    }

    async cargarGalponesPorGranja(formInstance, idGranja) {
        try {
            const response = await this.service.obtenerGalponesPorGranja(idGranja);
            
            if (response.success && response.data) {
                // Actualizar los galpones disponibles en el formulario
                formInstance.galponesDisponibles = response.data;
                formInstance.actualizarComboGalpones();
            } else {
                Notification.warning('No se encontraron galpones para esta granja');
                formInstance.galponesDisponibles = [];
                formInstance.actualizarComboGalpones();
            }
        } catch (error) {
            console.error('Error al cargar galpones por granja:', error);
            Notification.error('Error al cargar galpones de la granja');
        }
    }

    async crearGalpon(data) {
        try {
            console.log('Datos a enviar:', data);
            const response = await this.service.crear(data);
            console.log('Respuesta del servidor:', response);
            
            if (response.success) {
                const mensaje = response.procesadas 
                    ? `${response.procesadas} características guardadas correctamente` 
                    : 'Características guardadas correctamente';
                window.SwalHelpers.showSuccess(mensaje);
                if (this.modal) {
                    if (this.currentForm) {
                        this.currentForm.unmount();
                        this.currentForm = null;
                    }
                    this.modal.destroy();
                    this.modal = null;
                }
                await this.cargarGalpones();
            } else {
                window.SwalHelpers.showError(response.message || response.mensaje || 'Error al guardar características');
            }
        } catch (error) {
            console.error('Error al guardar características:', error);
            window.SwalHelpers.showError('Error de conexión al guardar características');
        }
    }

    async editarGalpon(id) {
        try {
            // Buscar el galpón en la lista actual para obtener su granja
            const galponActual = this.galpones.find(g => (g.id || g.id_galpon) === id);
            if (!galponActual) {
                window.SwalHelpers.showError('Galpón no encontrado');
                return;
            }

            const idGranja = galponActual.granja;
            const response = await this.service.obtener(id, idGranja);

            if (response.success) {
                const galpon = response.data;

                // Destruir modal anterior si existe
                if (this.modal) {
                    // IMPORTANTE: Desmontar formulario ANTES de destruir el modal
                    if (this.currentForm) {
                        this.currentForm.unmount();
                        this.currentForm = null;
                    }
                    this.modal.destroy();
                    this.modal = null;
                }

                // Limpiar cualquier modal huérfano que pueda quedar en el DOM
                const modalOverlays = document.querySelectorAll('.modal-overlay');
                modalOverlays.forEach(overlay => {
                    if (overlay && overlay.parentElement) {
                        overlay.remove();
                    }
                });

                // Restaurar scroll del body por si quedó bloqueado
                document.body.style.overflow = '';

                this.modal = new Modal({
                    title: '<i class="fas fa-edit text-yellow-600 mr-2"></i>Editar Galpón',
                    content: '<div id="formContainer"></div>',
                    size: 'xlarge',
                    onClose: () => {
                        if (this.currentForm) {
                            this.currentForm.unmount();
                            this.currentForm = null;
                        }
                        this.modal = null;
                    }
                });

                this.modal.open();

                // Esperar a que el modal esté completamente en el DOM
                setTimeout(() => {
                    const formContainer = this.modal.container?.querySelector('#formContainer');
                    if (formContainer) {
                        this.currentForm = new GalponForm(formContainer, {
                            mode: 'edit',
                            galpon: galpon,
                            caracteristicas: this.caracteristicas,
                            granjas: this.granjas,
                            onSubmit: (data) => this.actualizarGalpon(id, idGranja, data),
                            onCancel: () => {
                                if (this.modal) {
                                    this.modal.close();
                                }
                            }
                        });

                        this.currentForm.mount();
                    }
                }, 100);
            } else {
                window.SwalHelpers.showError('No se pudo cargar el galpón');
            }
        } catch (error) {
            console.error('Error al cargar galpón:', error);
            window.SwalHelpers.showError('Error de conexión');
        }
    }

    async actualizarGalpon(id, idGranja, data) {
        try {
            // Transformar datos al formato esperado por el backend para actualización
            const backendData = {
                nombre_galpon: data.nombre,
                caracteristicas: {}
            };

            // Convertir array de características a objeto con id como clave
            if (data.caracteristicas && Array.isArray(data.caracteristicas)) {
                data.caracteristicas.forEach(carac => {
                    backendData.caracteristicas[carac.id_caracteristica] = carac.valor;
                });
            }

            const response = await this.service.actualizar(id, idGranja, backendData);
            if (response.success) {
                window.SwalHelpers.showSuccess('Registro actualizado correctamente');
                if (this.modal) {
                    if (this.currentForm) {
                        this.currentForm.unmount();
                        this.currentForm = null;
                    }
                    this.modal.destroy();
                    this.modal = null;
                }
                await this.cargarGalpones();
            } else {
                window.SwalHelpers.showError(response.mensaje || 'Error al actualizar galpón');
            }
        } catch (error) {
            console.error('Error al actualizar galpón:', error);
            window.SwalHelpers.showError('Error de conexión al actualizar galpón');
        }
    }

    async eliminarGalpon(id) {
        // Buscar el galpón en la lista actual para obtener su granja
        const galponActual = this.galpones.find(g => (g.id || g.id_galpon) === id);
        if (!galponActual) {
            window.SwalHelpers.showError('Galpón no encontrado');
            return;
        }

        const idGranja = galponActual.granja;

        // Confirmación con SweetAlert2
        const confirmado = await window.SwalHelpers.showConfirm(
            '¿Eliminar este registro?',
            'Esta acción no se puede deshacer.'
        );
        if (!confirmado) return;

        try {
            const response = await this.service.eliminar(id, idGranja);
            
            if (response.success) {
                window.SwalHelpers.showSuccess('Registro eliminado correctamente');
                await this.cargarGalpones();
            } else {
                window.SwalHelpers.showError(response.mensaje || 'Error al eliminar galpón');
            }
        } catch (error) {
            console.error('Error al eliminar galpón:', error);
            window.SwalHelpers.showError('Error de conexión al eliminar galpón');
        }
    }

    verGalpon(id) {
        const galpon = this.galpones.find(g => g.id === id || g.id_galpon === id);
        if (!galpon) {
            window.SwalHelpers.showError('Galpón no encontrado');
            return;
        }

        // Obtener el nombre de la granja
        const granja = this.granjas.find(g => g.id == galpon.granja);
        const nombreGranja = granja ? granja.nombre : `Granja ${galpon.granja}`;

        const detalles = `
            <div class="space-y-4 text-left">
                <div class="bg-gray-50 p-4 rounded-lg">
                    <h4 class="font-semibold text-purple-700 mb-3 text-lg">Información General</h4>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <span class="font-semibold text-gray-700">ID:</span> 
                            <span class="text-gray-900">${galpon.id || galpon.id_galpon}</span>
                        </div>
                        <div>
                            <span class="font-semibold text-gray-700">Granja:</span> 
                            <span class="text-gray-900">${nombreGranja}</span>
                        </div>
                        <div>
                            <span class="font-semibold text-gray-700">Galpón:</span> 
                            <span class="text-gray-900">${galpon.galpon}</span>
                        </div>
                        <div>
                            <span class="font-semibold text-gray-700">Nombre:</span> 
                            <span class="text-gray-900">${galpon.nombre || '-'}</span>
                        </div>
                    </div>
                </div>
                
                ${this.caracteristicas.length > 0 ? `
                <div class="bg-blue-50 p-4 rounded-lg">
                    <h4 class="font-semibold text-blue-700 mb-3 text-lg">
                        <i class="fas fa-list-ul mr-2"></i>Características
                    </h4>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                        ${this.caracteristicas.map(carac => `
                            <div class="bg-white p-2 rounded border border-blue-200">
                                <span class="text-gray-600 text-sm font-medium">${carac.nombre}:</span>
                                <span class="font-semibold text-gray-900 ml-2">${galpon[carac.id] || '-'}</span>
                            </div>
                        `).join('')}
                    </div>
                </div>
                ` : ''}
            </div>
        `;

        // Destruir modal anterior si existe
        if (this.modal) {
            this.modal.destroy();
            this.modal = null;
        }

        this.modal = new Modal({
            title: `<i class="fas fa-warehouse text-purple-600 mr-2"></i>Detalles del Galpón ${galpon.galpon}`,
            content: detalles,
            size: 'large',
            footer: `
                <button onclick="window.galponesController.modal.close()" 
                    class="px-6 py-2 bg-gray-500 text-white rounded-lg hover:bg-gray-600 transition">
                    <i class="fas fa-times mr-2"></i>Cerrar
                </button>
            `,
            onClose: () => {
                this.modal = null;
            }
        });

        this.modal.open();
    }

    /**
     * Exportar galpones a PDF
     */
    async exportarPDF() {
        try {
            // Mostrar loading
            Swal.fire({
                title: 'Generando PDF...',
                text: 'Por favor espere mientras se genera el reporte',
                allowOutsideClick: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });

            // Obtener filtros actuales
            const granja = document.getElementById('filterGranja')?.value;
            const fechaDesde = document.getElementById('filterFechaDesde')?.value;
            const fechaHasta = document.getElementById('filterFechaHasta')?.value;

            // Usar los galpones ya cargados o recargar con filtros
            let galpones = this.galpones;
            if (granja || fechaDesde || fechaHasta) {
                const filtros = {};
                if (granja) filtros.id_granja = granja;
                if (fechaDesde) filtros.fecha_desde = fechaDesde;
                if (fechaHasta) filtros.fecha_hasta = fechaHasta;
                
                const response = await this.service.listar(filtros);
                if (response.success) {
                    galpones = response.data;
                }
            }

            if (galpones.length === 0) {
                Swal.fire('Advertencia', 'No hay galpones para exportar', 'warning');
                return;
            }

            // Crear el PDF (orientación vertical)
            const { jsPDF } = window.jspdf;
            const doc = new jsPDF('p', 'mm', 'a4');

            // Configurar fuente
            doc.setFont('helvetica');
            
            // Título
            doc.setFontSize(16);
            doc.setTextColor(40, 40, 40);
            doc.text('Sistema de Gestion GRS', doc.internal.pageSize.width / 2, 15, { align: 'center' });
            
            // Subtítulo
            doc.setFontSize(12);
            doc.setTextColor(80, 80, 80);
            doc.text('Reporte de Galpones y Características', doc.internal.pageSize.width / 2, 22, { align: 'center' });
            
            // Fecha de generación
            doc.setFontSize(9);
            doc.setTextColor(120, 120, 120);
            const fecha = new Date().toLocaleDateString('es-ES', {
                year: 'numeric',
                month: 'long',
                day: 'numeric',
                hour: '2-digit',
                minute: '2-digit'
            });
            doc.text(`Generado el: ${fecha}`, 14, 30);

            // Preparar datos transpuestos (características como filas, galpones como columnas)
            const maxGalponesPerPage = 4; // Máximo de galpones por página en vertical
            const totalGalpones = galpones.length;
            const pages = Math.ceil(totalGalpones / maxGalponesPerPage);
            
            let startY = 35;

            for (let page = 0; page < pages; page++) {
                if (page > 0) {
                    doc.addPage();
                    startY = 20;
                }

                const inicio = page * maxGalponesPerPage;
                const fin = Math.min(inicio + maxGalponesPerPage, totalGalpones);
                const galponesPage = galpones.slice(inicio, fin);
                
                // Preparar headers: Campo + Galpones
                const headers = ['Campo'];
                galponesPage.forEach(g => {
                    headers.push(`G${g.granja || '-'}-${g.galpon || '-'}`);
                });

                // Preparar filas
                const bodyData = [];
                
                // Filas básicas
                const camposBasicos = [
                    { campo: 'Granja', key: 'granja' },
                    { campo: 'Galpón', key: 'galpon' },
                    { campo: 'Nombre', key: 'nombre' }
                ];
                
                camposBasicos.forEach(info => {
                    const row = [info.campo];
                    galponesPage.forEach(galpon => {
                        row.push(galpon[info.key] || '-');
                    });
                    bodyData.push(row);
                });
                
                // Filas de características
                this.caracteristicas.forEach(carac => {
                    const row = [carac.nombre];
                    galponesPage.forEach(galpon => {
                        const valor = galpon[carac.id] || galpon[String(carac.id)] || '-';
                        row.push(valor);
                    });
                    bodyData.push(row);
                });

                // Generar tabla
                doc.autoTable({
                    startY: startY,
                    head: [headers],
                    body: bodyData,
                    styles: {
                        fontSize: 7,
                        cellPadding: 2,
                        overflow: 'linebreak',
                        cellWidth: 'wrap'
                    },
                    headStyles: {
                        fillColor: [37, 99, 235],
                        textColor: 255,
                        fontStyle: 'bold',
                        halign: 'center'
                    },
                    columnStyles: {
                        0: { cellWidth: 45, fontStyle: 'bold', halign: 'left' }
                    },
                    alternateRowStyles: {
                        fillColor: [243, 244, 246]
                    },
                    margin: { top: startY, right: 14, bottom: 20, left: 14 },
                    didDrawPage: function(data) {
                        // Pie de página
                        doc.setFontSize(8);
                        doc.setTextColor(120, 120, 120);
                        const pageNum = doc.internal.getNumberOfPages();
                        doc.text(`Página ${doc.internal.getCurrentPageInfo().pageNumber}`, 
                            doc.internal.pageSize.width / 2, 
                            doc.internal.pageSize.height - 10, 
                            { align: 'center' });
                    }
                });

                // Nota de continuación
                if (page < pages - 1) {
                    startY = doc.lastAutoTable.finalY + 5;
                    doc.setFontSize(8);
                    doc.setTextColor(107, 114, 128);
                    doc.text('Continúa en la siguiente página...', doc.internal.pageSize.width - 14, startY, { align: 'right' });
                }
            }

            // Agregar resumen en la última página
            const finalY = doc.lastAutoTable.finalY + 10;
            doc.setFontSize(10);
            doc.setTextColor(37, 99, 235);
            doc.text(`Total de galpones: ${totalGalpones}`, 14, finalY);

            // Guardar el PDF
            const nombreArchivo = `Reporte_Galpones_${new Date().toISOString().split('T')[0]}.pdf`;
            doc.save(nombreArchivo);

            // Cerrar loading y mostrar éxito
            Swal.fire({
                icon: 'success',
                title: '¡PDF generado!',
                text: `El reporte ha sido descargado como: ${nombreArchivo}`,
                timer: 3000
            });

        } catch (error) {
            console.error('Error generando PDF:', error);
            Swal.fire('Error', 'No se pudo generar el PDF', 'error');
        }
    }

    exportarExcel() {
        // Obtener filtros actuales
        const filtros = {};
        
        const granja = document.getElementById('filterGranja')?.value;
        const fechaDesde = document.getElementById('filterFechaDesde')?.value;
        const fechaHasta = document.getElementById('filterFechaHasta')?.value;
        
        if (granja) {
            filtros.id_granja = granja;
        }
        if (fechaDesde) {
            filtros.fecha_desde = fechaDesde;
        }
        if (fechaHasta) {
            filtros.fecha_hasta = fechaHasta;
        }
        
        // Llamar al servicio para exportar
        this.service.exportarExcel(filtros);
        
        // Mostrar notificación
        Notification.info('Generando Excel...');
    }
}
