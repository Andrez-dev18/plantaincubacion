<?php
date_default_timezone_set('America/Lima');

class ArticulosRepository
{
    /** @var PDO */
    private $conn;

    public function __construct(PDO $db)
    {
        $this->conn = $db;
    }

    public function listar(PDO $db, string $q = '', int $page = 1, int $pageSize = 25): array
    {
        $page = max(1, $page);
        $pageSize = max(1, min(100, $pageSize));
        $offset = ($page - 1) * $pageSize;

        $where = ' WHERE 1=1';
        $params = array();
        if ($q !== '') {
            $where .= ' AND (codigo LIKE :q1 OR descri LIKE :q2)';
            $like = '%' . $q . '%';
            $params[':q1'] = $like;
            $params[':q2'] = $like;
        }

        $stCount = $db->prepare('SELECT COUNT(*) FROM mitm' . $where);
        $stCount->execute($params);
        $total = (int)$stCount->fetchColumn();

        $sql = "SELECT codigo, descri, IFNULL(unidad,'') AS unidad, IFNULL(peso,'') AS peso,
                       IFNULL(lin,'') AS lin, IFNULL(cuenta,'') AS cuenta,
                       IFNULL(ctacos,'') AS ctacos, IFNULL(ctacar,'') AS ctacar,
                       IFNULL(ctaabo,'') AS ctaabo, IFNULL(c_venta,'') AS c_venta,
                       IFNULL(preuni,0) AS preuni, IFNULL(preven,0) AS preven,
                       IFNULL(igv,0) AS igv, IFNULL(predol,0) AS predol,
                       IFNULL(estado,'A') AS estado, IFNULL(tactivo,'S') AS tactivo
                  FROM mitm
                  $where
                 ORDER BY codigo ASC
                 LIMIT $pageSize OFFSET $offset";
        $st = $db->prepare($sql);
        $st->execute($params);
        return array(
            'rows' => $st->fetchAll(PDO::FETCH_ASSOC),
            'total' => $total,
        );
    }

    public function obtener(PDO $db, string $codigo): ?array {
        $st = $db->prepare(
            "SELECT codigo, descri, IFNULL(unidad,'') AS unidad, IFNULL(peso,'') AS peso,
                    IFNULL(lin,'') AS lin, IFNULL(cuenta,'') AS cuenta,
                    IFNULL(ctacos,'') AS ctacos, IFNULL(ctacar,'') AS ctacar,
                    IFNULL(ctaabo,'') AS ctaabo, IFNULL(c_venta,'') AS c_venta,
                    IFNULL(preuni,0) AS preuni, IFNULL(preven,0) AS preven,
                    IFNULL(igv,0) AS igv, IFNULL(predol,0) AS predol,
                    IFNULL(DATE_FORMAT(fchpre,'%Y-%m-%d'),'') AS fchpre,
                    IFNULL(safestk,0) AS safestk, IFNULL(hibri,'') AS hibri,
                    IFNULL(DATE_FORMAT(fchvcto,'%Y-%m-%d'),'') AS fchvcto,
                    IFNULL(ctanue,'') AS ctanue, IFNULL(tipo,'') AS tipo,
                    IFNULL(estado,'A') AS estado, IFNULL(tactivo,'S') AS tactivo
               FROM mitm
              WHERE codigo = :c
              LIMIT 1"
        );
        $st->execute(array(':c' => $codigo));
        $row = $st->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function insertar(PDO $db, array $data): void {
        $n = $this->normalizar($data);
        $st = $db->prepare(
            "INSERT INTO mitm (
                codigo, descri, unidad, peso, lin, cuenta, ctacos, ctacar, ctaabo, c_venta,
                preuni, preven, igv, predol, fchpre, safestk, hibri, fchvcto, ctanue, tipo,
                estado, tactivo, codbarra, tipo_opera
             ) VALUES (
                :codigo, :descri, :unidad, :peso, :lin, :cuenta, :ctacos, :ctacar, :ctaabo, :c_venta,
                :preuni, :preven, :igv, :predol, :fchpre, :safestk, :hibri, :fchvcto, :ctanue, :tipo,
                :estado, :tactivo, '', 'G'
             )"
        );
        $st->execute($n);
    }

