<?php
date_default_timezone_set('America/Lima');

class ContribuyentesRepository
{
    /** @var PDO */
    private $conn;

    public function __construct(PDO $db)
    {
        $this->conn = $db;
    }

    public function listar(PDO $db, string $q, int $page, int $pageSize): array
    {
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
                 ORDER BY CASE WHEN tdate IS NULL OR tdate = '0000-00-00' THEN '1900-01-01'
                               WHEN tdate > DATE_ADD(CURRENT_DATE(), INTERVAL 1 DAY) THEN '1900-01-01'
                               ELSE tdate END DESC, ttime DESC, nombre ASC, codigo ASC
                 LIMIT " . (int)$offset . ', ' . (int)$pageSize;
        $st = $db->prepare($sql);
        $st->execute($params);
        return array(
            'rows' => $st->fetchAll(PDO::FETCH_ASSOC),
            'total' => (int)$count->fetchColumn(),
        );
    }

    public function obtener(PDO $db, string $codigo): ?array
    {
        $st = $db->prepare(
            'SELECT ' . $this->columnasSelect() . ' FROM ccte WHERE codigo = :codigo LIMIT 1'
        );
        $st->execute(array(':codigo' => $codigo));
        $row = $st->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function existe(PDO $db, string $codigo): bool
    {
        $st = $db->prepare('SELECT 1 FROM ccte WHERE codigo = :codigo LIMIT 1');
        $st->execute(array(':codigo' => $codigo));
        return (bool)$st->fetchColumn();
    }

    public function insertar(PDO $db, array $data): void
    {
        $n = $this->normalizar($data);
        $n[':tdate'] = date('Y-m-d');
        $n[':ttime'] = date('H:i:s');

        $cols = array_keys($n);
        $cols = array_map(function ($key) {
            return ltrim($key, ':');
        }, $cols);
        $st = $db->prepare(
            'INSERT INTO ccte (' . implode(', ', $cols) . ')
             VALUES (' . implode(', ', array_keys($n)) . ')'
        );
        $st->execute($n);
    }

    public function actualizar(PDO $db, string $codigo, array $data): void
    {
        $n = $this->normalizar($data);
        unset($n[':codigo']);
        $sets = array();
        foreach (array_keys($n) as $key) {
            $sets[] = ltrim($key, ':') . ' = ' . $key;
        }
        $n[':codigo_where'] = $codigo;
        $st = $db->prepare(
            'UPDATE ccte SET ' . implode(', ', $sets) . ' WHERE codigo = :codigo_where'
        );
        $st->execute($n);
    }

    private function columnasSelect(): string
    {
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

    private function normalizar(array $data): array
    {
        // PERSONA (J/N) → columna tipo (+ sincroniza persona)
        $tipo = substr(strtoupper(trim((string)($data['tipo'] ?? $data['persona'] ?? ''))), 0, 1);

        // % detracción → columna modelo (legacy txtPorDetra)
        $modelo = '';
        if (array_key_exists('modelo', $data) && $data['modelo'] !== null && $data['modelo'] !== '') {
            $modelo = substr(trim((string)$data['modelo']), 0, 50);
        } elseif (array_key_exists('porc', $data) && $data['porc'] !== null && $data['porc'] !== '') {
            $modelo = substr(trim((string)$data['porc']), 0, 50);
        }

        $codigo = substr(trim((string)($data['codigo'] ?? '')), 0, 11);
        $tipodoc = substr(trim((string)($data['tipodoc'] ?? '')), 0, 8);
        $tdocid = substr(trim((string)($data['tdocid'] ?? '')), 0, 3);
        $ndocid = substr(trim((string)($data['ndocid'] ?? '')), 0, 12);
        $le = substr(trim((string)($data['le'] ?? '')), 0, 10);

        // Igual que legacy: derivar tdocid/ndocid si vienen vacíos
        if ($tdocid === '' || $ndocid === '') {
            if (strlen($codigo) >= 2 && substr($codigo, 0, 2) === '10') {
                if ($tdocid === '') {
                    $tdocid = 'DNI';
                }
                if ($ndocid === '') {
                    $ndocid = substr($codigo, 2, 8);
                }
            } elseif (strlen($codigo) >= 2 && substr($codigo, 0, 2) === '20') {
                if ($tdocid === '') {
                    $tdocid = 'RUC';
                }
                if ($ndocid === '') {
                    $ndocid = $codigo;
                }
            }
        }

        return array(
            ':codigo' => $codigo,
            ':nombre' => substr(trim((string)($data['nombre'] ?? '')), 0, 100),
            ':tipo' => $tipo,
            ':persona' => $tipo,
            ':tipodoc' => $tipodoc,
            ':tdocid' => $tdocid,
            ':formulario' => (float)($data['formulario'] ?? 0),
            ':ruc' => substr(preg_replace('/\D/', '', (string)($data['ruc'] ?? '')), 0, 11),
            ':direcc' => substr(trim((string)($data['direcc'] ?? '')), 0, 150),
            ':telefo' => substr(trim((string)($data['telefo'] ?? '')), 0, 15),
            ':ubigeo' => substr(preg_replace('/\D/', '', (string)($data['ubigeo'] ?? '')), 0, 6),
            ':codmer' => substr(trim((string)($data['codmer'] ?? '')), 0, 3),
            ':departa' => substr(trim((string)($data['departa'] ?? '')), 0, 50),
            ':codven' => substr(trim((string)($data['codven'] ?? '')), 0, 11),
            ':provincia' => substr(trim((string)($data['provincia'] ?? '')), 0, 50),
            ':fax' => substr(trim((string)($data['fax'] ?? '')), 0, 15),
            ':distrito' => substr(trim((string)($data['distrito'] ?? '')), 0, 50),
            ':pais' => substr(trim((string)($data['pais'] ?? '')), 0, 20),
            ':contacto' => substr(trim((string)($data['contacto'] ?? '')), 0, 20),
            ':le' => $le !== '' ? $le : '0',
            ':ndocid' => $ndocid,
            ':zpos' => substr(trim((string)($data['zpos'] ?? '')), 0, 3),
            ':clpr' => substr(trim((string)($data['clpr'] ?? '')), 0, 2),
            ':act_ivo' => substr(strtoupper(trim((string)($data['act_ivo'] ?? ''))), 0, 1),
            ':estado_ruc' => substr(trim((string)($data['estado_ruc'] ?? '')), 0, 255),
            ':condicion_ruc' => substr(trim((string)($data['condicion_ruc'] ?? '')), 0, 255),
            ':ruta' => substr(trim((string)($data['ruta'] ?? '')), 0, 8),
            ':email' => substr(trim((string)($data['email'] ?? '')), 0, 50),
            ':canal' => substr(trim((string)($data['canal'] ?? '')), 0, 6),
            ':tipocli' => substr(trim((string)($data['tipocli'] ?? '')), 0, 6),
            ':giro' => substr(trim((string)($data['giro'] ?? '')), 0, 6),
            ':codrep' => substr(trim((string)($data['codrep'] ?? '')), 0, 8),
            ':nomrep' => substr(trim((string)($data['nomrep'] ?? '')), 0, 50),
            ':reten' => substr(strtoupper(trim((string)($data['reten'] ?? ''))), 0, 1),
            ':ubcarp' => substr(trim((string)($data['ubcarp'] ?? '')), 0, 6),
            ':secuencia' => (int)($data['secuencia'] ?? 0),
            ':brevete' => substr(trim((string)($data['brevete'] ?? '')), 0, 11),
            ':ctactedetra' => substr(trim((string)($data['ctactedetra'] ?? '')), 0, 11),
            ':modelo' => $modelo,
            ':copa' => substr(trim((string)($data['copa'] ?? '')), 0, 4),
            ':placa' => substr(trim((string)($data['placa'] ?? '')), 0, 7),
            ':tipoprod' => substr(trim((string)($data['tipoprod'] ?? '')), 0, 1),
            ':ctaahorros' => substr(trim((string)($data['ctaahorros'] ?? '')), 0, 20),
            ':ctacorrientes' => substr(trim((string)($data['ctacorrientes'] ?? '')), 0, 20),
            ':ctaahorrod' => substr(trim((string)($data['ctaahorrod'] ?? '')), 0, 20),
            ':ctacorriented' => substr(trim((string)($data['ctacorriented'] ?? '')), 0, 20),
        );
    }

    public function eliminar(PDO $db, string $codigo): bool
    {
        $st = $db->prepare("DELETE FROM ccte WHERE codigo = :c");
        return $st->execute(array(':c' => $codigo));
    }

    public function cambiarEstado(PDO $db, string $codigo): bool
    {
        $stGet = $db->prepare("SELECT act_ivo FROM ccte WHERE codigo = :c LIMIT 1");
        $stGet->execute(array(':c' => $codigo));
        $actual = $stGet->fetchColumn();

        $actual = strtoupper(trim((string)$actual));
        $nuevo = ($actual === 'A' || $actual === 'S' || $actual === '') ? 'I' : 'A';

        $stUpdate = $db->prepare("UPDATE ccte SET act_ivo = :nuevo WHERE codigo = :c");
        return $stUpdate->execute(array(':nuevo' => $nuevo, ':c' => $codigo));
    }
}
