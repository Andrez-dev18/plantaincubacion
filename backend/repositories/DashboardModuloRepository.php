<?php
/**
 * DashboardModuloRepository
 *
 * Repositorio para gestionar el orden del dashboard
 */

class DashboardModuloRepository {
    private $conn;

    public function __construct($db) {
        $this->conn = $db;
    }

    private function normalizeProgramaName($value) {
        $value = strtolower(trim($value));
        $value = preg_replace('/\s+/', '', $value);
        $value = str_replace('de', '', $value);
        return $value;
    }

    private function resolveProgramaId($programa) {
        if (is_numeric($programa)) {
            return (int)$programa;
        }

        $sql = "SELECT id_programa, nombre FROM amd_programas";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute();
        $programas = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $normalized = $this->normalizeProgramaName($programa);
        foreach ($programas as $row) {
            if ($this->normalizeProgramaName($row['nombre']) === $normalized) {
                return (int)$row['id_programa'];
            }
        }

        return null;
    }

    /**
     * Listar módulos del dashboard por programa filtrados por los roles del usuario.
     * Incluye los grupos padre de los ítems accesibles.
     * Si el usuario no tiene roles asignados → devuelve [].
     *
     * @param int    $idPrograma
     * @param string $userCodigo  Código del usuario (columna 'codigo' de usuarios_L)
     * @return array
     */
    public function findAllByProgramaParaUsuario(string $programa = null, string $userCodigo = ''): array {
        $idPrograma = $this->resolveProgramaId($programa);
        if (!$idPrograma) {
            return [];
        }

        // ── BYPASS REAL PARA ADMINISTRADORES ────────────────────────────────
        // Si eres el usuario supremo del sistema, te entregamos la jerarquía completa 
        // directo de la tabla amd_dashboard_modulos sin pasar por las tablas de permisos rotas.
        if ($userCodigo === 'SYSTEM' || strtolower($userCodigo) === 'admin') {
            return $this->findAllByPrograma($programa);
        }
        // ───────────────────────────────────────────────────────────────────

        try {
            // ── ítems accesibles para el usuario (vía sus roles) ─────────────
            $sqlItems = "SELECT DISTINCT
                               dm.cod_mod, dm.tipo, dm.parent_cod, dm.nom_mod, dm.label_short, dm.icono,
                               dm.url, dm.tipo_param, dm.titulo, dm.nivel0, dm.nivel1, dm.nivel2, dm.nivel3,
                               dm.orden, dm.id_programa, p.nombre AS programa
                         FROM adm_usuario_rol ur
                         INNER JOIN adm_rol r
                                 ON r.cod_rol = ur.cod_rol
                                AND COALESCE(r.activo, 1) = 1
                         INNER JOIN adm_rol_progr_modulo rpm
                                 ON rpm.id_rol    = r.id
                                AND rpm.id_programa = :prog1
                         INNER JOIN amd_dashboard_modulos dm
                                 ON dm.cod_mod    = rpm.cod_mod
                                AND dm.id_programa = :prog2
                         LEFT  JOIN amd_programas p ON p.id_programa = dm.id_programa
                         WHERE ur.codigo = :codigo";

            // ── grupos que tienen al menos un ítem accesible ──────────────────
            $sqlGroups = "SELECT DISTINCT
                               grp.cod_mod, grp.tipo, grp.parent_cod, grp.nom_mod, grp.label_short, grp.icono,
                               grp.url, grp.tipo_param, grp.titulo, grp.nivel0, grp.nivel1, grp.nivel2, grp.nivel3,
                               grp.orden, grp.id_programa, p.nombre AS programa
                          FROM amd_dashboard_modulos grp
                          LEFT JOIN amd_programas p ON p.id_programa = grp.id_programa
                          WHERE grp.tipo       = 'group'
                            AND grp.id_programa = :prog3
                            AND EXISTS (
                                SELECT 1
                                FROM adm_usuario_rol ur2
                                INNER JOIN adm_rol r2
                                        ON r2.cod_rol = ur2.cod_rol
                                       AND COALESCE(r2.activo, 1) = 1
                                INNER JOIN adm_rol_progr_modulo rpm2
                                        ON rpm2.id_rol     = r2.id
                                       AND rpm2.id_programa = :prog4
                                INNER JOIN amd_dashboard_modulos dm2
                                        ON dm2.cod_mod     = rpm2.cod_mod
                                       AND dm2.id_programa  = :prog5
                                WHERE ur2.codigo = :codigo2
                                  AND dm2.parent_cod = grp.cod_mod
                            )";

            $sql = "({$sqlItems}) UNION ({$sqlGroups}) ORDER BY orden";

            $stmt = $this->conn->prepare($sql);
            $stmt->execute([
                ':prog1'   => $idPrograma,
                ':prog2'   => $idPrograma,
                ':codigo'  => $userCodigo,
                ':prog3'   => $idPrograma,
                ':prog4'   => $idPrograma,
                ':prog5'   => $idPrograma,
                ':codigo2' => $userCodigo,
            ]);

            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("DashboardModuloRepository::findAllByProgramaParaUsuario: " . $e->getMessage());
            throw new Exception("Error al obtener módulos del usuario: " . $e->getMessage());
        }
    }

