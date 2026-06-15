CREATE TABLE IF NOT EXISTS dashboard_modulos (
    programa VARCHAR(60) NOT NULL,
    cod_mod VARCHAR(80) NOT NULL,
    tipo VARCHAR(10) NOT NULL,
    parent_cod VARCHAR(80) NULL,
    nom_mod VARCHAR(150) NULL,
    label_short VARCHAR(60) NULL,
    icono VARCHAR(60) NULL,
    url VARCHAR(200) NULL,
    tipo_param VARCHAR(60) NULL,
    titulo VARCHAR(150) NULL,
    permiso VARCHAR(80) NULL,
    nivel0 INT NULL,
    nivel1 INT NULL,
    nivel2 INT NULL,
    nivel3 INT NULL,
    orden INT NOT NULL,
    PRIMARY KEY (programa, cod_mod),
    INDEX idx_dashboard_modulos_programa_orden (programa, orden),
    INDEX idx_dashboard_modulos_parent (programa, parent_cod)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


