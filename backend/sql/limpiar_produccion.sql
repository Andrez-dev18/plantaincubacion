-- =============================================================================
-- limpiar_produccion.sql
-- Limpieza de datos sucios en grs_picamana (producción)
-- Basado en diagnóstico del 21/04/2026
-- =============================================================================
-- INSTRUCCIONES:
--   1. Lee TODOS los comentarios antes de ejecutar.
--   2. Ejecuta primero los SELECT de verificación (marcados con --VERIFICAR).
--   3. Ejecuta cada bloque por separado, no todo de una vez.
--   4. Haz un BACKUP antes: mysqldump -u root -p grs_picamana > backup_antes_limpieza.sql
-- =============================================================================


-- =============================================================================
-- PASO 1: AGREGAR COLUMNAS FALTANTES EN adm_rol
-- (migrarColumnas() del RolRepository lo hace automáticamente al iniciar el app,
--  pero si quieres forzarlo manualmente antes, ejecuta esto)
-- =============================================================================

-- 1a. Columna activo (roles activos/inactivos)
ALTER TABLE adm_rol
    ADD COLUMN IF NOT EXISTS activo TINYINT(1) NOT NULL DEFAULT 1
    AFTER nom_rol;

-- 1b. Columna descripción
ALTER TABLE adm_rol
    ADD COLUMN IF NOT EXISTS descripcion VARCHAR(255) NULL
    AFTER activo;

-- 1c. Columna id_programa (programa principal del rol)
ALTER TABLE adm_rol
    ADD COLUMN IF NOT EXISTS id_programa TINYINT(4) NULL
    AFTER descripcion;

-- 1d. Columna fecha_update
ALTER TABLE adm_rol
    ADD COLUMN IF NOT EXISTS fecha_update DATETIME NULL
    AFTER id_programa;

-- 1e. Columnas de fecha si no existen
ALTER TABLE adm_rol
    ADD COLUMN IF NOT EXISTS fecha_creacion    DATETIME NULL,
    ADD COLUMN IF NOT EXISTS fecha_actualizacion DATETIME NULL;

-- VERIFICAR: ver estructura resultante
-- SHOW COLUMNS FROM adm_rol;


-- =============================================================================
-- PASO 2: LIMPIAR REGISTROS HUÉRFANOS EN adm_usuario_rol
-- Hay 13 códigos que no existen en la tabla `usuario`
-- =============================================================================

-- VERIFICAR primero (no borra nada):
SELECT ur.codigo,
       GROUP_CONCAT(ur.cod_rol SEPARATOR ', ') AS roles
FROM adm_usuario_rol ur
WHERE NOT EXISTS (SELECT 1 FROM usuario u WHERE u.codigo = ur.codigo)
GROUP BY ur.codigo
ORDER BY ur.codigo;

-- Si la lista coincide con lo esperado (datos de prueba), ejecuta el DELETE:
DELETE FROM adm_usuario_rol
WHERE codigo IN (
    '12345678',
    '2',
    '3',
    '4280700',
    '7089561',
    '70895611',
    'admin',
    'adroles',
    'prx1',
    'prx2',
    'TEST01',
    'test88',
    'test99'
);

-- VERIFICAR después: debe devolver 0 huérfanos
SELECT COUNT(*) AS huerfanos_restantes
FROM adm_usuario_rol ur
WHERE NOT EXISTS (SELECT 1 FROM usuario u WHERE u.codigo = ur.codigo);


-- =============================================================================
-- PASO 3: LIMPIAR ROLES DE PRUEBA
-- Revisar y decidir cuáles eliminar (PELIGRO: también borra módulos asignados)
-- =============================================================================

-- VERIFICAR qué usuarios usan estos roles antes de borrarlos:
SELECT ur.codigo,
       IFNULL(u.nombre,'(no existe)') AS nombre,
       ur.cod_rol
FROM adm_usuario_rol ur
LEFT JOIN usuario u ON u.codigo = ur.codigo
WHERE ur.cod_rol IN ('ADMINISTRA', 'ADMINISTR1', 'Q', 'OPERADOR')
ORDER BY ur.cod_rol, ur.codigo;

-- Opción A: Renombrar roles con nombres incorrectos (más seguro)
-- Renombrar "Operador editado222" a algo correcto:
UPDATE adm_rol SET nom_rol = 'Operador de Planta'
WHERE cod_rol = 'OPERADOR';

-- Opción B: Eliminar roles de prueba (borra también sus módulos y asignaciones)
-- SOLO si estás seguro de que nadie los usa. Descomenta para ejecutar:

/*
-- Primero remover asignaciones de usuarios
DELETE FROM adm_usuario_rol WHERE cod_rol IN ('ADMINISTRA');
-- Luego módulos del rol
DELETE FROM adm_rol_progr_modulo WHERE id_rol IN (SELECT id FROM adm_rol WHERE cod_rol IN ('ADMINISTRA'));
-- Finalmente el rol
DELETE FROM adm_rol WHERE cod_rol IN ('ADMINISTRA');
*/


-- =============================================================================
-- PASO 4: REVISAR USUARIO '1' (Usuario 1 - Acceso Total)
-- Tiene los 8 roles asignados — claramente es un usuario de prueba
-- =============================================================================

-- VERIFICAR: ver datos de este usuario
SELECT u.codigo, u.nombre, u.estado
FROM usuario u WHERE u.codigo = '1';

-- Si confirmas que es de prueba, eliminar sus asignaciones de roles:
-- DELETE FROM adm_usuario_rol WHERE codigo = '1';
-- Y opcionalmente el usuario mismo:
-- UPDATE usuario SET estado = 'I' WHERE codigo = '1';  -- desactivar (más seguro que borrar)


-- =============================================================================
-- PASO 5: REVISAR USUARIO 'cesar' (nombre = "1")
-- Nombre parece incorrecto, tiene asignados 2 roles
-- =============================================================================

-- VERIFICAR:
SELECT codigo, nombre, estado FROM usuario WHERE codigo = 'cesar';
-- Si el nombre es incorrecto, corregirlo manualmente desde el módulo de usuarios
-- o via: UPDATE usuario SET nombre = 'NOMBRE CORRECTO' WHERE codigo = 'cesar';


-- =============================================================================
-- PASO 6 (OPCIONAL): AGREGAR MÓDULOS AL PROGRAMA 1 (Sanidad)
-- amd_dashboard_modulos tiene 0 módulos para id_programa=1
-- Si el programa Sanidad se va a usar, hay que insertar sus módulos aquí
-- =============================================================================

-- VERIFICAR:
SELECT COUNT(*) FROM amd_dashboard_modulos WHERE id_programa = 1;
-- Si devuelve 0, el árbol de permisos estará vacío para el programa Sanidad


-- =============================================================================
-- RESUMEN DE CONTEOS FINAL (ejecutar al terminar para verificar)
-- =============================================================================
SELECT 'adm_rol'              AS tabla, COUNT(*) AS registros FROM adm_rol
UNION SELECT 'adm_rol_progr_modulo', COUNT(*) FROM adm_rol_progr_modulo
UNION SELECT 'adm_usuario_rol',      COUNT(*) FROM adm_usuario_rol
UNION SELECT 'usuario',              COUNT(*) FROM usuario
ORDER BY tabla;
