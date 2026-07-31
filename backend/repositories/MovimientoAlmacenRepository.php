<?php

/**
 * MovimientoAlmacenRepository
 * Tablas: guia, imov, mzon, alma, coal, conempre, indi, dola, ccos, tdoc
 *         reg_costoabc_proceso/subproceso/actividad/tarea
 */
class MovimientoAlmacenRepository
{
    private $db;
    private $mark    = 'J';    // Zona La Joya  — usado en guia
    private $markImov  = 'CW1'; // Marca imov para movimientos principales
    private $markImovE = 'CW2'; // Marca imov para contra-asientos auto-generados

    /** Marcas válidas de imov (incluye legacy 'J' para datos históricos) */
    private function imovMarks(): array
    {
        return [$this->markImov, $this->markImovE, $this->mark];
    }

    public function __construct($db)
    {
        $this->db = $db;
    }

    // ─── VALIDACIONES DE FECHA ────────────────────────────────────────────────

    public function getAnoSistema(): string
    {
        $stmt = $this->db->prepare("SELECT eano FROM conempre LIMIT 1");
        $stmt->execute();
        return $stmt->fetchColumn() ?? '';
    }

    public function getMesCerrado(string $fechaMes): bool
    {
        // fechaMes formato: 'yyyy/mm'
        $stmt = $this->db->prepare("SELECT cierre FROM indi WHERE fecha = ?");
        $stmt->execute([$fechaMes]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row && $row['cierre'] === 'C';
    }

    public function getDiaCerradoJoya(string $fechaDia): bool
    {
        // fechaDia formato: 'yyyy/mm/dd'
        $stmt = $this->db->prepare("SELECT cerrajoya FROM dola WHERE fecha = ?");
        $stmt->execute([$fechaDia]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row && $row['cerrajoya'] === 'C';
    }

    public function getTipoCambioPorFecha(string $fecha): ?array
    {
        $stmt = $this->db->prepare("SELECT fecha, lib_compra, lib_venta FROM dola WHERE fecha = ?");
        $stmt->execute([$fecha]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    // ─── MAESTROS / COMBOS ────────────────────────────────────────────────────

    public function getAlmacenes(): array
    {
        $stmt = $this->db->prepare(
            "SELECT codalm, descri, COALESCE(libro, 'AL') AS libro, moneda FROM alma ORDER BY codalm"
        );
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getAlmacenById(string $codalm): ?array
    {
        $stmt = $this->db->prepare(
            "SELECT codalm, descri, COALESCE(libro, 'AL') AS libro, moneda FROM alma WHERE codalm = ?"
        );
        $stmt->execute([$codalm]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function getTransacciones(): array
    {
        $stmt = $this->db->prepare(
            "SELECT codtra, descri, emidoc, precio, cencos, gragui, pidemotivo,
                    pmoned, observ, ordcom, gentsa, merma, palmde
             FROM coal ORDER BY codtra"
        );
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getTransaccionById(string $codtra): ?array
    {
        $stmt = $this->db->prepare(
            "SELECT codtra, descri, emidoc, precio, cencos, gragui, pidemotivo,
                    pmoned, observ, ordcom, gentsa, merma, palmde
             FROM coal WHERE codtra = ?"
        );
        $stmt->execute([$codtra]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function getTiposDocumento(): array
    {
        $stmt = $this->db->prepare(
            "SELECT tipdoc, descri, moneda FROM tdoc ORDER BY tipdoc"
        );
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getCentrosCosto(): array
    {
        $stmt = $this->db->prepare(
            "SELECT codigo, nombre FROM ccos WHERE swac IS NULL OR swac != 'I' ORDER BY codigo"
        );
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getClientesProveedores(string $termino = ''): array
    {
        $cleanFn = "TRIM(REPLACE(codigo, CHAR(9), ''))";
        if ($termino !== '') {
            $like = "%{$termino}%";
            $stmt = $this->db->prepare(
                "SELECT
                    {$cleanFn} AS tprocli,
                    COALESCE(NULLIF(TRIM(MAX(nombre)), ''), 'SIN NOMBRE') AS nombre
                 FROM ccte
                 WHERE codigo IS NOT NULL
                   AND {$cleanFn} <> ''
                   AND ({$cleanFn} LIKE ? OR TRIM(nombre) LIKE ?)
                 GROUP BY {$cleanFn}
                 ORDER BY {$cleanFn}
                 LIMIT 100"
            );
            $stmt->execute([$like, $like]);
        } else {
            $stmt = $this->db->prepare(
                "SELECT
                    {$cleanFn} AS tprocli,
                    COALESCE(NULLIF(TRIM(MAX(nombre)), ''), 'SIN NOMBRE') AS nombre
                 FROM ccte
                 WHERE codigo IS NOT NULL
                   AND {$cleanFn} <> ''
                 GROUP BY {$cleanFn}
                 ORDER BY {$cleanFn}
                 LIMIT 200"
            );
            $stmt->execute();
        }
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getProductos(int $limit = 200, int $offset = 0, string $alma = '', string $codtra = ''): array
    {
        $limit = max(1, min(500, (int)$limit));
        $offset = max(0, (int)$offset);

        $codtra = trim($codtra);
        $esSalida = ($codtra !== '' && strtoupper(substr($codtra, 0, 1)) === 'S');

        if ($alma !== '') {
            $sql = "SELECT m.codigo AS tcodigo, m.descri AS tdescri, m.unidad AS tunidad,
                           m.peso AS tpeso, m.cuenta AS tcuenta,
                           COALESCE(z.qstock, 0) AS tstock
                    FROM (
                        SELECT codigo, MIN(descri) AS descri, MIN(unidad) AS unidad,
                               MIN(peso) AS peso, MIN(cuenta) AS cuenta
                        FROM mitm
                        WHERE alma IN ('010','018')
                        GROUP BY codigo
                    ) AS m
                    LEFT JOIN (
                        SELECT TRIM(REPLACE(codigo, CHAR(9), '')) AS codigo, alma, SUM(qstock) AS qstock
                        FROM mzon
                        GROUP BY TRIM(REPLACE(codigo, CHAR(9), '')), alma
                    ) z ON z.codigo = TRIM(REPLACE(m.codigo, CHAR(9), '')) AND z.alma = ? ";

            if ($esSalida) {
                $sql .= " WHERE COALESCE(z.qstock, 0) > 0 ";
            }

            $sql .= " ORDER BY m.descri
                      LIMIT {$limit} OFFSET {$offset}";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([$alma]);
        } else {
            $stmt = $this->db->prepare(
                "SELECT codigo AS tcodigo, MIN(descri) AS tdescri, MIN(unidad) AS tunidad,
                        MIN(peso) AS tpeso, MIN(cuenta) AS tcuenta, 0 AS tstock
                 FROM mitm
                 WHERE alma IN ('010','018')
                 GROUP BY codigo
                 ORDER BY MIN(descri)
                 LIMIT {$limit} OFFSET {$offset}"
            );
            $stmt->execute();
        }
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function buscarProductos(string $termino, int $limit = 200, int $offset = 0, string $alma = '', string $codtra = ''): array
    {
        $limit = max(1, min(500, (int)$limit));
        $offset = max(0, (int)$offset);
        $like = "%{$termino}%";

        $codtra = trim($codtra);
        $esSalida = ($codtra !== '' && strtoupper(substr($codtra, 0, 1)) === 'S');

        if ($alma !== '') {
            $sql = "SELECT m.codigo AS tcodigo, m.descri AS tdescri, m.unidad AS tunidad,
                           m.peso AS tpeso, m.cuenta AS tcuenta,
                           COALESCE(z.qstock, 0) AS tstock
                    FROM (
                        SELECT codigo, MIN(descri) AS descri, MIN(unidad) AS unidad,
                               MIN(peso) AS peso, MIN(cuenta) AS cuenta
                        FROM mitm
                        WHERE (codigo LIKE ? OR descri LIKE ?)
                          AND alma IN ('010','018')
                        GROUP BY codigo
                    ) AS m
                    LEFT JOIN (
                        SELECT TRIM(REPLACE(codigo, CHAR(9), '')) AS codigo, alma, SUM(qstock) AS qstock
                        FROM mzon
                        GROUP BY TRIM(REPLACE(codigo, CHAR(9), '')), alma
                    ) z ON z.codigo = TRIM(REPLACE(m.codigo, CHAR(9), '')) AND z.alma = ? ";

            if ($esSalida) {
                $sql .= " WHERE COALESCE(z.qstock, 0) > 0 ";
            }

            $sql .= " ORDER BY m.codigo
                      LIMIT {$limit} OFFSET {$offset}";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([$like, $like, $alma]);
        } else {
            $stmt = $this->db->prepare(
                "SELECT codigo AS tcodigo, MIN(descri) AS tdescri, MIN(unidad) AS tunidad,
                        MIN(peso) AS tpeso, MIN(cuenta) AS tcuenta, 0 AS tstock
                 FROM mitm
                 WHERE (codigo LIKE ? OR descri LIKE ?)
                   AND alma IN ('010','018')
                 GROUP BY codigo
                 ORDER BY MIN(codigo)
                 LIMIT {$limit} OFFSET {$offset}"
            );
            $stmt->execute([$like, $like]);
        }
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getLotes(string $alma = '', string $codigo = '', string $fecha = ''): array
    {
        // Modo condicional solicitado: almacén + producto + fecha de corte.
        if ($alma !== '' && $codigo !== '' && $fecha !== '') {
             $stmt = $this->db->prepare(
                 "SELECT m.codigo, m.lote, m.qstock AS cantidad, m.pstock AS peso, m.vstock AS valor
                  FROM mzon m
                  WHERE m.alma = ? AND TRIM(REPLACE(m.codigo, CHAR(9), '')) = TRIM(REPLACE(?, CHAR(9), ''))
                  ORDER BY m.lote"
             );
             $stmt->execute([$alma, $codigo]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        }

        $params = [];
        $where = [];

        if ($alma !== '') {
            $where[] = 'm.alma = ?';
            $params[] = $alma;
        }

        $sqlWhere = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

        $stmt = $this->db->prepare(
            "SELECT m.codigo, m.lote
             FROM mzon m
             {$sqlWhere}
             GROUP BY m.codigo, m.lote
             ORDER BY m.codigo, m.lote
             LIMIT 1000"
        );
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getProductoById(string $codigo): ?array
    {
        $stmt = $this->db->prepare(
            "SELECT codigo AS tcodigo, descri AS tdescri, unidad AS tunidad,
                    peso AS tpeso, cuenta AS tcuenta
             FROM mitm WHERE codigo = ?"
        );
        $stmt->execute([$codigo]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function getClientePorCodigo(string $codigo): ?array
    {
        $codigoTrimmed = trim($codigo);
        if ($codigoTrimmed === '') return null;
        // REPLACE(CHAR(9)) + TRIM para manejar codigos con tabs/espacios en la BD
        // MySQL TRIM() solo quita espacios, no tabs (ASCII 9)
        $stmt = $this->db->prepare(
            "SELECT TRIM(REPLACE(codigo, CHAR(9), '')) AS codigo,
                    COALESCE(NULLIF(TRIM(MAX(nombre)), ''), '') AS nombre
             FROM ccte
             WHERE TRIM(REPLACE(codigo, CHAR(9), '')) = ?
             GROUP BY TRIM(REPLACE(codigo, CHAR(9), ''))
             LIMIT 1"
        );
        $stmt->execute([$codigoTrimmed]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }


    // ─── ABC COSTING ─────────────────────────────────────────────────────────

    public function getProcesos(): array
    {
        $stmt = $this->db->prepare(
            "SELECT tcod_proceso, tnom_proceso FROM reg_costoabc_proceso
             WHERE testado = 'A' ORDER BY torden"
        );
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getSubprocesos(string $codProceso): array
    {
        $stmt = $this->db->prepare(
            "SELECT tcod_subproc, tnom_subproc FROM reg_costoabc_subproceso
             WHERE tcod_proceso = ? AND testado = 'A' ORDER BY torden"
        );
        $stmt->execute([$codProceso]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getActividades(string $codProceso, string $codSubproc): array
    {
        $stmt = $this->db->prepare(
            "SELECT tcod_acti, tnom_acti FROM reg_costoabc_actividad
             WHERE tcod_proc = ? AND tcod_subproc = ? AND testado = 'A' ORDER BY torden"
        );
        $stmt->execute([$codProceso, $codSubproc]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getTareas(string $codProceso, string $codSubproc, string $codActi): array
    {
        $stmt = $this->db->prepare(
            "SELECT tcod_tarea, tnom_tarea FROM reg_costoabc_tarea
             WHERE tcod_proc = ? AND tcod_subproc = ? AND tcod_acti = ?
             AND testado = 'A' ORDER BY torden"
        );
        $stmt->execute([$codProceso, $codSubproc, $codActi]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // ─── NÚMERO DE REGISTRO ──────────────────────────────────────────────────

    public function getNuevoReg(): int
    {
        // MAX global sin filtro mark (igual al VBA original)
        $stmtGuia = $this->db->query("SELECT COALESCE(MAX(treg), 0) FROM guia");
        $maxGuia = (int)$stmtGuia->fetchColumn();

        $stmtImov = $this->db->query("SELECT COALESCE(MAX(treg), 0) FROM imov");
        $maxImov = (int)$stmtImov->fetchColumn();

        return max($maxGuia, $maxImov) + 1;
    }

    public function validarConsistenciaReg(int $treg): bool
    {
        $stmtGuia = $this->db->prepare(
            "SELECT COUNT(*) FROM guia WHERE treg = ? AND mark = ?"
        );
        $stmtGuia->execute([$treg, $this->mark]);
        $enGuia = (int)$stmtGuia->fetchColumn();

        $stmtImov = $this->db->prepare(
            "SELECT COUNT(*) FROM imov WHERE treg = ? AND mark IN (?,?,?)"
        );
        $stmtImov->execute(array_merge([$treg], $this->imovMarks()));
        $enImov = (int)$stmtImov->fetchColumn();

        return $enGuia === 0 && $enImov === 0;
    }

    // ─── CABECERA (guia) ─────────────────────────────────────────────────────

    public function listarMovimientos(array $filtros = []): array
    {
        $where = ["g.mark = ?"];
        $params = [$this->mark, $this->mark];

        if (!empty($filtros['talm'])) {
            $where[] = "g.talm = ?";
            $params[] = $filtros['talm'];
        }
        if (!empty($filtros['fecini'])) {
            $where[] = "g.tfectra >= ?";
            $params[] = $filtros['fecini'];
        }
        if (!empty($filtros['fecfin'])) {
            $where[] = "g.tfectra <= ?";
            $params[] = $filtros['fecfin'];
        }
        if (!empty($filtros['tcodtra'])) {
            $where[] = "g.tcodtra = ?";
            $params[] = $filtros['tcodtra'];
        }

        $sql = "SELECT g.treg, g.tfectra, g.tcodtra, g.tdoc, g.tserie, g.tnumfac,
                  g.talm, d.talr, g.tprocli, g.tmon, g.tlib, g.tordcom,
                  g.tfecfac, d.tcencos_dest, g.tglosa, g.tcostmin, g.tpesotot,
                  g.timport, g.tcod_conductor, g.tplaca, g.tmotivo_traslado,
                  g.tuser, g.tdate, g.ttime,
                  a.descri AS nom_almacen, c.descri AS nom_transaccion
                FROM guia g
                LEFT JOIN (
                  SELECT i.treg,
                      MIN(i.talr) AS talr,
                      MIN(i.tcencos) AS tcencos_dest
                  FROM imov i
                  WHERE i.mark = ?
                  GROUP BY i.treg
                ) d ON d.treg = g.treg
                LEFT JOIN alma a ON g.talm = a.codalm
                LEFT JOIN coal c ON g.tcodtra = c.codtra
                WHERE " . implode(' AND ', $where) . "
                ORDER BY g.tfectra DESC, g.treg DESC
                LIMIT 200";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function listarMovimientosDashboard(array $filtros = []): array {
        $page    = max(1, (int)($filtros['page'] ?? 1));
        $perPage = max(1, min(500, (int)($filtros['per_page'] ?? 25)));
        $offset  = ($page - 1) * $perPage;

        // Filtros base sobre guia e imov
        $where  = ["g.mark = ?"];
        $params = [$this->mark];

        if (!empty($filtros['talm'])) {
            $where[]  = 'i.talm = ?';
            $params[] = trim((string)$filtros['talm']);
        }

        if (!empty($filtros['tcodtra'])) {
            $where[]  = 'i.tcodtra = ?';
            $params[] = trim((string)$filtros['tcodtra']);
        }

        if (!empty($filtros['fecini'])) {
            $where[]  = 'g.tfectra >= ?';
            $params[] = trim((string)$filtros['fecini']);
        }

        if (!empty($filtros['fecfin'])) {
            $where[]  = 'g.tfectra <= ?';
            $params[] = trim((string)$filtros['fecfin']);
        }

        $search = trim((string)($filtros['q'] ?? ''));
        if ($search !== '') {
            $like    = "%{$search}%";
            $where[] = "(
                CAST(g.treg AS CHAR) LIKE ?
                OR g.tprocli LIKE ?
                OR g.tdoc LIKE ?
                OR g.tserie LIKE ?
                OR CAST(g.tnumfac AS CHAR) LIKE ?
                OR g.tglosa LIKE ?
                OR a.descri LIKE ?
                OR c.descri LIKE ?
            )";
            for ($i = 0; $i < 8; $i++) {
                $params[] = $like;
            }
        }

        $whereSql = implode(' AND ', $where);

        // 1. Resumen unificado y conteo filtrado en UNA sola consulta optimizada
        $sqlSummary = "SELECT 
                            COUNT(*) AS total_movimientos,
                            COALESCE(SUM(sub.timporttot), 0) AS total_importe,
                            COALESCE(SUM(sub.tpesotot), 0) AS total_peso
                       FROM (
                            SELECT 
                                SUM(i.timport) AS timporttot,
                                SUM(i.tpeso) AS tpesotot
                            FROM guia g
                            INNER JOIN imov i ON i.treg = g.treg AND i.mark IN ('J', 'CW1', 'CW2')
                            LEFT JOIN alma a ON i.talm = a.codalm
                            LEFT JOIN coal c ON i.tcodtra = c.codtra
                            WHERE {$whereSql}
                            GROUP BY g.treg, i.tcodtra, i.talm
                       ) AS sub";

        $stmtSummary = $this->db->prepare($sqlSummary);
        $stmtSummary->execute($params);
        $resumenRow = $stmtSummary->fetch(PDO::FETCH_ASSOC);

        $totalFiltered = (int)($resumenRow['total_movimientos'] ?? 0);
        $totalImporte  = (float)($resumenRow['total_importe'] ?? 0);
        $totalPeso     = (float)($resumenRow['total_peso'] ?? 0);

        // 2. Conteo total sin filtros adicionales (recordsTotal para DataTables)
        $hasExtraFilters = !empty($filtros['talm']) || !empty($filtros['tcodtra']) || 
                           !empty($filtros['fecini']) || !empty($filtros['fecfin']) || $search !== '';

        if (!$hasExtraFilters) {
            $recordsTotal = $totalFiltered;
        } else {
            $sqlTotal = "SELECT COUNT(*) FROM (
                            SELECT 1
                            FROM guia g
                            INNER JOIN imov i ON i.treg = g.treg AND i.mark IN ('J', 'CW1', 'CW2')
                            WHERE g.mark = ?
                            GROUP BY g.treg, i.tcodtra, i.talm
                         ) AS sub_total";
            $stmtTotal = $this->db->prepare($sqlTotal);
            $stmtTotal->execute([$this->mark]);
            $recordsTotal = (int)($stmtTotal->fetchColumn() ?: 0);
        }

        // 3. Mapeo seguro de columnas para ordenamiento dinámico (DataTables)
        $sortableColumns = [
            'treg'            => 'g.treg',
            'tfectra'         => 'g.tfectra',
            'tcodtra'         => 'i.tcodtra',
            'talm'            => 'i.talm',
            'tprocli'         => 'g.tprocli',
            'tdoc'            => 'g.tdoc',
            'tserie'          => 'g.tserie',
            'tnumfac'         => 'g.tnumfac',
            'tglosa'          => 'g.tglosa',
            'tpesotot'        => 'tpesotot',
            'timport'         => 'timporttot',
            'timporttot'      => 'timporttot',
            'nom_almacen'     => 'nom_almacen',
            'nom_transaccion' => 'nom_transaccion',
            'tuser'           => 'g.tuser',
        ];

        $orderCol = trim((string)($filtros['order_column'] ?? ''));
        $orderDir = strtoupper(trim((string)($filtros['order_dir'] ?? 'DESC'))) === 'ASC' ? 'ASC' : 'DESC';

        if (isset($sortableColumns[$orderCol])) {
            $orderSql = "{$sortableColumns[$orderCol]} {$orderDir}";
        } else {
            $orderSql = "g.tfectra DESC, g.treg DESC, i.tcodtra DESC";
        }

        // 4. Obtención de la página de filas requerida
        $sqlRows = "SELECT 
                        g.treg, 
                        MAX(g.tfectra) AS tfectra, 
                        i.tcodtra, 
                        i.talm,
                        MAX(g.tprocli) AS tprocli, 
                        MAX(g.tdoc) AS tdoc, 
                        MAX(g.tserie) AS tserie, 
                        MAX(g.tnumfac) AS tnumfac,
                        MAX(g.tglosa) AS tglosa, 
                        SUM(i.tpeso) AS tpesotot, 
                        SUM(i.timport) AS timporttot, 
                        SUM(i.timport) AS timport, 
                        MAX(g.tuser) AS tuser,
                        MAX(g.tdate) AS tdate, 
                        MAX(g.ttime) AS ttime, 
                        MAX(g.tmon) AS tmon,
                        IF(i.tcodtra = 'E005', '-', MIN(i.talr)) AS talr,
                        MAX(a.descri) AS nom_almacen,
                        COALESCE(MAX(c.descri), IF(i.tcodtra='E005', 'INGRESO TRANSFERENCIA GRANJAS L', 'MOVIMIENTO')) AS nom_transaccion,
                        MAX(c.gentsa) AS gentsa
                    FROM guia g
                    INNER JOIN imov i ON i.treg = g.treg AND i.mark IN ('J', 'CW1', 'CW2')
                    LEFT JOIN alma a ON i.talm = a.codalm
                    LEFT JOIN coal c ON i.tcodtra = c.codtra
                    WHERE {$whereSql}
                    GROUP BY g.treg, i.tcodtra, i.talm
                    ORDER BY {$orderSql}
                    LIMIT {$perPage} OFFSET {$offset}";

        $stmtRows = $this->db->prepare($sqlRows);
        $stmtRows->execute($params);
        $rows = $stmtRows->fetchAll(PDO::FETCH_ASSOC);

        return [
            'rows' => $rows,
            'meta' => [
                'page'            => $page,
                'per_page'        => $perPage,
                'total'           => $totalFiltered,
                'recordsTotal'    => $recordsTotal,
                'recordsFiltered' => $totalFiltered,
                'total_pages'     => $totalFiltered > 0 ? (int)ceil($totalFiltered / $perPage) : 1,
                'resumen' => [
                    'total_movimientos' => $totalFiltered,
                    'total_importe'     => $totalImporte,
                    'total_peso'        => $totalPeso,
                ],
            ],
        ];
    }

    public function getMovimientoPorReg(string $treg): ?array
    {
        $stmt = $this->db->prepare(
            "SELECT g.*, a.descri AS nom_almacen, c.descri AS nom_transaccion,
                    c.emidoc, c.precio, c.cencos, c.gragui, c.pidemotivo,
                    c.pmoned, c.observ, c.ordcom, c.gentsa, c.merma
             FROM guia g
             LEFT JOIN alma a ON g.talm = a.codalm
             LEFT JOIN coal c ON g.tcodtra = c.codtra
             WHERE g.treg = ? AND g.mark = ?"
        );
        $stmt->execute([$treg, $this->mark]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            return $row;
        }

        // Fallback para registros históricos con mark distinto al configurado.
        $stmt = $this->db->prepare(
            "SELECT g.*, a.descri AS nom_almacen, c.descri AS nom_transaccion,
                    c.emidoc, c.precio, c.cencos, c.gragui, c.pidemotivo,
                    c.pmoned, c.observ, c.ordcom, c.gentsa, c.merma
             FROM guia g
             LEFT JOIN alma a ON g.talm = a.codalm
             LEFT JOIN coal c ON g.tcodtra = c.codtra
             WHERE g.treg = ?
             ORDER BY g.mark ASC
             LIMIT 1"
        );
        $stmt->execute([$treg]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function crearCabecera(array $data): bool
    {
        $stmt = $this->db->prepare(
            "INSERT INTO guia (
                treg, tfectra, tcodtra, talm, tprocli, tdoc, tserie, tnumfac,
                tfecfac, tmon, tlib, tordcom, tglosa, tcostmin, tpesotot,
                timport, tcod_conductor, tplaca, tmotivo_traslado,
                tuser, tdate, ttime, mark
             ) VALUES (
                :treg, :tfectra, :tcodtra, :talm, :tprocli, :tdoc, :tserie, :tnumfac,
                :tfecfac, :tmon, :tlib, :tordcom, :tglosa, :tcostmin, :tpesotot,
                :timport, :tcod_conductor, :tplaca, :tmotivo_traslado,
                :tuser, CURDATE(), CURTIME(), :mark
             )"
        );
        return $stmt->execute(array_merge($data, ['mark' => $this->mark]));
    }

    public function actualizarCabecera(int $treg, array $data): bool
    {
        $data['treg']  = $treg;
        $data['mark']  = $this->mark;
        $stmt = $this->db->prepare(
            "UPDATE guia SET
                tfectra = :tfectra, tcodtra = :tcodtra, talm = :talm,
                tprocli = :tprocli, tdoc = :tdoc, tserie = :tserie,
                tnumfac = :tnumfac, tfecfac = :tfecfac, tmon = :tmon,
                tlib = :tlib, tordcom = :tordcom, tglosa = :tglosa,
                tcostmin = :tcostmin, tpesotot = :tpesotot, timport = :timport,
                tcod_conductor = :tcod_conductor, tplaca = :tplaca,
                tmotivo_traslado = :tmotivo_traslado
             WHERE treg = :treg AND mark = :mark"
        );
        return $stmt->execute($data);
    }

    public function eliminarCabecera(int $treg): bool
    {
        $stmt = $this->db->prepare(
            "DELETE FROM guia WHERE treg = ? AND mark = ?"
        );
        return $stmt->execute([$treg, $this->mark]);
    }

    // ─── DETALLE (imov) ──────────────────────────────────────────────────────

    public function getDetallePorReg(string $treg): array
    {
        // Buscar incluyendo todas las marcas imov válidas
        $stmt = $this->db->prepare(
            "SELECT i.*, m.descri AS nom_producto, m.unidad AS tunidad
             FROM imov i
             LEFT JOIN mitm m ON i.tcodigo = m.codigo
             WHERE i.treg = ? AND i.mark IN (?,?,?)
             ORDER BY i.count"
        );
        $stmt->execute(array_merge([$treg], $this->imovMarks()));
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getMaxCount(int $treg): int
    {
        $stmt = $this->db->prepare(
            "SELECT COALESCE(MAX(count), 0) FROM imov WHERE treg = ? AND mark IN (?,?,?)"
        );
        $stmt->execute(array_merge([$treg], $this->imovMarks()));
        return (int)$stmt->fetchColumn();
    }

    /**
     * Inserta una línea en imov.
     * @param array  $data         Campos del item
     * @param string|null $markOverride  Marca a usar ('CW1' por defecto, 'CW2' para contra-asientos)
     */
    public function agregarDetalle(array $data, string $markOverride = null): bool
    {
        $markValue = $markOverride ?? $this->markImov;
        $stmt = $this->db->prepare(
            "INSERT INTO imov (
                treg, count, tcodigo, tfectra, tcodtra, talm, talr,
                tcantid, tpreuni, timport, tpeso, tkardex, tmon,
                tcencos, tsacos, tnumlot, tlote, tdf, tctabal,
                tfecfac, tcodproc, tcodsubproc, tcodacti, tcodtarea,
                ttoneladas, tprod, tglosa, tuser, tdate, ttime, mark,
                tlib, tnumreg, tdoc, tserie, tnumfac
             ) VALUES (
                :treg, :count, :tcodigo, :tfectra, :tcodtra, :talm, :talr,
                :tcantid, :tpreuni, :timport, :tpeso, :tkardex, :tmon,
                :tcencos, :tsacos, :tnumlot, :tlote, :tdf, :tctabal,
                :tfecfac, :tcodproc, :tcodsubproc, :tcodacti, :tcodtarea,
                :ttoneladas, :tprod, :tglosa, :tuser, CURDATE(), CURTIME(), :mark,
                :tlib, :tnumreg, :tdoc, :tserie, :tnumfac
             )"
        );
        return $stmt->execute(array_merge($data, ['mark' => $markValue]));
    }

    public function eliminarDetalle(int $treg, int $count): bool
    {
        $stmt = $this->db->prepare(
            "DELETE FROM imov WHERE treg = ? AND count = ? AND mark IN (?,?,?)"
        );
        return $stmt->execute(array_merge([$treg, $count], $this->imovMarks()));
    }

    public function eliminarDetalleCompleto(int $treg): bool
    {
        $stmt = $this->db->prepare(
            "DELETE FROM imov WHERE treg = ? AND mark IN (?,?,?)"
        );
        return $stmt->execute(array_merge([$treg], $this->imovMarks()));
    }

    // ─── SALIDAS RÁPIDAS ────────────────────────────────────────────────────

    public function getLineas(): array
    {
        $stmt = $this->db->prepare(
            "SELECT l.linea AS codigo, l.descri
             FROM linea l
             INNER JOIN mitm mt ON mt.lin = l.linea
             WHERE mt.alma IN ('010','018')
             GROUP BY l.linea, l.descri
             ORDER BY l.linea"
        );
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getProductosStockSalida(string $alma, string $linea = ''): array
    {
        $lineaSql = $linea !== '' ? ' AND mt.lin = ?' : '';
        $params   = $linea !== '' ? [$alma, $linea] : [$alma];
        $stmt = $this->db->prepare(
            "SELECT mz.alma AS alm, mz.codigo, mt.descri AS descripcion,
                    mt.unidad, mz.lote, mt.lin AS linea,
                    COALESCE(mz.qstock, 0) AS stock,
                    COALESCE(mz.pstock, 0) AS peso_stock
             FROM mzon mz
             INNER JOIN mitm mt ON mt.codigo = mz.codigo
             WHERE mz.alma = ?
               AND mt.alma IN ('010','018')
               AND mz.qstock > 0
               {$lineaSql}
             ORDER BY mt.descri, mz.lote
             LIMIT 1000"
        );
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function buscarProductosStockSalida(string $alma, string $termino, string $linea = ''): array
    {
        $like     = "%{$termino}%";
        $lineaSql = $linea !== '' ? ' AND mt.lin = ?' : '';
        $params   = $linea !== '' ? [$alma, $like, $like, $linea] : [$alma, $like, $like];
        $stmt = $this->db->prepare(
            "SELECT mz.alma AS alm, mz.codigo, mt.descri AS descripcion,
                    mt.unidad, mz.lote, mt.lin AS linea,
                    COALESCE(mz.qstock, 0) AS stock,
                    COALESCE(mz.pstock, 0) AS peso_stock
             FROM mzon mz
             INNER JOIN mitm mt ON mt.codigo = mz.codigo
             WHERE mz.alma = ?
               AND mt.alma IN ('010','018')
               AND mz.qstock > 0
               AND (mz.codigo LIKE ? OR mt.descri LIKE ?)
               {$lineaSql}
             ORDER BY mt.descri, mz.lote
             LIMIT 500"
        );
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getMisSalidasUsuario(string $fecha, string $usuario): array
    {
        $stmt = $this->db->prepare(
            "SELECT g.treg, g.tfectra, g.tcodtra, g.talm, g.tprocli,
                    g.tdoc, g.tserie, g.tnumfac, g.tglosa,
                    COALESCE(g.tpesotot, 0) AS tpesotot,
                    COALESCE(g.timport, 0) AS timport,
                    g.tuser, g.ttime,
                    c.descri AS nom_transaccion,
                    a.descri AS nom_almacen,
                    COALESCE(cc.nombre, g.tprocli) AS nom_solicitante
             FROM guia g
             LEFT JOIN coal c ON c.codtra = g.tcodtra
             LEFT JOIN alma a ON a.codalm = g.talm
             LEFT JOIN (
                 SELECT codigo, MAX(nombre) AS nombre FROM ccte GROUP BY codigo
             ) cc ON cc.codigo = g.tprocli
             WHERE g.mark = ?
               AND (g.tuser = ? OR g.tuser IS NULL OR g.tuser = '')
               AND g.tfectra = ?
               AND LEFT(g.tcodtra, 1) = 'S'
             ORDER BY g.ttime DESC, g.treg DESC"
        );
        $stmt->execute([$this->mark, $usuario, $fecha]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // ─── STOCK / KARDEX (mzon) ───────────────────────────────────────────────

    public function getKardex(string $codigo, string $lote, string $alma): ?array
    {
        $stmt = $this->db->prepare(
            "SELECT qiniano, piniano, viniano, qingre, qsalid, qstock,
                    cosuni, vstock, pingre, psalid, pstock
             FROM mzon WHERE codigo = ? AND lote = ? AND alma = ?"
        );
        $stmt->execute([$codigo, $lote, $alma]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function getResumenHastaFecha(string $codigo, string $lote, string $alma, string $fecha): ?array
    {
        $sql = "SELECT frm1.codigo,
                       frm1.lote,
                       SUM(frm1.cantidad) AS cantidad,
                       SUM(frm1.peso) AS peso,
                       SUM(frm1.valor) AS valor
                FROM (
                    SELECT m.codigo AS codigo,
                           m.lote AS lote,
                           m.qiniano AS cantidad,
                           m.piniano AS peso,
                           m.viniano AS valor
                    FROM mzon m
                    WHERE m.alma = :alma
                      AND m.codigo = :codigo
                      AND m.lote = :lote

                    UNION ALL

                    SELECT i.tcodigo AS codigo,
                           i.tlote AS lote,
                           SUM(IF(LEFT(i.tcodtra, 1) = 'E', i.tcantid, (-1) * i.tcantid)) AS cantidad,
                           SUM(IF(LEFT(i.tcodtra, 1) = 'E', i.tpeso,   (-1) * i.tpeso))   AS peso,
                           SUM(IF(LEFT(i.tcodtra, 1) = 'E', i.tkardex, (-1) * i.tkardex)) AS valor
                    FROM imov i
                    WHERE i.mark IN ('J','CW1','CW2')
                      AND i.talm = :alma
                      AND i.tcodigo = :codigo
                      AND i.tfectra <= :fecha
                      AND i.tlote = :lote
                    GROUP BY i.tcodigo, i.tlote
                ) AS frm1
                GROUP BY frm1.codigo, frm1.lote";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'alma' => $alma,
            'codigo' => $codigo,
            'lote' => $lote,
            'fecha' => $fecha,
        ]);

        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function getStockPorAlmacen(string $alma): array
    {
        $stmt = $this->db->prepare(
            "SELECT m.codigo, m.lote, m.qstock, m.pstock, m.vstock,
                    m.cosuni, mt.tdescri AS descripcion, mt.tunidad
             FROM mzon m
             LEFT JOIN mitm mt ON m.codigo = mt.tcodigo
             WHERE m.alma = ? AND m.qstock > 0
             ORDER BY m.codigo"
        );
        $stmt->execute([$alma]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getKardexMovimientosReporte(array $filtros): array
    {
        $alma = (string)($filtros['alma'] ?? '');
        if ($alma === '') {
            return [];
        }

        $where = [
            "i.mark IN ('J','CW1','CW2')",
            'g.mark = ?',
            'i.talm = ?'
        ];
        $params = [$this->mark, $alma];

        if (!empty($filtros['fecha_desde'])) {
            $where[] = 'g.tfectra >= ?';
            $params[] = $filtros['fecha_desde'];
        }

        if (!empty($filtros['fecha_hasta'])) {
            $where[] = 'g.tfectra <= ?';
            $params[] = $filtros['fecha_hasta'];
        }

        if (!empty($filtros['codigo_desde'])) {
            $where[] = 'i.tcodigo >= ?';
            $params[] = $filtros['codigo_desde'];
        }

        if (!empty($filtros['codigo_hasta'])) {
            $where[] = 'i.tcodigo <= ?';
            $params[] = $filtros['codigo_hasta'];
        }

        $sql = "SELECT
                    g.tfectra,
                    i.tcodigo,
                    COALESCE(m.descri, '') AS descripcion,
                    COALESCE(g.tdoc, '') AS tdoc,
                    COALESCE(g.tserie, '') AS tserie,
                    COALESCE(g.tnumfac, '') AS tnumfac,
                    COALESCE(g.tprocli, '') AS clipro,
                    COALESCE(i.tcantid, 0) AS tcantid,
                    COALESCE(i.timport, 0) AS timport,
                    COALESCE(i.tpreuni, 0) AS tpreuni,
                    COALESCE(c.gentsa, 0) AS gentsa,
                    i.treg,
                    i.count
                FROM imov i
                INNER JOIN guia g ON g.treg = i.treg AND g.mark = i.mark
                LEFT JOIN mitm m ON m.codigo = i.tcodigo
                LEFT JOIN coal c ON c.codtra = g.tcodtra
                WHERE " . implode(' AND ', $where) . "
                ORDER BY i.tcodigo, g.tfectra, i.treg, i.count";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Actualizar mzon al grabar un movimiento (entrada/salida)
    public function actualizarStock(
        string $codigo,
        string $lote,
        string $alma,
        float $cantidad,
        float $peso,
        float $valor,
        string $tipo // 'E'=entrada, 'S'=salida
    ): bool {
        $existe = $this->getKardex($codigo, $lote, $alma);

        if (!$existe) {
            // Crear registro en mzon
            $stmt = $this->db->prepare(
                "INSERT INTO mzon (codigo, lote, alma, qingre, qsalid, qstock,
                                   pingre, psalid, pstock, vingre, vsalid, vstock, cosuni)
                 VALUES (?, ?, ?, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0)"
            );
            $stmt->execute([$codigo, $lote, $alma]);
        }

        if ($tipo === 'E') {
            $sql = "UPDATE mzon SET
                        qingre = qingre + :cant, qstock = qstock + :cant,
                        pingre = pingre + :peso, pstock = pstock + :peso,
                        vingre = vingre + :valor, vstock = vstock + :valor
                    WHERE codigo = :cod AND lote = :lote AND alma = :alma";
        } else {
            $sql = "UPDATE mzon SET
                        qsalid = qsalid + :cant, qstock = qstock - :cant,
                        psalid = psalid + :peso, pstock = pstock - :peso,
                        vsalid = vsalid + :valor, vstock = vstock - :valor
                    WHERE codigo = :cod AND lote = :lote AND alma = :alma";
        }

        $stmt = $this->db->prepare($sql);
        return $stmt->execute([
            'cant'  => $cantidad,
            'peso'  => $peso,
            'valor' => $valor,
            'cod'   => $codigo,
            'lote'  => $lote,
            'alma'  => $alma
        ]);
    }

    public function getCorrelativo(string $tdoc, string $tserie): string
    {
        $sql = "SELECT COALESCE(MAX(CAST(tnumfac AS UNSIGNED)), 0) + 1 AS correlativo 
                FROM guia 
                WHERE tdoc = :tdoc AND tserie = :tserie";
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':tdoc', $tdoc, PDO::PARAM_STR);
        $stmt->bindValue(':tserie', $tserie, PDO::PARAM_STR);
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return (string)($row['correlativo'] ?? '1');
    }
}
