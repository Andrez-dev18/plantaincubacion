<?php
class ReporteStockRepository
{
    private $db;

    public function __construct($db)
    {
        $this->db = $db;
    }

    //FUNCIONES PARA FILTROS REPORTE STOCK
    public function obtenerAlmacenesUnicos()
    {
        $sql = "SELECT DISTINCT c.codalm AS talm, c.descri 
            FROM alma AS c
            INNER JOIN mzon AS z ON c.codalm = z.alma
            WHERE c.codalm IS NOT NULL AND c.codalm != '' AND c.descri IS NOT NULL AND c.descri != ''
            ORDER BY c.descri ASC";

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


    //FUNCION DE REPORTE STOCK
    public function obtenerReporteStock($filtros)
    {
        $params = [];
        $fechaInicio = $filtros['fechaInicio'] ?? date('Y-m-d');
        $fechaFin = $filtros['fechaFin'] ?? date('Y-m-d');
        $zona = $filtros['zona'] ?? ''; // Si viene vacío, es "TODAS"

        $params[':fechaInicio'] = $fechaInicio;
        $params[':fechaFin'] = $fechaFin;

        // ── LA CONSULTA MAESTRA ──
        $agruparLote = !empty($filtros['agruparPorLote']) && $filtros['agruparPorLote'] == 'SI';
        $columnaLote = $agruparLote ? "a.tlote AS lote," : "'00000000' AS lote,";

        $sql = "SELECT 
                    b.codigo,
                    b.descri AS descripcion,
                    $columnaLote
                    b.lin AS linea_codigo,
                    f.descri AS linea_descri,
                    b.ctacar AS cuenta_codigo,
                    e.descri AS cuenta_descri,
                    COALESCE(a.talm, z.alma) AS alma_codigo,
                    c.descri AS alma_descri,
                    
                    /* 1. DÍA CERO */
                    COALESCE(z.qiniano, 0) AS stock_dia_cero,
                    COALESCE(z.viniano, 0) AS valor_dia_cero,
                    COALESCE(z.piniano, 0) AS peso_dia_cero,

                    /* 2. HISTORIA (Acumulado hasta antes del rango) */
                    COALESCE(SUM(
                        CASE 
                            WHEN a.tfectra < :fechaInicio AND a.tcodtra LIKE 'E%' THEN a.tcantid 
                            WHEN a.tfectra < :fechaInicio AND a.tcodtra LIKE 'S%' THEN -a.tcantid 
                            ELSE 0 
                        END
                    ), 0) AS historia_unidades,
                    
                    COALESCE(SUM(
                        CASE 
                            WHEN a.tfectra < :fechaInicio AND a.tcodtra LIKE 'E%' THEN a.tkardex 
                            WHEN a.tfectra < :fechaInicio AND a.tcodtra LIKE 'S%' THEN -a.tkardex 
                            ELSE 0 
                        END
                    ), 0) AS historia_valor,

                    /* 3. PERIODO (Entradas) */
                    COALESCE(SUM(
                        CASE WHEN a.tfectra BETWEEN :fechaInicio AND :fechaFin AND a.tcodtra LIKE 'E%' THEN a.tcantid ELSE 0 END
                    ), 0) AS entrada_unidades,
                    COALESCE(SUM(
                        CASE WHEN a.tfectra BETWEEN :fechaInicio AND :fechaFin AND a.tcodtra LIKE 'E%' THEN a.tkardex ELSE 0 END
                    ), 0) AS entrada_valor,

                    /* 4. PERIODO (Salidas) */
                    COALESCE(SUM(
                        CASE WHEN a.tfectra BETWEEN :fechaInicio AND :fechaFin AND a.tcodtra LIKE 'S%' THEN a.tcantid ELSE 0 END
                    ), 0) AS salida_unidades,
                    COALESCE(SUM(
                        CASE WHEN a.tfectra BETWEEN :fechaInicio AND :fechaFin AND a.tcodtra LIKE 'S%' THEN a.tkardex ELSE 0 END
                    ), 0) AS salida_valor

                FROM mitm AS b 
                
                /* Cruzamos con mzon (Saldo Inicial) */
                LEFT JOIN mzon AS z ON b.codigo = z.codigo 
                
                /* Cruzamos con imov (Movimientos del año en curso) */
                LEFT JOIN imov AS a ON b.codigo = a.tcodigo 
                                   AND (a.talm = z.alma OR z.alma IS NULL)
                                   AND YEAR(a.tfectra) = YEAR(:fechaInicio)
                
                /* Tablas maestras para los QUIEBRES */
                LEFT JOIN linea AS f ON b.lin = f.linea
                LEFT JOIN cmae AS e ON b.ctacar = e.cuenta
                LEFT JOIN alma AS c ON COALESCE(a.talm, z.alma) = c.codalm
                
                WHERE 1=1 ";

        // ── FILTROS DINÁMICOS ──
        if (!empty($zona)) {
            $sql .= " AND COALESCE(a.talm, z.alma) = :zona ";
            $params[':zona'] = $zona;
        }

        if (!empty($filtros['lineasValores'])) {
            $valores = explode(',', $filtros['lineasValores']);
            $placeholders = implode(',', array_map(function ($k) {
                return ":lin_$k";
            }, array_keys($valores)));
            foreach ($valores as $k => $v) {
                $params[":lin_$k"] = $v;
            }
            $sql .= " AND b.lin IN ($placeholders) ";
        }

        if (!empty($filtros['codigosValores'])) {
            $valores = explode(',', $filtros['codigosValores']);
            $placeholders = implode(',', array_map(function ($k) {
                return ":cod_$k";
            }, array_keys($valores)));
            foreach ($valores as $k => $v) {
                $params[":cod_$k"] = $v;
            }
            $sql .= " AND b.codigo IN ($placeholders) ";
        }

        if ($agruparLote) {
            $sql .= " GROUP BY b.codigo, b.descri, b.lin, f.descri, b.ctacar, e.descri, COALESCE(a.talm, z.alma), c.descri, z.qiniano, z.viniano, z.piniano, a.tlote ";
        } else {
            $sql .= " GROUP BY b.codigo, b.descri, b.lin, f.descri, b.ctacar, e.descri, COALESCE(a.talm, z.alma), c.descri, z.qiniano, z.viniano, z.piniano ";
        }

        // El orden dependerá del QUIEBRE seleccionado
        $quiebre = $filtros['quiebre'] ?? 'ALMACEN';
        if ($quiebre === 'LINEA') {
            $sql .= " ORDER BY b.lin ASC, b.codigo ASC ";
        } elseif ($quiebre === 'CUENTA') {
            $sql .= " ORDER BY b.ctacar ASC, b.codigo ASC ";
        } else {
            if ($agruparLote) {
                $sql .= " ORDER BY alma_codigo ASC, b.codigo ASC, a.tlote ASC ";
            } else {
                $sql .= " ORDER BY alma_codigo ASC, b.codigo ASC "; // Por defecto ALMACEN
            }
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
