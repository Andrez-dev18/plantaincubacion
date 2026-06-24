<?php

class ReporteTransaccionesRepository
{
    private $db;

    public function __construct($db)
    {
        $this->db = $db;
    }

    //FILTROS
    public function obtenerTransaccionesUnicas()
    {
        $sql = "SELECT codtra AS tcodtra, descri 
                FROM coal 
                WHERE descri IS NOT NULL AND descri != ''
                ORDER BY descri ASC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function obtenerAlmacenesUnicos()
    {
        $sql = "SELECT a.talm, c.descri 
                FROM imov AS a
                LEFT JOIN alma AS c ON a.talm = c.codalm
                WHERE a.talm IS NOT NULL AND a.talm != '' AND c.descri IS NOT NULL AND c.descri != ''
                GROUP BY a.talm, c.descri
                ORDER BY c.descri ASC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function obtenerCencosUnicos()
    {
        // Cruzamos imov con ccos usando los campos de tu consulta base
        $sql = "SELECT a.tcencos AS codigo, g.nombre 
                FROM imov AS a
                INNER JOIN ccos AS g ON a.tcencos = g.codigo
                WHERE a.tcencos IS NOT NULL AND a.tcencos != '' AND g.nombre IS NOT NULL AND g.nombre != ''
                GROUP BY a.tcencos, g.nombre
                ORDER BY g.nombre ASC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function obtenerCuentasCorrientesUnicas()
    {
        // Vinculamos imov con ccte usando el campo tprocli
        $sql = "SELECT codigo, nombre 
                FROM ccte 
                WHERE codigo IS NOT NULL AND codigo != '' 
                  AND nombre IS NOT NULL AND nombre != ''
                ORDER BY nombre ASC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function obtenerLineasUnicas()
    {
        // Vinculamos imov con mitm y luego con linea usando el campo lin
        $sql = "SELECT f.linea AS codigo, f.descri 
                FROM imov AS a
                INNER JOIN mitm AS b ON a.tcodigo = b.codigo
                INNER JOIN linea AS f ON f.linea = b.lin
                WHERE f.linea IS NOT NULL AND f.linea != '' AND f.descri IS NOT NULL AND f.descri != ''
                GROUP BY f.linea, f.descri
                ORDER BY f.linea ASC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function obtenerArticulosUnicos()
    {
        // Vinculamos imov con mitm usando el campo tcodigo
        $sql = "SELECT a.tcodigo AS codigo, b.descri 
                FROM imov AS a
                INNER JOIN mitm AS b ON a.tcodigo = b.codigo
                WHERE a.tcodigo IS NOT NULL AND a.tcodigo != '' AND b.descri IS NOT NULL AND b.descri != ''
                GROUP BY a.tcodigo, b.descri
                ORDER BY b.descri ASC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    //END FILTROS


    //reporte
    public function obtenerReporteTransacciones($filtros)
    {
        $sql = "SELECT 
                    DATE_FORMAT(a.tfectra, '%m/%d') AS fecha_formato,
                    a.tnumfac AS nro_doc,
                    '0' AS doc_ref,
                    a.tprocli AS cod_pro,
                    h.nombre AS proveedor_cliente,
                    a.tcencos AS cencos,
                    a.tcodigo AS codigo,
                    b.descri AS descripcion,
                    a.tcantid AS cantidad,
                    ROUND((CAST(a.tkardex AS DECIMAL(18,6)) / NULLIF(CAST(a.tcantid AS DECIMAL(18,6)), 0)), 3) AS c_unit,
                    a.tkardex AS c_kardex,
                    a.tkardex AS costo_total,
                    a.tcoscen AS cc_dest
                FROM imov AS a 
                
                /* ✅ BLINDAJE: Agregamos MAX(lin) a la subconsulta para poder filtrar por Línea */
                LEFT JOIN (SELECT codigo, MAX(descri) AS descri, MAX(lin) AS lin FROM mitm GROUP BY codigo) AS b 
                    ON a.tcodigo = b.codigo
                LEFT JOIN (SELECT codigo, MAX(nombre) AS nombre FROM ccte GROUP BY codigo) AS h 
                    ON a.tprocli = h.codigo
                    
                WHERE 1=1 ";

        $params = [];

        // Filtros simples
        if (!empty($filtros['fechaInicio']) && !empty($filtros['fechaFin'])) {
            $sql .= " AND DATE(a.tfectra) BETWEEN :fechaInicio AND :fechaFin ";
            $params[':fechaInicio'] = $filtros['fechaInicio'];
            $params[':fechaFin'] = $filtros['fechaFin'];
        }

        if (!empty($filtros['transaccion'])) {
            $sql .= " AND a.tcodtra = :transaccion ";
            $params[':transaccion'] = $filtros['transaccion'];
        }

        if (!empty($filtros['zona'])) {
            $sql .= " AND a.talm = :zona ";
            $params[':zona'] = $filtros['zona'];
        }

        // ─────────────────────────────────────────────────────────
        // ✅ FILTROS MÚLTIPLES DINÁMICOS (Para los select con checkboxes)
        // ─────────────────────────────────────────────────────────

        // Filtro por Cencos Múltiples
        if (!empty($filtros['cencos'])) {
            $valores = explode(',', $filtros['cencos']);
            $placeholders = [];
            foreach ($valores as $index => $valor) {
                $paramName = ":cen_" . $index;
                $placeholders[] = $paramName;
                $params[$paramName] = $valor;
            }
            $sql .= " AND a.tcencos IN (" . implode(',', $placeholders) . ") ";
        }

        // Filtro por Cuenta Corriente (Proveedor/Cliente) Múltiples
        if (!empty($filtros['cuentaCorriente'])) {
            $valores = explode(',', $filtros['cuentaCorriente']);
            $placeholders = [];
            foreach ($valores as $index => $valor) {
                $paramName = ":cct_" . $index;
                $placeholders[] = $paramName;
                $params[$paramName] = $valor;
            }
            $sql .= " AND a.tprocli IN (" . implode(',', $placeholders) . ") ";
        }

        // Filtro por Líneas Múltiples
        if (!empty($filtros['lineasValores'])) {
            $valores = explode(',', $filtros['lineasValores']);
            $placeholders = [];
            foreach ($valores as $index => $valor) {
                $paramName = ":lin_" . $index;
                $placeholders[] = $paramName;
                $params[$paramName] = $valor;
            }
            $sql .= " AND b.lin IN (" . implode(',', $placeholders) . ") ";
        }

        // Filtro por Códigos de Artículo Múltiples
        if (!empty($filtros['codigosValores'])) {
            $valores = explode(',', $filtros['codigosValores']);
            $placeholders = [];
            foreach ($valores as $index => $valor) {
                $paramName = ":cod_" . $index;
                $placeholders[] = $paramName;
                $params[$paramName] = $valor;
            }
            $sql .= " AND a.tcodigo IN (" . implode(',', $placeholders) . ") ";
        }

        // Order dinamico
        $agruparPor = $filtros['agruparPor'] ?? 'FECHA';

        switch ($agruparPor) {
            case 'PRODUCTO':
                // Ordena todos los ítems iguales juntos, luego por fecha
                $sql .= " ORDER BY b.descri ASC, a.tfectra ASC, a.treg ASC ";
                break;
            case 'ZONA_DESTINO':
                // Ordena por el centro de costo de destino, luego por fecha
                $sql .= " ORDER BY a.tcoscen ASC, a.tfectra ASC, a.treg ASC ";
                break;
            case 'LINEA':
                // Ordena por familia/línea, luego producto, luego fecha
                $sql .= " ORDER BY b.lin ASC, b.descri ASC, a.tfectra ASC ";
                break;
            case 'TOTALES':
            case 'FECHA':
            default:
                // El clásico orden cronológico por días
                $sql .= " ORDER BY a.tfectra ASC, a.treg ASC, a.count ASC ";
                break;
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
