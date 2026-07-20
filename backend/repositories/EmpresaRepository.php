<?php
date_default_timezone_set('America/Lima');

class EmpresaRepository
{
    /** @var PDO */
    private $conn;

    public function __construct(PDO $db)
    {
        $this->conn = $db;
    }

    public function listarCCTE(PDO $db, string $q, int $page, int $pageSize): array {
        $page = max(1, $page);
        $pageSize = max(1, min(100, $pageSize));
        $offset = ($page - 1) * $pageSize;
        $where = '';
        $params = array();
        if ($q !== '') {
            $where = ' WHERE codigo LIKE :q1 OR nombre LIKE :q2 OR ruc LIKE :q3';
            $like = '%' . $q . '%';
            $params = array(':q1' => $like, ':q2' => $like, ':q3' => $like);
        }

        $count = $db->prepare('SELECT COUNT(*) FROM ccte' . $where);
        $count->execute($params);
        $sql = "SELECT " . $this->columnasSelect() . "
                  FROM ccte" . $where . "
                 ORDER BY nombre ASC, codigo ASC
                 LIMIT " . (int)$offset . ', ' . (int)$pageSize;
        $st = $db->prepare($sql);
        $st->execute($params);
        return array(
            'rows' => $st->fetchAll(PDO::FETCH_ASSOC),
            'total' => (int)$count->fetchColumn(),
        );
    }

    public function listar(?string $q = null): array
    {
        $sql = "SELECT id_proveedor, ruc, nombre, guifac_url, guifac_token, serie_cpe_ft, activo
                  FROM rc_proveedor_facturacion WHERE 1=1";
        $params = array();
        if ($q !== null && trim($q) !== '') {
            $sql .= " AND (ruc LIKE :q OR nombre LIKE :q2)";
            $like = '%' . trim($q) . '%';
            $params[':q']  = $like;
            $params[':q2'] = $like;
        }
        $sql .= " ORDER BY nombre ASC, ruc ASC LIMIT 200";
        $st = $this->conn->prepare($sql);
        $st->execute($params);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    public function guardar(array $data, ?int $id = null): int
    {
        $ruc = trim((string)($data['ruc'] ?? $data['codigo'] ?? ''));
        if (strpos($ruc, ' - ') !== false) {
            $ruc = trim(explode(' - ', $ruc, 2)[0]);
        }
        if ($ruc === '') {
            throw new RuntimeException('Código de proveedor (ccte) es obligatorio.');
        }
        $fields = array(
            'ruc'           => $ruc,
            'nombre'        => trim((string)($data['nombre'] ?? '')),
            'guifac_url'    => trim((string)($data['guifac_url'] ?? '')),
            'guifac_token'  => trim((string)($data['guifac_token'] ?? '')),
            'serie_cpe_ft'  => trim((string)($data['serie_cpe_ft'] ?? '')),
            'activo'        => !empty($data['activo']) ? 1 : 0,
        );
        if ($fields['serie_cpe_ft'] === '') {
            throw new RuntimeException('Serie CPE de factura es obligatoria.');
        }
        if ($fields['guifac_url'] === '' || $fields['guifac_token'] === '') {
            throw new RuntimeException('URL y token GuiFac son obligatorios.');
        }

        if ($id !== null && $id > 0) {
            $st = $this->conn->prepare(
                "UPDATE rc_proveedor_facturacion SET
                    ruc = :ruc, nombre = :nombre, guifac_url = :url, guifac_token = :tok,
                    serie_cpe_ft = :serie, activo = :act, fecha_modificacion = NOW()
                 WHERE id_proveedor = :id"
            );
            $st->execute(array(
                ':ruc' => $fields['ruc'],
                ':nombre' => $fields['nombre'],
                ':url' => $fields['guifac_url'],
                ':tok' => $fields['guifac_token'],
                ':serie' => $fields['serie_cpe_ft'],
                ':act' => $fields['activo'],
                ':id' => $id,
            ));
            return $id;
        }

        $st = $this->conn->prepare(
            "INSERT INTO rc_proveedor_facturacion
                (ruc, nombre, guifac_url, guifac_token, serie_cpe_ft, activo, fecha_creacion)
             VALUES (:ruc, :nombre, :url, :tok, :serie, :act, NOW())"
        );
        $st->execute(array(
            ':ruc' => $fields['ruc'],
            ':nombre' => $fields['nombre'],
            ':url' => $fields['guifac_url'],
            ':tok' => $fields['guifac_token'],
            ':serie' => $fields['serie_cpe_ft'],
            ':act' => $fields['activo'],
        ));
        return (int)$this->conn->lastInsertId();
    }

    public function mapActivosPorCodigos(array $codigos): array
    {
        $codigos = array_values(array_unique(array_filter(array_map('trim', $codigos))));
        if (empty($codigos)) {
            return array();
        }
        $ph = array();
        $params = array();
        foreach ($codigos as $i => $c) {
            $k = ':c' . $i;
            $ph[] = $k;
            $params[$k] = $c;
        }
        $st = $this->conn->prepare(
            'SELECT * FROM rc_proveedor_facturacion
              WHERE activo = 1 AND ruc IN (' . implode(',', $ph) . ')'
        );
        $st->execute($params);
        $map = array();
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $map[trim((string)$row['ruc'])] = $row;
        }
        return $map;
    }

