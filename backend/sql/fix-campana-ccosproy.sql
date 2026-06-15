-- ===========================================================
-- Script para expandir el campo 'campana' en ccosproy
-- Problema: El campo es VARCHAR(3) pero se generan valores 
-- como "188-1", "188-2", etc. que necesitan más caracteres
-- ===========================================================

USE grs_picamana;

-- 1. Expandir el campo campana en ccosproy de VARCHAR(3) a VARCHAR(10)
ALTER TABLE ccosproy 
MODIFY COLUMN campana VARCHAR(10) DEFAULT NULL;

-- Verificar el cambio
DESCRIBE ccosproy;

SELECT 'Campo campana expandido exitosamente en ccosproy' AS mensaje;
