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
            $sql .= " WHERE ruc LIKE ? OR nombre LIKE ? OR nombrecomercial LIKE ?";
            $term = '%' . trim($search) . '%';
            $params = [$term, $term, $term];
        }

        $sql .= " ORDER BY nombre ASC LIMIT 100";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function obtenerConductores(?string $search = null): array
    {
        $sql = "SELECT dni, nombre, licencia, testado 
                FROM dchofer WHERE testado = 'A'";

        $params = [];
        if ($search !== null && trim($search) !== '') {
            $sql .= " WHERE dni LIKE ? OR nombre LIKE ?";
            $term = '%' . trim($search) . '%';
            $params = [$term, $term];
        }

        $sql .= " ORDER BY nombre ASC LIMIT 100";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function obtenerCamiones(?string $search = null): array
    {
        $sql = "SELECT placa, marca, cinscripcion, confvehicular, ntm, soat, fechaisoat, testado 
                FROM dcamion 
                WHERE testado = 'A'";

        $params = [];
        if ($search !== null && trim($search) !== '') {
            $sql .= " AND (placa LIKE ? OR marca LIKE ?)";
            $term = '%' . trim($search) . '%';
            $params = [$term, $term];
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

    public function obtenerArticulos(?string $search = null): array
    {
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
}
