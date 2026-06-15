DELETE FROM dashboard_modulos WHERE programa = 'Planta de Incubacion';

INSERT INTO dashboard_modulos
(programa, cod_mod, tipo, parent_cod, nom_mod, label_short, icono, url, tipo_param, titulo, nivel0, nivel1, nivel2, nivel3, orden)
VALUES
('Planta de Incubacion', 'grp-1', 'group', NULL, '1. Programa de Carga', 'Gest.<br>Objetivos', 'fas fa-seedling', NULL, NULL, NULL, 1, NULL, NULL, NULL, 1),
('Planta de Incubacion', 'item-1-1', 'item', 'grp-1', '1.1. Galpones y sus características', 'Galpones', 'fas fa-warehouse', 'pages/galpones.html', 'galpones', 'Galpones y sus características', 1, 1, NULL, NULL, 2),
('Planta de Incubacion', 'item-1-2', 'item', 'grp-1', '1.2. Gestión de Características', 'Caract.', 'fas fa-cogs', 'pages/caracteristicas.html', 'caracteristicas', 'Gestión de Características', 1, 2, NULL, NULL, 3),
('Planta de Incubacion', 'item-1-3', 'item', 'grp-1', '1.3. Programa de carga de pollo bebé', 'Control<br>Objetivos', 'fas fa-table', 'pages/trabajando.html', 'gestion-objetivos', 'Gestión de Objetivos', 1, 3, NULL, NULL, 4),


('Planta de Incubacion', 'grp-2', 'group', NULL, '2. Programa de carga de pollo bebé', 'Carga<br>Pollo BB', 'fas fa-egg', NULL, NULL, NULL, 2, NULL, NULL, NULL, 5),
('Planta de Incubacion', 'item-2-1', 'item', 'grp-2', '2.1. Gestión de Proyecciones', 'Proyecciones', 'fas fa-folder-open', 'pages/dashboard-gestion-proyecciones.html', 'gestion-proyecciones', 'Gestión de Proyecciones', 2, 1, NULL, NULL, 6),
('Planta de Incubacion', 'item-2-2', 'item', 'grp-2', '2.2. Proyecciones de Cargas de Pollos', 'Proy. Cargas', 'fas fa-chart-line', 'pages/dashboard-proyecciones-de-cargas-pollos.html', 'proyecciones-cargas-pollos', 'Proyecciones de Cargas de Pollos', 2, 2, NULL, NULL, 7),
('Planta de Incubacion', 'item-2-3', 'item', 'grp-2', '2.3. Reportes', 'Reportes', 'fas fa-file-medical', 'pages/reportes.html', 'reportes', 'Panel de Reportes', 2, 3, NULL, NULL, 8),

('Planta de Incubacion', 'grp-3', 'group', NULL, '3. Procedencia – Granja', 'Gest.<br>Objetivos', 'fas fa-seedling', NULL, NULL, NULL, 3, NULL, NULL, NULL, 9),
('Planta de Incubacion', 'item-3-1', 'item', 'grp-3', '3.1. Programación de requerimiento de huevo incubable', 'Panel', 'fas fa-chart-pie', 'pages/trabajando.html', 'trabajando', 'Trabajando para usted', 3, 1, NULL, NULL, 10),

('Planta de Incubacion', 'grp-4', 'group', NULL, '4. Transporte', 'Gest.<br>Objetivos', 'fas fa-seedling', NULL, NULL, NULL, 4, NULL, NULL, NULL, 11),
('Planta de Incubacion', 'item-4-1', 'item', 'grp-4', '4.1. Programa de asignación de transporte huevo', 'Panel', 'fas fa-chart-pie', 'pages/trabajando.html', 'trabajando', 'Trabajando para usted', 4, 1, NULL, NULL, 12),
('Planta de Incubacion', 'item-4-2', 'item', 'grp-4', '4.2. Programa de asignación de transporte Pollo BB', 'Panel', 'fas fa-chart-pie', 'pages/trabajando.html', 'trabajando', 'Trabajando para usted', 4, 2, NULL, NULL, 13),

('Planta de Incubacion', 'grp-5', 'group', NULL, '5. Almacén', 'Gest.<br>Objetivos', 'fas fa-seedling', NULL, NULL, NULL, 5, NULL, NULL, NULL, 14),
('Planta de Incubacion', 'item-5-1', 'item', 'grp-5', '5.1. Desarrollo de módulo almacén', 'Panel', 'fas fa-chart-pie', 'pages/trabajando.html', 'trabajando', 'Trabajando para usted', 5, 1, NULL, NULL, 15),
('Planta de Incubacion', 'item-5-2', 'item', 'grp-5', '5.2. Almacenaje PT', 'Panel', 'fas fa-chart-pie', 'pages/trabajando.html', 'trabajando', 'Trabajando para usted', 5, 2, NULL, NULL, 16),
('Planta de Incubacion', 'item-5-3', 'item', 'grp-5', '5.3. Recepción de pollo BB', 'Panel', 'fas fa-chart-pie', 'pages/trabajando.html', 'trabajando', 'Trabajando para usted', 5, 3, NULL, NULL, 17),
('Planta de Incubacion', 'item-5-4', 'item', 'grp-5', '5.4. Despacho de pollo BB a granjas GRS', 'Panel', 'fas fa-chart-pie', 'pages/trabajando.html', 'trabajando', 'Trabajando para usted', 5, 4, NULL, NULL, 18),
('Planta de Incubacion', 'item-5-5', 'item', 'grp-5', '5.5. Venta de pollo BB', 'Panel', 'fas fa-chart-pie', 'pages/trabajando.html', 'trabajando', 'Trabajando para usted', 5, 5, NULL, NULL, 19),

