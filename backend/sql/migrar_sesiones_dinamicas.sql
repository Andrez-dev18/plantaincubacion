-- ═══════════════════════════════════════════════════════════════════════════
-- MIGRACIÓN: Escenarios Dinámicos con Sesiones Históricas
-- Fecha: 28-Feb-2026
-- Permite N escenarios por sesión + historial + notas
-- ═══════════════════════════════════════════════════════════════════════════

-- ─────────────────────────────────────────────
-- PASO 1: Crear tabla de Sesiones
-- ─────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `sim_sesion` (
  `id`              INT(11)      NOT NULL AUTO_INCREMENT,
  `proyeccion`      VARCHAR(25)  NOT NULL COMMENT 'FK cpproy.nombre',
  `nombre_sesion`   VARCHAR(100) NOT NULL COMMENT 'Ej: Simulación 01-Mar-2026',
  `descripcion`     TEXT         DEFAULT NULL,
  `fecha_sesion`    DATE         NOT NULL COMMENT 'Fecha en que se generó',
  `usuario_crea`    VARCHAR(50)  DEFAULT NULL,
  `fecha_creacion`  DATETIME     DEFAULT CURRENT_TIMESTAMP,
  `estado`          CHAR(1)      DEFAULT 'A' COMMENT 'A=Activo / I=Archivado',
  PRIMARY KEY (`id`),
  KEY `idx_sesion_proyeccion` (`proyeccion`, `fecha_sesion`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
  COMMENT='Sesión de comparación de escenarios - con historial por fecha';

-- ─────────────────────────────────────────────
-- PASO 2: Respaldar datos existentes
-- ─────────────────────────────────────────────
DROP TABLE IF EXISTS `sim_escenario_backup`;
CREATE TABLE `sim_escenario_backup` LIKE `sim_escenario`;
INSERT INTO `sim_escenario_backup` SELECT * FROM `sim_escenario`;

-- ─────────────────────────────────────────────
-- PASO 3: Eliminar FKs de tablas dependientes
-- ─────────────────────────────────────────────
-- Nota: Ignorar errores si las FKs no existen

-- ─────────────────────────────────────────────
-- PASO 4: Recrear sim_escenario con nueva estructura
-- ─────────────────────────────────────────────
DROP TABLE IF EXISTS `sim_escenario`;

CREATE TABLE `sim_escenario` (
  `id`                INT(11)       NOT NULL AUTO_INCREMENT,
  `id_sesion`         INT(11)       NOT NULL COMMENT 'FK sim_sesion.id',
  `orden`             INT(11)       NOT NULL DEFAULT 0 COMMENT 'Posición columna: 0=Actual, 1,2,3,4...',
  `es_base`           TINYINT(1)    DEFAULT 0 COMMENT '1=Escenario Actual/referencia',
  `nom_escenario`     VARCHAR(100)  NOT NULL COMMENT 'Ej: Escenario Actual / +6 Galpones / 15 cargas',
  `notas`             TEXT          DEFAULT NULL COMMENT 'Notas del usuario sobre el escenario',
  -- Parámetros ROJOS (modificables)
  `n_galpones_2640`   INT(11)       DEFAULT 0,
  `n_galpones_1800`   INT(11)       DEFAULT 0,
  `pollos_por_galpon` INT(11)       DEFAULT 0,
  `cargas_semanales`  DECIMAL(5,1)  DEFAULT 0,
  `tpo_limpieza_dias` DECIMAL(5,1)  DEFAULT 0,
  -- Auditoría
  `usuario_crea`      VARCHAR(50)   DEFAULT NULL,
  `fecha_creacion`    DATETIME      DEFAULT CURRENT_TIMESTAMP,
  `usuario_modifica`  VARCHAR(50)   DEFAULT NULL,
  `fecha_modifica`    DATETIME      DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_escenario_sesion` (`id_sesion`, `orden`),
  CONSTRAINT `fk_escenario_sesion`
    FOREIGN KEY (`id_sesion`) REFERENCES `sim_sesion`(`id`)
    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
  COMMENT='Escenarios dinámicos por sesión - sin límite de cantidad';

-- ─────────────────────────────────────────────
-- PASO 5: Restaurar FKs de tablas dependientes
-- ─────────────────────────────────────────────
ALTER TABLE `sim_escenario_zona`
  ADD CONSTRAINT `fk_zona_escenario`
    FOREIGN KEY (`id_escenario`) REFERENCES `sim_escenario`(`id`)
    ON DELETE CASCADE;

ALTER TABLE `sim_escenario_resultado`
  ADD CONSTRAINT `fk_resultado_escenario`
    FOREIGN KEY (`id_escenario`) REFERENCES `sim_escenario`(`id`)
    ON DELETE CASCADE;

-- ─────────────────────────────────────────────
-- PASO 6: Migrar datos antiguos (si existen)
-- ─────────────────────────────────────────────
-- Este script crea una sesión "Migración Inicial" por cada proyección
-- y convierte los escenarios antiguos al nuevo formato

INSERT INTO `sim_sesion` (`proyeccion`, `nombre_sesion`, `descripcion`, `fecha_sesion`, `usuario_crea`)
SELECT DISTINCT 
    proyeccion,
    CONCAT('Migración Inicial - ', proyeccion),
    'Escenarios migrados del sistema anterior',
    CURDATE(),
    'SYSTEM'
FROM `sim_escenario_backup`
WHERE EXISTS (SELECT 1 FROM `sim_escenario_backup` LIMIT 1);

-- Migrar escenarios con orden basado en cod_escenario
INSERT INTO `sim_escenario` 
    (`id_sesion`, `orden`, `es_base`, `nom_escenario`, `notas`,
     `n_galpones_2640`, `n_galpones_1800`, `pollos_por_galpon`, 
     `cargas_semanales`, `tpo_limpieza_dias`, `usuario_crea`, `fecha_creacion`)
SELECT 
    s.id,
    CASE b.cod_escenario
        WHEN 'ACTUAL' THEN 0
        WHEN 'ESC1' THEN 1
        WHEN 'ESC2' THEN 2
        ELSE 3
    END,
    CASE WHEN b.cod_escenario = 'ACTUAL' THEN 1 ELSE 0 END,
    COALESCE(b.nom_escenario, b.cod_escenario),
    NULL,
    b.n_galpones_2640,
    b.n_galpones_1800,
    b.pollos_por_galpon,
    b.cargas_semanales,
    b.tpo_limpieza_dias,
    b.usuario_crea,
    b.fecha_creacion
FROM `sim_escenario_backup` b
INNER JOIN `sim_sesion` s ON s.proyeccion = b.proyeccion
WHERE b.estado = 'A'
AND EXISTS (SELECT 1 FROM `sim_escenario_backup` LIMIT 1);

-- ═══════════════════════════════════════════════════════════════════════════
-- VERIFICACIÓN POST-MIGRACIÓN
-- ═══════════════════════════════════════════════════════════════════════════

SELECT 'Sesiones creadas:' AS msg, COUNT(*) AS total FROM sim_sesion WHERE estado = 'A'
UNION ALL
SELECT 'Escenarios migrados:', COUNT(*) FROM sim_escenario
UNION ALL  
SELECT 'Zonas migradas:', COUNT(*) FROM sim_escenario_zona
UNION ALL
SELECT 'Resultados migrados:', COUNT(*) FROM sim_escenario_resultado;