    public function actualizar(PDO $db, string $codigo, array $data): void {
        $n = $this->normalizar($data);
        $st = $db->prepare(
            "UPDATE mitm SET
                descri = :descri, unidad = :unidad, peso = :peso, lin = :lin,
                cuenta = :cuenta, ctacos = :ctacos, ctacar = :ctacar, ctaabo = :ctaabo,
                c_venta = :c_venta, preuni = :preuni, preven = :preven, igv = :igv,
                predol = :predol, fchpre = :fchpre, safestk = :safestk, hibri = :hibri,
                fchvcto = :fchvcto, ctanue = :ctanue, tipo = :tipo,
                estado = :estado, tactivo = :tactivo
              WHERE codigo = :codigo"
        );
        $st->execute(array(
            ':descri' => $n[':descri'],
            ':unidad' => $n[':unidad'],
            ':peso' => $n[':peso'],
            ':lin' => $n[':lin'],
            ':cuenta' => $n[':cuenta'],
            ':ctacos' => $n[':ctacos'],
            ':ctacar' => $n[':ctacar'],
            ':ctaabo' => $n[':ctaabo'],
            ':c_venta' => $n[':c_venta'],
            ':preuni' => $n[':preuni'],
            ':preven' => $n[':preven'],
            ':igv' => $n[':igv'],
            ':predol' => $n[':predol'],
            ':fchpre' => $n[':fchpre'],
            ':safestk' => $n[':safestk'],
            ':hibri' => $n[':hibri'],
            ':fchvcto' => $n[':fchvcto'],
            ':ctanue' => $n[':ctanue'],
            ':tipo' => $n[':tipo'],
            ':estado' => $n[':estado'],
            ':tactivo' => $n[':tactivo'],
            ':codigo' => substr(trim($codigo), 0, 8),
        ));
    }

    private function normalizar(array $data): array {
        $fchpre = trim((string)($data['fchpre'] ?? ''));
        $fchvcto = trim((string)($data['fchvcto'] ?? ''));
        if ($fchpre === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $fchpre)) {
            $fchpre = '0000-00-00';
        }
        if ($fchvcto === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $fchvcto)) {
            $fchvcto = '0000-00-00';
        }
        $estado = strtoupper(substr(trim((string)($data['estado'] ?? 'A')), 0, 1)) ?: 'A';
        $tactivo = strtoupper(substr(trim((string)($data['tactivo'] ?? 'S')), 0, 1)) ?: 'S';
        return array(
            ':codigo' => substr(trim((string)($data['codigo'] ?? '')), 0, 8),
            ':descri' => substr(trim((string)($data['descri'] ?? '')), 0, 250),
            ':unidad' => substr(trim((string)($data['unidad'] ?? '')), 0, 3),
            ':peso' => substr(trim((string)($data['peso'] ?? '')), 0, 5),
            ':lin' => substr(trim((string)($data['lin'] ?? '')), 0, 3),
            ':cuenta' => substr(trim((string)($data['cuenta'] ?? '')), 0, 8),
            ':ctacos' => substr(trim((string)($data['ctacos'] ?? '')), 0, 8),
            ':ctacar' => substr(trim((string)($data['ctacar'] ?? '')), 0, 8),
            ':ctaabo' => substr(trim((string)($data['ctaabo'] ?? '')), 0, 8),
            ':c_venta' => substr(trim((string)($data['c_venta'] ?? '')), 0, 8),
            ':preuni' => (float)($data['preuni'] ?? 0),
            ':preven' => (float)($data['preven'] ?? 0),
            ':igv' => (float)($data['igv'] ?? 0),
            ':predol' => (float)($data['predol'] ?? 0),
            ':fchpre' => $fchpre,
            ':safestk' => (float)($data['safestk'] ?? 0),
            ':hibri' => substr(trim((string)($data['hibri'] ?? '')), 0, 1),
            ':fchvcto' => $fchvcto,
            ':ctanue' => substr(trim((string)($data['ctanue'] ?? '')), 0, 8),
            ':tipo' => substr(trim((string)($data['tipo'] ?? '')), 0, 4),
            ':estado' => $estado,
            ':tactivo' => $tactivo,
        );
    }

    public function eliminar(PDO $db, string $codigo): bool
    {
        $st = $db->prepare("DELETE FROM mitm WHERE codigo = :c");
        return $st->execute(array(':c' => $codigo));
    }
}