('Planta de Incubacion', 'grp-6', 'group', NULL, '6. Proceso Incubación', 'Gest.<br>Objetivos', 'fas fa-seedling', NULL, NULL, NULL, 6, NULL, NULL, NULL, 20),
('Planta de Incubacion', 'item-6-1', 'item', 'grp-6', '6.1. Programa de carga de huevo incubable', 'Panel', 'fas fa-chart-pie', 'pages/trabajando.html', 'trabajando', 'Trabajando para usted', 6, 1, NULL, NULL, 21),
('Planta de Incubacion', 'item-6-2', 'item', 'grp-6', '6.2. Programa asignación Incubadora nacedoras', 'Panel', 'fas fa-chart-pie', 'pages/trabajando.html', 'trabajando', 'Trabajando para usted', 6, 2, NULL, NULL, 22),
('Planta de Incubacion', 'item-6-3', 'item', 'grp-6', '6.3. Captura de información incubadora nacedora', 'Panel', 'fas fa-chart-pie', 'pages/trabajando.html', 'trabajando', 'Trabajando para usted', 6, 3, NULL, NULL, 23),
('Planta de Incubacion', 'item-6-4', 'item', 'grp-6', '6.4. Desarrollo de módulo de producción PI', 'Panel', 'fas fa-chart-pie', 'pages/trabajando.html', 'trabajando', 'Trabajando para usted', 6, 4, NULL, NULL, 24),
('Planta de Incubacion', 'item-6-5', 'item', 'grp-6', '6.5. Trazabilidad del Proceso Incubación', 'Panel', 'fas fa-chart-pie', 'pages/trabajando.html', 'trabajando', 'Trabajando para usted', 6, 5, NULL, NULL, 25),

('Planta de Incubacion', 'grp-7', 'group', NULL, '7. Vacunación', 'Gest.<br>Objetivos', 'fas fa-seedling', NULL, NULL, NULL, 7, NULL, NULL, NULL, 26),
('Planta de Incubacion', 'item-7-1', 'item', 'grp-7', '7.1. Programa de vacunación', 'Panel', 'fas fa-chart-pie', 'pages/trabajando.html', 'trabajando', 'Trabajando para usted', 7, 1, NULL, NULL, 27),
('Planta de Incubacion', 'item-7-2', 'item', 'grp-7', '7.2. Requerimiento de vacunas', 'Panel', 'fas fa-chart-pie', 'pages/trabajando.html', 'trabajando', 'Trabajando para usted', 7, 2, NULL, NULL, 28),
('Planta de Incubacion', 'item-7-3', 'item', 'grp-7', '7.3. Check list calidad proceso de vacunación', 'Panel', 'fas fa-chart-pie', 'pages/trabajando.html', 'trabajando', 'Trabajando para usted', 7, 3, NULL, NULL, 29),
('Planta de Incubacion', 'item-7-4', 'item', 'grp-7', '7.4. Check List Calidad pollito BB planta Incubación', 'Panel', 'fas fa-chart-pie', 'pages/trabajando.html', 'trabajando', 'Trabajando para usted', 7, 4, NULL, NULL, 30),
('Planta de Incubacion', 'item-7-5', 'item', 'grp-7', '7.5. Historia Clínica pollo BB', 'Panel', 'fas fa-chart-pie', 'pages/trabajando.html', 'trabajando', 'Trabajando para usted', 7, 5, NULL, NULL, 31),

('Planta de Incubacion', 'grp-8', 'group', NULL, '8. Granja', 'Gest.<br>Objetivos', 'fas fa-seedling', NULL, NULL, NULL, 8, NULL, NULL, NULL, 32),
('Planta de Incubacion', 'item-8-1', 'item', 'grp-8', '8.1. Recepción de pollo BB', 'Panel', 'fas fa-chart-pie', 'pages/trabajando.html', 'trabajando', 'Trabajando para usted', 8, 1, NULL, NULL, 33),

('Planta de Incubacion', 'grp-9', 'group', NULL, '9. Configuración de Roles', 'Config<br>Roles', 'fas fa-users-cog', NULL, NULL, NULL, 9, NULL, NULL, NULL, 34),
('Planta de Incubacion', 'item-9-1', 'item', 'grp-9', '9.1. Panel de jerarquía', 'Panel', 'fas fa-list-ol', 'pages/modulos.html', 'modulos', 'Jerarquía de módulos', 9, 1, NULL, NULL, 35),
('Planta de Incubacion', 'item-9-2', 'item', 'grp-9', '9.2. Gestión de Roles', 'Roles', 'fas fa-shield-alt', 'pages/roles.html', 'roles', 'Gestión de Roles', 9, 2, NULL, NULL, 36),
('Planta de Incubacion', 'item-9-3', 'item', 'grp-9', '9.3. Gestión de Usuarios', 'Usuarios', 'fas fa-users', 'pages/usuarios.html', 'usuarios', 'Gestión de Usuarios', 9, 3, NULL, NULL, 37);
