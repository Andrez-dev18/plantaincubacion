<?php

class ListaGuiaElectronicaRepository
{
    private $db;

    public function __construct($db)
    {
        $this->db = $db;
    }

    /**
     * Obtiene el listado de guías de remisión electrónicas de salida con filtros dinámicos
     */
    public function listarGuias(?string $search = null, ?string $almacen = null, ?string $desde = null, ?string $hasta = null, ?string $serie = null, ?string $numero = null, ?int $start = null, ?int $length = null): array
    {
        // 1. Total records without filters (only base condition)
        $sqlTotal = "SELECT COUNT(g.treg) FROM guia g WHERE LEFT(g.tcodtra, 1) = 'S'";
        $stmtTotal = $this->db->prepare($sqlTotal);
        $stmtTotal->execute();
        $recordsTotal = (int)$stmtTotal->fetchColumn();

        // 2. Base query for filtering
        $sqlBase = " FROM guia g
                     LEFT JOIN ccte c ON g.tprocli = c.codigo
                     LEFT JOIN alma a ON g.talm = a.codalm
                     WHERE LEFT(g.tcodtra, 1) = 'S'";

        $where = "";
        $params = [];

        if ($almacen !== null && trim($almacen) !== '') {
            $where .= " AND g.talm = ?";
            $params[] = trim($almacen);
        }

        if ($desde !== null && trim($desde) !== '') {
            $where .= " AND g.tfectra >= ?";
            $params[] = trim($desde);
        }

        if ($hasta !== null && trim($hasta) !== '') {
            $where .= " AND g.tfectra <= ?";
            $params[] = trim($hasta);
        }

        if ($serie !== null && trim($serie) !== '') {
            $where .= " AND g.tserie LIKE ?";
            $params[] = '%' . trim($serie) . '%';
        }

        if ($numero !== null && trim($numero) !== '') {
            $where .= " AND g.tnumfac LIKE ?";
            $params[] = '%' . trim($numero) . '%';
        }

        if ($search !== null && trim($search) !== '') {
            $where .= " AND (g.tprocli LIKE ? OR c.nombre LIKE ? OR g.tserie LIKE ? OR g.tnumfac LIKE ?)";
            $term = '%' . trim($search) . '%';
            $params[] = $term;
            $params[] = $term;
            $params[] = $term;
            $params[] = $term;
        }

        // 3. Count filtered records
        $sqlFiltered = "SELECT COUNT(g.treg) " . $sqlBase . $where;
        $stmtFiltered = $this->db->prepare($sqlFiltered);
        $stmtFiltered->execute($params);
        $recordsFiltered = (int)$stmtFiltered->fetchColumn();

        // 4. Fetch page records
        $sqlData = "SELECT 
                        g.treg AS treg,
                        g.tfectra AS fecha_emision, g.tfectra AS tfectra,
                        g.tdoc AS tipo_doc, g.tdoc AS tdoc,
                        g.tserie AS serie, g.tserie AS tserie,
                        g.tnumfac AS numero, g.tnumfac AS tnumfac,
                        g.talm AS almacen_origen, g.talm AS talm,
                        a.descri AS nom_almacen,
                        g.tprocli AS cliente_ruc, g.tprocli AS tprocli,
                        COALESCE(c.nombre, g.tdesmot_traslado) AS cliente_razon_social, COALESCE(c.nombre, g.tdesmot_traslado) AS nombre,
                        ROUND(g.tcanttot, 0) AS bultos, ROUND(g.tcanttot, 0) AS tcanttot,
                        ROUND(g.tpesotot, 2) AS peso_total, ROUND(g.tpesotot, 2) AS tpesotot"
                    . $sqlBase . $where
                    . " ORDER BY g.tfectra DESC, g.tnumfac DESC";

        if ($start !== null && $length !== null) {
            $sqlData .= " LIMIT ?, ?";
        } else {
            $sqlData .= " LIMIT 500";
        }

        $stmt = $this->db->prepare($sqlData);

        // Bind parameters using 1-based index
        $index = 1;
        foreach ($params as $param) {
            $stmt->bindValue($index, $param, PDO::PARAM_STR);
            $index++;
        }

        if ($start !== null && $length !== null) {
            $stmt->bindValue($index, (int)$start, PDO::PARAM_INT);
            $index++;
            $stmt->bindValue($index, (int)$length, PDO::PARAM_INT);
        }

        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return [
            'recordsTotal' => $recordsTotal,
            'recordsFiltered' => $recordsFiltered,
            'rows' => $rows
        ];
    }

    /**
     * Obtiene los detalles de los ítems de una guía de remisión por su serie y número
     */
    public function obtenerDetalleGuia($treg): array
    {
        $sql = "SELECT 
                    i.tcodigo AS codigo,
                    m.descri AS descripcion,
                    m.unidad AS unidad,
                    i.tlote AS lote,
                    ROUND(i.tcantid, 2) AS cantidad,
                    ROUND(i.tpeso, 2) AS peso,
                    i.tcencos AS cencos,
                    i.tgalpon AS galpon,
                    i.tdet_adicional AS observacion
                FROM imov i
                LEFT JOIN mitm m ON i.tcodigo = m.codigo
                WHERE i.treg = ?
                ORDER BY i.count ASC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([$treg]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function obtenerGuiaPorTreg(string $treg): ?array
    {
        $sql = "SELECT 
                    g.treg AS treg,
                    g.tfectra AS fecha_emision,
                    g.tfecrem AS fecha_traslado,
                    g.tdoc AS tipo_doc,
                    g.tserie AS serie,
                    g.tnumfac AS numero,
                    g.talm AS almacen_origen,
                    g.tprocli AS cliente_ruc,
                    COALESCE(c.nombre, g.tdesmot_traslado) AS cliente_razon_social,
                    ROUND(g.tcanttot, 0) AS bultos,
                    ROUND(g.tpesotot, 2) AS peso_total,
                    COALESCE(mt.nom, g.tdesmot_traslado) AS motivo,
                    COALESCE(dt.nom, 'TRANSPORTE PÚBLICO') AS modalidad_transporte,
                    g.ttip_transporte AS tipo_transporte,
                    g.tcod_transportista AS transp_ruc,
                    transp.nombre AS transp_nombre,
                    g.tplaca AS vehiculo_placa,
                    cam.marca AS vehiculo_marca,
                    cam.ntm AS vehiculo_ntm,
                    cam.confvehicular AS vehiculo_conf,
                    g.tcod_conductor AS cond_dni,
                    ch.nombre AS cond_nombre,
                    ch.licencia AS cond_licencia,
                    c_ori.direcc AS punto_partida,
                    c_des.direcc AS punto_llegada
                FROM guia g
                LEFT JOIN ccte c ON g.tprocli = c.codigo
                LEFT JOIN alma a ON g.talm = a.codalm
                LEFT JOIN dtipo_transporte dt ON g.ttip_transporte = dt.cod
                LEFT JOIN dmotivo_traslado mt ON g.tmotivo_traslado = mt.cod
                LEFT JOIN dtransportista transp ON g.tcod_transportista = transp.ruc
                LEFT JOIN dcamion cam ON g.tplaca = cam.placa
                LEFT JOIN dchofer ch ON g.tcod_conductor = ch.dni
                LEFT JOIN ccte c_ori ON g.tcli_origen = c_ori.codigo
                LEFT JOIN ccte c_des ON g.tcli_destino = c_des.codigo
                WHERE g.treg = ? AND LEFT(g.tcodtra, 1) = 'S'";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([$treg]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }
}
