// Envolver en IIFE para evitar conflictos de ámbito
(function() {
    // Esperar a que el DOM esté listo
    document.addEventListener('DOMContentLoaded', () => {
        const toggleBtn = document.getElementById('btnToggleFiltros');
        const filterContent = document.getElementById('filterContent');
        let filtrosVisibles = false; // ⬅️ empieza oculto

        // Verificar si los elementos existen antes de manipularlos
        if (!filterContent || !toggleBtn) {
            // Los elementos no existen en esta página, salir silenciosamente
            return;
        }

        // Inicialmente oculto
        filterContent.style.maxHeight = "0";
        filterContent.style.opacity = "0";
        filterContent.style.transition = "all 0.3s ease";
        toggleBtn.innerHTML = '<i class="fas fa-chevron-down"></i>'; // icono hacia abajo

        // Evento de clic
        toggleBtn.addEventListener('click', () => {
            filtrosVisibles = !filtrosVisibles;

            if (filtrosVisibles) {
                filterContent.style.maxHeight = filterContent.scrollHeight + "px";
                filterContent.style.opacity = "1";
                toggleBtn.innerHTML = '<i class="fas fa-chevron-up"></i>';
            } else {
                filterContent.style.maxHeight = "0";
                filterContent.style.opacity = "0";
                toggleBtn.innerHTML = '<i class="fas fa-chevron-down"></i>';
            }
        });
    });
})();