    /**
     * Listar modulos del dashboard por programa
     *
     * @param string|null $programa Si es null, devuelve todos los módulos de todos los programas
     * @return array
     */
    public function findAllByPrograma($programa) {
        try {
            // Si programa es null, devolver TODOS los módulos de todos los programas
            if ($programa === null || $programa === '') {
                $sql = "SELECT dm.cod_mod, dm.tipo, dm.parent_cod, dm.nom_mod, dm.label_short, dm.icono, 
                               dm.url, dm.tipo_param, dm.titulo, dm.nivel0, dm.nivel1, dm.nivel2, dm.nivel3, 
                               dm.orden, dm.id_programa, p.nombre as programa
                        FROM amd_dashboard_modulos dm
                        LEFT JOIN amd_programas p ON p.id_programa = dm.id_programa
                        ORDER BY p.nombre, dm.orden";
                
                $stmt = $this->conn->prepare($sql);
                $stmt->execute();
                
                return $stmt->fetchAll(PDO::FETCH_ASSOC);
            }
            
            $idPrograma = $this->resolveProgramaId($programa);
            if (!$idPrograma) {
                return [];
            }

            $sql = "SELECT dm.cod_mod, dm.tipo, dm.parent_cod, dm.nom_mod, dm.label_short, dm.icono, 
                           dm.url, dm.tipo_param, dm.titulo, dm.nivel0, dm.nivel1, dm.nivel2, dm.nivel3, 
                           dm.orden, dm.id_programa, p.nombre as programa
                    FROM amd_dashboard_modulos dm
                    LEFT JOIN amd_programas p ON p.id_programa = dm.id_programa
                    WHERE dm.id_programa = ?
                    ORDER BY dm.orden";

            $stmt = $this->conn->prepare($sql);
            $stmt->execute([$idPrograma]);

            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error en DashboardModuloRepository::findAllByPrograma: " . $e->getMessage());
            throw new Exception("Error al obtener modulos del dashboard: " . $e->getMessage());
        }
    }

