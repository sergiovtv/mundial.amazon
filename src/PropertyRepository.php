<?php

declare(strict_types=1);

final class PropertyRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function options(): array
    {
        return [
            'types' => $this->pdo->query(
                "SELECT codtipoimovel AS id, desctipoimovel AS label
                 FROM tipoimovel
                 ORDER BY desctipoimovel"
            )->fetchAll(),
            'cities' => $this->pdo->query(
                "SELECT DISTINCT c.codcidade AS id, c.desccidade AS label
                 FROM cidade c
                 JOIN bairro b ON b.codcidade = c.codcidade
                 JOIN imovel i ON i.codbairro = b.codbairro
                 WHERE i.exibesite = 'S'
                 ORDER BY c.desccidade"
            )->fetchAll(),
        ];
    }

    public function search(array $filters, int $page = 1, int $perPage = 12): array
    {
        $where = ["i.exibesite = 'S'", 'oi.datacancelamento IS NULL'];
        $params = [];

        $operation = ($filters['finalidade'] ?? 'venda') === 'locacao' ? 4 : 5;
        $where[] = 'oi.codoperacao = :operation';
        $params['operation'] = $operation;

        foreach (['tipo' => 'i.codtipoimovel', 'cidade' => 'c.codcidade', 'bairro' => 'b.codbairro'] as $key => $column) {
            if (($filters[$key] ?? '') !== '' && ctype_digit((string) $filters[$key])) {
                $where[] = "{$column} = :{$key}";
                $params[$key] = (int) $filters[$key];
            }
        }

        if (($filters['dormitorios'] ?? '') !== '' && ctype_digit((string) $filters['dormitorios'])) {
            $where[] = 'COALESCE(i.dormitorios, 0) >= :dormitorios';
            $params['dormitorios'] = (int) $filters['dormitorios'];
        }

        $priceColumn = $operation === 4 ? 'i.valorlocacao' : 'i.valorvenda';
        foreach (['preco_min' => '>=', 'preco_max' => '<='] as $key => $operator) {
            $raw = str_replace(['.', ','], ['', '.'], (string) ($filters[$key] ?? ''));
            if ($raw !== '' && is_numeric($raw)) {
                $where[] = "{$priceColumn} {$operator} :{$key}";
                $params[$key] = (float) $raw;
            }
        }

        $whereSql = implode(' AND ', $where);
        $fromSql = "
            FROM imovel i
            JOIN operacaoimovel oi ON oi.codimovel = i.codimovel
            JOIN tipoimovel t ON t.codtipoimovel = i.codtipoimovel
            JOIN bairro b ON b.codbairro = i.codbairro
            JOIN cidade c ON c.codcidade = b.codcidade
        ";

        $count = $this->pdo->prepare("SELECT COUNT(DISTINCT i.codimovel) {$fromSql} WHERE {$whereSql}");
        $count->execute($params);
        $total = (int) $count->fetchColumn();

        $offset = max(0, ($page - 1) * $perPage);
        $sql = "
            SELECT DISTINCT ON (i.codimovel)
                i.codimovel,
                i.codedificio,
                t.desctipoimovel AS tipo,
                b.descbairro AS bairro,
                c.desccidade AS cidade,
                i.valorvenda,
                i.valorlocacao,
                i.dormitorios,
                i.suite,
                i.garagem,
                COALESCE(NULLIF(i.areaconstruida, 0), NULLIF(i.terrenototal, 0)) AS area,
                (
                    SELECT f.nome_imagem
                    FROM fotoimovel f
                    WHERE f.codimovel = i.codimovel
                      AND f.situacao_cadastro = 'A'
                    ORDER BY f.ordemexibicao NULLS LAST, f.codfotoimovel
                    LIMIT 1
                ) AS foto_imovel,
                (
                    SELECT f.nome_imagem
                    FROM fotoedificio f
                    WHERE f.codedificio = i.codedificio
                      AND f.situacao_cadastro = 'A'
                    ORDER BY f.ordemexibicao NULLS LAST, f.codfotoedificio
                    LIMIT 1
                ) AS foto_edificio
            {$fromSql}
            WHERE {$whereSql}
            ORDER BY i.codimovel, i.dataalteracao DESC NULLS LAST
            LIMIT :limit OFFSET :offset
        ";

        $statement = $this->pdo->prepare($sql);
        foreach ($params as $key => $value) {
            $statement->bindValue(':' . $key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
        $statement->bindValue(':limit', $perPage, PDO::PARAM_INT);
        $statement->bindValue(':offset', $offset, PDO::PARAM_INT);
        $statement->execute();

        return [
            'items' => $statement->fetchAll(),
            'total' => $total,
            'page' => $page,
            'pages' => max(1, (int) ceil($total / $perPage)),
            'priceColumn' => $operation === 4 ? 'valorlocacao' : 'valorvenda',
        ];
    }

    public function neighborhoods(?int $cityId): array
    {
        $sql = "SELECT DISTINCT b.codbairro AS id, b.descbairro AS label
                FROM bairro b
                JOIN imovel i ON i.codbairro = b.codbairro
                WHERE i.exibesite = 'S'";
        $params = [];

        if ($cityId !== null) {
            $sql .= ' AND b.codcidade = :city';
            $params['city'] = $cityId;
        }

        $sql .= ' ORDER BY b.descbairro';
        $statement = $this->pdo->prepare($sql);
        $statement->execute($params);
        return $statement->fetchAll();
    }
}
