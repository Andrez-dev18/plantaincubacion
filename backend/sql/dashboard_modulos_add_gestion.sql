-- Script para agregar módulos de gestión de roles y usuarios al menú
-- Ejecutar después de dashboard_modulos_seed.sql

-- Actualizar el grupo 8 para que se llame "Configuración de Roles"
UPDATE dashboard_modulos 
SET nom_mod = '8. Configuración de Roles',
    label_short = 'Config<br>Roles',
    icono = 'fas fa-users-cog'
WHERE programa = 'Planta de Incubacion' 
AND cod_mod = 'grp-8';

-- Verificar si ya existe el item-8-2 antes de insertarlo
INSERT INTO dashboard_modulos
(programa, cod_mod, tipo, parent_cod, nom_mod, label_short, icono, url, tipo_param, titulo, nivel0, nivel1, nivel2, nivel3, orden)
SELECT 'Planta de Incubacion', 'item-8-2', 'item', 'grp-8', '8.2. Gestión de Roles', 'Roles', 'fas fa-shield-alt', 'pages/roles.html', 'roles', 'Gestión de Roles', 8, 2, NULL, NULL, 32
WHERE NOT EXISTS (
    SELECT 1 FROM dashboard_modulos 
    WHERE programa = 'Planta de Incubacion' AND cod_mod = 'item-8-2'
);

-- Verificar si ya existe el item-8-3 antes de insertarlo
INSERT INTO dashboard_modulos
(programa, cod_mod, tipo, parent_cod, nom_mod, label_short, icono, url, tipo_param, titulo, nivel0, nivel1, nivel2, nivel3, orden)
SELECT 'Planta de Incubacion', 'item-8-3', 'item', 'grp-8', '8.3. Gestión de Usuarios', 'Usuarios', 'fas fa-users', 'pages/usuarios.html', 'usuarios', 'Gestión de Usuarios', 8, 3, NULL, NULL, 33
WHERE NOT EXISTS (
    SELECT 1 FROM dashboard_modulos 
    WHERE programa = 'Planta de Incubacion' AND cod_mod = 'item-8-3'
);
