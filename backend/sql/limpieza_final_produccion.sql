-- =============================================================================
-- limpieza_final_produccion.sql
-- Script definitivo de limpieza basado en diagnóstico del 21/04/2026
-- BD: cia2026 (producción)
-- Compatible con MySQL 5.7
--
-- NOTA: Las instrucciones ALTER TABLE ADD COLUMN aquí NO usan IF NOT EXISTS
-- porque MySQL 5.7 no lo soporta. El script PHP (ejecutar_limpieza.php) verifica
-- la existencia de columnas antes de ejecutarlos. Si corres este SQL manualmente,
-- comenta las líneas ALTER TABLE de columnas que ya existan.
--
-- EJECUTAR EN ORDEN, UN BLOQUE A LA VEZ.
-- Haz backup antes: mysqldump -u root -p cia2026 > backup_21abr2026.sql
-- =============================================================================


-- =============================================================================
-- PASO 1: AGREGAR COLUMNAS FALTANTES EN adm_rol
-- MySQL 5.7: verificar manualmente si la columna ya existe antes de ejecutar.
-- El script PHP lo hace automáticamente.
-- =============================================================================

-- (solo ejecutar si la columna 'activo' NO existe en adm_rol)
ALTER TABLE adm_rol ADD COLUMN activo       TINYINT(1)   NOT NULL DEFAULT 1  AFTER nom_rol;
-- (solo ejecutar si 'descripcion' NO existe)
ALTER TABLE adm_rol ADD COLUMN descripcion  VARCHAR(255) NULL                AFTER activo;
-- (solo ejecutar si 'id_programa' NO existe)
ALTER TABLE adm_rol ADD COLUMN id_programa  TINYINT(4)   NULL                AFTER descripcion;
-- (solo ejecutar si 'fecha_update' NO existe)
ALTER TABLE adm_rol ADD COLUMN fecha_update DATETIME     NULL                AFTER id_programa;

-- Verificar:
-- SHOW COLUMNS FROM adm_rol;


-- =============================================================================
-- PASO 2: RENOMBRAR ROLES CON NOMBRES INCORRECTOS
-- =============================================================================

-- OPERADOR tiene nombre de prueba "Operador editado222"
UPDATE adm_rol SET nom_rol = 'Operador de Planta'
WHERE cod_rol = 'OPERADOR' AND nom_rol LIKE '%editado%';

-- ADMINISTR1: "Funciones de administrador" → nombre más claro
-- (opcional — descomenta si quieres renombrarlo)
-- UPDATE adm_rol SET nom_rol = 'Administrador de Roles' WHERE cod_rol = 'ADMINISTR1';

-- Verificar roles después:
SELECT id, cod_rol, nom_rol, COALESCE(activo,1) AS activo FROM adm_rol ORDER BY nom_rol;


-- =============================================================================
-- PASO 3: CREAR TABLA usuarios_L (usuarios de login IAM)
-- Separada de `usuario` que es la tabla de personal/trabajadores (254 personas)
-- =============================================================================

CREATE TABLE IF NOT EXISTS `usuarios_L` (
    `id`              INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    `codigo`          VARCHAR(20)     NOT NULL COMMENT 'Login username. Igual al codigo en adm_usuario_rol',
    `nombre`          VARCHAR(100)    NOT NULL,
    `password`        BINARY(8)       NULL     COMMENT 'AES_ENCRYPT(pass, enom) — mismo cifrado que tabla usuario',
    `epre`            VARCHAR(5)      NOT NULL DEFAULT 'RS',
    `email`           VARCHAR(100)    NULL,
    `activo`          TINYINT(1)      NOT NULL DEFAULT 1,
    `fecha_registro`  DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `ultimo_acceso`   DATETIME        NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_codigo` (`codigo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Usuarios de login para el modulo IAM/Roles. NO es la tabla de personal.';


-- =============================================================================
-- PASO 4: MIGRAR USUARIOS LEGÍTIMOS A usuarios_L
-- Solo se migran usuarios que:
--   a) Existen en `usuario` (son reales)
--   b) Tienen al menos 1 rol en adm_usuario_rol
--   c) NO son el usuario de prueba codigo='1'
-- La contraseña BINARY(8) se copia tal cual (ya está cifrada).
-- =============================================================================

INSERT IGNORE INTO `usuarios_L` (codigo, nombre, password, epre, activo, fecha_registro, ultimo_acceso)
SELECT
    u.codigo,
    u.nombre,
    u.password,
    IFNULL(u.epre, 'RS'),
    CASE WHEN u.estado = 'A' THEN 1 ELSE 0 END,
    IFNULL(u.fecha_registro, NOW()),
    u.ultimo_acceso
FROM usuario u
WHERE u.codigo <> '1'                          -- excluir usuario de prueba "Acceso Total"
  AND EXISTS (
      SELECT 1 FROM adm_usuario_rol ur
      WHERE ur.codigo = u.codigo
  );

-- Ver qué se migró:
SELECT ul.codigo, ul.nombre, ul.activo,
       GROUP_CONCAT(r.nom_rol ORDER BY r.nom_rol SEPARATOR ' | ') AS roles
FROM usuarios_L ul
LEFT JOIN adm_usuario_rol ur ON ur.codigo = ul.codigo
LEFT JOIN adm_rol r ON r.cod_rol = ur.cod_rol
GROUP BY ul.id ORDER BY ul.nombre;


