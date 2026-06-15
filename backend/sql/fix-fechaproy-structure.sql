-- Script para actualizar la estructura de la tabla fechaproy
-- La tabla necesita tener las columnas requeridas por el método crearCalendario

-- Eliminar la tabla antigua y recrearla con la estructura correcta
DROP TABLE IF EXISTS fechaproy;

CREATE TABLE fechaproy (
    proyeccion VARCHAR(25) NOT NULL,
    campana VARCHAR(10) NOT NULL,
    galpon VARCHAR(2) NOT NULL,
    fecaqp DATE DEFAULT NULL,
    fecliqui DATE DEFAULT NULL,
    feclima DATE DEFAULT NULL,
    fecdespo DATE DEFAULT NULL,
    PRIMARY KEY (proyeccion, campana, galpon),
    KEY idx_proyeccion (proyeccion),
    KEY idx_campana (campana),
    KEY idx_galpon (galpon)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Actualizar la tabla fechasemproy si no existe
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