    public function obtenerPorId(int $id): ?array
    {
        $st = $this->conn->prepare(
            "SELECT id_proveedor, ruc, nombre, guifac_url, guifac_token, serie_cpe_ft, activo
             FROM rc_proveedor_facturacion
             WHERE id_proveedor = :id"
        );
        $st->execute([':id' => $id]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        return $row ? $row : null;
    }

    public function cambiarEstado(int $id): bool
    {
        $st = $this->conn->prepare(
            "UPDATE rc_proveedor_facturacion 
             SET activo = IF(activo = 1, 0, 1), fecha_modificacion = NOW() 
             WHERE id_proveedor = :id"
        );
        return $st->execute([':id' => $id]);
    }

    public function eliminar(int $id): bool
    {
        $st = $this->conn->prepare(
            "DELETE FROM rc_proveedor_facturacion 
             WHERE id_proveedor = :id"
        );
        return $st->execute([':id' => $id]);
    }

    private function columnasSelect(): string {
        // Alias persona ← tipo (legacy PERSONA guarda en columna tipo)
        // Alias porc ← modelo (% detracción en legacy)
        return "codigo, IFNULL(nombre, '') AS nombre,
                IFNULL(tipo, IFNULL(persona, '')) AS tipo,
                IFNULL(tipo, IFNULL(persona, '')) AS persona,
                IFNULL(tipodoc, '') AS tipodoc,
                IFNULL(tdocid, '') AS tdocid,
                IFNULL(formulario, 0) AS formulario,
                IFNULL(ruc, '') AS ruc, IFNULL(direcc, '') AS direcc,
                IFNULL(telefo, '') AS telefo, IFNULL(ubigeo, '') AS ubigeo,
                IFNULL(codmer, '') AS codmer, IFNULL(departa, '') AS departa,
                IFNULL(codven, '') AS codven, IFNULL(provincia, '') AS provincia,
                IFNULL(fax, '') AS fax, IFNULL(distrito, '') AS distrito,
                IFNULL(pais, '') AS pais, IFNULL(contacto, '') AS contacto,
                IFNULL(le, '') AS le,
                IFNULL(ndocid, '') AS ndocid,
                IFNULL(zpos, '') AS zpos,
                IFNULL(clpr, '') AS clpr,
                IFNULL(act_ivo, '') AS act_ivo,
                IFNULL(estado_ruc, '') AS estado_ruc,
                IFNULL(condicion_ruc, '') AS condicion_ruc,
                IFNULL(ruta, '') AS ruta, IFNULL(email, '') AS email,
                IFNULL(canal, '') AS canal, IFNULL(tipocli, '') AS tipocli,
                IFNULL(giro, '') AS giro, IFNULL(codrep, '') AS codrep,
                IFNULL(nomrep, '') AS nomrep,
                IFNULL(reten, '') AS reten, IFNULL(ubcarp, '') AS ubcarp,
                IFNULL(secuencia, 0) AS secuencia, IFNULL(brevete, '') AS brevete,
                IFNULL(ctactedetra, '') AS ctactedetra,
                IFNULL(modelo, '') AS modelo,
                IFNULL(modelo, '') AS porc,
                IFNULL(copa, '') AS copa, IFNULL(placa, '') AS placa,
                IFNULL(tipoprod, '') AS tipoprod,
                IFNULL(ctaahorros, '') AS ctaahorros,
                IFNULL(ctacorrientes, '') AS ctacorrientes,
                IFNULL(ctaahorrod, '') AS ctaahorrod,
                IFNULL(ctacorriented, '') AS ctacorriented";
    }

}
