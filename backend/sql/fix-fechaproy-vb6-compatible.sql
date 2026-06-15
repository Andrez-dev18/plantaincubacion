-- Script para actualizar la estructura de fechaproy compatible con VB6
-- La tabla debe funcionar como CALENDARIO diario

DROP TABLE IF EXISTS fechaproy;

CREATE TABLE fechaproy (
    proyeccion VARCHAR(25) NOT NULL,
    fecha DATE NOT NULL,
    cargas INT DEFAULT 1,
    sem INT DEFAULT NULL,
    flag CHAR(1) DEFAULT 'A',
    PRIMARY KEY (proyeccion, fecha),
    KEY idx_proyeccion (proyeccion),
    KEY idx_fecha (fecha),
    KEY idx_flag (flag)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Calendario diario de cargas por proyección';

-- Crear tabla cargas_config para permitir ediciones de usuario
CREATE TABLE IF NOT EXISTS cargas_config (
    proyeccion VARCHAR(25) NOT NULL,
    fecha DATE NOT NULL,
    cargas INT NOT NULL,
    PRIMARY KEY (proyeccion, fecha),
    KEY idx_proyeccion (proyeccion)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Override de cargas editadas por usuario';

-- Tabla de resumen oferta/demanda (ya existe pero verificar estructura)
CREATE TABLE IF NOT EXISTS fechasemproy (
    proyeccion VARCHAR(25) NOT NULL,
    anuo INT NOT NULL,
    sem INT NOT NULL,
    oferta INT DEFAULT 0,
    demanda INT DEFAULT 0,
    diferencia INT DEFAULT 0,
    PRIMARY KEY (proyeccion, anuo, sem),
    KEY idx_proyeccion (proyeccion)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
