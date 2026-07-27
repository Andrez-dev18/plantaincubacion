<?php
date_default_timezone_set('America/Lima');

class ServiciosRepository
{
    /** @var PDO */
    private $conn;

    public function __construct(PDO $db)
    {
        $this->conn = $db;
    }

    public function listar(PDO $db, string $q = '', int $page = 1, int $pageSize = 25, string $codi = ''): array
    {
        $page = max(1, $page);
        $pageSize = max(1, min(100, $pageSize));
        $offset = ($page - 1) * $pageSize;

        $where = ' WHERE 1=1';
        $params = array();
        if ($q !== '') {
            $where .= ' AND (codi LIKE :q1 OR descri LIKE :q2)';
            $like = '%' . $q . '%';
            $params[':q1'] = $like;
            $params[':q2'] = $like;
        }

        if ($codi !== '') {
            $where .= ' AND codi = :codi';
            $params[':codi'] = $codi;
        }

        $stCount = $db->prepare('SELECT COUNT(*) FROM amar' . $where);
        $stCount->execute($params);
        $total = (int)$stCount->fetchColumn();

        $sql = "SELECT codi, descri, IFNULL(funcio,'') AS funcio,
                       IFNULL(natu_1,'') AS natu_1, IFNULL(natu_2,'') AS natu_2,
                       IFNULL(grupo,'') AS grupo, IFNULL(tipo,'') AS tipo,
                       IFNULL(bien,'') AS bien, IFNULL(porc,0) AS porc, IFNULL(monto,0) AS monto
                  FROM amar
                  $where
                 ORDER BY codi ASC
                 LIMIT $pageSize OFFSET $offset";
        $st = $db->prepare($sql);
        $st->execute($params);
        return array(
            'rows' => $st->fetchAll(PDO::FETCH_ASSOC),
            'total' => $total,
        );
    }

    public function obtener(PDO $db, string $codi): ?array
    {
        $st = $db->prepare(
            "SELECT codi, descri, IFNULL(funcio,'') AS funcio,
                    IFNULL(natu_1,'') AS natu_1, IFNULL(natu_2,'') AS natu_2,
                    IFNULL(grupo,'') AS grupo, IFNULL(tipo,'') AS tipo,
                    IFNULL(bien,'') AS bien, IFNULL(porc,0) AS porc, IFNULL(monto,0) AS monto,
                    IFNULL(igv,0) AS igv, IFNULL(ctanue,'') AS ctanue
               FROM amar
              WHERE codi = :c
              LIMIT 1"
        );
        $st->execute(array(':c' => $codi));
        $row = $st->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function insertar(PDO $db, array $data): void {
        $n = $this->normalizar($data);
        $st = $db->prepare(
            "INSERT INTO amar (
                codi, descri, funcio, natu_1, natu_2, grupo, tipo, bien, porc, monto, igv, ctanue
             ) VALUES (
                :codi, :descri, :funcio, :natu_1, :natu_2, :grupo, :tipo, :bien, :porc, :monto, :igv, :ctanue
             )"
        );
        $st->execute($n);
    }


    public function actualizar(PDO $db, string $codi, array $data): void {
        $n = $this->normalizar($data);
        $st = $db->prepare(
            "UPDATE amar SET
                descri = :descri, funcio = :funcio, natu_1 = :natu_1, natu_2 = :natu_2,
                grupo = :grupo, tipo = :tipo, bien = :bien, porc = :porc, monto = :monto,
                igv = :igv, ctanue = :ctanue
              WHERE codi = :codi"
        );
        $st->execute(array(
            ':descri' => $n[':descri'],
            ':funcio' => $n[':funcio'],
            ':natu_1' => $n[':natu_1'],
            ':natu_2' => $n[':natu_2'],
            ':grupo' => $n[':grupo'],
            ':tipo' => $n[':tipo'],
            ':bien' => $n[':bien'],
            ':porc' => $n[':porc'],
            ':monto' => $n[':monto'],
            ':igv' => $n[':igv'],
            ':ctanue' => $n[':ctanue'],
            ':codi' => substr(trim($codi), 0, 10),
        ));
    }

    private function normalizar(array $data): array {
        return array(
            ':codi' => substr(trim((string)($data['codi'] ?? '')), 0, 10),
            ':descri' => substr(trim((string)($data['descri'] ?? '')), 0, 150),
            ':funcio' => substr(trim((string)($data['funcio'] ?? '')), 0, 8),
            ':natu_1' => substr(trim((string)($data['natu_1'] ?? '')), 0, 8),
            ':natu_2' => substr(trim((string)($data['natu_2'] ?? '')), 0, 8),
            ':grupo' => substr(trim((string)($data['grupo'] ?? '')), 0, 3),
            ':tipo' => substr(trim((string)($data['tipo'] ?? '')), 0, 4),
            ':bien' => substr(trim((string)($data['bien'] ?? '')), 0, 4),
            ':porc' => (float)($data['porc'] ?? 0),
            ':monto' => (float)($data['monto'] ?? 0),
            ':igv' => (float)($data['igv'] ?? 0),
            ':ctanue' => substr(trim((string)($data['ctanue'] ?? '')), 0, 8),
        );
    }

    public function eliminar(PDO $db, string $codi): bool
    {
        $st = $db->prepare("DELETE FROM amar WHERE codi = :c");
        return $st->execute(array(':c' => $codi));
    }
}
