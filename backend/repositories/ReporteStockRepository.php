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


    //FUNCION DE REPORTE STOCK
    public function obtenerReporteStock($filtros)
    {
        $params = [];
        $fechaInicio = $filtros['fechaInicio'] ?? date('Y-m-d');
        $fechaFin    = $filtros['fechaFin']    ?? date('Y-m-d');
        $zona        = $filtros['zona']        ?? '';

        $params[':fechaInicio'] = $fechaInicio;
        $params[':fechaFin']    = $fechaFin;
        
        $anio = date('Y', strtotime($fechaInicio));
        $params[':anio_sub'] = $anio;
        $params[':anio_main'] = $anio;

        // 1. Construir las consultas base de la UNION para obtener todas las combinaciones (codigo, lote, alma) únicas
        if (!empty($filtros['lineasValores'])) {
            $valoresLineas = explode(',', $filtros['lineasValores']);
            $placeholdersLinMzon = [];
            $placeholdersLinImov = [];
            $placeholdersLinMain = [];
            foreach ($valoresLineas as $k => $v) {
                $placeholdersLinMzon[] = ":lin_mzon_$k";
                $params[":lin_mzon_$k"] = $v;

                $placeholdersLinImov[] = ":lin_imov_$k";
                $params[":lin_imov_$k"] = $v;

                $placeholdersLinMain[] = ":lin_main_$k";
                $params[":lin_main_$k"] = $v;
            }
            $linMzonIn = implode(',', $placeholdersLinMzon);
            $linImovIn = implode(',', $placeholdersLinImov);
            $linMainIn = implode(',', $placeholdersLinMain);

            $subqueryMzon = "SELECT z.codigo, z.lote, z.alma 
                             FROM mzon AS z 
                             INNER JOIN mitm AS b_sub ON z.codigo = b_sub.codigo 
                             WHERE z.lote <> '00000000' AND b_sub.lin IN ($linMzonIn) ";
                             
            $subqueryImov = "SELECT a.tcodigo AS codigo, a.tlote AS lote, a.talm AS alma 
                             FROM imov AS a 
                             INNER JOIN mitm AS b_sub ON a.tcodigo = b_sub.codigo 
                             WHERE a.tlote <> '00000000' AND YEAR(a.tfectra) = :anio_sub AND b_sub.lin IN ($linImovIn) ";
        } else {
            $subqueryMzon = "SELECT z.codigo, z.lote, z.alma FROM mzon AS z WHERE z.lote <> '00000000' ";
            $subqueryImov = "SELECT a.tcodigo AS codigo, a.tlote AS lote, a.talm AS alma FROM imov AS a WHERE a.tlote <> '00000000' AND YEAR(a.tfectra) = :anio_sub ";
        }

        // Aplicamos el filtro de zona/almacén directamente en los subqueries para maximizar el uso de índices
        if (!empty($zona)) {
            $subqueryMzon .= " AND z.alma = :zona_mzon ";
            $subqueryImov .= " AND a.talm = :zona_imov ";
            $params[':zona_mzon'] = $zona;
            $params[':zona_imov'] = $zona;
        }

        // Aplicamos el filtro de códigos directamente en los subqueries
        if (!empty($filtros['codigosValores'])) {
            $valoresCodigos = explode(',', $filtros['codigosValores']);
            $placeholdersCodMzon = [];
            $placeholdersCodImov = [];
            $placeholdersCodMain = [];
            foreach ($valoresCodigos as $k => $v) {
                $placeholdersCodMzon[] = ":cod_mzon_$k";
                $params[":cod_mzon_$k"] = $v;

                $placeholdersCodImov[] = ":cod_imov_$k";
                $params[":cod_imov_$k"] = $v;

                $placeholdersCodMain[] = ":cod_main_$k";
                $params[":cod_main_$k"] = $v;
            }
            $codMzonIn = implode(',', $placeholdersCodMzon);
            $codImovIn = implode(',', $placeholdersCodImov);
            $codMainIn = implode(',', $placeholdersCodMain);

            $subqueryMzon .= " AND z.codigo IN ($codMzonIn) ";
            $subqueryImov .= " AND a.tcodigo IN ($codImovIn) ";
        }

        // 2. Construir la consulta principal utilizando la UNION de lotes
        $sql = "SELECT 
                u.codigo,
                b.descri        AS descripcion,
                u.lote          AS lote,
                b.lin           AS linea_codigo,
                f.descri        AS linea_descri,
                b.ctacar        AS cuenta_codigo,
                e.descri        AS cuenta_descri,
                u.alma          AS alma_codigo,
                c.descri        AS alma_descri,

                /* DÍA CERO — directo de mzon por lote */
                COALESCE(z.qiniano, 0) AS stock_dia_cero,
                COALESCE(z.viniano, 0) AS valor_dia_cero,
                COALESCE(z.piniano, 0) AS peso_dia_cero,

                /* HISTORIA: movimientos del año ANTES del periodo */
                COALESCE(SUM(
                    CASE 
                        WHEN a.tfectra < :fechaInicio AND a.tcodtra LIKE 'E%' THEN  a.tcantid 
                        WHEN a.tfectra < :fechaInicio AND a.tcodtra LIKE 'S%' THEN -a.tcantid 
                        ELSE 0 
                    END
                ), 0) AS historia_unidades,

                COALESCE(SUM(
                    CASE 
                        WHEN a.tfectra < :fechaInicio AND a.tcodtra LIKE 'E%' THEN  a.tkardex 
                        WHEN a.tfectra < :fechaInicio AND a.tcodtra LIKE 'S%' THEN -a.tkardex 
                        ELSE 0 
                    END
                ), 0) AS historia_valor,

                /* ENTRADAS del periodo */
                COALESCE(SUM(
                    CASE WHEN a.tfectra BETWEEN :fechaInicio AND :fechaFin 
                              AND a.tcodtra LIKE 'E%' THEN a.tcantid ELSE 0 END
                ), 0) AS entrada_unidades,

                COALESCE(SUM(
                    CASE WHEN a.tfectra BETWEEN :fechaInicio AND :fechaFin 
                              AND a.tcodtra LIKE 'E%' THEN a.tkardex ELSE 0 END
                ), 0) AS entrada_valor,

                /* SALIDAS del periodo */
                COALESCE(SUM(
                    CASE WHEN a.tfectra BETWEEN :fechaInicio AND :fechaFin 
                              AND a.tcodtra LIKE 'S%' THEN a.tcantid ELSE 0 END
                ), 0) AS salida_unidades,

                COALESCE(SUM(
                    CASE WHEN a.tfectra BETWEEN :fechaInicio AND :fechaFin 
                              AND a.tcodtra LIKE 'S%' THEN a.tkardex ELSE 0 END
                ), 0) AS salida_valor

            FROM (
                $subqueryMzon
                UNION
                $subqueryImov
            ) AS u

            /* mitm se une desde el subquery u */
            INNER JOIN mitm AS b ON u.codigo = b.codigo

            /* mzon se une por izquierda para traer los saldos iniciales del año */
            LEFT JOIN mzon AS z ON z.codigo = u.codigo AND z.lote = u.lote AND z.alma = u.alma

            /* imov trae movimientos del año */
            LEFT JOIN imov AS a ON a.tcodigo = u.codigo
                               AND a.tlote   = u.lote
                               AND a.talm    = u.alma
                               AND YEAR(a.tfectra) = :anio_main

            LEFT JOIN linea AS f ON b.lin    = f.linea
            LEFT JOIN cmae  AS e ON b.ctacar = e.cuenta
            LEFT JOIN alma  AS c ON u.alma   = c.codalm

            WHERE 1=1 ";

        if (!empty($zona)) {
            $sql .= " AND u.alma = :zona_main ";
            $params[':zona_main'] = $zona;
        }

        if (!empty($filtros['lineasValores'])) {
            $sql .= " AND b.lin IN ($linMainIn) ";
        }

        if (!empty($filtros['codigosValores'])) {
            $sql .= " AND b.codigo IN ($codMainIn) ";
        }

        // ── GROUP BY 
        $sql .= " GROUP BY 
                u.codigo, u.lote, u.alma,
                b.descri, b.lin, b.ctacar,
                f.descri, e.descri, c.descri,
                z.qiniano, z.viniano, z.piniano ";

        // ── HAVING: excluir lotes sin ningún movimiento ni saldo y considerar precisión de coma flotante 
        $sql .= " HAVING (
                ROUND(COALESCE(z.qiniano, 0) + COALESCE(historia_unidades, 0), 4) <> 0
                OR ROUND(COALESCE(entrada_unidades, 0), 4) <> 0
                OR ROUND(COALESCE(salida_unidades, 0), 4)  <> 0
            ) ";

        // ── ORDER BY 
        $quiebre = $filtros['quiebre'] ?? 'ALMACEN';
        if ($quiebre === 'LINEA') {
            $sql .= " ORDER BY b.lin ASC, u.codigo ASC, u.lote ASC ";
        } elseif ($quiebre === 'CUENTA') {
            $sql .= " ORDER BY b.ctacar ASC, u.codigo ASC, u.lote ASC ";
        } else {
            $sql .= " ORDER BY u.alma ASC, u.codigo ASC, u.lote ASC ";
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