    /**
     * Contar modulos del dashboard por programa
     *
     * @param string $programa
     * @return int
     */
    public function countByPrograma($programa) {
        $idPrograma = $this->resolveProgramaId($programa);
        if (!$idPrograma) {
            return 0;
        }

        $sql = "SELECT COUNT(*) as total
            FROM amd_dashboard_modulos
            WHERE id_programa = ?";

        $stmt = $this->conn->prepare($sql);
        $stmt->execute([$idPrograma]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        return (int)($result['total'] ?? 0);
    }

    /**
     * Sembrar desde la tabla modulos
     *
     * @param string $programa
     * @return int
     */
    public function seedFromModulos($programa) {
        try {
            $idPrograma = $this->resolveProgramaId($programa);
            if (!$idPrograma) {
                return 0;
            }

            $sql = "SELECT cod_mod, nom_mod, nivel0, nivel1, nivel2, nivel3
                    FROM modulos
                    WHERE LOWER(programa) = LOWER(?)
                    ORDER BY nivel0, nivel1, nivel2, nivel3";

            $stmt = $this->conn->prepare($sql);
            $stmt->execute([$programa]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if (!$rows) {
                return 0;
            }

            $this->conn->beginTransaction();

                $insertSql = "INSERT INTO amd_dashboard_modulos
                        (id_programa, cod_mod, tipo, parent_cod, nom_mod, label_short, icono, url, tipo_param, titulo,
                     nivel0, nivel1, nivel2, nivel3, orden)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            $insertStmt = $this->conn->prepare($insertSql);

            $orden = 1;
            foreach ($rows as $row) {
                $insertStmt->execute([
                    $idPrograma,
                    $row['cod_mod'],
                    'item',
                    null,
                    $row['nom_mod'],
                    null,
                    null,
                    null,
                    null,
                    null,
                    $row['nivel0'],
                    $row['nivel1'],
                    $row['nivel2'],
                    $row['nivel3'],
                    $orden
                ]);
                $orden += 1;
            }

            $this->conn->commit();

            return count($rows);
        } catch (PDOException $e) {
            $this->conn->rollBack();
            error_log("Error en DashboardModuloRepository::seedFromModulos: " . $e->getMessage());
            throw new Exception("Error al sembrar modulos del dashboard: " . $e->getMessage());
        }
    }

    /**
     * Obtener lista ordenada para reordenamiento
     *
     * @param string $programa
     * @return array
     */
    public function findOrdenList($programa) {
        $idPrograma = $this->resolveProgramaId($programa);
        if (!$idPrograma) {
            return [];
        }

        $sql = "SELECT cod_mod, orden, parent_cod, tipo
            FROM amd_dashboard_modulos
            WHERE id_programa = ?
            ORDER BY orden";

        $stmt = $this->conn->prepare($sql);
        $stmt->execute([$idPrograma]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Intercambiar orden con un vecino
     *
     * @param string $programa
     * @param string $codModActual
     * @param string $codModObjetivo
     * @param int $ordenActual
     * @param int $ordenObjetivo
     * @return void
     */
    public function swapOrden($programa, $codModActual, $codModObjetivo, $ordenActual, $ordenObjetivo) {
        $idPrograma = $this->resolveProgramaId($programa);
        if (!$idPrograma) {
            return;
        }

        $sql = "UPDATE amd_dashboard_modulos
                SET orden = CASE
                    WHEN cod_mod = ? THEN ?
                    WHEN cod_mod = ? THEN ?
                    ELSE orden
                END
                WHERE id_programa = ?
                  AND cod_mod IN (?, ?)";

        $stmt = $this->conn->prepare($sql);
        $stmt->execute([
            $codModActual,
            $ordenObjetivo,
            $codModObjetivo,
            $ordenActual,
            $idPrograma,
            $codModActual,
            $codModObjetivo
        ]);
    }

    /**
     * Actualizar orden segun lista completa de cod_mod
     *
     * @param string $programa
     * @param array $orderedCods
     * @return void
     */
    public function updateOrdenFromList($programa, $orderedCods) {
        try {
            $idPrograma = $this->resolveProgramaId($programa);
            if (!$idPrograma) {
                return;
            }

            $this->conn->beginTransaction();

            $sql = "UPDATE amd_dashboard_modulos
                    SET orden = ?
                    WHERE id_programa = ?
                      AND cod_mod = ?";
            $stmt = $this->conn->prepare($sql);

            $orden = 1;
            foreach ($orderedCods as $codMod) {
                $stmt->execute([
                    $orden,
                    $idPrograma,
                    $codMod
                ]);
                $orden += 1;
            }

            $this->conn->commit();
        } catch (PDOException $e) {
            $this->conn->rollBack();
            error_log("Error en DashboardModuloRepository::updateOrdenFromList: " . $e->getMessage());
            throw new Exception("Error al actualizar orden del dashboard: " . $e->getMessage());
        }
    }

    /**
     * Reemplazar el orden completo del dashboard
     *
     * @param string $programa
     * @param array $items
     * @return int
     */
    public function replaceForPrograma($programa, $items) {
        try {
            $idPrograma = $this->resolveProgramaId($programa);
            if (!$idPrograma) {
                return 0;
            }

            // --- FILTRO ANTIDUPLICADOS ---
            // Creamos un set para rastrear combinaciones únicas dentro del mismo lote
            $itemsUnicos = [];
            $procesados = [];
            
            foreach ($items as $item) {
                if (empty($item['cod_mod'])) {
                    continue;
                }
                
                // Filtramos única y estrictamente por el código del módulo
                $keyModulo = $item['cod_mod'];
                
                if (isset($procesados[$keyModulo])) {
                    // Si el código ya se procesó en este lote, lo saltamos
                    continue; 
                }
                
                $procesados[$keyModulo] = true;
                $itemsUnicos[] = $item;
            }
            // ------------------------------

            $this->conn->beginTransaction();

            $deleteSql = "DELETE FROM amd_dashboard_modulos WHERE id_programa = ?";
            $deleteStmt = $this->conn->prepare($deleteSql);
            $deleteStmt->execute([$idPrograma]);

            $insertSql = "INSERT INTO amd_dashboard_modulos
                    (id_programa, cod_mod, tipo, parent_cod, nom_mod, label_short, icono, url, tipo_param, titulo,
                     nivel0, nivel1, nivel2, nivel3, orden)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            $insertStmt = $this->conn->prepare($insertSql);

            $orden = 1;
            // Iteramos sobre el array limpio libre de duplicados de PK
            foreach ($itemsUnicos as $item) {
                $insertStmt->execute([
                    $idPrograma,
                    $item['cod_mod'],
                    $item['tipo'] ?? 'item',
                    $item['parent_cod'] ?? null,
                    $item['nom_mod'] ?? null,
                    $item['label_short'] ?? null,
                    $item['icono'] ?? null,
                    $item['url'] ?? null,
                    $item['tipo_param'] ?? null,
                    $item['titulo'] ?? null,
                    $item['nivel0'] ?? null,
                    $item['nivel1'] ?? null,
                    $item['nivel2'] ?? null,
                    $item['nivel3'] ?? null,
                    $orden
                ]);
                $orden += 1;
            }

            $this->conn->commit();

            return $orden - 1;
        } catch (PDOException $e) {
            if ($this->conn->inTransaction()) {
                $this->conn->rollBack();
            }
            error_log("Error en DashboardModuloRepository::replaceForPrograma: " . $e->getMessage());
            throw new Exception("Error al sincronizar modulos del dashboard: " . $e->getMessage());
        }
    }

    /**
     * Obtener el orden máximo para un programa
     *
     * @param string $programa
     * @return int
     */
    public function getMaxOrden($programa) {
        try {
            $idPrograma = $this->resolveProgramaId($programa);
            if (!$idPrograma) {
                return 0;
            }

            $sql = "SELECT MAX(orden) as max_orden
                    FROM amd_dashboard_modulos
                    WHERE id_programa = ?";

            $stmt = $this->conn->prepare($sql);
            $stmt->execute([$idPrograma]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);

            return (int)($result['max_orden'] ?? 0);
        } catch (PDOException $e) {
            error_log("Error en DashboardModuloRepository::getMaxOrden: " . $e->getMessage());
            throw new Exception("Error al obtener orden máximo: " . $e->getMessage());
        }
    }

    /**
     * Crear un nuevo módulo
     *
     * @param array $data
     * @return array
     */
    public function crear($data) {
        try {
            $idPrograma = $this->resolveProgramaId($data['programa'] ?? null);
            if (!$idPrograma) {
                throw new Exception('Programa no valido para crear modulo');
            }

            $sql = "INSERT INTO amd_dashboard_modulos
                    (id_programa, cod_mod, tipo, parent_cod, nom_mod, label_short, icono, url, 
                     tipo_param, titulo, nivel0, nivel1, nivel2, nivel3, orden)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

            $stmt = $this->conn->prepare($sql);
            $stmt->execute([
                $idPrograma,
                $data['cod_mod'],
                $data['tipo'],
                $data['parent_cod'] ?? null,
                $data['nom_mod'],
                $data['label_short'] ?? null,
                $data['icono'] ?? null,
                $data['url'] ?? null,
                $data['tipo_param'] ?? null,
                $data['titulo'] ?? null,
                $data['nivel0'] ?? null,
                $data['nivel1'] ?? null,
                $data['nivel2'] ?? null,
                $data['nivel3'] ?? null,
                $data['orden'] ?? 1
            ]);

            return ['id' => $this->conn->lastInsertId()];
        } catch (PDOException $e) {
            error_log("Error en DashboardModuloRepository::crear: " . $e->getMessage());
            throw new Exception("Error al crear módulo: " . $e->getMessage());
        }
    }

    /**
     * Actualizar un módulo existente
     *
     * @param array $data
     * @return array
     */
    public function actualizar($data) {
        try {
            $idPrograma = $this->resolveProgramaId($data['programa'] ?? null);
            if (!$idPrograma) {
                throw new Exception('Programa no valido para actualizar modulo');
            }

            $sql = "UPDATE amd_dashboard_modulos
                    SET nom_mod = ?,
                        tipo = ?,
                        parent_cod = ?,
                        label_short = ?,
                        icono = ?,
                        url = ?,
                        tipo_param = ?,
                        titulo = ?
                    WHERE cod_mod = ?
                      AND id_programa = ?";

            $stmt = $this->conn->prepare($sql);
            $stmt->execute([
                $data['nom_mod'],
                $data['tipo'],
                $data['parent_cod'] ?? null,
                $data['label_short'] ?? null,
                $data['icono'] ?? null,
                $data['url'] ?? null,
                $data['tipo_param'] ?? null,
                $data['titulo'] ?? null,
                $data['cod_mod'],
                $idPrograma
            ]);

            return ['affected_rows' => $stmt->rowCount()];
        } catch (PDOException $e) {
            error_log("Error en DashboardModuloRepository::actualizar: " . $e->getMessage());
            throw new Exception("Error al actualizar módulo: " . $e->getMessage());
        }
    }

    /**
     * Eliminar un módulo
     *
     * @param string $codMod
     * @param string $programa
     * @return array
     */
    public function eliminar($codMod, $programa) {
        try {
            $idPrograma = $this->resolveProgramaId($programa);
            if (!$idPrograma) {
                return ['affected_rows' => 0];
            }

            $sql = "DELETE FROM amd_dashboard_modulos
                    WHERE cod_mod = ?
                      AND id_programa = ?";

            $stmt = $this->conn->prepare($sql);
            $stmt->execute([$codMod, $idPrograma]);

            return ['affected_rows' => $stmt->rowCount()];
        } catch (PDOException $e) {
            error_log("Error en DashboardModuloRepository::eliminar: " . $e->getMessage());
            throw new Exception("Error al eliminar módulo: " . $e->getMessage());
        }
    }
}

