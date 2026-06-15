-- ================================================================
-- OPTIMIZACIÓN DE ÍNDICES PARA MÓDULO DE SECUENCIA BASE
-- ================================================================
-- Fecha: 2026-03-06
-- Propósito: Acelerar consultas en producción para crear secuencia y calendario
--
-- IMPORTANTE: Ejecutar este script en producción para mejorar el rendimiento
-- ================================================================

-- ================================================================
-- TABLA: ccosbase (tabla base de secuencias)
-- ================================================================

-- Índice compuesto para búsquedas por proyección + secuencia
CREATE INDEX IF NOT EXISTS idx_ccosbase_proyeccion_secuencia 
ON ccosbase(proyeccion, secuencia);

-- Índice para búsquedas por proyección (usado en filtros)
CREATE INDEX IF NOT EXISTS idx_ccosbase_proyeccion 
ON ccosbase(proyeccion);

-- Índice para búsquedas por granja (código) y galpón
CREATE INDEX IF NOT EXISTS idx_ccosbase_codigo_galpon 
ON ccosbase(codigo, galpon);

-- ================================================================
-- TABLA: ccosproy (secuencia proyectada/generada)
-- ================================================================

-- Índice compuesto para búsquedas y ordenamiento
CREATE INDEX IF NOT EXISTS idx_ccosproy_proyeccion_secuencia 
ON ccosproy(proyeccion, secuencia);

-- Índice para filtros por proyección y SWAC activo
CREATE INDEX IF NOT EXISTS idx_ccosproy_proyeccion_swac 
ON ccosproy(proyeccion, swac);

-- Índice para búsquedas por código de granja
CREATE INDEX IF NOT EXISTS idx_ccosproy_codigo 
ON ccosproy(codigo);

-- ================================================================
-- TABLA: fechaproy (calendario de fechas)
-- ================================================================

-- Índice compuesto para búsquedas por proyección y fecha
CREATE INDEX IF NOT EXISTS idx_fechaproy_proyeccion_fecha 
ON fechaproy(proyeccion, fecha);

-- Índice para cálculos de semana
CREATE INDEX IF NOT EXISTS idx_fechaproy_proyeccion_sem 
ON fechaproy(proyeccion, sem);

-- ================================================================
-- TABLA: fechasemproy (semanas agregadas)
-- ================================================================

-- Índice compuesto para búsquedas únicas
CREATE INDEX IF NOT EXISTS idx_fechasemproy_proyeccion_anuo_sem 
ON fechasemproy(proyeccion, anuo, sem);

-- ================================================================
-- TABLA: ccoscargapollo (cargas de pollos por día)
-- ================================================================

-- Índice compuesto principal
CREATE INDEX IF NOT EXISTS idx_ccoscargapollo_proyeccion_fecaqp 
ON ccoscargapollo(proyeccion, fecaqp);

-- Índice para agregaciones por semana
CREATE INDEX IF NOT EXISTS idx_ccoscargapollo_semana 
ON ccoscargapollo(proyeccion, semana);

-- Índice para búsquedas por código de granja
CREATE INDEX IF NOT EXISTS idx_ccoscargapollo_codigo 
ON ccoscargapollo(codigo);

-- ================================================================
-- TABLA: cpproy (proyecciones maestras)
-- ================================================================

-- Índice para búsquedas por nombre (si no existe)
CREATE INDEX IF NOT EXISTS idx_cpproy_nombre 
ON cpproy(nombre);

-- ================================================================
-- OPTIMIZACIÓN DE MOTOR DE BD
-- ================================================================

-- Optimizar las tablas después de crear índices
OPTIMIZE TABLE ccosbase;
OPTIMIZE TABLE ccosproy;
OPTIMIZE TABLE fechaproy;
OPTIMIZE TABLE fechasemproy;
OPTIMIZE TABLE ccoscargapollo;
OPTIMIZE TABLE cpproy;

-- ================================================================
-- VERIFICAR ÍNDICES CREADOS
-- ================================================================

SELECT 
    'ccosbase' as tabla,
    INDEX_NAME,
    COLUMN_NAME,
    SEQ_IN_INDEX,
    INDEX_TYPE
FROM INFORMATION_SCHEMA.STATISTICS 
WHERE TABLE_NAME = 'ccosbase' 
    AND TABLE_SCHEMA = DATABASE()
ORDER BY INDEX_NAME, SEQ_IN_INDEX;

SELECT 
    'ccosproy' as tabla,
    INDEX_NAME,
    COLUMN_NAME,
    SEQ_IN_INDEX,
    INDEX_TYPE
FROM INFORMATION_SCHEMA.STATISTICS 
WHERE TABLE_NAME = 'ccosproy' 
    AND TABLE_SCHEMA = DATABASE()
ORDER BY INDEX_NAME, SEQ_IN_INDEX;

SELECT 
    'fechaproy' as tabla,
    INDEX_NAME,
    COLUMN_NAME,
    SEQ_IN_INDEX,
    INDEX_TYPE
FROM INFORMATION_SCHEMA.STATISTICS 
WHERE TABLE_NAME = 'fechaproy' 
    AND TABLE_SCHEMA = DATABASE()
ORDER BY INDEX_NAME, SEQ_IN_INDEX;

SELECT 
    'ccoscargapollo' as tabla,
    INDEX_NAME,
    COLUMN_NAME,
    SEQ_IN_INDEX,
    INDEX_TYPE
FROM INFORMATION_SCHEMA.STATISTICS 
WHERE TABLE_NAME = 'ccoscargapollo' 
    AND TABLE_SCHEMA = DATABASE()
ORDER BY INDEX_NAME, SEQ_IN_INDEX;

-- ================================================================
-- FIN DEL SCRIPT
-- ================================================================