-- =============================================================================
-- PASO 5: ELIMINAR ASIGNACIONES DEL USUARIO DE PRUEBA '1'
-- "Usuario 1 - Acceso Total" con los 8 roles — claramente datos de prueba
-- =============================================================================

-- Ver cuántos roles tiene:
SELECT codigo, cod_rol FROM adm_usuario_rol WHERE codigo = '1';

-- Eliminar sus asignaciones:
DELETE FROM adm_usuario_rol WHERE codigo = '1';

-- Desactivar el usuario en la tabla personal (más seguro que borrar):
UPDATE usuario SET estado = 'I' WHERE codigo = '1';


-- =============================================================================
-- PASO 6: ELIMINAR REGISTROS HUÉRFANOS EN adm_usuario_rol
-- Cualquier código que no existe en usuarios_L (ya limpia y validada) se elimina.
-- Esto cubre automáticamente: 12345678, 2, 3, 4280700, 7089561, 70895611,
--   admin/administrador, adroles, prx1, prx2, TEST01/PRUEBA01, test88, test99
-- =============================================================================

-- VERIFICAR primero (no borra nada):
SELECT ur.codigo,
       GROUP_CONCAT(ur.cod_rol SEPARATOR ', ') AS roles_asignados,
       COUNT(*)                                 AS n
FROM adm_usuario_rol ur
WHERE NOT EXISTS (SELECT 1 FROM usuarios_L ul WHERE ul.codigo = ur.codigo)
GROUP BY ur.codigo
ORDER BY ur.codigo;

-- Si la lista son solo datos de prueba, ejecutar:
DELETE FROM adm_usuario_rol
WHERE NOT EXISTS (SELECT 1 FROM usuarios_L ul WHERE ul.codigo = adm_usuario_rol.codigo);

-- Confirmar que no quedan huérfanos:
SELECT COUNT(*) AS huerfanos_restantes
FROM adm_usuario_rol ur
WHERE NOT EXISTS (SELECT 1 FROM usuarios_L ul WHERE ul.codigo = ur.codigo);
-- Debe devolver 0


-- =============================================================================
-- PASO 7: REVISAR USUARIO 'cesar' (nombre = "1")
-- Existe en la tabla `usuario` con nombre="1" — parece mal configurado
-- Tiene 2 roles asignados. Verificar si es real o de prueba.
-- =============================================================================

SELECT u.codigo, u.nombre, u.estado, u.fecha_registro
FROM usuario u WHERE u.codigo = 'cesar';

-- Si es de prueba, eliminar roles y desactivar:
-- DELETE FROM adm_usuario_rol WHERE codigo = 'cesar';
-- UPDATE usuario SET estado = 'I' WHERE codigo = 'cesar';

-- Si es un usuario real con nombre incorrecto, corregir el nombre:
-- UPDATE usuarios_L SET nombre = 'NOMBRE CORRECTO' WHERE codigo = 'cesar';
-- (y opcionalmente también en usuario si sigue siendo activo)


-- =============================================================================
-- PASO 8 (OPCIONAL): REVISAR ROLES CON POCAS ASIGNACIONES
-- - GESTION: solo 1 módulo asignado (grp-1 sin ítems) → el rol funciona pero está incompleto
-- - OPERADOR: 0 módulos asignados → los usuarios con este rol no verán nada
-- =============================================================================

-- Ver módulos del rol GESTION:
SELECT r.cod_rol, r.nom_rol, rpm.id_programa, rpm.cod_mod
FROM adm_rol r
LEFT JOIN adm_rol_progr_modulo rpm ON rpm.id_rol = r.id
WHERE r.cod_rol IN ('GESTION', 'OPERADOR')
ORDER BY r.cod_rol, rpm.cod_mod;

-- Si quieres limpiar roles de prueba (Q y ADMINISTRA):
-- ADVERTENCIA: esto también borra los módulos asignados y los usuarios asignados a esos roles.
-- Solo ejecutar si estás seguro.
/*
DELETE FROM adm_usuario_rol        WHERE cod_rol IN ('Q', 'ADMINISTRA');
DELETE FROM adm_rol_progr_modulo   WHERE id_rol IN (SELECT id FROM adm_rol WHERE cod_rol IN ('Q', 'ADMINISTRA'));
DELETE FROM adm_rol                WHERE cod_rol IN ('Q', 'ADMINISTRA');
*/


-- =============================================================================
-- RESUMEN FINAL — ejecutar al terminar para verificar estado limpio
-- =============================================================================
SELECT 'usuarios_L'           AS tabla, COUNT(*) AS registros FROM usuarios_L
UNION SELECT 'adm_usuario_rol',          COUNT(*) FROM adm_usuario_rol
UNION SELECT 'adm_rol',                  COUNT(*) FROM adm_rol
UNION SELECT 'adm_rol_progr_modulo',     COUNT(*) FROM adm_rol_progr_modulo
UNION SELECT 'amd_dashboard_modulos',    COUNT(*) FROM amd_dashboard_modulos
ORDER BY tabla;

-- Asignaciones usuario↔rol limpias:
SELECT ul.codigo, ul.nombre, ul.activo,
       COUNT(ur.cod_rol) AS roles
FROM usuarios_L ul
LEFT JOIN adm_usuario_rol ur ON ur.codigo = ul.codigo
GROUP BY ul.id ORDER BY ul.nombre;
