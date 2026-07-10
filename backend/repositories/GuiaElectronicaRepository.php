<?php

/**
 * Repositorio para la gestión de Guías de Remisión Electrónica
 */
class GuiaElectronicaRepository
{
    private $db;

    public function __construct($db)
    {
        $this->db = $db;
    }

    public function obtenerAlmacenes(): array
    {
        $sql = "SELECT codalm AS codigo, descri AS descripcion, descri AS descri 
                FROM alma 
                WHERE codalm IS NOT NULL AND codalm != ''
                ORDER BY codalm ASC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function obtenerTiposTransporte(): array
    {
        $sql = "SELECT cod AS codigo, nom AS descripcion, cod, nom 
                FROM dtipo_transporte 
                WHERE est = 'A'
                ORDER BY cod ASC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function obtenerTransportistas(?string $search = null): array
    {
        $sql = "SELECT ruc, nombre, nombrecomercial, tuc, testado 
                FROM dtransportista WHERE testado = 'A'";

        $params = [];
        if ($search !== null && trim($search) !== '') {
            $sql .= " AND (ruc LIKE ? OR nombre LIKE ? OR nombrecomercial LIKE ?)";
            $term = '%' . trim($search) . '%';
            $params = [$term, $term, $term];
        }

        $sql .= " ORDER BY nombre ASC LIMIT 100";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function obtenerConductores(?string $search = null, ?string $rucTransportista = null, bool $mostrarTodos = false): array
    {
        $sql = "SELECT dni, nombre, licencia, testado 
                FROM dchofer WHERE testado = 'A'";

        $params = [];
        if ($rucTransportista !== null && trim($rucTransportista) !== '' && !$mostrarTodos) {
            $sql .= " AND tcod_transportista = ?";
            $params[] = trim($rucTransportista);
        }

        if ($search !== null && trim($search) !== '') {
            $sql .= " AND (dni LIKE ? OR nombre LIKE ?)";
            $term = '%' . trim($search) . '%';
            $params[] = $term;
            $params[] = $term;
        }

        $sql .= " ORDER BY nombre ASC LIMIT 100";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function obtenerCamiones(?string $search = null, ?string $rucTransportista = null): array
    {
        $sql = "SELECT placa, marca, cinscripcion, confvehicular, ntm, soat, fechaisoat, testado 
                FROM dcamion 
                WHERE testado = 'A'";

        $params = [];
        if ($rucTransportista !== null && trim($rucTransportista) !== '') {
            $sql .= " AND tcod_transportista = ?";
            $params[] = trim($rucTransportista);
        }

        if ($search !== null && trim($search) !== '') {
            $sql .= " AND (placa LIKE ? OR marca LIKE ?)";
            $term = '%' . trim($search) . '%';
            $params[] = $term;
            $params[] = $term;
        }

        $sql .= " ORDER BY placa ASC LIMIT 100";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function obtenerClientes(?string $search = null): array
    {
        $sql = "SELECT codigo, nombre, direcc
                FROM ccte";

        $params = [];
        if ($search !== null && trim($search) !== '') {
            $sql .= " WHERE codigo LIKE ? OR nombre LIKE ?";
            $term = '%' . trim($search) . '%';
            $params = [$term, $term];
        }

        $sql .= " ORDER BY codigo ASC LIMIT 100";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function obtenerArticulos(?string $search = null, ?string $almacen = null, ?int $anio = null): array
    {
        // Si no se especifica almacén o año, hacemos la consulta simple sin filtro de stock
        if (empty($almacen) || empty($anio)) {
            $sql = "SELECT codigo, descri, unidad 
                    FROM mitm 
                    WHERE estado = 'A'";
            $params = [];
            if ($search !== null && trim($search) !== '') {
                $sql .= " AND (codigo LIKE ? OR descri LIKE ?)";
                $term = '%' . trim($search) . '%';
                $params = [$term, $term];
            }
            $sql .= " ORDER BY descri ASC LIMIT 100";

            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        }

        // Si se especifica almacén y año, filtramos por stock real (Kardex) aplicando reglas estrictas
        $sql = "SELECT c.codigo, c.descri, c.unidad 
                FROM mitm c
                INNER JOIN (
                    SELECT 
                        k.codigo,
                        SUM(k.stock_cantidad) AS tot_cantidad,
                        SUM(k.stock_peso) AS tot_peso
                    FROM (
                        SELECT 
                            tcodigo AS codigo, 
                            SUM(IF(LEFT(tcodtra, 1) = 'E', tcantid, -1 * tcantid)) AS stock_cantidad, 
                            SUM(IF(LEFT(tcodtra, 1) = 'E', tpeso, -1 * tpeso)) AS stock_peso
                        FROM imov
                        WHERE talm = :almacen1 AND YEAR(tfectra) = :anio1
                        GROUP BY tcodigo

                        UNION ALL

                        SELECT 
                            codigo, 
                            SUM(qiniano) AS stock_cantidad, 
                            SUM(piniano) AS stock_peso
                        FROM mzon
                        WHERE alma = :almacen2
                        GROUP BY codigo
                    ) AS k
                    GROUP BY k.codigo
                ) AS stock_res ON c.codigo = stock_res.codigo
                WHERE c.estado = 'A'
                  AND (
                      (LEFT(:almacen3, 1) = 'M' AND c.unidad = 'KGS' AND stock_res.tot_peso > 0)
                      OR 
                      ( (LEFT(:almacen4, 1) != 'M' OR c.unidad != 'KGS') AND stock_res.tot_cantidad > 0 )
                  )";

        if ($search !== null && trim($search) !== '') {
            $sql .= " AND (c.codigo LIKE :search1 OR c.descri LIKE :search2)";
        }

        $sql .= " ORDER BY c.descri ASC LIMIT 100";

        $stmt = $this->db->prepare($sql);

        // Bindings para el cálculo de saldos y reglas de validación
        $stmt->bindValue(':almacen1', $almacen, PDO::PARAM_STR);
        $stmt->bindValue(':anio1', $anio, PDO::PARAM_INT);
        $stmt->bindValue(':almacen2', $almacen, PDO::PARAM_STR);
        $stmt->bindValue(':almacen3', $almacen, PDO::PARAM_STR);
        $stmt->bindValue(':almacen4', $almacen, PDO::PARAM_STR);

        // Bindings para el buscador si el usuario digitó algo
        if ($search !== null && trim($search) !== '') {
            $term = '%' . trim($search) . '%';
            $stmt->bindValue(':search1', $term, PDO::PARAM_STR);
            $stmt->bindValue(':search2', $term, PDO::PARAM_STR);
        }

        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function obtenerLotesPorArticulo(string $almacen, string $codigoArticulo, int $anio): array
    {
        // Consulta simplificada sin la tabla 'regcencosagranel'
        $sql = "
            SELECT 
                kardex.lote,
                ROUND(SUM(kardex.cantidad), 4) AS stock_cantidad,
                ROUND(SUM(kardex.peso), 2) AS stock_peso,
                MAX(kardex.unidad) AS unidad
            FROM (
                -- 1. Movimientos del año actual (imov)
                SELECT 
                    a.tlote AS lote,
                    c.unidad,
                    (IF(LEFT(a.tcodtra, 1) = 'E', a.tcantid, -1 * a.tcantid)) AS cantidad,
                    (IF(LEFT(a.tcodtra, 1) = 'E', a.tpeso, -1 * a.tpeso)) AS peso
                FROM imov a
                INNER JOIN mitm c ON a.tcodigo = c.codigo
                WHERE a.tcodigo = :codigo1 
                  AND a.talm = :almacen1 
                  AND YEAR(a.tfectra) = :anio
                  
                UNION ALL
                
                -- 2. Saldos Iniciales (mzon)
                SELECT 
                    m.lote,
                    c.unidad,
                    m.qiniano AS cantidad,
                    m.piniano AS peso
                FROM mzon m
                INNER JOIN mitm c ON m.codigo = c.codigo
                WHERE m.codigo = :codigo2 
                  AND m.alma = :almacen2
            ) AS kardex
            GROUP BY kardex.lote
            -- 3. Emulamos los DELETEs finales del VB6 para descartar los <= 0
            HAVING 
                (LEFT(:almacen3, 1) = 'M' AND MAX(kardex.unidad) = 'KGS' AND stock_peso > 0)
                OR 
                ( (LEFT(:almacen4, 1) != 'M' OR MAX(kardex.unidad) != 'KGS') AND stock_cantidad > 0 )
            ORDER BY kardex.lote ASC
        ";

        $stmt = $this->db->prepare($sql);

        $stmt->bindValue(':codigo1', $codigoArticulo, PDO::PARAM_STR);
        $stmt->bindValue(':almacen1', $almacen, PDO::PARAM_STR);
        $stmt->bindValue(':anio', $anio, PDO::PARAM_INT);

        $stmt->bindValue(':codigo2', $codigoArticulo, PDO::PARAM_STR);
        $stmt->bindValue(':almacen2', $almacen, PDO::PARAM_STR);

        $stmt->bindValue(':almacen3', $almacen, PDO::PARAM_STR);
        $stmt->bindValue(':almacen4', $almacen, PDO::PARAM_STR);

        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function obtenerSeriesConCorrelativo(string $almacen, string $cliente): array
    {
        $sql = "SELECT 
                    s.tserie AS serie, 
                    s.tdescripcion AS descripcion,
                    COALESCE(
                        (SELECT MAX(CAST(g.tnumfac AS UNSIGNED)) FROM guia g WHERE g.tdoc = '09' AND g.tserie = s.tserie),
                        0
                    ) + 1 AS correlativo
                FROM dserie s
                WHERE s.tzona = :almacen AND s.tprocli = :cliente AND s.tipo_doc = '09'
                ORDER BY s.tserie ASC";

        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':almacen', $almacen, PDO::PARAM_STR);
        $stmt->bindValue(':cliente', $cliente, PDO::PARAM_STR);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function obtenerMotivosTraslado(): array
    {
        $sql = "SELECT cod AS codigo, nom AS descripcion 
                FROM dmotivo_traslado 
                WHERE est = 'A' 
                ORDER BY ord ASC, cod ASC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function obtenerDireccionCliente(string $codigoCliente): ?array
    {
        $sql = "SELECT nombre, direcc, ubigeo FROM ccte WHERE codigo = :codigo LIMIT 1";
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':codigo', $codigoCliente, PDO::PARAM_STR);
        $stmt->execute();
        $res = $stmt->fetch(PDO::FETCH_ASSOC);
        return $res ? $res : null;
    }

    public function obtenerCencos(?string $search = null): array
    {
        $sql = "SELECT codigo, nombre AS descripcion 
                FROM ccos 
                WHERE swac = 'A'";
        $params = [];
        if ($search !== null && trim($search) !== '') {
            $sql .= " AND (codigo LIKE ? OR nombre LIKE ?)";
            $term = '%' . trim($search) . '%';
            $params = [$term, $term];
        }
        $sql .= " ORDER BY codigo ASC LIMIT 100";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    public function obtenerGalponesPorCencos(string $cencos): array
    {
        $sql = "SELECT tcodint AS galpon 
                FROM regcencosgalpones 
                WHERE tcencos = LEFT(:cencos, 3) 
                GROUP BY ABS(tcodint)";
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':cencos', $cencos, PDO::PARAM_STR);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getNuevoRegImov(): int
    {
        $stmtGuia = $this->db->query("SELECT COALESCE(MAX(treg), 0) FROM guia");
        $maxGuia = (int)$stmtGuia->fetchColumn();

        $stmtImov = $this->db->query("SELECT COALESCE(MAX(treg), 0) FROM imov");
        $maxImov = (int)$stmtImov->fetchColumn();

        return max($maxGuia, $maxImov) + 1;
    }

    public function getNuevoRegPro(): int
    {
        $stmtCabe = $this->db->query("SELECT COALESCE(MAX(treg), 0) FROM cabe_zonas");
        $maxCabe = (int)$stmtCabe->fetchColumn();

        $stmtMovi = $this->db->query("SELECT COALESCE(MAX(treg), 0) FROM movi_zonas");
        $maxMovi = (int)$stmtMovi->fetchColumn();

        return max($maxCabe, $maxMovi) + 1;
    }

    private function calcularPrecioUnitario(string $almacen, string $codigoArticulo, string $lote, int $anio): float
    {
        $sql = "
            SELECT 
                COALESCE(SUM(tcantid), 0) AS total_cantidad,
                COALESCE(SUM(tkardex), 0) AS total_valor
            FROM (
                -- 1. Movimientos (imov)
                SELECT 
                    SUM(IF(LEFT(a.tcodtra, 1) = 'E', a.tcantid, 
                        -1 * IF(c.lin = '002' AND r.tgranel = 'S', 
                            IF(LEFT(a.tlote, 1) = 'P', ROUND(a.tpeso / c.tkilo_alim), 0), 
                            a.tcantid
                        )
                    )) AS tcantid,
                    SUM(IF(LEFT(a.tcodtra, 1) = 'E', a.tkardex, -1 * a.tkardex)) AS tkardex
                FROM imov a
                INNER JOIN mitm c ON a.tcodigo = c.codigo
                LEFT JOIN regcencosagranel r ON LEFT(a.tcencos, 3) = r.tcencos AND a.tgalpon = r.tgalpon
                WHERE a.talm = :almacen1 
                  AND a.tcodigo = :codigo1 
                  AND a.tlote = :lote1 
                  AND YEAR(a.tfectra) = :anio
                GROUP BY a.tcodigo

                UNION ALL

                -- 2. Saldo Inicial (mzon)
                SELECT 
                    SUM(qiniano) AS tcantid,
                    SUM(viniano) AS tkardex
                FROM mzon
                WHERE alma = :almacen2 
                  AND codigo = :codigo2 
                  AND lote = :lote2
                GROUP BY codigo
            ) AS t
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':almacen1', $almacen, PDO::PARAM_STR);
        $stmt->bindValue(':codigo1', $codigoArticulo, PDO::PARAM_STR);
        $stmt->bindValue(':lote1', $lote, PDO::PARAM_STR);
        $stmt->bindValue(':anio', $anio, PDO::PARAM_INT);

        $stmt->bindValue(':almacen2', $almacen, PDO::PARAM_STR);
        $stmt->bindValue(':codigo2', $codigoArticulo, PDO::PARAM_STR);
        $stmt->bindValue(':lote2', $lote, PDO::PARAM_STR);

        $stmt->execute();
        $res = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($res && (float)$res['total_cantidad'] != 0.0) {
            return round((float)$res['total_valor'] / (float)$res['total_cantidad'], 2);
        }

        return 0.0;
    }

    public function guardarGuia(array $cabecera, array $detalle): string
    {
        $this->db->beginTransaction();
        $tregCreado = '';
        try {
            $user = $cabecera['tuser'] ?? 'SYS';
            $fechaEmision = $cabecera['fechaEmision'];
            $fechaTraslado = $cabecera['fechaTraslado'];
            $anio = (int)date('Y', strtotime($fechaEmision));

            $transaccion = strtoupper($cabecera['transaccion']); // S440 o S400

            if ($transaccion === 'S440') {
                // ─────────────────────────────────────────────────────────────
                // FLUJO ALMACÉN (S440)
                // ─────────────────────────────────────────────────────────────

                // Documento 1 (Origen)
                $registro1 = $this->getNuevoRegImov();
                $tregCreado = $registro1;

                $sqlCab = "INSERT INTO guia (
                    tuser, tdate, ttime, tprocli, tdoc, tserie, tnumfac, tfectra, tlib, 
                    treg, talm, tnumreg, tcodtra, ttip_transporte, tmotivo_traslado, tdesmot_traslado,
                    tfecrem, tglosa, tcod_transportista, tcod_conductor, tplaca, tplaca2, 
                    tcli_origen, tcli_destino, tcanttot, tpesotot, mark, tgre
                ) VALUES (
                    :tuser, CURDATE(), CURTIME(), :tprocli, '09', :tserie, :tnumfac, :tfectra, 'AL', 
                    :treg, :talm, :tnumreg, :tcodtra, :ttip_transporte, :tmotivo_traslado, :tdesmot_traslado,
                    :tfecrem, :tglosa, :tcod_transportista, :tcod_conductor, :tplaca, :tplaca2, 
                    :tcli_origen, :tcli_destino, :tcanttot, :tpesotot, 'JE1', 'S'
                )";

                $stmtCab1 = $this->db->prepare($sqlCab);
                $stmtCab1->execute([
                    ':tuser' => $user,
                    ':tprocli' => $cabecera['clienteRuc'],
                    ':tserie' => $cabecera['serie'],
                    ':tnumfac' => $cabecera['numeroGuia'],
                    ':tfectra' => $fechaEmision,
                    ':treg' => $registro1,
                    ':talm' => $cabecera['zonaOrigen'],
                    ':tnumreg' => $cabecera['numeroGuia'],
                    ':tcodtra' => $transaccion,
                    ':ttip_transporte' => $cabecera['tipoTransporte'],
                    ':tmotivo_traslado' => $cabecera['motivoTraslado'],
                    ':tdesmot_traslado' => $cabecera['motivoTrasladoOtros'],
                    ':tfecrem' => $fechaTraslado,
                    ':tglosa' => $cabecera['observaciones'] ?: '-',
                    ':tcod_transportista' => $cabecera['codTransportista'],
                    ':tcod_conductor' => $cabecera['codConductor'],
                    ':tplaca' => $cabecera['placaP'],
                    ':tplaca2' => $cabecera['placaR'],
                    ':tcli_origen' => $cabecera['clienteOrigen'],
                    ':tcli_destino' => $cabecera['clienteDestino'],
                    ':tcanttot' => $cabecera['totalCantidad'],
                    ':tpesotot' => $cabecera['totalPeso']
                ]);

                // Detalle Documento 1
                $sqlDet1 = "INSERT INTO imov (
                    tuser, tdate, ttime, tcodigo, tfectra, tcodtra, tlib, tnumreg, treg,
                    talm, talr, tnumlot, tprocli, tdoc, tserie, tnumfac, tfecrem,
                    tcencos, tcoscen, tgalpon, tcantid, tsacos, tsacos2, tpeso, count, tlote,
                    tglosa, tdet_adicional, timport, tkardex, mark, tgre
                ) VALUES (
                    :tuser, CURDATE(), CURTIME(), :tcodigo, :tfectra, :tcodtra, 'AL', :tnumreg, :treg,
                    :talm, :talr, '00000000', :tprocli, '09', :tserie, :tnumfac, :tfecrem,
                    :tcencos, :tcoscen, :tgalpon, :tcantid, :tsacos, :tsacos2, :tpeso, :count, :tlote,
                    :tglosa, :tdet_adicional, :timport, :tkardex, 'JE1', :tgre
                )";
                $stmtDet1 = $this->db->prepare($sqlDet1);

                foreach ($detalle as $index => $item) {
                    $iPu = $this->calcularPrecioUnitario($cabecera['zonaOrigen'], $item['codigo'], $item['lote'], $anio);
                    $iImporte = round($item['cantidad'] * $iPu, 2);
                    $xGuiEle = (strpos(strtoupper($item['codigo']), 'E') === 0) ? 'N' : 'S';

                    $stmtDet1->execute([
                        ':tuser' => $user,
                        ':tcodigo' => $item['codigo'],
                        ':tfectra' => $fechaEmision,
                        ':tcodtra' => $transaccion,
                        ':tnumreg' => $cabecera['numeroGuia'],
                        ':treg' => $registro1,
                        ':talm' => $cabecera['zonaOrigen'],
                        ':talr' => $cabecera['zonaDestino'],
                        ':tprocli' => $cabecera['clienteRuc'],
                        ':tserie' => $cabecera['serie'],
                        ':tnumfac' => $cabecera['numeroGuia'],
                        ':tfecrem' => $fechaTraslado,
                        ':tcencos' => $item['cencos'],
                        ':tcoscen' => $item['cencos'],
                        ':tgalpon' => $item['galpon'],
                        ':tcantid' => $item['cantidad'],
                        ':tsacos' => $item['cantidad'],
                        ':tsacos2' => $item['cantidad'],
                        ':tpeso' => $item['peso'],
                        ':count' => $index + 1,
                        ':tlote' => $item['lote'],
                        ':tglosa' => $item['detalleAdicional'] ?: '',
                        ':tdet_adicional' => $item['detalleAdicional'] ?: '',
                        ':timport' => $iImporte,
                        ':tkardex' => $iImporte,
                        ':tgre' => $xGuiEle
                    ]);
                }

                // Documento 2 (Destino)
                $registro2 = $this->getNuevoRegImov();
                $gCodtra2 = 'E' . substr($transaccion, 1); // "ES440"

                $stmtCab2 = $this->db->prepare($sqlCab);
                $stmtCab2->execute([
                    ':tuser' => $user,
                    ':tprocli' => $cabecera['clienteRuc'],
                    ':tserie' => $cabecera['serie'],
                    ':tnumfac' => $cabecera['numeroGuia'],
                    ':tfectra' => $fechaEmision,
                    ':treg' => $registro2,
                    ':talm' => $cabecera['zonaDestino'],
                    ':tnumreg' => $cabecera['numeroGuia'],
                    ':tcodtra' => $gCodtra2,
                    ':ttip_transporte' => $cabecera['tipoTransporte'],
                    ':tmotivo_traslado' => $cabecera['motivoTraslado'],
                    ':tdesmot_traslado' => $cabecera['motivoTrasladoOtros'],
                    ':tfecrem' => $fechaTraslado,
                    ':tglosa' => $cabecera['observaciones'] ?: '-',
                    ':tcod_transportista' => $cabecera['codTransportista'],
                    ':tcod_conductor' => $cabecera['codConductor'],
                    ':tplaca' => $cabecera['placaP'],
                    ':tplaca2' => $cabecera['placaR'],
                    ':tcli_origen' => $cabecera['clienteOrigen'],
                    ':tcli_destino' => $cabecera['clienteDestino'],
                    ':tcanttot' => $cabecera['totalCantidad'],
                    ':tpesotot' => $cabecera['totalPeso']
                ]);

                // Detalle Documento 2 (Sin timport ni tkardex en INSERT, mark='JE1', tgre='N')
                $sqlDet2 = "INSERT INTO imov (
                    tuser, tdate, ttime, tcodigo, tfectra, tcodtra, tlib, tnumreg, treg,
                    talm, talr, tnumlot, tprocli, tdoc, tserie, tnumfac, tfecrem,
                    tcencos, tcoscen, tgalpon, tcantid, tsacos, tsacos2, tpeso, count, tlote,
                    tglosa, tdet_adicional, mark, tgre
                ) VALUES (
                    :tuser, CURDATE(), CURTIME(), :tcodigo, :tfectra, :tcodtra, 'AL', :tnumreg, :treg,
                    :talm, :talr, '00000000', :tprocli, '09', :tserie, :tnumfac, :tfecrem,
                    :tcencos, :tcoscen, :tgalpon, :tcantid, :tsacos, :tsacos2, :tpeso, :count, :tlote,
                    :tglosa, :tdet_adicional, 'JE1', 'N'
                )";
                $stmtDet2 = $this->db->prepare($sqlDet2);

                foreach ($detalle as $index => $item) {
                    $stmtDet2->execute([
                        ':tuser' => $user,
                        ':tcodigo' => $item['codigo'],
                        ':tfectra' => $fechaEmision,
                        ':tcodtra' => $gCodtra2,
                        ':tnumreg' => $cabecera['numeroGuia'],
                        ':treg' => $registro2,
                        ':talm' => $cabecera['zonaDestino'],
                        ':talr' => $cabecera['zonaOrigen'],
                        ':tprocli' => $cabecera['clienteRuc'],
                        ':tserie' => $cabecera['serie'],
                        ':tnumfac' => $cabecera['numeroGuia'],
                        ':tfecrem' => $fechaTraslado,
                        ':tcencos' => $item['cencos'],
                        ':tcoscen' => $item['cencos'],
                        ':tgalpon' => $item['galpon'],
                        ':tcantid' => $item['cantidad'],
                        ':tsacos' => $item['cantidad'],
                        ':tsacos2' => $item['cantidad'],
                        ':tpeso' => $item['peso'],
                        ':count' => $index + 1,
                        ':tlote' => $item['lote'],
                        ':tglosa' => $item['detalleAdicional'] ?: '',
                        ':tdet_adicional' => $item['detalleAdicional'] ?: ''
                    ]);
                }
            } else {
                // ─────────────────────────────────────────────────────────────
                // FLUJO GRANJA (S400)
                // ─────────────────────────────────────────────────────────────
                $xNumTra = 1;
                if ($cabecera['zonaOrigen'] === '010') {
                    $xNumTra = 3;
                }

                $registroImov = $this->getNuevoRegImov();
                $tregCreado = $registroImov;

                $sqlCab = "INSERT INTO guia (
                    tuser, tdate, ttime, tprocli, tdoc, tserie, tnumfac, tfectra, tlib, 
                    treg, talm, tnumreg, tcodtra, ttip_transporte, tmotivo_traslado, tdesmot_traslado,
                    tfecrem, tglosa, tcod_transportista, tcod_conductor, tplaca, tplaca2, 
                    tcli_origen, tcli_destino, tcanttot, tpesotot, mark, tgre
                ) VALUES (
                    :tuser, CURDATE(), CURTIME(), :tprocli, '09', :tserie, :tnumfac, :tfectra, 'AL', 
                    :treg, :talm, :tnumreg, :tcodtra, :ttip_transporte, :tmotivo_traslado, :tdesmot_traslado,
                    :tfecrem, :tglosa, :tcod_transportista, :tcod_conductor, :tplaca, :tplaca2, 
                    :tcli_origen, :tcli_destino, :tcanttot, :tpesotot, :mark, :tgre
                )";
                $stmtCab = $this->db->prepare($sqlCab);

                $sqlDet = "INSERT INTO imov (
                    tuser, tdate, ttime, tcodigo, tfectra, tcodtra, tlib, tnumreg, treg,
                    talm, talr, tnumlot, tprocli, tdoc, tserie, tnumfac, tfecrem,
                    tcencos, tcoscen, tgalpon, tcantid, tsacos, tsacos2, tpeso, count, tlote,
                    tglosa, tdet_adicional, timport, tkardex, mark, tgre
                ) VALUES (
                    :tuser, CURDATE(), CURTIME(), :tcodigo, :tfectra, :tcodtra, 'AL', :tnumreg, :treg,
                    :talm, :talr, '00000000', :tprocli, '09', :tserie, :tnumfac, :tfecrem,
                    :tcencos, :tcoscen, :tgalpon, :tcantid, :tsacos, :tsacos2, :tpeso, :count, :tlote,
                    :tglosa, :tdet_adicional, :timport, :tkardex, :mark, :tgre
                )";
                $stmtDet = $this->db->prepare($sqlDet);

                for ($q = 1; $q <= $xNumTra; $q++) {
                    if ($q === 1) {
                        $gAlma = $cabecera['zonaOrigen'];
                        $gAlmaDestino = ($xNumTra > 1) ? '10T' : $cabecera['zonaDestino'];
                        $gCodtra = $transaccion;
                        $mark = 'JE1';
                        $tgre = 'S';
                        $gFecha = $fechaEmision;
                    } elseif ($q === 2) {
                        $gAlma = '10T';
                        $gAlmaDestino = '010';
                        $gCodtra = 'E450';
                        $mark = 'TE1';
                        $tgre = 'N';
                        $gFecha = $fechaEmision;
                    } else {
                        $gAlma = '10T';
                        $gAlmaDestino = '010';
                        $gCodtra = 'S450';
                        $mark = 'TS1';
                        $tgre = 'N';
                        $gFecha = $fechaTraslado;
                    }

                    // Insert guia (xNumTra loop)
                    $stmtCab->execute([
                        ':tuser' => $user,
                        ':tprocli' => $cabecera['clienteRuc'],
                        ':tserie' => $cabecera['serie'],
                        ':tnumfac' => $cabecera['numeroGuia'],
                        ':tfectra' => $gFecha,
                        ':treg' => $registroImov,
                        ':talm' => $gAlma,
                        ':tnumreg' => $cabecera['numeroGuia'],
                        ':tcodtra' => $gCodtra,
                        ':ttip_transporte' => $cabecera['tipoTransporte'],
                        ':tmotivo_traslado' => $cabecera['motivoTraslado'],
                        ':tdesmot_traslado' => $cabecera['motivoTrasladoOtros'],
                        ':tfecrem' => $fechaTraslado,
                        ':tglosa' => $cabecera['observaciones'] ?: '-',
                        ':tcod_transportista' => $cabecera['codTransportista'],
                        ':tcod_conductor' => $cabecera['codConductor'],
                        ':tplaca' => $cabecera['placaP'],
                        ':tplaca2' => $cabecera['placaR'],
                        ':tcli_origen' => $cabecera['clienteOrigen'],
                        ':tcli_destino' => $cabecera['clienteDestino'],
                        ':tcanttot' => $cabecera['totalCantidad'],
                        ':tpesotot' => $cabecera['totalPeso'],
                        ':mark' => $mark,
                        ':tgre' => $tgre
                    ]);

                    // Insert imov (xNumTra loop)
                    foreach ($detalle as $index => $item) {
                        $iPu = $this->calcularPrecioUnitario($gAlma, $item['codigo'], $item['lote'], $anio);
                        $iImporte = round($item['cantidad'] * $iPu, 2);

                        if ($q === 1) {
                            $xGuiEle = (strpos(strtoupper($item['codigo']), 'E') === 0) ? 'N' : 'S';
                        } else {
                            $xGuiEle = 'N';
                        }

                        $stmtDet->execute([
                            ':tuser' => $user,
                            ':tcodigo' => $item['codigo'],
                            ':tfectra' => $gFecha,
                            ':tcodtra' => $gCodtra,
                            ':tnumreg' => $cabecera['numeroGuia'],
                            ':treg' => $registroImov,
                            ':talm' => $gAlma,
                            ':talr' => $gAlmaDestino,
                            ':tprocli' => $cabecera['clienteRuc'],
                            ':tserie' => $cabecera['serie'],
                            ':tnumfac' => $cabecera['numeroGuia'],
                            ':tfecrem' => $fechaTraslado,
                            ':tcencos' => $item['cencos'],
                            ':tcoscen' => $item['cencos'],
                            ':tgalpon' => $item['galpon'],
                            ':tcantid' => $item['cantidad'],
                            ':tsacos' => $item['cantidad'],
                            ':tsacos2' => $item['cantidad'],
                            ':tpeso' => $item['peso'],
                            ':count' => $index + 1,
                            ':tlote' => $item['lote'],
                            ':tglosa' => $item['detalleAdicional'] ?: '',
                            ':tdet_adicional' => $item['detalleAdicional'] ?: '',
                            ':timport' => $iImporte,
                            ':tkardex' => $iImporte,
                            ':mark' => $mark,
                            ':tgre' => $xGuiEle
                        ]);
                    }
                }

                // Documento Granja (cabe_zonas + movi_zonas)
                $gFechaMZ = ($xNumTra === 3) ? $fechaTraslado : $fechaEmision;
                $registroPro = $this->getNuevoRegPro();

                $sqlCabeZonas = "INSERT INTO cabe_zonas (
                    tuser, tdate, ttime, tprocli, tdoc, tserie, tnumfac, tfectra, tlib, 
                    treg, talm, tnumreg, tcodtra, ttip_transporte, tmotivo_traslado, tdesmot_traslado,
                    tfecrem, tglosa, tcod_transportista, tcod_conductor, tplaca, tplaca2, 
                    tcli_origen, tcli_destino, tcanttot, tpesotot, mark, tgre
                ) VALUES (
                    :tuser, CURDATE(), CURTIME(), :tprocli, '09', :tserie, :tnumfac, :tfectra, 'AL', 
                    :treg, :talm, :tnumreg, 'E400', :ttip_transporte, :tmotivo_traslado, :tdesmot_traslado,
                    :tfecrem, :tglosa, :tcod_transportista, :tcod_conductor, :tplaca, :tplaca2, 
                    :tcli_origen, :tcli_destino, :tcanttot, :tpesotot, 'JE1', 'N'
                )";

                $stmtCabeZonas = $this->db->prepare($sqlCabeZonas);
                $stmtCabeZonas->execute([
                    ':tuser' => $user,
                    ':tprocli' => $cabecera['clienteRuc'],
                    ':tserie' => $cabecera['serie'],
                    ':tnumfac' => $cabecera['numeroGuia'],
                    ':tfectra' => $gFechaMZ,
                    ':treg' => $registroPro,
                    ':talm' => $cabecera['zonaOrigen'],
                    ':tnumreg' => $cabecera['numeroGuia'],
                    ':ttip_transporte' => $cabecera['tipoTransporte'],
                    ':tmotivo_traslado' => $cabecera['motivoTraslado'],
                    ':tdesmot_traslado' => $cabecera['motivoTrasladoOtros'],
                    ':tfecrem' => $fechaTraslado,
                    ':tglosa' => $cabecera['observaciones'] ?: '-',
                    ':tcod_transportista' => $cabecera['codTransportista'],
                    ':tcod_conductor' => $cabecera['codConductor'],
                    ':tplaca' => $cabecera['placaP'],
                    ':tplaca2' => $cabecera['placaR'],
                    ':tcli_origen' => $cabecera['clienteOrigen'],
                    ':tcli_destino' => $cabecera['clienteDestino'],
                    ':tcanttot' => $cabecera['totalCantidad'],
                    ':tpesotot' => $cabecera['totalPeso']
                ]);

                // Detalle Granja (movi_zonas)
                $sqlMoviZonas = "INSERT INTO movi_zonas (
                    tuser, tdate, ttime, tcodigo, tline, tfectra, tcodtra, treg,
                    tcencos, tcodint, tcoscen, tgalpon, tprocli, tdoc, tserie, tnumfac, tfecrem,
                    tcantid, tsacos, tsacos2, tpeso, idmovi,
                    tglosa, tdet_adicional, timport, mark, tgre
                ) VALUES (
                    :tuser, CURDATE(), CURTIME(), :tcodigo, :tline, :tfectra, 'E400', :treg,
                    :tcencos, :tcodint, '', '', :tprocli, '09', :tserie, :tnumfac, :tfecrem,
                    :tcantid, :tsacos, :tsacos2, :tpeso, :idmovi,
                    :tglosa, :tdet_adicional, :timport, 'JE1', 'N'
                )";
                $stmtMoviZonas = $this->db->prepare($sqlMoviZonas);

                $stmtLin = $this->db->prepare("SELECT lin FROM mitm WHERE codigo = :codigo LIMIT 1");

                foreach ($detalle as $index => $item) {
                    $stmtLin->execute([':codigo' => $item['codigo']]);
                    $linRes = $stmtLin->fetchColumn();
                    $iLineaPro = ($linRes !== false && $linRes !== null) ? $linRes : '000';

                    $iPu = $this->calcularPrecioUnitario($cabecera['zonaOrigen'], $item['codigo'], $item['lote'], $anio);
                    $iImporte = round($item['cantidad'] * $iPu, 2);

                    $stmtMoviZonas->execute([
                        ':tuser' => $user,
                        ':tcodigo' => $item['codigo'],
                        ':tline' => $iLineaPro,
                        ':tfectra' => $gFechaMZ,
                        ':treg' => $registroPro,
                        ':tcencos' => $item['cencos'],
                        ':tcodint' => $item['galpon'],
                        ':tprocli' => $cabecera['clienteRuc'],
                        ':tserie' => $cabecera['serie'],
                        ':tnumfac' => $cabecera['numeroGuia'],
                        ':tfecrem' => $fechaTraslado,
                        ':tcantid' => $item['cantidad'],
                        ':tsacos' => $item['peso'],
                        ':tsacos2' => $item['peso'],
                        ':tpeso' => $item['peso'],
                        ':idmovi' => $index + 1,
                        ':tglosa' => $item['detalleAdicional'] ?: '',
                        ':tdet_adicional' => $item['detalleAdicional'] ?: '',
                        ':timport' => $iImporte
                    ]);
                }
            }

            $this->db->commit();
            return $tregCreado;
        } catch (Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }
}
