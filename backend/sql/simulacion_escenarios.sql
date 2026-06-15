-- ============================================
-- Tablas para Simulación de Escenarios de Carga
-- Hoja 6.Calculo del Excel
-- ============================================

-- Tabla principal de escenarios
CREATE TABLE `sim_escenario` (
  `id`                  INT(11)       NOT NULL AUTO_INCREMENT,
  `proyeccion`          VARCHAR(25)   NOT NULL COMMENT 'FK a cpproy.nombre',
  `cod_escenario`       VARCHAR(20)   NOT NULL COMMENT 'ACTUAL / ESC1 / ESC2',
  `nom_escenario`       VARCHAR(80)   DEFAULT NULL,
  `n_galpones_2640`     INT(11)       DEFAULT 0 COMMENT 'Rojo - modificable',
  `n_galpones_1800`     INT(11)       DEFAULT 0 COMMENT 'Rojo - modificable',
  `pollos_por_galpon`   INT(11)       DEFAULT 0 COMMENT 'Rojo - modificable',
  `cargas_semanales`    DECIMAL(5,1)  DEFAULT 0 COMMENT 'Rojo - modificable',
  `tpo_limpieza_dias`   DECIMAL(5,1)  DEFAULT 0 COMMENT 'Rojo - modificable',
  `estado`              CHAR(1)       DEFAULT 'A' COMMENT 'A=Activo / I=Inactivo',
  `usuario_crea`        VARCHAR(50)   DEFAULT NULL,
  `fecha_creacion`      DATETIME      DEFAULT CURRENT_TIMESTAMP,
  `usuario_modifica`    VARCHAR(50)   DEFAULT NULL,
  `fecha_modifica`      DATETIME      DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_proyeccion_escenario` (`proyeccion`, `cod_escenario`),
  KEY `idx_proyeccion` (`proyeccion`),
  KEY `idx_estado` (`estado`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1
  COMMENT='Parámetros modificables por escenario - Hoja 6.Calculo';

-- Tabla de zonas por escenario
CREATE TABLE `sim_escenario_zona` (
  `id`                  INT(11)       NOT NULL AUTO_INCREMENT,
  `id_escenario`        INT(11)       NOT NULL COMMENT 'FK a sim_escenario.id',
  `zona`                VARCHAR(30)   NOT NULL COMMENT 'La Joya / Mollendo / San Lucas',
  `porcentaje_zona`     DECIMAL(5,2)  DEFAULT 0 COMMENT 'Ej: 63.8 para La Joya',
  `tpo_crianza_dias`    DECIMAL(5,1)  DEFAULT 0 COMMENT 'Rojo - modificable por escenario',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_escenario_zona` (`id_escenario`, `zona`),
  KEY `idx_escenario` (`id_escenario`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1
  COMMENT='Tiempos de crianza por zona geográfica - Hoja 6.Calculo';

-- Tabla de resultados calculados
CREATE TABLE `sim_escenario_resultado` (
  `id`                      INT(11)       NOT NULL AUTO_INCREMENT,
  `id_escenario`            INT(11)       NOT NULL COMMENT 'FK a sim_escenario.id',
  `total_galpones`          INT(11)       DEFAULT 0,
  `area_total_m2`           DECIMAL(12,2) DEFAULT 0,
  `galp_std_2640`           INT(11)       DEFAULT 0 COMMENT 'Galpones estandarizados a 2640m2',
  `pollos_semana`           INT(11)       DEFAULT 0 COMMENT 'N° pollos criados por semana',
  `oferta_pollos_semana`    INT(11)       DEFAULT 0 COMMENT 'N° pollos oferta por semana',
  `cargas_diario`           DECIMAL(5,1)  DEFAULT 0 COMMENT 'Galpones/Día - 1 decimal',
  `tpo_ciclo_crianza`       DECIMAL(5,1)  DEFAULT 0 COMMENT 'Días - 1 decimal',
  `tpo_crianza_ponderado`   DECIMAL(5,1)  DEFAULT 0 COMMENT 'Tiempo crianza ponderado - 1 decimal',
  `tpo_descanso_total`      INT(11)       DEFAULT 0 COMMENT 'Días - 0 decimales',
  `tpo_descanso_efectivo`   INT(11)       DEFAULT 0 COMMENT 'Días - 0 decimales',
  `fecha_calculo`           DATETIME      DEFAULT CURRENT_TIMESTAMP,
  `usuario_calculo`         VARCHAR(50)   DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_resultado_escenario` (`id_escenario`),
  KEY `idx_escenario` (`id_escenario`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1
  COMMENT='Valores calculados por escenario - Hoja 6.Calculo';

-- ============================================
-- Datos iniciales de ejemplo
-- ============================================

-- Zonas estándar (porcentajes)
-- La Joya:   63.8%
-- Mollendo:  30.4%
-- San Lucas:  5.8%

