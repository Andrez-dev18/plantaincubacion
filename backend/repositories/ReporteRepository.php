<?php

class ReporteRepository {
    private $db;

    public function __construct($db) {
        $this->db = $db;
    }

    /**
     * Obtiene todas las granjas/galpones únicos de una proyección
     */
    public function obtenerGranjasUnicas($proyeccion) {
        $sql = "SELECT DISTINCT 
                    c.codigo,
                    c.nombre,
                    c.galpon,
                    CONCAT(c.codigo, ' : ', c.galpon) AS granja_completa,
                    c.secuencia
                FROM ccoscargapollo c
                WHERE c.proyeccion = :proyeccion
                ORDER BY c.secuencia, c.codigo, c.galpon";
        
        $stmt = $this->db->prepare($sql);
        $stmt->bindParam(':proyeccion', $proyeccion, PDO::PARAM_STR);
        $stmt->execute();
        
        $granjas = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $granjas[] = $row;
        }
        
        return $granjas;
    }

    /**
     * Obtiene los datos agrupados por semana para el reporte
     */
    public function obtenerDatosPorSemana($proyeccion, $limit = 500) {
        $sql = "SELECT 
                    c.semana,
                    YEAR(c.fecaqp) AS anio,
                    MONTH(c.fecaqp) AS mes,
                    c.codigo,
                    c.nombre,
                    c.galpon,
                    c.campana,
                    MAX(c.viajessem) AS viajessem,
                    MAX(c.pollossem) AS pollossem,
                    SUM(c.pollos) AS pollos,
                    MAX(c.fecaqp) AS fecaqp,
                    MAX(c.fecliqui) AS fecliqui,
                    MAX(c.feclima) AS feclima,
                    CONCAT(c.codigo, ' : ', c.galpon) AS granja_completa
                FROM ccoscargapollo c
                WHERE c.proyeccion = :proyeccion
                GROUP BY 
                    c.semana, 
                    YEAR(c.fecaqp), 
                    MONTH(c.fecaqp),
                    c.codigo,
                    c.nombre,
                    c.galpon,
                    c.campana
                ORDER BY c.semana ASC, c.codigo ASC
                LIMIT :limit";
        
        $stmt = $this->db->prepare($sql);
        $stmt->bindParam(':proyeccion', $proyeccion, PDO::PARAM_STR);
        $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        
        $datos = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $datos[] = $row;
        }
        
        return $datos;
    }

    /**
     * Obtiene resumen de oferta/demanda por semana
     */
    public function obtenerResumenSemanal($proyeccion) {
        $sql = "SELECT 
                    f.anuo,
                    f.sem,
                    f.oferta,
                    f.demanda,
                    f.diferencia
                FROM fechasemproy f
                WHERE f.proyeccion = :proyeccion
                ORDER BY f.anuo, ABS(f.sem)";
        
        $stmt = $this->db->prepare($sql);
        $stmt->bindParam(':proyeccion', $proyeccion, PDO::PARAM_STR);
        $stmt->execute();
        
        $resumen = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $resumen[] = $row;
        }
        
        return $resumen;
    }

    /**
     * Obtiene lista de proyecciones disponibles
     */
    public function obtenerProyecciones() {
        $sql = "SELECT nombre, indicador FROM cpproy ORDER BY nombre";
        $stmt = $this->db->query($sql);

        $proyecciones = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $proyecciones[] = [
                'nombre' => $row['nombre'],
                'indicador' => $row['indicador']
            ];
        }

        return $proyecciones;
    }

    /**
     * Obtiene datos detallados para el calendario
     */
    public function obtenerCalendarioDetallado($proyeccion) {
        $sql = "SELECT 
                    c.fecaqp,
                    c.semana,
                    c.codigo,
                    c.nombre,
                    c.galpon,
                    c.campana,
                    c.pollos,
                    c.pollosliqui,
                    c.fecliqui,
                    c.feclima,
                    c.fecdespo,
                    c.diasefec,
                    c.secuencia
                FROM ccoscargapollo c
                WHERE c.proyeccion = :proyeccion
                ORDER BY c.fecaqp ASC, c.secuencia ASC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->bindParam(':proyeccion', $proyeccion, PDO::PARAM_STR);
        $stmt->execute();
        
        $calendario = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $calendario[] = $row;
        }
        
        return $calendario;
    }

    /**
     * Cuenta total de registros del calendario
     */
    public function contarRegistrosCalendario($proyeccion) {
        $sql = "SELECT COUNT(*) as total FROM ccoscargapollo WHERE proyeccion = :proyeccion";
        $stmt = $this->db->prepare($sql);
        $stmt->bindParam(':proyeccion', $proyeccion, PDO::PARAM_STR);
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return intval($row['total']);
    }

    /**
     * Cuenta registros filtrados del calendario
     */
    public function contarRegistrosCalendarioFiltrados($proyeccion, $search) {
        $sql = "SELECT COUNT(*) as total 
                FROM ccoscargapollo 
                WHERE proyeccion = :proyeccion
                AND (
                    CAST(semana AS CHAR) LIKE :search
                    OR codigo LIKE :search
                    OR nombre LIKE :search
                    OR galpon LIKE :search
                    OR CAST(pollos AS CHAR) LIKE :search
                )";
        
        $stmt = $this->db->prepare($sql);
        $searchParam = "%{$search}%";
        $stmt->bindParam(':proyeccion', $proyeccion, PDO::PARAM_STR);
        $stmt->bindParam(':search', $searchParam, PDO::PARAM_STR);
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return intval($row['total']);
    }

    /**
     * Obtiene calendario paginado (server-side DataTables)
     */
    public function obtenerCalendarioPaginado($proyeccion, $start, $length, $search, $orderColumn, $orderDir) {
        $whereClause = "WHERE proyeccion = :proyeccion";
        $searchParam = null;
        
        if (!empty($search)) {
            $whereClause .= " AND (
                CAST(semana AS CHAR) LIKE :search
                OR codigo LIKE :search
                OR nombre LIKE :search
                OR galpon LIKE :search
                OR CAST(pollos AS CHAR) LIKE :search
            )";
            $searchParam = "%{$search}%";
        }
        
        // Validar columna de ordenamiento
        $allowedColumns = ['semana', 'fecaqp', 'codigo', 'nombre', 'galpon', 'pollos'];
        if (!in_array($orderColumn, $allowedColumns)) {
            $orderColumn = 'fecaqp';
        }
        
        // Validar dirección
        $orderDir = strtoupper($orderDir) === 'DESC' ? 'DESC' : 'ASC';
        
        $sql = "SELECT 
                    fecaqp,
                    semana,
                    codigo,
                    nombre,
                    galpon,
                    pollos,
                    1 as num_cargas
                FROM ccoscargapollo 
                {$whereClause}
                ORDER BY {$orderColumn} {$orderDir}, secuencia ASC
                LIMIT :start, :length";
        
        $stmt = $this->db->prepare($sql);
        $stmt->bindParam(':proyeccion', $proyeccion, PDO::PARAM_STR);
        if ($searchParam !== null) {
            $stmt->bindParam(':search', $searchParam, PDO::PARAM_STR);
        }
        $stmt->bindParam(':start', $start, PDO::PARAM_INT);
        $stmt->bindParam(':length', $length, PDO::PARAM_INT);
        $stmt->execute();
        
        $datos = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $datos[] = $row;
        }
        
        return $datos;
    }

    /**
     * Cuenta total de registros del resumen
     */
    public function contarRegistrosResumen($proyeccion) {
        $sql = "SELECT COUNT(*) as total FROM fechasemproy WHERE proyeccion = :proyeccion";
        $stmt = $this->db->prepare($sql);
        $stmt->bindParam(':proyeccion', $proyeccion, PDO::PARAM_STR);
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return intval($row['total']);
    }

    /**
     * Cuenta registros filtrados del resumen
     */
    public function contarRegistrosResumenFiltrados($proyeccion, $search) {
        $sql = "SELECT COUNT(*) as total 
                FROM fechasemproy 
                WHERE proyeccion = :proyeccion
                AND (
                    CAST(anuo AS CHAR) LIKE :search
                    OR CAST(sem AS CHAR) LIKE :search
                    OR CAST(oferta AS CHAR) LIKE :search
                    OR CAST(demanda AS CHAR) LIKE :search
                    OR CAST(diferencia AS CHAR) LIKE :search
                )";
        
        $stmt = $this->db->prepare($sql);
        $searchParam = "%{$search}%";
        $stmt->bindParam(':proyeccion', $proyeccion, PDO::PARAM_STR);
        $stmt->bindParam(':search', $searchParam, PDO::PARAM_STR);
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return intval($row['total']);
    }

    /**
     * Obtiene resumen paginado (server-side DataTables)
     */
    public function obtenerResumenPaginado($proyeccion, $start, $length, $search, $orderColumn, $orderDir) {
        $whereClause = "WHERE proyeccion = :proyeccion";
        $searchParam = null;
        
        if (!empty($search)) {
            $whereClause .= " AND (
                CAST(anuo AS CHAR) LIKE :search
                OR CAST(sem AS CHAR) LIKE :search
                OR CAST(oferta AS CHAR) LIKE :search
                OR CAST(demanda AS CHAR) LIKE :search
                OR CAST(diferencia AS CHAR) LIKE :search
            )";
            $searchParam = "%{$search}%";
        }
        
        // Validar columna de ordenamiento
        $allowedColumns = ['anuo', 'sem', 'oferta', 'demanda', 'diferencia'];
        if (!in_array($orderColumn, $allowedColumns)) {
            $orderColumn = 'anuo';
        }
        
        // Validar dirección
        $orderDir = strtoupper($orderDir) === 'DESC' ? 'DESC' : 'ASC';
        
        $sql = "SELECT 
                    anuo,
                    sem,
                    oferta,
                    demanda,
                    diferencia
                FROM fechasemproy 
                {$whereClause}
                ORDER BY {$orderColumn} {$orderDir}
                LIMIT :start, :length";
        
        $stmt = $this->db->prepare($sql);
        $stmt->bindParam(':proyeccion', $proyeccion, PDO::PARAM_STR);
        if ($searchParam !== null) {
            $stmt->bindParam(':search', $searchParam, PDO::PARAM_STR);
        }
        $stmt->bindParam(':start', $start, PDO::PARAM_INT);
        $stmt->bindParam(':length', $length, PDO::PARAM_INT);
        $stmt->execute();
        
        $datos = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $datos[] = $row;
        }
        
        return $datos;
    }

    /**
     * Obtiene datos de calendario desde fechaproy (calendario diario VB6-compatible)
     * Retorna TODAS las fechas de la proyección ordenadas cronológicamente
     */
    public function obtenerFechaProy($proyeccion) {
        $sql = "SELECT 
                    fecha,
                    COALESCE(sem, WEEK(fecha) + 1) as semana,
                    COALESCE(cargas, 1) as cargas
                FROM fechaproy
                WHERE proyeccion = :proyeccion
                ORDER BY fecha ASC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->bindParam(':proyeccion', $proyeccion, PDO::PARAM_STR);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Actualiza cargas de una fecha específica en fechaproy (calendario diario)
     */
    public function actualizarCargas($proyeccion, $fecha, $cargas) {
        // Cambiar UPDATE simple sin subquery para evitar error MySQL 1093
        try {
            // Primero verificar si existe el registro
            $check = $this->db->prepare("SELECT COUNT(*) as cnt FROM fechaproy WHERE proyeccion = :p AND fecha = :f");
            $check->execute([':p' => $proyeccion, ':f' => $fecha]);
            $exists = $check->fetch(PDO::FETCH_ASSOC)['cnt'] > 0;

            if ($exists) {
                // Si existe, actualizar solo el campo cargas
                $stmt = $this->db->prepare("UPDATE fechaproy SET cargas = :c WHERE proyeccion = :p AND fecha = :f");
                $stmt->bindParam(':c', $cargas, PDO::PARAM_INT);
                $stmt->bindParam(':p', $proyeccion, PDO::PARAM_STR);
                $stmt->bindParam(':f', $fecha, PDO::PARAM_STR);
                $stmt->execute();
            } else {
                // Si no existe, calcular semana primero en variable separada
                $semQuery = $this->db->prepare("SELECT COALESCE(FLOOR(DATEDIFF(:f, MIN(fecha)) / 7) + 1, 1) as sem 
                                                 FROM fechaproy WHERE proyeccion = :p");
                $semQuery->execute([':f' => $fecha, ':p' => $proyeccion]);
                $semana = $semQuery->fetch(PDO::FETCH_ASSOC)['sem'] ?? 1;

                // Insertar con la semana ya calculada
                $ins = $this->db->prepare("INSERT INTO fechaproy (proyeccion, fecha, cargas, sem, flag) 
                                            VALUES (:p, :f, :c, :s, 'A')");
                $ins->execute([':p' => $proyeccion, ':f' => $fecha, ':c' => $cargas, ':s' => $semana]);
            }

            return ['fecha' => $fecha, 'cargas' => $cargas];
        } catch (Exception $e) {
            error_log("Error en actualizarCargas: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Importa calendario completo (elimina todo el calendario anterior y lo reemplaza)
     */
    public function importarCalendario($proyeccion, $registros) {
        $this->db->beginTransaction();
        try {
            // 1. Eliminar todo el calendario anterior de esta proyección
            $del = $this->db->prepare("DELETE FROM fechaproy WHERE proyeccion = :p");
            $del->execute([':p' => $proyeccion]);
            
            // 2. Encontrar la fecha mínima para calcular semanas secuenciales
            $fechaMin = null;
            foreach ($registros as $reg) {
                if (!$fechaMin || $reg['fecha'] < $fechaMin) {
                    $fechaMin = $reg['fecha'];
                }
            }
            
            // 3. Insertar todos los nuevos registros con semanas secuenciales
            $ins = $this->db->prepare(
                "INSERT INTO fechaproy (proyeccion, fecha, cargas, sem, flag) 
                 VALUES (:p, :f, :c, :s, 'A')"
            );
            
            $insertados = 0;
            foreach ($registros as $reg) {
                $fecha = $reg['fecha'];
                $cargas = $reg['cargas'];
                
                // Calcular semana secuencial desde la fecha mínima
                $dias = (strtotime($fecha) - strtotime($fechaMin)) / 86400;
                $semana = floor($dias / 7) + 1;
                
                $ins->execute([
                    ':p' => $proyeccion,
                    ':f' => $fecha,
                    ':c' => $cargas,
                    ':s' => $semana
                ]);
                $insertados++;
            }
            
            // Hacer commit
            $this->db->commit();
            
            // NOTA: No recalcular automáticamente. El usuario debe usar el botón "Recalcular".
            // Esto evita demoras innecesarias al importar/editar cargas.
            // $this->recalcularCalendario($proyeccion);
            
            return [
                'eliminados' => 'todo',
                'insertados' => $insertados,
                'recalculado' => false
            ];
        } catch (Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    /**
     * Recalcula el calendario completo basado en secuencia y cargas
     * Replica la lógica del VB6 CmdRecalcular_Click
     * Usa cargas_config para las cargas editadas por el usuario
     */
    public function recalcularCalendario($proyeccion, $fechaDesde = null, $fechaHasta = null) {
        try {
            $this->db->beginTransaction();

            // 1. Obtener secuencia de granjas activas
            // Primero intentar desde ccosproy
            $stmt = $this->db->prepare("SELECT * FROM ccosproy WHERE proyeccion = :proyeccion AND swac='A' ORDER BY secuencia");
            $stmt->bindParam(':proyeccion', $proyeccion, PDO::PARAM_STR);
            $stmt->execute();
            $granjas = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Si ccosproy está vacío, usar datos únicos de ccoscargapollo como secuencia
            if (empty($granjas)) {
                $stmt = $this->db->prepare("
                    SELECT DISTINCT 
                        codigo, nombre, galpon, campana, pollos, 
                        COALESCE(mortalidad, 0) as mortalidad,
                        secuencia, 
                        COALESCE(diasefec, 45) as diasefec
                    FROM ccoscargapollo 
                    WHERE proyeccion = :proyeccion 
                    ORDER BY secuencia
                ");
                $stmt->bindParam(':proyeccion', $proyeccion, PDO::PARAM_STR);
                $stmt->execute();
                $granjas = $stmt->fetchAll(PDO::FETCH_ASSOC);
            }
            
            $totalGranjas = count($granjas);

            if ($totalGranjas === 0) {
                $this->db->rollBack();
                return ['granjas_procesadas' => 0, 'mensaje' => 'No hay granjas activas en la secuencia'];
            }

            // 2. Obtener configuración de cargas desde fechaproy (calendario diario VB6-compatible)
            // Marcar todas las fechas como activas antes de recalcular
            $stmt = $this->db->prepare("UPDATE fechaproy SET flag = 'A' WHERE proyeccion = :proyeccion");
            $stmt->bindParam(':proyeccion', $proyeccion, PDO::PARAM_STR);
            $stmt->execute();
            
            // Obtener fechas ordenadas con sus cargas
            $stmt = $this->db->prepare("SELECT fecha, cargas FROM fechaproy WHERE proyeccion = :proyeccion AND flag = 'A' ORDER BY fecha");
            $stmt->bindParam(':proyeccion', $proyeccion, PDO::PARAM_STR);
            $stmt->execute();
            $cargasConfig = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Si no hay datos en fechaproy, no se puede recalcular
            if (empty($cargasConfig)) {
                $this->db->rollBack();
                return ['granjas_procesadas' => 0, 'fechas_procesadas' => 0, 'mensaje' => 'No hay configuración de fechas/cargas en fechaproy. Debe crear/importar un calendario primero.'];
            }

            // 3. Borrar ccoscargapollo COMPLETO (lógica VB6: siempre reconstruye desde cero)
            $stmt = $this->db->prepare("DELETE FROM ccoscargapollo WHERE proyeccion = :proyeccion");
            $stmt->bindParam(':proyeccion', $proyeccion, PDO::PARAM_STR);
            $stmt->execute();

            // 4. ULTRA-OPTIMIZADO: INSERT BATCH - todos los registros en pocas queries
            $granjaIndex = 0;
            $fechasProcesadas = 0;
            $batchSize = 500; // Insertar 500 registros por query
            $batchValues = [];
            $batchParams = [];
            
            foreach ($cargasConfig as $configFecha) {
                $fecha = $configFecha['fecha'];
                $cargas = max(1, intval($configFecha['cargas']));
                
                // Generar registros para esta fecha
                for ($i = 0; $i < $cargas; $i++) {
                    // Asignacion ciclica: cuando llega al final de granjas, vuelve al inicio.
                    $granja = $granjas[$granjaIndex % $totalGranjas];

                    // Agregar a batch con placeholders
                    $batchValues[] = "(?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                    $batchParams[] = $proyeccion;
                    $batchParams[] = $fecha;
                    $batchParams[] = $granja['codigo'];
                    $batchParams[] = $granja['nombre'];
                    $batchParams[] = $granja['campana'];
                    $batchParams[] = $granja['galpon'];
                    $batchParams[] = $granja['pollos'];
                    $batchParams[] = $granja['mortalidad'];
                    $batchParams[] = $granja['secuencia'];
                    $batchParams[] = $granja['diasefec'];

                    // Avanza por cada carga para mantener la secuencia global entre fechas.
                    $granjaIndex++;
                    
                    // Si alcanzamos el tamaño del batch, ejecutar INSERT
                    if (count($batchValues) >= $batchSize) {
                        $insertSql = "INSERT INTO ccoscargapollo(proyeccion, fecaqp, codigo, nombre, campana, galpon, pollos, mortalidad, secuencia, diasefec) 
                                      VALUES " . implode(', ', $batchValues);
                        $stmt = $this->db->prepare($insertSql);
                        $stmt->execute($batchParams);
                        
                        // Resetear batch
                        $batchValues = [];
                        $batchParams = [];
                    }
                }
                
                $fechasProcesadas++;
            }
            
            // Insertar registros restantes del último batch
            if (count($batchValues) > 0) {
                $insertSql = "INSERT INTO ccoscargapollo(proyeccion, fecaqp, codigo, nombre, campana, galpon, pollos, mortalidad, secuencia, diasefec) 
                              VALUES " . implode(', ', $batchValues);
                $stmt = $this->db->prepare($insertSql);
                $stmt->execute($batchParams);
            }
            
            // Actualizar flags de fechaproy en una sola query (en lugar de loop)
            $stmt = $this->db->prepare("UPDATE fechaproy SET flag = 'I' WHERE proyeccion = :proyeccion");
            $stmt->bindParam(':proyeccion', $proyeccion, PDO::PARAM_STR);
            $stmt->execute();

            // 5. OPTIMIZADO: Actualizar campos calculados con subqueries inline
            $proy = $this->db->quote($proyeccion);
            
            // semana secuencial desde la primera fecha del calendario
            $this->db->exec("SET @min_fecha := (SELECT MIN(fecha) FROM fechaproy WHERE proyeccion = {$proy})");

            // PASO 1: Campos básicos de fechas y semanas (rápido)
            $this->db->exec("UPDATE ccoscargapollo SET 
                semana = IF(@min_fecha IS NULL, 1, FLOOR(DATEDIFF(fecaqp, @min_fecha) / 7) + 1),
                feclima = DATE_SUB(fecaqp, INTERVAL 1 DAY),
                fecliqui = DATE_ADD(fecaqp, INTERVAL (IFNULL(diasefec, 45) - 1) DAY),
                fecdespo = DATE_ADD(fecaqp, INTERVAL 36 DAY),
                pollosliqui = pollos * (1 - IFNULL(mortalidad, 0))
                WHERE proyeccion = {$proy}");

            // PASO 2: semliqui secuencial desde la misma fecha base
            $this->db->exec("UPDATE ccoscargapollo SET semliqui = IF(@min_fecha IS NULL, 1, FLOOR(DATEDIFF(fecliqui, @min_fecha) / 7) + 1) WHERE proyeccion = {$proy}");

            // Ajustar semliqui si cae en domingo
            $this->db->exec("UPDATE ccoscargapollo SET semliqui = semliqui - 1 WHERE DATE_FORMAT(fecliqui, '%W') = 'Sunday' AND proyeccion = {$proy}");

            // PASO 3: OPTIMIZADO - 1 SOLO UPDATE para pollosdia, pollossem, viajessem
            // Usa subqueries inline que se evalúan una sola vez
            $this->db->exec("UPDATE ccoscargapollo c
                LEFT JOIN (
                    SELECT fecaqp, SUM(pollos) as pollosdia
                    FROM ccoscargapollo
                    WHERE proyeccion = {$proy}
                    GROUP BY fecaqp
                ) d ON c.fecaqp = d.fecaqp
                LEFT JOIN (
                    SELECT 
                        WEEK(fecaqp, 1) + 1 as semana,
                        YEAR(fecaqp) as anio,
                        SUM(pollos) as pollossem,
                        COUNT(*) as viajessem_carga
                    FROM ccoscargapollo
                    WHERE proyeccion = {$proy}
                    GROUP BY YEAR(fecaqp), WEEK(fecaqp, 1)
                ) s ON WEEK(c.fecaqp, 1) + 1 = s.semana AND YEAR(c.fecaqp) = s.anio
                SET 
                    c.pollosdia = COALESCE(d.pollosdia, 0),
                    c.pollossem = COALESCE(s.pollossem, 0),
                    c.viajessem = COALESCE(s.viajessem_carga, 0)
                WHERE c.proyeccion = {$proy}");

            // Sincronizar cargas diarias en fechaproy segun el resultado recalculado
            $this->db->exec("UPDATE fechaproy fp 
                JOIN (
                    SELECT MIN(fecha) AS min_fecha FROM fechaproy WHERE proyeccion = {$proy}
                ) mf
                LEFT JOIN (
                    SELECT fecaqp AS fecha, COUNT(*) AS cargas
                    FROM ccoscargapollo WHERE proyeccion = {$proy} GROUP BY fecaqp
                ) AS cc ON cc.fecha = fp.fecha
                SET fp.cargas = COALESCE(cc.cargas, 0),
                    fp.sem = IF(mf.min_fecha IS NULL, 1, FLOOR(DATEDIFF(fp.fecha, mf.min_fecha) / 7) + 1)
                WHERE fp.proyeccion = {$proy}");

            // 6. OPTIMIZADO: Actualizar resumen oferta/demanda (fechasemproy) en batch
            $stmt = $this->db->prepare("SELECT YEAR(fecliqui) AS anuo, semliqui AS sem, SUM(pollosliqui) AS pollos 
                                        FROM ccoscargapollo WHERE proyeccion = :proyeccion GROUP BY YEAR(fecliqui), semliqui");
            $stmt->bindParam(':proyeccion', $proyeccion, PDO::PARAM_STR);
            $stmt->execute();
            $resumenData = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Batch INSERT para fechasemproy (evitar loop de queries individuales)
            if (count($resumenData) > 0) {
                $upsertValues = [];
                $upsertParams = [];
                
                foreach ($resumenData as $row) {
                    if ($row['anuo'] === null || $row['sem'] === null) continue;
                    
                    $upsertValues[] = "(?, ?, ?, ?, 0, 0)";
                    $upsertParams[] = $proyeccion;
                    $upsertParams[] = $row['anuo'];
                    $upsertParams[] = $row['sem'];
                    $upsertParams[] = $row['pollos'];
                }
                
                if (count($upsertValues) > 0) {
                    $upsertSql = "INSERT INTO fechasemproy(proyeccion, anuo, sem, oferta, demanda, diferencia) 
                                  VALUES " . implode(', ', $upsertValues) . "
                                  ON DUPLICATE KEY UPDATE oferta = VALUES(oferta)";
                    $upsertStmt = $this->db->prepare($upsertSql);
                    $upsertStmt->execute($upsertParams);
                }
            }

            // Calcular diferencia
            $this->db->exec("UPDATE fechasemproy SET diferencia = oferta - demanda WHERE proyeccion = {$proy}");

            $this->db->commit();

            return [
                'granjas_procesadas' => $granjaIndex,
                'fechas_procesadas' => $fechasProcesadas,
                'mensaje' => 'Calendario recalculado exitosamente'
            ];

        } catch (Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    /**
     * Obtiene los datos completos de ccoscargapollo para la tabla detalle
     * Retorna en formato esperado por el frontend
     */
    public function obtenerDatosDetalleTabla($proyeccion) {
        $sql = "SELECT 
                    CONCAT(YEAR(c.fecaqp), '-', LPAD(c.semana, 2, '0')) AS SemCard,
                    c.viajessem AS ViajesSem,
                    c.pollossem AS PollosSem,
                    DATE_FORMAT(c.feclima, '%Y-%m-%d') AS FecSalidaPlanta,
                    DATE_FORMAT(c.fecaqp, '%Y-%m-%d') AS FecRecepcionGr,
                    c.nombre AS Granja,
                    c.galpon AS Galpon,
                    c.campana AS Campana,
                    c.pollos AS Pollos,
                    DATE_FORMAT(c.fecliqui, '%Y-%m-%d') AS FecLiqui,
                    c.secuencia
                FROM ccoscargapollo c
                WHERE c.proyeccion = :proyeccion
                ORDER BY c.fecaqp ASC, c.secuencia ASC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->bindParam(':proyeccion', $proyeccion, PDO::PARAM_STR);
        $stmt->execute();
        
        $datos = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $datos[] = $row;
        }
        
        return $datos;
    }

    /**
     * Obtiene datos agrupados por semana para PDF
     * Con información completa para mostrar en reporte PDF con totales
     */
    public function obtenerDatosPorSemanaParaPDF($proyeccion) {
        $sql = "SELECT 
                    WEEK(c.fecaqp, 3) AS semana,
                    YEAR(c.fecaqp) AS anio,
                    c.viajessem AS pollosxsem,
                    c.pollossem,
                    c.feclima AS diasalida,
                    c.fecaqp AS diallegada,
                    c.fecliqui AS fecliquidacion,
                    c.codigo,
                    c.nombre AS granja,
                    c.galpon,
                    c.pollos,
                    c.campana,
                    c.secuencia
                FROM ccoscargapollo c
                WHERE c.proyeccion = :proyeccion
                ORDER BY c.fecaqp ASC, c.secuencia ASC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->bindParam(':proyeccion', $proyeccion, PDO::PARAM_STR);
        $stmt->execute();
        
        $datos = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $datos[] = $row;
        }
        
        return $datos;
    }
}

