-- =============================================================================
-- crear_usuarios_L.sql
-- Crea la tabla usuarios_L para usuarios de LOGIN del sistema IAM
-- Separa los usuarios de sesión de la tabla usuario (personal/trabajadores)
--
-- Patrón igual a controlYplanilla (tabla `usuarios`)
-- En plantaincubacion: usuario = tabla de personal (254 trabajadores)
--                      usuarios_L = usuarios que se loguean en el sistema IAM
-- =============================================================================


-- =============================================================================
-- PASO 1: Crear tabla usuarios_L
-- =============================================================================

CREATE TABLE IF NOT EXISTS `usuarios_L` (
    `id`              INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    `codigo`          VARCHAR(20)     NOT NULL COMMENT 'Login username. Igual al codigo en adm_usuario_rol',
    `nombre`          VARCHAR(100)    NOT NULL COMMENT 'Nombre completo para mostrar',
    `password`        BINARY(8)       NULL     COMMENT 'AES_ENCRYPT(pass, enom) igual que tabla usuario',
    `epre`            VARCHAR(5)      NOT NULL DEFAULT 'RS' COMMENT 'Referencia a conempre.epre para la clave AES',
    `email`           VARCHAR(100)    NULL,
    `activo`          TINYINT(1)      NOT NULL DEFAULT 1 COMMENT '1=activo, 0=inactivo',
    `fecha_registro`  DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `ultimo_acceso`   DATETIME        NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_codigo` (`codigo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Usuarios de login para el módulo IAM/Roles. NO confundir con tabla usuario (personal).';


-- =============================================================================
-- PASO 2: Migrar usuarios reales que ya tienen roles asignados
-- Solo migramos usuarios que:
--   a) Existen en la tabla `usuario` (son reales, no fantasmas)
--   b) Tienen al menos un rol asignado en `adm_usuario_rol`
-- La contraseña (BINARY 8) se copia tal cual (ya está cifrada con AES).
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
WHERE EXISTS (
    SELECT 1
    FROM adm_usuario_rol ur
    WHERE ur.codigo = u.codigo
);

-- Verificar cuántos se migraron
SELECT COUNT(*) AS usuarios_migrados FROM usuarios_L;


-- =============================================================================
-- PASO 3: Limpiar registros huérfanos en adm_usuario_rol
-- (usuarios que no existen ni en usuario ni en usuarios_L)
-- =============================================================================

-- VERIFICAR antes:
SELECT ur.codigo,
       GROUP_CONCAT(ur.cod_rol SEPARATOR ', ') AS roles
FROM adm_usuario_rol ur
WHERE NOT EXISTS (SELECT 1 FROM usuarios_L ul WHERE ul.codigo = ur.codigo)
GROUP BY ur.codigo
ORDER BY ur.codigo;

-- Si la lista son solo datos de prueba, ejecutar:
-- DELETE FROM adm_usuario_rol
-- WHERE NOT EXISTS (SELECT 1 FROM usuarios_L ul WHERE ul.codigo = adm_usuario_rol.codigo);


-- =============================================================================
-- NOTAS PARA DESPUÉS DE MIGRAR
-- =============================================================================
-- 1. La primera vez que el app corra con usuarios_L, el loginConRol() usará usuarios_L.
-- 2. Para crear un nuevo usuario de login, usar el módulo Usuarios del sistema IAM.
-- 3. La tabla `usuario` NO se toca: sigue siendo la tabla de personal.
-- 4. Si un trabajador necesita acceder al sistema, se crea un registro en usuarios_L
--    con su codigo y nombre. El campo `codigo` puede ser el mismo DNI o un alias.
-- =============================================================================

-- Listar usuarios_L creados con sus roles
SELECT ul.codigo, ul.nombre, ul.activo,
       GROUP_CONCAT(r.nom_rol ORDER BY r.nom_rol SEPARATOR ', ') AS roles
FROM usuarios_L ul
LEFT JOIN adm_usuario_rol ur ON ur.codigo = ul.codigo
LEFT JOIN adm_rol r ON r.cod_rol = ur.cod_rol
GROUP BY ul.id, ul.codigo, ul.nombre, ul.activo
ORDER BY ul.nombre;
