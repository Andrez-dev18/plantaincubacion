<?php

/**
 * Repositorio para Secuencia Base Proyección
 * Gestiona acceso a datos de ccosbase, ccosproy, fechasemproy
 * Completamente independiente del módulo de reportes
 */
class SecuenciaBaseProyeccionRepository {
    private $db;

    public function __construct($db) {
        $this->db = $db;
    }

    /**
     * Obtiene lista de proyecciones disponibles
     */
    public function obtenerProyecciones() {
        try {
            $sql = "SELECT nombre, indicador FROM cpproy ORDER BY nombre";
            $stmt = $this->db->query($sql);
            $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            error_log("Proyecciones obtenidas: " . count($result));
            
            return $result;
        } catch (PDOException $e) {
            error_log("Error en obtenerProyecciones: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Cuenta total de registros en ccosbase para una proyección
     */
    public function contarDatosBase($proyeccion) {
        try {
            $sql = "SELECT COUNT(*) as total FROM ccosbase WHERE proyeccion = :proyeccion";
            $stmt = $this->db->prepare($sql);
            $stmt->bindParam(':proyeccion', $proyeccion);
            $stmt->execute();
            
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return $result['total'] ?? 0;
        } catch (PDOException $e) {
            error_log("Error en contarDatosBase: " . $e->getMessage());
            return 0;
        }
    }

    /**
     * Cuenta total de registros en ccosproy para una proyección
     */
    public function contarDatosProyeccion($proyeccion) {
        try {
            $sql = "SELECT COUNT(*) as total FROM ccosproy WHERE proyeccion = :proyeccion";
            $stmt = $this->db->prepare($sql);
            $stmt->bindParam(':proyeccion', $proyeccion);
            $stmt->execute();
            
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return $result['total'] ?? 0;
        } catch (PDOException $e) {
            error_log("Error en contarDatosProyeccion: " . $e->getMessage());
            return 0;
        }
    }

    /**
     * Obtiene todos los datos de ccosbase para una proyección
     */
    public function obtenerDatosBase($proyeccion, $offset = 0, $limit = 200) {
        try {
            $sql = "SELECT * FROM ccosbase WHERE proyeccion = :proyeccion ORDER BY secuencia LIMIT :offset, :limit";
            $stmt = $this->db->prepare($sql);
            $stmt->bindParam(':proyeccion', $proyeccion);
            $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
            $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
            $stmt->execute();
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error en obtenerDatosBase: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Obtiene todos los datos de ccosproy para una proyección (secuencia generada)
     */
    public function obtenerDatosProyeccion($proyeccion, $offset = 0, $limit = 200) {
        try {
            $sql = "SELECT * FROM ccosproy WHERE proyeccion = :proyeccion ORDER BY secuencia LIMIT :offset, :limit";
            $stmt = $this->db->prepare($sql);
            $stmt->bindParam(':proyeccion', $proyeccion);
            $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
            $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
            $stmt->execute();
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error en obtenerDatosProyeccion: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Copia secuencia de una proyección a otra
     */
    public function copiarSecuencia($proyeccionOrigen, $proyeccionDestino) {
        try {
            $this->db->beginTransaction();
            
            // 1. Eliminar datos existentes en la proyección destino
            $sqlDelete = "DELETE FROM ccosbase WHERE proyeccion = :destino";
            $stmtDelete = $this->db->prepare($sqlDelete);
            $stmtDelete->bindParam(':destino', $proyeccionDestino);
            $stmtDelete->execute();
            $filasEliminadas = $stmtDelete->rowCount();
            
            // 2. Obtener todas las columnas de la tabla excepto 'proyeccion'
            $sqlColumns = "SHOW COLUMNS FROM ccosbase";
            $stmtColumns = $this->db->query($sqlColumns);
            $columns = $stmtColumns->fetchAll(PDO::FETCH_COLUMN);
            
            // Filtrar columnas (excluir 'proyeccion' porque se reemplazará)
            $columnList = array_filter($columns, function($col) {
                return $col !== 'proyeccion';
            });
            
            $columnStr = implode(', ', $columnList);
            
            // 3. Copiar datos de la proyección origen a la destino
            $sqlInsert = "INSERT INTO ccosbase (proyeccion, $columnStr)
                         SELECT :destino, $columnStr
                         FROM ccosbase 
                         WHERE proyeccion = :origen";
            
            $stmtInsert = $this->db->prepare($sqlInsert);
            $stmtInsert->bindParam(':destino', $proyeccionDestino);
            $stmtInsert->bindParam(':origen', $proyeccionOrigen);
            $stmtInsert->execute();
            $filasCopiadas = $stmtInsert->rowCount();
            
            $this->db->commit();
            
            error_log("Secuencia copiada: $filasCopiadas registros de '$proyeccionOrigen' a '$proyeccionDestino'");
            
            return [
                'filas_eliminadas' => $filasEliminadas,
                'filas_copiadas' => $filasCopiadas
            ];
        } catch (PDOException $e) {
            $this->db->rollBack();
            error_log("Error en copiarSecuencia: " . $e->getMessage());
            throw new Exception('Error al copiar secuencia: ' . $e->getMessage());
        }
    }

    /**
     * Crea la secuencia en ccosproy basado en ccosbase (ETAPA 1)
     */
    public function crearSecuencia($proyeccion, $hastaCiclo = 13) {
        try {
            $this->db->beginTransaction();
            
            // 0. Asegurar que la proyección existe en cpproy
            $this->asegurarProyeccionEnCpproy($proyeccion);
            
            // 1. Eliminar datos existentes de ccosproy
            $sqlDelete = "DELETE FROM ccosproy WHERE proyeccion = :proyeccion";
            $stmtDelete = $this->db->prepare($sqlDelete);
            $stmtDelete->bindParam(':proyeccion', $proyeccion, PDO::PARAM_STR);
            $stmtDelete->execute();
            
            // 2. OPTIMIZADO: Insertar secuencia sin variables de sesión, usando subquery indexada
            // Generamos números de ciclo dinámicamente
            $ciclosUnion = [];
            for ($i = 1; $i <= $hastaCiclo; $i++) {
                $ciclosUnion[] = "SELECT $i AS num";
            }
            $ciclosSQL = implode(' UNION ALL ', $ciclosUnion);
            
            $sqlInsert = "INSERT INTO ccosproy 
                          (proyeccion, codigo, nombre, campana, galpon, swac, diasefec, mortalidad, 
                           diasdesc, pollos, area, densidad, secuencia, fecini)
                          SELECT 
                              :proyeccion as proyeccion,
                              b.codigo,
                              b.nombre,
                              LPAD(CAST(b.campana AS UNSIGNED) + (ciclo.num - 1), 3, '0') AS campana,
                              b.galpon,
                              b.swac,
                              b.diasefec,
                              b.mortalidad,
                              b.diasdesc,
                              b.pollos,
                              b.area,
                              b.densidad,
                              (1000 + ((ciclo.num - 1) * (SELECT COUNT(*) FROM ccosbase WHERE proyeccion = :proyeccion2) + b.secuencia)) AS secuencia,
                              b.fecini
                          FROM ccosbase b
                          CROSS JOIN ($ciclosSQL) AS ciclo
                          WHERE b.proyeccion = :proyeccion3
                          ORDER BY ciclo.num, b.secuencia";
            
            $stmtInsert = $this->db->prepare($sqlInsert);
            $stmtInsert->bindParam(':proyeccion', $proyeccion, PDO::PARAM_STR);
            $stmtInsert->bindParam(':proyeccion2', $proyeccion, PDO::PARAM_STR);
            $stmtInsert->bindParam(':proyeccion3', $proyeccion, PDO::PARAM_STR);
            $stmtInsert->execute();
            
            $registrosCreados = $stmtInsert->rowCount();
            
            // NOTA: El calendario y la carga de pollos se generan en el PASO 2
            // usando el botón "Crear Calendario" (no aquí)
            
            $this->db->commit();
            return $registrosCreados;
            
        } catch (Exception $e) {
            $this->db->rollBack();
            error_log("Error en crearSecuencia: " . $e->getMessage());
            throw new Exception('Error al crear secuencia: ' . $e->getMessage());
        }
    }

    /**
     * Genera registros en ccoscargapollo basándose en ccosproy
     * OPTIMIZADO: Usa una sola consulta SQL compleja en lugar de loops
     * MODIFICADO: Siempre inserta TODAS las fechas del calendario, incluso sin granjas
     */
    private function generarCargaPolloPorDia($proyeccion) {
        try {
            // 1. Eliminar registros existentes
            $sqlDelete = "DELETE FROM ccoscargapollo WHERE proyeccion = :proyeccion";
            $stmtDelete = $this->db->prepare($sqlDelete);
            $stmtDelete->bindParam(':proyeccion', $proyeccion, PDO::PARAM_STR);
            $stmtDelete->execute();
            
            // 2. ULTRA-OPTIMIZADO: Usando variables de sesión para ROW_NUMBER
            $sqlInsert = "
                INSERT INTO ccoscargapollo 
                (proyeccion, fecaqp, codigo, nombre, campana, galpon, pollos, mortalidad, 
                 secuencia, diasefec, semana, feclima, fecliqui, fecdespo, pollosliqui)
                SELECT 
                    fp.proyeccion,
                    fp.fecha as fecaqp,
                    COALESCE(cp.codigo, '') as codigo,
                    COALESCE(g.tnomcen, cp.nombre, 'SIN ASIGNAR') as nombre,
                    COALESCE(cp.campana, '') as campana,
                    COALESCE(cp.galpon, '') as galpon,
                    COALESCE(cp.pollos, 0) as pollos,
                    COALESCE(cp.mortalidad, 0) as mortalidad,
                    COALESCE(cp.secuencia, '') as secuencia,
                    COALESCE(cp.diasefec, 45) as diasefec,
                    WEEK(fp.fecha, 1) as semana,
                    DATE_ADD(fp.fecha, INTERVAL -1 DAY) as feclima,
                    DATE_ADD(fp.fecha, INTERVAL COALESCE(cp.diasefec, 45) - 1 DAY) as fecliqui,
                    DATE_ADD(fp.fecha, INTERVAL 36 DAY) as fecdespo,
                    COALESCE(cp.pollos, 0) * (1 - COALESCE(cp.mortalidad, 0)) as pollosliqui
                FROM (
                    SELECT 
                        fp2.fecha,
                        fp2.proyeccion,
                        @frow := @frow + 1 as rn
                    FROM fechaproy fp2,
                         (SELECT @frow := 0) AS init
                    WHERE fp2.proyeccion = :proyeccion
                    ORDER BY fp2.fecha
                ) fp
                LEFT JOIN (
                    SELECT 
                        cp2.*,
                        @crow := @crow + 1 as rn
                    FROM ccosproy cp2,
                         (SELECT @crow := 0) AS init
                    WHERE cp2.proyeccion = :proyeccion2 AND cp2.swac = 'A'
                    ORDER BY cp2.secuencia
                ) cp ON cp.rn = fp.rn
                LEFT JOIN (
                    SELECT tcencos, MIN(tnomcen) as tnomcen
                    FROM regcencosgalpones
                    WHERE tnomcen IS NOT NULL AND tnomcen != ''
                    GROUP BY tcencos
                ) g ON cp.codigo = g.tcencos
            ";
            
            $stmtInsert = $this->db->prepare($sqlInsert);
            $stmtInsert->bindParam(':proyeccion', $proyeccion, PDO::PARAM_STR);
            $stmtInsert->bindParam(':proyeccion2', $proyeccion, PDO::PARAM_STR);
            $stmtInsert->execute();
            
            $registrosInsertados = $stmtInsert->rowCount();
            
            // 3. OPTIMIZADO: Actualizar campos calculados en 1 sola query multi-tabla
            $this->actualizarCamposCalculadosCargaPolloOptimizado($proyeccion);
            
            error_log("✅ ULTRA-OPTIMIZADO: $registrosInsertados registros en ccoscargapollo");
            
            return $registrosInsertados;
            
        } catch (Exception $e) {
            error_log("Error en generarCargaPolloPorDia: " . $e->getMessage());
            throw new Exception('Error al generar carga pollo por día: ' . $e->getMessage());
        }
    }

    /**
     * Actualiza campos calculados de ccoscargapollo de forma optimizada
     * Sin tablas temporales - usa subqueries en JOINs (evaluadas 1 sola vez)
     */
    private function actualizarCamposCalculadosCargaPolloOptimizado($proyeccion) {
        try {
            // 1 solo UPDATE con subqueries inline en los JOINs
            // Las subqueries se evalúan una sola vez, no por cada fila
            $sql = "UPDATE ccoscargapollo c
                    LEFT JOIN (
                        SELECT fecaqp, SUM(pollos) as pollosdia
                        FROM ccoscargapollo
                        WHERE proyeccion = :proyeccion_dia
                        GROUP BY fecaqp
                    ) d ON c.fecaqp = d.fecaqp
                    LEFT JOIN (
                        SELECT 
                            WEEK(fecaqp, 1) as semana,
                            YEAR(fecaqp) as anio,
                            SUM(pollos) as pollossem
                        FROM ccoscargapollo
                        WHERE proyeccion = :proyeccion_sem
                        GROUP BY YEAR(fecaqp), WEEK(fecaqp, 1)
                    ) s ON WEEK(c.fecaqp, 1) = s.semana AND YEAR(c.fecaqp) = s.anio
                    LEFT JOIN (
                        SELECT 
                            WEEK(fecha, 1) as semana,
                            YEAR(fecha) as anio,
                            SUM(cargas) as viajessem
                        FROM fechaproy
                        WHERE proyeccion = :proyeccion_viajes
                        GROUP BY YEAR(fecha), WEEK(fecha, 1)
                    ) v ON WEEK(c.fecaqp, 1) = v.semana AND YEAR(c.fecaqp) = v.anio
                    SET 
                        c.pollosdia = COALESCE(d.pollosdia, 0),
                        c.pollossem = COALESCE(s.pollossem, 0),
                        c.viajessem = COALESCE(v.viajessem, 0)
                    WHERE c.proyeccion = :proyeccion";
            
            $stmt = $this->db->prepare($sql);
            $stmt->bindParam(':proyeccion', $proyeccion);
            $stmt->bindParam(':proyeccion_dia', $proyeccion);
            $stmt->bindParam(':proyeccion_sem', $proyeccion);
            $stmt->bindParam(':proyeccion_viajes', $proyeccion);
            $stmt->execute();
            
            error_log("✅ Campos calculados actualizados (1 UPDATE con subqueries - sin tablas temporales)");
            
        } catch (PDOException $e) {
            error_log("Error en actualizarCamposCalculadosCargaPolloOptimizado: " . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Actualiza campos calculados agregados en ccoscargapollo (OPTIMIZADO)
     * Solo actualiza los campos que requieren agregaciones, el resto ya se calculó en INSERT
     */
    private function actualizarCamposCalculadosCargaPollo($proyeccion) {
        try {
            // UPDATE viajessem (cargas por semana)
            $sql1 = "UPDATE ccoscargapollo c
                     INNER JOIN (
                         SELECT WEEK(fecha) + 1 as wek, YEAR(fecha) as yea, SUM(cargas) as car
                         FROM fechaproy
                         WHERE proyeccion = :proyeccion
                         GROUP BY YEAR(fecha), WEEK(fecha)
                     ) frm ON frm.wek = c.semana AND frm.yea = YEAR(c.fecaqp)
                     SET c.viajessem = frm.car
                     WHERE c.proyeccion = :proyeccion";
            $stmt1 = $this->db->prepare($sql1);
            $stmt1->bindParam(':proyeccion', $proyeccion);
            $stmt1->execute();
            
            // UPDATE pollossem (pollos por semana)
            $sql2 = "UPDATE ccoscargapollo c
                     INNER JOIN (
                         SELECT WEEK(fecaqp) + 1 as wek, YEAR(fecaqp) as yea, SUM(pollos) as pol
                         FROM ccoscargapollo
                         WHERE proyeccion = :proyeccion
                         GROUP BY YEAR(fecaqp), WEEK(fecaqp)
                     ) frm ON frm.wek = c.semana AND frm.yea = YEAR(c.fecaqp)
                     SET c.pollossem = frm.pol
                     WHERE c.proyeccion = :proyeccion";
            $stmt2 = $this->db->prepare($sql2);
            $stmt2->bindParam(':proyeccion', $proyeccion);
            $stmt2->execute();
            
            // UPDATE pollosdia (pollos por día)
            $sql3 = "UPDATE ccoscargapollo c
                     INNER JOIN (
                         SELECT fecaqp as fec, SUM(pollos) as pol
                         FROM ccoscargapollo
                         WHERE proyeccion = :proyeccion
                         GROUP BY fecaqp
                     ) frm ON frm.fec = c.fecaqp
                     SET c.pollosdia = frm.pol
                     WHERE proyeccion = :proyeccion";
            $stmt3 = $this->db->prepare($sql3);
            $stmt3->bindParam(':proyeccion', $proyeccion);
            $stmt3->execute();
            
        } catch (PDOException $e) {
            error_log("Error en actualizarCamposCalculadosCargaPollo: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Crea el calendario en fechaproy basado en ccosproy (ETAPA 2)
     * MÉTODO PÚBLICO: Maneja transacciones
     */
    public function crearCalendario($proyeccion) {
        try {
            $this->db->beginTransaction();
            
            // Asegurar que la proyección existe en cpproy
            $this->asegurarProyeccionEnCpproy($proyeccion);
            
            // Ejecutar la lógica interna
            $registros = $this->generarCalendarioInterno($proyeccion);
            
            // Generar automáticamente registros en ccoscargapollo usando el método optimizado
            // (igual que en crearSecuencia para mantener consistencia y velocidad)
            $this->generarCargaPolloPorDia($proyeccion);
            
            $this->db->commit();
            return $registros;
            
        } catch (Exception $e) {
            $this->db->rollBack();
            error_log("Error en crearCalendario: " . $e->getMessage());
            throw new Exception('Error al crear calendario: ' . $e->getMessage());
        }
    }
    
    /**
     * Genera el calendario en fechaproy (lógica interna, sin transacciones)
     * OPTIMIZADO: Genera fechas e inserta semanas en lotes para máxima velocidad
     */
    private function generarCalendarioInterno($proyeccion) {
        try {
            // 1. Eliminar datos existentes
            $sqlDeleteSem = "DELETE FROM fechasemproy WHERE proyeccion = :proyeccion";
            $stmtDeleteSem = $this->db->prepare($sqlDeleteSem);
            $stmtDeleteSem->execute([':proyeccion' => $proyeccion]);
            
            $sqlDeleteProy = "DELETE FROM fechaproy WHERE proyeccion = :proyeccion";
            $stmtDeleteProy = $this->db->prepare($sqlDeleteProy);
            $stmtDeleteProy->execute([':proyeccion' => $proyeccion]);
            
            // 2. Obtener cantidad y fecha inicial
            $sqlInfo = "SELECT COUNT(*) as conteo, MAX(fecini) as fecha_inicio 
                        FROM ccosproy 
                        WHERE proyeccion = :proyeccion";
            $stmtInfo = $this->db->prepare($sqlInfo);
            $stmtInfo->execute([':proyeccion' => $proyeccion]);
            $info = $stmtInfo->fetch(PDO::FETCH_ASSOC);
            
            $conteo = (int)$info['conteo'];
            $fechaInicio = $info['fecha_inicio'];
            
            if ($conteo == 0 || !$fechaInicio) {
                throw new Exception('No hay registros en ccosproy para generar calendario');
            }
            
            error_log("📅 Generando calendario: $conteo días secuenciales desde $fechaInicio");
            
            // 3. ULTRA-OPTIMIZADO: Usar tabla temporal para generar números
            // Evita UNION ALL masivos - mucho más rápido
            
            // Crear tabla temporal con números
            $this->db->exec("DROP TEMPORARY TABLE IF EXISTS temp_numbers");
            $this->db->exec("
                CREATE TEMPORARY TABLE temp_numbers (
                    num INT PRIMARY KEY
                )
            ");
            
            // Insertar números en lotes para evitar query muy grande
            $loteTemporal = 5000;
            for ($i = 0; $i < $conteo; $i += $loteTemporal) {
                $valores = [];
                $limite = min($i + $loteTemporal, $conteo);
                for ($j = $i; $j < $limite; $j++) {
                    $valores[] = "($j)";
                }
                $this->db->exec("INSERT INTO temp_numbers (num) VALUES " . implode(',', $valores));
            }
            
            // Insertar fechas usando la tabla temporal
            $sqlInsert = "
                INSERT INTO fechaproy (proyeccion, fecha, cargas, flag, sem)
                SELECT 
                    :proyeccion as proyeccion,
                    DATE_ADD(:fecha_inicio, INTERVAL n.num DAY) as fecha,
                    1 as cargas,
                    'A' as flag,
                    WEEK(DATE_ADD(:fecha_inicio, INTERVAL n.num DAY), 1) as sem
                FROM temp_numbers n
                ORDER BY n.num
            ";
            
            $stmtInsert = $this->db->prepare($sqlInsert);
            $stmtInsert->execute([
                ':proyeccion' => $proyeccion,
                ':fecha_inicio' => $fechaInicio
            ]);
            
            $registrosInsertados = $stmtInsert->rowCount();
            
            // Limpiar tabla temporal
            $this->db->exec("DROP TEMPORARY TABLE temp_numbers");
            
            error_log("  ✓ Insertados: $registrosInsertados fechas (tabla temporal)");
            
            // 4. Pre-poblar fechasemproy con todas las combinaciones únicas de año/semana
            $sqlInsertSemanas = "
                INSERT IGNORE INTO fechasemproy (proyeccion, anuo, sem, oferta, demanda, diferencia)
                SELECT 
                    proyeccion,
                    YEAR(fecha) as anuo,
                    sem,
                    0 as oferta,
                    0 as demanda,
                    0 as diferencia
                FROM fechaproy
                WHERE proyeccion = :proyeccion
                GROUP BY proyeccion, YEAR(fecha), sem
            ";
            
            $stmtInsertSemanas = $this->db->prepare($sqlInsertSemanas);
            $stmtInsertSemanas->execute([':proyeccion' => $proyeccion]);
            
            $semanasCreadas = $stmtInsertSemanas->rowCount();
            error_log("  ✓ Semanas únicas: $semanasCreadas");
            
            error_log("✅ Calendario generado: $registrosInsertados fechas");
            
            return $registrosInsertados;
            
        } catch (Exception $e) {
            // No hacer rollBack aquí porque este método puede ser llamado dentro de una transacción existente
            error_log("Error en generarCalendarioInterno: " . $e->getMessage());
            throw new Exception('Error al generar calendario: ' . $e->getMessage());
        }
    }

    /**
     * Guarda un registro en ccosbase
     */
    public function guardarBase($datos) {
        try {
            // Asegurar que la proyección existe en cpproy
            if (isset($datos['proyeccion'])) {
                $this->asegurarProyeccionEnCpproy($datos['proyeccion']);
            }
            
            // Formatear campaña con 3 dígitos si es numérica
            if (isset($datos['campana']) && is_numeric($datos['campana'])) {
                $datos['campana'] = str_pad($datos['campana'], 3, '0', STR_PAD_LEFT);
            }
            
            if (isset($datos['id']) && $datos['id']) {
                // UPDATE
                $sql = "UPDATE ccosbase SET ";
                $updates = [];
                $params = [];
                
                foreach ($datos as $key => $value) {
                    if ($key !== 'id' && $key !== 'proyeccion') {
                        $updates[] = "$key = :$key";
                        $params[$key] = $value;
                    }
                }
                
                $sql .= implode(', ', $updates) . " WHERE id = :id AND proyeccion = :proyeccion";
                $params['id'] = $datos['id'];
                $params['proyeccion'] = $datos['proyeccion'];
                
                $stmt = $this->db->prepare($sql);
                foreach ($params as $key => $value) {
                    $stmt->bindValue(":$key", $value);
                }
                $stmt->execute();
            } else {
                // INSERT
                $campos = array_keys($datos);
                $placeholders = array_map(function($k) { return ":$k"; }, $campos);
                
                $sql = "INSERT INTO ccosbase (" . implode(', ', $campos) . ") VALUES (" . implode(', ', $placeholders) . ")";
                
                $stmt = $this->db->prepare($sql);
                foreach ($datos as $key => $value) {
                    $stmt->bindValue(":$key", $value);
                }
                $stmt->execute();
            }
            
            return ['exito' => true];
        } catch (PDOException $e) {
            error_log("Error en guardarBase: " . $e->getMessage());
            throw new Exception('Error al guardar registro: ' . $e->getMessage());
        }
    }

    /**
     * Elimina un registro de ccosbase
     */
    public function eliminarBase($proyeccion, $secuencia) {
        try {
            $this->db->beginTransaction();

            $sql = "DELETE FROM ccosbase WHERE proyeccion = :proyeccion AND secuencia = :secuencia";
            $stmt = $this->db->prepare($sql);
            $stmt->bindParam(':proyeccion', $proyeccion);
            $stmt->bindParam(':secuencia', $secuencia);
            $stmt->execute();

            $eliminados = $stmt->rowCount();

            if ($eliminados > 0) {
                $sqlReordenar = "UPDATE ccosbase 
                                SET secuencia = secuencia - 1 
                                WHERE proyeccion = :proyeccion AND secuencia > :secuencia";
                $stmtReordenar = $this->db->prepare($sqlReordenar);
                $stmtReordenar->bindParam(':proyeccion', $proyeccion);
                $stmtReordenar->bindParam(':secuencia', $secuencia);
                $stmtReordenar->execute();
            }

            $this->db->commit();
            return ['eliminados' => $eliminados];
        } catch (PDOException $e) {
            $this->db->rollBack();
            error_log("Error en eliminarBase: " . $e->getMessage());
            throw new Exception('Error al eliminar registro: ' . $e->getMessage());
        }
    }

    /**
     * Mueve un registro de ccosbase arriba o abajo
     */
    public function moverBase($proyeccion, $secuencia, $direccion) {
        try {
            $this->db->beginTransaction();

            // Verificar que el registro exista en la proyeccion
            $sqlGet = "SELECT secuencia FROM ccosbase WHERE proyeccion = :proyeccion AND secuencia = :secuencia";
            $stmtGet = $this->db->prepare($sqlGet);
            $stmtGet->bindParam(':proyeccion', $proyeccion);
            $stmtGet->bindParam(':secuencia', $secuencia);
            $stmtGet->execute();
            $row = $stmtGet->fetch(PDO::FETCH_ASSOC);

            if (!$row) {
                throw new Exception('Registro no encontrado');
            }

            $secuenciaActual = $row['secuencia'];
            $nuevaSecuencia = $direccion === 'arriba' ? $secuenciaActual - 1 : $secuenciaActual + 1;

            // Intercambiar secuencias
            $sql = "UPDATE ccosbase SET secuencia = :temp WHERE proyeccion = :proyeccion AND secuencia = :secuencia";
            $stmt = $this->db->prepare($sql);
            $stmt->bindValue(':temp', 999999, PDO::PARAM_INT);
            $stmt->bindParam(':proyeccion', $proyeccion);
            $stmt->bindParam(':secuencia', $secuenciaActual);
            $stmt->execute();

            // Mover el otro registro
            $sql2 = "UPDATE ccosbase SET secuencia = :secuenciaActual WHERE proyeccion = :proyeccion AND secuencia = :nuevaSecuencia";
            $stmt2 = $this->db->prepare($sql2);
            $stmt2->bindParam(':secuenciaActual', $secuenciaActual);
            $stmt2->bindParam(':proyeccion', $proyeccion);
            $stmt2->bindParam(':nuevaSecuencia', $nuevaSecuencia, PDO::PARAM_INT);
            $stmt2->execute();

            // Actualizar el registro original con la nueva secuencia
            $sql3 = "UPDATE ccosbase SET secuencia = :nuevaSecuencia WHERE proyeccion = :proyeccion AND secuencia = :temp";
            $stmt3 = $this->db->prepare($sql3);
            $stmt3->bindParam(':nuevaSecuencia', $nuevaSecuencia, PDO::PARAM_INT);
            $stmt3->bindParam(':proyeccion', $proyeccion);
            $stmt3->bindValue(':temp', 999999, PDO::PARAM_INT);
            $stmt3->execute();

            $this->db->commit();
            
            return ['exito' => true];
        } catch (Exception $e) {
            $this->db->rollBack();
            error_log("Error en moverBase: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Mueve una secuencia a una posición específica (drag & drop)
     * Reordena todas las secuencias intermedias
     */
    public function moverSecuenciaA($proyeccion, $secuenciaOrigen, $nuevaPosicion) {
        try {
            $this->db->beginTransaction();

            // Verificar que el registro origen exista
            $sqlGet = "SELECT secuencia FROM ccosbase WHERE proyeccion = :proyeccion AND secuencia = :secuencia";
            $stmtGet = $this->db->prepare($sqlGet);
            $stmtGet->bindParam(':proyeccion', $proyeccion);
            $stmtGet->bindParam(':secuencia', $secuenciaOrigen);
            $stmtGet->execute();
            $row = $stmtGet->fetch(PDO::FETCH_ASSOC);

            if (!$row) {
                throw new Exception('Registro origen no encontrado');
            }

            // Si la posición es la misma, no hacer nada
            if ($secuenciaOrigen == $nuevaPosicion) {
                $this->db->commit();
                return ['exito' => true, 'mensaje' => 'Posición sin cambios'];
            }

            // Mover el registro a una posición temporal
            $sqlTemp = "UPDATE ccosbase SET secuencia = -1 WHERE proyeccion = :proyeccion AND secuencia = :secuencia";
            $stmtTemp = $this->db->prepare($sqlTemp);
            $stmtTemp->bindParam(':proyeccion', $proyeccion);
            $stmtTemp->bindParam(':secuencia', $secuenciaOrigen);
            $stmtTemp->execute();

            // Reordenar las secuencias intermedias
            if ($secuenciaOrigen < $nuevaPosicion) {
                // Mover hacia abajo: decrementar las secuencias entre origen y destino
                $sqlShift = "UPDATE ccosbase SET secuencia = secuencia - 1 
                            WHERE proyeccion = :proyeccion 
                            AND secuencia > :origen 
                            AND secuencia <= :destino";
                $stmtShift = $this->db->prepare($sqlShift);
                $stmtShift->bindParam(':proyeccion', $proyeccion);
                $stmtShift->bindParam(':origen', $secuenciaOrigen);
                $stmtShift->bindParam(':destino', $nuevaPosicion);
                $stmtShift->execute();
            } else {
                // Mover hacia arriba: incrementar las secuencias entre destino y origen
                $sqlShift = "UPDATE ccosbase SET secuencia = secuencia + 1 
                            WHERE proyeccion = :proyeccion 
                            AND secuencia >= :destino 
                            AND secuencia < :origen";
                $stmtShift = $this->db->prepare($sqlShift);
                $stmtShift->bindParam(':proyeccion', $proyeccion);
                $stmtShift->bindParam(':destino', $nuevaPosicion);
                $stmtShift->bindParam(':origen', $secuenciaOrigen);
                $stmtShift->execute();
            }

            // Mover el registro original a su nueva posición
            $sqlFinal = "UPDATE ccosbase SET secuencia = :nuevaPosicion WHERE proyeccion = :proyeccion AND secuencia = -1";
            $stmtFinal = $this->db->prepare($sqlFinal);
            $stmtFinal->bindParam(':nuevaPosicion', $nuevaPosicion);
            $stmtFinal->bindParam(':proyeccion', $proyeccion);
            $stmtFinal->execute();

            $this->db->commit();
            
            return ['exito' => true, 'mensaje' => 'Secuencia reordenada correctamente'];
        } catch (Exception $e) {
            $this->db->rollBack();
            error_log("Error en moverSecuenciaA: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Mueve una entrada en ccosproy a una nueva posición (drag & drop en modal)
     * Reordena todos los registros para mantener la secuencia continua
     */
    public function moverProyeccion($proyeccion, $secuenciaOrigen, $secuenciaDestino, $posicion = 'despues') {
        try {
            $this->db->beginTransaction();

            // 1. Obtener todos los registros de la proyección ordenados por secuencia
            $sqlSelect = "SELECT proyeccion, codigo, nombre, campana, galpon, swac, diasefec, 
                                 mortalidad, diasdesc, pollos, area, densidad, secuencia, fecini 
                          FROM ccosproy 
                          WHERE proyeccion = :proyeccion 
                          ORDER BY secuencia ASC";
            $stmtSelect = $this->db->prepare($sqlSelect);
            $stmtSelect->bindParam(':proyeccion', $proyeccion);
            $stmtSelect->execute();
            $registros = $stmtSelect->fetchAll(PDO::FETCH_ASSOC);

            if (empty($registros)) {
                throw new Exception("No se encontraron registros para la proyección {$proyeccion}");
            }

            // 2. Buscar índices del origen y destino en el array
            $indiceOrigen = null;
            $indiceDestino = null;
            $registroOrigen = null;

            foreach ($registros as $idx => $registro) {
                if ($registro['secuencia'] == $secuenciaOrigen) {
                    $indiceOrigen = $idx;
                    $registroOrigen = $registro;
                }
                if ($registro['secuencia'] == $secuenciaDestino) {
                    $indiceDestino = $idx;
                }
            }

            if ($indiceOrigen === null) {
                throw new Exception("Registro origen con secuencia {$secuenciaOrigen} no encontrado");
            }
            if ($indiceDestino === null) {
                throw new Exception("Registro destino con secuencia {$secuenciaDestino} no encontrado");
            }

            // 3. Remover el elemento origen del array
            array_splice($registros, $indiceOrigen, 1);

            // 4. Calcular la nueva posición de inserción
            // Si quitamos un elemento antes del destino, el índice del destino disminuye en 1
            if ($indiceOrigen < $indiceDestino) {
                $indiceDestino--;
            }

            // Insertar antes o después según el parámetro
            if ($posicion === 'antes') {
                $nuevaPosicion = $indiceDestino;
            } else {
                $nuevaPosicion = $indiceDestino + 1;
            }

            // 5. Insertar el elemento en la nueva posición
            array_splice($registros, $nuevaPosicion, 0, [$registroOrigen]);

            // 6. Actualizar todos los registros con su nueva secuencia
            $sqlUpdate = "UPDATE ccosproy 
                          SET secuencia = :nueva_secuencia 
                          WHERE proyeccion = :proyeccion 
                            AND codigo = :codigo 
                            AND nombre = :nombre 
                            AND campana = :campana 
                            AND galpon = :galpon";
            $stmtUpdate = $this->db->prepare($sqlUpdate);

            $nuevaSecuencia = 1001;
            foreach ($registros as $registro) {
                $stmtUpdate->execute([
                    ':nueva_secuencia' => $nuevaSecuencia,
                    ':proyeccion' => $proyeccion,
                    ':codigo' => $registro['codigo'],
                    ':nombre' => $registro['nombre'],
                    ':campana' => $registro['campana'],
                    ':galpon' => $registro['galpon']
                ]);
                $nuevaSecuencia++;
            }

            $this->db->commit();
            
            return ['exito' => true, 'mensaje' => 'Posición actualizada correctamente'];
        } catch (Exception $e) {
            $this->db->rollBack();
            error_log("Error en moverProyeccion: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Verifica si una proyección existe en cpproy, si no existe la crea
     * Este método es llamado internamente por crearSecuencia() y guardarBase()
     */
    private function asegurarProyeccionEnCpproy($nombre, $indicador = 'PENDIENTE') {
        try {
            // Verificar si ya existe
            $sqlCheck = "SELECT COUNT(*) as total FROM cpproy WHERE nombre = :nombre";
            $stmtCheck = $this->db->prepare($sqlCheck);
            $stmtCheck->bindParam(':nombre', $nombre);
            $stmtCheck->execute();
            $result = $stmtCheck->fetch(PDO::FETCH_ASSOC);
            
            // Si no existe, crearla
            if ($result['total'] == 0) {
                $sqlMax = "SELECT COALESCE(MAX(cont), 0) + 1 as next_cont FROM cpproy";
                $stmtMax = $this->db->query($sqlMax);
                $rowMax = $stmtMax->fetch(PDO::FETCH_ASSOC);
                $nextCont = $rowMax['next_cont'];

                $sql = "INSERT INTO cpproy (cont, nombre, indicador) VALUES (:cont, :nombre, :indicador)";
                $stmt = $this->db->prepare($sql);
                $stmt->bindParam(':cont', $nextCont);
                $stmt->bindParam(':nombre', $nombre);
                $stmt->bindParam(':indicador', $indicador);
                $stmt->execute();
                
                error_log("Proyección '$nombre' registrada con indicador '$indicador' en cpproy");
            }
        } catch (PDOException $e) {
            error_log("Error en asegurarProyeccionEnCpproy: " . $e->getMessage());
            // No lanzar excepción, solo registrar el error
        }
    }

    /**
     * Crea una nueva proyección manualmente (llamado desde API)
     */
    public function crearProyeccion($nombre, $indicador = 'PENDIENTE') {
        try {
            $this->asegurarProyeccionEnCpproy($nombre, $indicador);
            return ['nombre' => $nombre, 'indicador' => $indicador];
        } catch (PDOException $e) {
            error_log("Error en crearProyeccion: " . $e->getMessage());
            throw new Exception('Error al crear proyección: ' . $e->getMessage());
        }
    }

    /**
     * Edita una proyección en todas las tablas relacionadas
     * @param string $proyeccion Nombre actual de la proyección
     * @param string $nuevoNombre Nuevo nombre de la proyección
     * @param string|null $indicador Nuevo indicador (VALIDO/NO VALIDO) si se proporciona
     */
    public function editarProyeccion($proyeccion, $nuevoNombre, $indicador = null) {
        try {
            $this->db->beginTransaction();
            
            // Actualizar en todas las tablas relacionadas (excepto cpproy que se maneja aparte)
            $tablas = [
                ['tabla' => 'ccosbase', 'campo' => 'proyeccion'],
                ['tabla' => 'ccosproy', 'campo' => 'proyeccion'],
                ['tabla' => 'fechaproy', 'campo' => 'proyeccion'],
                ['tabla' => 'fechasemproy', 'campo' => 'proyeccion'],
                ['tabla' => 'ccoscargapollo', 'campo' => 'proyeccion']
            ];
            
            $registrosActualizados = 0;

            foreach ($tablas as $info) {
                try {
                    $sql = "UPDATE {$info['tabla']} 
                            SET {$info['campo']} = :nuevoNombre 
                            WHERE {$info['campo']} = :proyeccion";
                    $stmt = $this->db->prepare($sql);
                    $stmt->execute([
                        'nuevoNombre' => $nuevoNombre,
                        'proyeccion' => $proyeccion
                    ]);
                    $affected = $stmt->rowCount();
                    $registrosActualizados += $affected;
                    error_log("✓ Actualizada tabla {$info['tabla']}: $affected registros");
                } catch (PDOException $e) {
                    error_log("✗ Error actualizando {$info['tabla']}: " . $e->getMessage());
                    throw new Exception("Error al actualizar tabla {$info['tabla']}: " . $e->getMessage());
                }
            }

            // Actualizar cpproy con nombre e indicador si se proporciona
            try {
                if ($indicador !== null) {
                    // Actualizar nombre e indicador
                    $sql = "UPDATE cpproy 
                            SET nombre = :nuevoNombre, indicador = :indicador 
                            WHERE nombre = :proyeccion";
                    $stmt = $this->db->prepare($sql);
                    $stmt->execute([
                        'nuevoNombre' => $nuevoNombre,
                        'indicador' => $indicador,
                        'proyeccion' => $proyeccion
                    ]);
                    error_log("✓ Actualizada cpproy: nombre e indicador");
                } else {
                    // Solo actualizar nombre
                    $sql = "UPDATE cpproy 
                            SET nombre = :nuevoNombre 
                            WHERE nombre = :proyeccion";
                    $stmt = $this->db->prepare($sql);
                    $stmt->execute([
                        'nuevoNombre' => $nuevoNombre,
                        'proyeccion' => $proyeccion
                    ]);
                    error_log("✓ Actualizada cpproy: solo nombre");
                }
                $registrosActualizados += $stmt->rowCount();
            } catch (PDOException $e) {
                error_log("✗ Error actualizando cpproy: " . $e->getMessage());
                throw new Exception("Error al actualizar tabla cpproy: " . $e->getMessage());
            }

            $this->db->commit();
            error_log("✅ Proyección actualizada: '$proyeccion' → '$nuevoNombre' ($registrosActualizados registros)");
            return ['exito' => true, 'registros_actualizados' => $registrosActualizados];
            
        } catch (PDOException $e) {
            $this->db->rollBack();
            error_log("❌ Error en editarProyeccion: " . $e->getMessage());
            throw new Exception('Error al editar proyección: ' . $e->getMessage());
        }
    }

    /**
     * Elimina una proyección
     */
    public function eliminarProyeccion($proyeccion) {
        try {
            $this->db->beginTransaction();

            // Eliminar datos relacionados
            $this->db->prepare("DELETE FROM ccosbase WHERE proyeccion = :p")->execute([':p' => $proyeccion]);
            $this->db->prepare("DELETE FROM ccosproy WHERE proyeccion = :p")->execute([':p' => $proyeccion]);
            $this->db->prepare("DELETE FROM fechaproy WHERE proyeccion = :p")->execute([':p' => $proyeccion]);
            $this->db->prepare("DELETE FROM fechasemproy WHERE proyeccion = :p")->execute([':p' => $proyeccion]);
            $this->db->prepare("DELETE FROM ccoscargapollo WHERE proyeccion = :p")->execute([':p' => $proyeccion]);

            // Eliminar proyección
            $sql = "DELETE FROM cpproy WHERE nombre = :nombre";
            $stmt = $this->db->prepare($sql);
            $stmt->bindParam(':nombre', $proyeccion);
            $stmt->execute();

            $this->db->commit();
            return ['exito' => true];
        } catch (Exception $e) {
            $this->db->rollBack();
            error_log("Error en eliminarProyeccion: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Obtiene lista de galpones disponibles
     */
    public function obtenerGalpones() {
        try {
            $sql = "SELECT DISTINCT codigo, nombre, galpon FROM ccosbase ORDER BY codigo, galpon";
            $stmt = $this->db->query($sql);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error en obtenerGalpones: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Obtiene el último registro de una granja/galpón específica
     */
    public function obtenerUltimoRegistroGranjaGalpon($codigo, $galpon) {
        try {
            $sql = "SELECT * FROM ccosbase WHERE codigo = :codigo AND galpon = :galpon ORDER BY semana DESC LIMIT 1";
            $stmt = $this->db->prepare($sql);
            $stmt->bindParam(':codigo', $codigo);
            $stmt->bindParam(':galpon', $galpon);
            $stmt->execute();
            
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error en obtenerUltimoRegistroGranjaGalpon: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Obtiene el calendario de una proyección desde fechaproy
     */
    public function obtenerCalendario($proyeccion) {
        try {
            $sql = "SELECT fecha, sem as semana, cargas, flag FROM fechaproy WHERE proyeccion = :proyeccion ORDER BY fecha";
            $stmt = $this->db->prepare($sql);
            $stmt->bindParam(':proyeccion', $proyeccion);
            $stmt->execute();
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error en obtenerCalendario: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Obtiene calendario con paginación (por ciclos)
     */
    public function obtenerCalendarioPaginado($proyeccion, $offset = 0, $limit = 100) {
        try {
            $sql = "SELECT fecha, sem as semana, cargas, flag FROM fechaproy 
                    WHERE proyeccion = :proyeccion 
                    ORDER BY fecha 
                    LIMIT :offset, :limit";
            $stmt = $this->db->prepare($sql);
            $stmt->bindParam(':proyeccion', $proyeccion);
            $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
            $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
            $stmt->execute();
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error en obtenerCalendarioPaginado: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Cuenta los registros totales del calendario
     */
    public function contarCalendario($proyeccion) {
        try {
            $sql = "SELECT COUNT(*) as total FROM fechaproy WHERE proyeccion = :proyeccion";
            $stmt = $this->db->prepare($sql);
            $stmt->bindParam(':proyeccion', $proyeccion);
            $stmt->execute();
            
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return (int)($result['total'] ?? 0);
        } catch (PDOException $e) {
            error_log("Error en contarCalendario: " . $e->getMessage());
            return 0;
        }
    }

    /**
     * Copia el calendario de una proyección a otra
     * Incluye fechaproy, fechasemproy y ccoscargapollo
     */
    public function copiarCalendario($proyeccionOrigen, $proyeccionDestino) {
        try {
            $this->db->beginTransaction();
            
            // 1. Verificar que la proyección origen tenga calendario
            $sqlCheck = "SELECT COUNT(*) as total FROM fechaproy WHERE proyeccion = :origen";
            $stmtCheck = $this->db->prepare($sqlCheck);
            $stmtCheck->bindParam(':origen', $proyeccionOrigen);
            $stmtCheck->execute();
            $result = $stmtCheck->fetch(PDO::FETCH_ASSOC);
            
            if ($result['total'] == 0) {
                throw new Exception('La proyección origen no tiene calendario generado');
            }
            
            // 2. Eliminar calendario existente en destino
            $sqlDeleteFechaproy = "DELETE FROM fechaproy WHERE proyeccion = :destino";
            $stmtDeleteFechaproy = $this->db->prepare($sqlDeleteFechaproy);
            $stmtDeleteFechaproy->bindParam(':destino', $proyeccionDestino);
            $stmtDeleteFechaproy->execute();
            
            $sqlDeleteFechasem = "DELETE FROM fechasemproy WHERE proyeccion = :destino";
            $stmtDeleteFechasem = $this->db->prepare($sqlDeleteFechasem);
            $stmtDeleteFechasem->bindParam(':destino', $proyeccionDestino);
            $stmtDeleteFechasem->execute();
            
            $sqlDeleteCargapollo = "DELETE FROM ccoscargapollo WHERE proyeccion = :destino";
            $stmtDeleteCargapollo = $this->db->prepare($sqlDeleteCargapollo);
            $stmtDeleteCargapollo->bindParam(':destino', $proyeccionDestino);
            $stmtDeleteCargapollo->execute();
            
            // 3. Copiar fechaproy
            $sqlCopyFechaproy = "INSERT INTO fechaproy (proyeccion, fecha, cargas, flag, sem)
                                 SELECT :destino, fecha, cargas, flag, sem
                                 FROM fechaproy 
                                 WHERE proyeccion = :origen";
            $stmtCopyFechaproy = $this->db->prepare($sqlCopyFechaproy);
            $stmtCopyFechaproy->bindParam(':destino', $proyeccionDestino);
            $stmtCopyFechaproy->bindParam(':origen', $proyeccionOrigen);
            $stmtCopyFechaproy->execute();
            $fechasCopiadas = $stmtCopyFechaproy->rowCount();
            
            // 4. Copiar fechasemproy
            $sqlCopyFechasem = "INSERT INTO fechasemproy (proyeccion, anuo, sem, oferta, demanda, diferencia)
                                SELECT :destino, anuo, sem, oferta, demanda, diferencia
                                FROM fechasemproy 
                                WHERE proyeccion = :origen";
            $stmtCopyFechasem = $this->db->prepare($sqlCopyFechasem);
            $stmtCopyFechasem->bindParam(':destino', $proyeccionDestino);
            $stmtCopyFechasem->bindParam(':origen', $proyeccionOrigen);
            $stmtCopyFechasem->execute();
            $semanasCopiadas = $stmtCopyFechasem->rowCount();
            
            // 5. Copiar ccoscargapollo (solo si existe data)
            $sqlCheckCargapollo = "SELECT COUNT(*) as total FROM ccoscargapollo WHERE proyeccion = :origen";
            $stmtCheckCargapollo = $this->db->prepare($sqlCheckCargapollo);
            $stmtCheckCargapollo->bindParam(':origen', $proyeccionOrigen);
            $stmtCheckCargapollo->execute();
            $resultCargapollo = $stmtCheckCargapollo->fetch(PDO::FETCH_ASSOC);
            
            $cargasCopiadas = 0;
            if ($resultCargapollo['total'] > 0) {
                $sqlCopyCargapollo = "INSERT INTO ccoscargapollo 
                                      (proyeccion, fecaqp, codigo, nombre, campana, galpon, pollos, mortalidad, 
                                       secuencia, diasefec, semana, feclima, fecliqui, fecdespo, pollosliqui, 
                                       pollosdia, pollossem, viajessem)
                                      SELECT :destino, fecaqp, codigo, nombre, campana, galpon, pollos, mortalidad, 
                                             secuencia, diasefec, semana, feclima, fecliqui, fecdespo, pollosliqui,
                                             pollosdia, pollossem, viajessem
                                      FROM ccoscargapollo 
                                      WHERE proyeccion = :origen";
                $stmtCopyCargapollo = $this->db->prepare($sqlCopyCargapollo);
                $stmtCopyCargapollo->bindParam(':destino', $proyeccionDestino);
                $stmtCopyCargapollo->bindParam(':origen', $proyeccionOrigen);
                $stmtCopyCargapollo->execute();
                $cargasCopiadas = $stmtCopyCargapollo->rowCount();
            }
            
            $this->db->commit();
            
            error_log("Calendario copiado: $fechasCopiadas fechas, $semanasCopiadas semanas, $cargasCopiadas cargas de '$proyeccionOrigen' a '$proyeccionDestino'");
            
            return [
                'fechas_copiadas' => $fechasCopiadas,
                'semanas_copiadas' => $semanasCopiadas,
                'cargas_copiadas' => $cargasCopiadas
            ];
        } catch (Exception $e) {
            $this->db->rollBack();
            error_log("Error en copiarCalendario: " . $e->getMessage());
            throw new Exception('Error al copiar calendario: ' . $e->getMessage());
        }
    }
}
?>
