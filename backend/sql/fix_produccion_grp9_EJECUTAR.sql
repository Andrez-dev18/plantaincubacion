-- ============================================================
-- fix_produccion_grp9_EJECUTAR.sql
-- Inserta el grupo "Configuración de Roles" (grp-9) y sus
-- 4 ítems en amd_dashboard_modulos de PRODUCCIÓN.
--
-- CONFIRMADO: id_programa=2 ("Planta Incubacion")
--
-- INSTRUCCIONES:
--   1. Abrir phpMyAdmin en el servidor de producción.
--   2. Seleccionar la base de datos correcta.
--   3. Ir a la pestaña "SQL" y pegar todo este script.
--   4. Clic en "Ejecutar".
--   5. Abrir fix_produccion_roles.php en el navegador
--      (ahora deberá mostrar 38 módulos y asignarlos todos).
--   6. Eliminar ambos archivos PHP del servidor.
-- ============================================================

-- ── Grupo grp-9 ─────────────────────────────────────────────
INSERT INTO amd_dashboard_modulos
    (id_programa, cod_mod, tipo, parent_cod, nom_mod, label_short,
     icono, url, tipo_param, titulo, nivel0, nivel1, nivel2, nivel3, orden)
SELECT 2, 'grp-9', 'group', NULL,
    '9. Configuración de Roles', 'Config<br>Roles',
    'fas fa-users-cog', NULL, NULL, NULL,
    9, NULL, NULL, NULL, 34
WHERE NOT EXISTS (
    SELECT 1 FROM amd_dashboard_modulos
    WHERE id_programa = 2 AND cod_mod = 'grp-9'
);

-- ── item-9-1: Panel de jerarquía ─────────────────────────────
INSERT INTO amd_dashboard_modulos
    (id_programa, cod_mod, tipo, parent_cod, nom_mod, label_short,
     icono, url, tipo_param, titulo, nivel0, nivel1, nivel2, nivel3, orden)
SELECT 2, 'item-9-1', 'item', 'grp-9',
    '9.1. Panel de jerarquía', 'Panel',
    'fas fa-list-ol', 'pages/modulos.html', 'modulos', 'Jerarquía de módulos',
    9, 1, NULL, NULL, 35
WHERE NOT EXISTS (
    SELECT 1 FROM amd_dashboard_modulos
    WHERE id_programa = 2 AND cod_mod = 'item-9-1'
);

-- ── item-9-2: Gestión de Roles ───────────────────────────────
INSERT INTO amd_dashboard_modulos
    (id_programa, cod_mod, tipo, parent_cod, nom_mod, label_short,
     icono, url, tipo_param, titulo, nivel0, nivel1, nivel2, nivel3, orden)
SELECT 2, 'item-9-2', 'item', 'grp-9',
    '9.2. Gestión de Roles', 'Roles',
    'fas fa-shield-alt', 'pages/roles.html', 'roles', 'Gestión de Roles',
    9, 2, NULL, NULL, 36
WHERE NOT EXISTS (
    SELECT 1 FROM amd_dashboard_modulos
    WHERE id_programa = 2 AND cod_mod = 'item-9-2'
);

-- ── item-9-3: Gestión de Usuarios ───────────────────────────
INSERT INTO amd_dashboard_modulos
    (id_programa, cod_mod, tipo, parent_cod, nom_mod, label_short,
     icono, url, tipo_param, titulo, nivel0, nivel1, nivel2, nivel3, orden)
SELECT 2, 'item-9-3', 'item', 'grp-9',
    '9.3. Gestión de Usuarios', 'Usuarios',
    'fas fa-users', 'pages/usuarios.html', 'usuarios', 'Gestión de Usuarios',
    9, 3, NULL, NULL, 37
WHERE NOT EXISTS (
    SELECT 1 FROM amd_dashboard_modulos
    WHERE id_programa = 2 AND cod_mod = 'item-9-3'
);

-- ── item-9-4: Asignación Usuarios-Roles ─────────────────────
INSERT INTO amd_dashboard_modulos
    (id_programa, cod_mod, tipo, parent_cod, nom_mod, label_short,
     icono, url, tipo_param, titulo, nivel0, nivel1, nivel2, nivel3, orden)
SELECT 2, 'item-9-4', 'item', 'grp-9',
    '9.4. Asignación Usuarios-Roles', 'Asig.<br>Roles',
    'fas fa-user-tag', 'pages/usuarios-roles.html', 'usuarios-roles', 'Asignación Usuarios-Roles',
    9, 4, NULL, NULL, 38
WHERE NOT EXISTS (
    SELECT 1 FROM amd_dashboard_modulos
    WHERE id_programa = 2 AND cod_mod = 'item-9-4'
);

-- ── Verificación — deberías ver 5 filas ─────────────────────
SELECT cod_mod, tipo, nom_mod, url, orden
FROM amd_dashboard_modulos
WHERE id_programa = 2
  AND cod_mod IN ('grp-9','item-9-1','item-9-2','item-9-3','item-9-4')
ORDER BY orden;

-- ── Conteo total esperado: 38 ────────────────────────────────
SELECT COUNT(*) AS total_modulos_programa2
FROM amd_dashboard_modulos
WHERE id_programa = 2;
