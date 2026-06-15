-- ============================================================
-- fix_amd_grp9_configuracion.sql
-- Inserta el grupo "Configuración de Roles" (grp-9) y sus
-- ítems en amd_dashboard_modulos si aún no existen.
--
-- Pasos de uso:
--   1. Ejecutar este script en la BD de producción.
--   2. Abrir fix_produccion_roles.php en el navegador para
--      reasignar TODOS los módulos al rol ADMIN.
--   3. Eliminar ambos archivos del servidor.
-- ============================================================

-- ── Detectar id_programa de "Planta de Incubacion" ──────────
-- Si en tu BD el id_programa es distinto de 2,
-- cambia el número en todas las líneas de abajo.
SET @id_prog = (
    SELECT id_programa
    FROM amd_programas
    WHERE LOWER(REPLACE(nombre,' ','')) LIKE '%plantaincubac%'
    LIMIT 1
);

-- Fallback: si la subquery no encontró nada, usar 2
SET @id_prog = COALESCE(@id_prog, 2);

SELECT CONCAT('Usando id_programa = ', @id_prog) AS info;

-- ── Grupo grp-9 ─────────────────────────────────────────────
INSERT INTO amd_dashboard_modulos
    (id_programa, cod_mod, tipo, parent_cod, nom_mod, label_short,
     icono, url, tipo_param, titulo, nivel0, nivel1, nivel2, nivel3, orden)
SELECT
    @id_prog, 'grp-9', 'group', NULL,
    '9. Configuración de Roles', 'Config<br>Roles',
    'fas fa-users-cog', NULL, NULL, NULL,
    9, NULL, NULL, NULL, 34
WHERE NOT EXISTS (
    SELECT 1 FROM amd_dashboard_modulos
    WHERE id_programa = @id_prog AND cod_mod = 'grp-9'
);

-- ── item-9-1: Panel de jerarquía ─────────────────────────────
INSERT INTO amd_dashboard_modulos
    (id_programa, cod_mod, tipo, parent_cod, nom_mod, label_short,
     icono, url, tipo_param, titulo, nivel0, nivel1, nivel2, nivel3, orden)
SELECT
    @id_prog, 'item-9-1', 'item', 'grp-9',
    '9.1. Panel de jerarquía', 'Panel',
    'fas fa-list-ol', 'pages/modulos.html', 'modulos', 'Jerarquía de módulos',
    9, 1, NULL, NULL, 35
WHERE NOT EXISTS (
    SELECT 1 FROM amd_dashboard_modulos
    WHERE id_programa = @id_prog AND cod_mod = 'item-9-1'
);

-- ── item-9-2: Gestión de Roles ───────────────────────────────
INSERT INTO amd_dashboard_modulos
    (id_programa, cod_mod, tipo, parent_cod, nom_mod, label_short,
     icono, url, tipo_param, titulo, nivel0, nivel1, nivel2, nivel3, orden)
SELECT
    @id_prog, 'item-9-2', 'item', 'grp-9',
    '9.2. Gestión de Roles', 'Roles',
    'fas fa-shield-alt', 'pages/roles.html', 'roles', 'Gestión de Roles',
    9, 2, NULL, NULL, 36
WHERE NOT EXISTS (
    SELECT 1 FROM amd_dashboard_modulos
    WHERE id_programa = @id_prog AND cod_mod = 'item-9-2'
);

-- ── item-9-3: Gestión de Usuarios ───────────────────────────
INSERT INTO amd_dashboard_modulos
    (id_programa, cod_mod, tipo, parent_cod, nom_mod, label_short,
     icono, url, tipo_param, titulo, nivel0, nivel1, nivel2, nivel3, orden)
SELECT
    @id_prog, 'item-9-3', 'item', 'grp-9',
    '9.3. Gestión de Usuarios', 'Usuarios',
    'fas fa-users', 'pages/usuarios.html', 'usuarios', 'Gestión de Usuarios',
    9, 3, NULL, NULL, 37
WHERE NOT EXISTS (
    SELECT 1 FROM amd_dashboard_modulos
    WHERE id_programa = @id_prog AND cod_mod = 'item-9-3'
);

-- ── item-9-4: Asignación Usuarios-Roles ─────────────────────
INSERT INTO amd_dashboard_modulos
    (id_programa, cod_mod, tipo, parent_cod, nom_mod, label_short,
     icono, url, tipo_param, titulo, nivel0, nivel1, nivel2, nivel3, orden)
SELECT
    @id_prog, 'item-9-4', 'item', 'grp-9',
    '9.4. Asignación Usuarios-Roles', 'Asig.<br>Roles',
    'fas fa-user-tag', 'pages/usuarios-roles.html', 'usuarios-roles', 'Asignación Usuarios-Roles',
    9, 4, NULL, NULL, 38
WHERE NOT EXISTS (
    SELECT 1 FROM amd_dashboard_modulos
    WHERE id_programa = @id_prog AND cod_mod = 'item-9-4'
);

-- ── Verificación ─────────────────────────────────────────────
SELECT cod_mod, nom_mod, url
FROM amd_dashboard_modulos
WHERE id_programa = @id_prog
  AND cod_mod IN ('grp-9','item-9-1','item-9-2','item-9-3','item-9-4')
ORDER BY orden;
