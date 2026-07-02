<?php
class ReporteKardexRepository
{
    private $db;

    public function __construct($db)
    {
        $this->db = $db;
    }

    //FUNCIONES PARA FILTROS REPORTE KARDEX
    public function obtenerAlmacenesUnicos()
    {
        $sql = "SELECT DISTINCT c.codalm AS talm, c.descri 
            FROM alma AS c
            INNER JOIN mzon AS z ON c.codalm = z.alma
            WHERE c.codalm IS NOT NULL AND c.codalm != '' AND c.descri IS NOT NULL AND c.descri != ''
            ORDER BY c.codalm ASC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function obtenerArticulosUnicos()
    {
        $sql = "SELECT codigo, descri 
                FROM mitm 
                WHERE codigo IS NOT NULL AND codigo != '' 
                ORDER BY codigo ASC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function obtenerLineasUnicas()
    {
        $sql = "SELECT DISTINCT f.linea AS codigo, f.descri 
            FROM linea AS f
            INNER JOIN mitm AS b ON f.linea = b.lin
            INNER JOIN mzon AS z ON b.codigo = z.codigo
            WHERE f.linea IS NOT NULL AND f.linea != '' AND f.descri IS NOT NULL AND f.descri != ''
            ORDER BY f.linea ASC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }


    //FUNCION DE REPORTE KARDEX
    public function obtenerSaldosIniciales($filtros)
    {
        $almacen = $filtros['zona'] ?? '010';
        $fechaInicio = $filtros['fechaInicio'];
        $anio = date('Y', strtotime($fechaInicio));

        $sql = "SELECT 
                a.codigo,
                a.lote AS lote,
                MAX(m.descri) AS item_descri,
                MAX(a.qiniano) AS peso_dia_cero,
                MAX(a.viniano) AS val_dia_cero,
                MAX(a.piniano) AS cant_dia_cero,
                IFNULL(SUM(CASE WHEN LEFT(i.tcodtra, 1) = 'E' THEN i.tpeso ELSE -i.tpeso END), 0) AS mov_cant_hist,
                IFNULL(SUM(CASE WHEN LEFT(i.tcodtra, 1) = 'E' THEN i.tkardex ELSE -i.tkardex END), 0) AS mov_val_hist,
                IFNULL(SUM(CASE WHEN LEFT(i.tcodtra, 1) = 'E' THEN i.tcantid ELSE -i.tcantid END), 0) AS mov_peso_hist
            FROM mzon AS a
            LEFT JOIN mitm AS m ON a.codigo = m.codigo
            LEFT JOIN imov AS i ON a.codigo = i.tcodigo
                               AND a.alma = i.talm 
                               AND a.lote = i.tlote
                               AND i.tfectra < ? 
                               AND YEAR(i.tfectra) = ?
            WHERE a.alma = ?";

        $params = [$fechaInicio, $anio, $almacen];

        if (!empty($filtros['codigosValores'])) {
            $codigosArray = explode(',', $filtros['codigosValores']);
            $placeholders = implode(',', array_fill(0, count($codigosArray), '?'));
            $sql .= " AND a.codigo IN ($placeholders)";
            $params = array_merge($params, $codigosArray);
        }

        $sql .= " GROUP BY a.codigo, a.lote ORDER BY a.codigo ASC, a.lote ASC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $resultados = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $saldos = [];
        foreach ($resultados as $row) {
            $loteLimpio = !empty($row['lote']) ? $row['lote'] : '00000000';
            $llave = $row['codigo'] . '|' . $loteLimpio;
            $saldos[$llave] = $row;
        }
        return $saldos;
    }

    // 2. Obtener los Movimientos en el rango de fechas
    public function obtenerMovimientosRango($filtros)
    {
        $almacen = $filtros['zona'] ?? '010';
        $fechaInicio = $filtros['fechaInicio'];
        $fechaFin = $filtros['fechaFin'];

        $sql = "SELECT 
                a.talm, c.descri AS alma_descri,
                a.tfectra,
                b.ctacar, e.descri AS cuenta_descri,
                b.lin, f.descri AS linea_descri,
                IF(a.tcodtra<>'E001', a.tcencos, a.tprocli) AS codcen,
                IF(a.tcodtra<>'E001', CONCAT(g.nombre, ' C=', RIGHT(a.tcencos, 3)), h.nombre) AS NOM,
                a.tnumfac, a.tcodigo, a.tlote, b.descri AS item_descri,
                a.tcodtra, d.descri AS tra_descri,
                a.tcantid AS mov_peso,
                a.tpeso   AS mov_cant,
                a.tkardex, a.timpdol
            FROM imov AS a 
            LEFT JOIN mitm AS b ON a.tcodigo = b.codigo
            LEFT JOIN alma AS c ON a.talm = c.codalm
            LEFT JOIN coal AS d ON a.tcodtra = d.codtra
            LEFT JOIN cmae AS e ON b.ctacar = e.cuenta
            LEFT JOIN linea AS f ON f.linea = b.lin
            LEFT JOIN ccos AS g ON LEFT(a.tcencos, 3) = g.codigo
            LEFT JOIN ccte AS h ON a.tprocli = h.codigo
            WHERE a.talm = ? 
              AND a.tfectra BETWEEN ? AND ?";

        if (!empty($filtros['codigosValores'])) {
            $codigosArray = explode(',', $filtros['codigosValores']);
            $placeholders = implode(',', array_fill(0, count($codigosArray), '?'));
            $sql .= " AND a.tcodigo IN ($placeholders)";
        }

        $sql .= " ORDER BY a.tcodigo ASC, a.tlote ASC, a.tfectra ASC, a.tcodtra ASC";

        $stmt = $this->db->prepare($sql);

        $params = [$almacen, $fechaInicio, $fechaFin];
        if (!empty($filtros['codigosValores'])) {
            $params = array_merge($params, explode(',', $filtros['codigosValores']));
        }

        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
