ALTER TABLE dashboard_modulos
    MODIFY cod_mod VARCHAR(80) NOT NULL,
    ADD COLUMN tipo VARCHAR(10) NOT NULL AFTER cod_mod,
    ADD COLUMN parent_cod VARCHAR(80) NULL AFTER tipo,
    ADD COLUMN label_short VARCHAR(60) NULL AFTER nom_mod,
    ADD COLUMN icono VARCHAR(60) NULL AFTER label_short,
    ADD COLUMN url VARCHAR(200) NULL AFTER icono,
    ADD COLUMN tipo_param VARCHAR(60) NULL AFTER url,
    ADD COLUMN titulo VARCHAR(150) NULL AFTER tipo_param,
    ADD COLUMN permiso VARCHAR(80) NULL AFTER titulo,
    ADD INDEX idx_dashboard_modulos_parent (programa, parent_cod);
