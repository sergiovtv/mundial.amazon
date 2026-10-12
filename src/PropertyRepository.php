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

    public function siteParameter(string $description): string
    {
        $statement = $this->pdo->prepare(
            'SELECT valorparametrosite
             FROM parametrosite
             WHERE descparametrosite = :description
             ORDER BY codparametrosite
             LIMIT 1'
        );
        $statement->execute(['description' => $description]);

        return trim((string) ($statement->fetchColumn() ?: ''));
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
        $where[] = "COALESCE({$priceColumn}, 0) > 0";

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
                      AND f.situacao_cadastro <> 'E' AND f.coduserdesativacao IS NULL
                    ORDER BY f.ordemexibicao NULLS LAST, f.codfotoimovel
                    LIMIT 1
                ) AS foto_imovel,
                (
                    SELECT f.nome_imagem
                    FROM fotoedificio f
                    WHERE f.codedificio = i.codedificio
                      AND f.situacao_cadastro <> 'E' AND f.coduserdesativacao IS NULL
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

    /** Foto de piscina do imóvel residencial à venda de maior valor, usada no fundo da home. */
    public function heroPhoto(): ?array
    {
        $statement = $this->pdo->query(
            "SELECT 'imovel' AS photo_source, i.codimovel, i.codedificio, f.nome_imagem
               FROM fotoimovel f
               JOIN imovel i ON i.codimovel = f.codimovel
               JOIN operacaoimovel oi ON oi.codimovel = i.codimovel
                AND oi.codoperacao = 5 AND oi.datacancelamento IS NULL
               JOIN tipoimovel t ON t.codtipoimovel = i.codtipoimovel
              WHERE f.codtipofoto IN (41, 34)
                AND f.situacao_cadastro <> 'E' AND f.coduserdesativacao IS NULL
                AND NULLIF(TRIM(f.nome_imagem), '') IS NOT NULL
                AND t.desctipoimovel IN ('Casa', 'Apartamento', 'Loft')
                AND i.exibesite = 'S' AND COALESCE(i.valorvenda, 0) > 0
              ORDER BY i.valorvenda DESC, i.codimovel, f.ordemexibicao NULLS LAST, f.codfotoimovel
              LIMIT 1"
        );
        $photo = $statement->fetch();
        return $photo ?: null;
    }

    public function visualGalleries(int $limitPerGallery = 8, ?string $visitorHash = null): array
    {
        $limitPerGallery = max(1, min(12, $limitPerGallery));
        $candidateLimit = min(24, $limitPerGallery * 2);
        $feedbackAvailable = $this->photoFeedbackAvailable();
        $scoreColumns = $feedbackAvailable
            ? 'COALESCE(s.likes, 0) AS likes, COALESCE(s.dislikes, 0) AS dislikes, COALESCE(s.score, 0) AS score,'
            : '0::integer AS likes, 0::integer AS dislikes, 0::integer AS score,';
        $propertyScoreJoin = $feedbackAvailable
            ? "LEFT JOIN homepage_photo_score s ON s.source = 'imovel' AND s.photo_id = f.codfotoimovel"
            : '';
        $buildingScoreJoin = $feedbackAvailable
            ? "LEFT JOIN homepage_photo_score s ON s.source = 'edificio' AND s.photo_id = f.codfotoedificio"
            : '';
        $propertyFeedbackWhere = $feedbackAvailable
            ? "AND COALESCE(s.hidden, FALSE) = FALSE
               AND (COALESCE(s.likes, 0) + COALESCE(s.dislikes, 0) < 5
                    OR COALESCE(s.dislikes, 0) * 100 < (COALESCE(s.likes, 0) + COALESCE(s.dislikes, 0)) * 60)
               AND NOT EXISTS (
                   SELECT 1 FROM homepage_photo_reaction r
                   WHERE r.source = 'imovel' AND r.photo_id = f.codfotoimovel
                     AND r.visitor_hash = :property_visitor_hash AND r.reaction = -1
               )"
            : '';
        $buildingFeedbackWhere = $feedbackAvailable
            ? "AND COALESCE(s.hidden, FALSE) = FALSE
               AND (COALESCE(s.likes, 0) + COALESCE(s.dislikes, 0) < 5
                    OR COALESCE(s.dislikes, 0) * 100 < (COALESCE(s.likes, 0) + COALESCE(s.dislikes, 0)) * 60)
               AND NOT EXISTS (
                   SELECT 1 FROM homepage_photo_reaction r
                   WHERE r.source = 'edificio' AND r.photo_id = f.codfotoedificio
                     AND r.visitor_hash = :building_visitor_hash AND r.reaction = -1
               )"
            : '';
        $sql = "
            WITH photo_theme(codtipofoto, theme) AS (
                VALUES
                    (41, 'piscinas'), (34, 'piscinas'),
                    (16, 'jardins'), (49, 'jardins'),
                    (84, 'vistas'),
                    (36, 'varandas'), (65, 'varandas'), (64, 'varandas'),
                    (46, 'gourmet'), (30, 'gourmet'), (29, 'gourmet'),
                    (1, 'fachadas'), (4, 'fachadas'),
                    (10, 'cozinhas'), (69, 'cozinhas')
            ),
            candidates AS (
                SELECT
                    pt.theme,
                    f.codfotoimovel AS photo_id,
                    i.codimovel,
                    i.codedificio,
                    t.desctipoimovel AS tipo,
                    b.descbairro AS bairro,
                    c.desccidade AS cidade,
                    f.nome_imagem,
                    'imovel'::text AS photo_source,
                    {$scoreColumns}
                    COALESCE(f.dataalteracao, f.dataatualizacao, f.data) AS photo_date,
                    CASE WHEN oi.codoperacao = 5 THEN 'venda' ELSE 'locacao' END AS finalidade
                FROM fotoimovel f
                JOIN photo_theme pt ON pt.codtipofoto = f.codtipofoto
                JOIN imovel i ON i.codimovel = f.codimovel
                JOIN operacaoimovel oi ON oi.codimovel = i.codimovel
                JOIN tipoimovel t ON t.codtipoimovel = i.codtipoimovel
                JOIN bairro b ON b.codbairro = i.codbairro
                JOIN cidade c ON c.codcidade = b.codcidade
                {$propertyScoreJoin}
                WHERE f.situacao_cadastro <> 'E' AND f.coduserdesativacao IS NULL
                  AND NULLIF(TRIM(f.nome_imagem), '') IS NOT NULL
                  AND i.exibesite = 'S'
                  AND oi.codoperacao IN (4, 5)
                  AND oi.datacancelamento IS NULL
                  AND CASE oi.codoperacao
                      WHEN 4 THEN COALESCE(i.valorlocacao, 0)
                      ELSE COALESCE(i.valorvenda, 0)
                  END > 0
                  {$propertyFeedbackWhere}

                UNION ALL

                SELECT
                    pt.theme,
                    f.codfotoedificio AS photo_id,
                    i.codimovel,
                    i.codedificio,
                    t.desctipoimovel AS tipo,
                    b.descbairro AS bairro,
                    c.desccidade AS cidade,
                    f.nome_imagem,
                    'edificio'::text AS photo_source,
                    {$scoreColumns}
                    COALESCE(f.dataalteracao, f.dataatualizacao, f.data) AS photo_date,
                    CASE WHEN oi.codoperacao = 5 THEN 'venda' ELSE 'locacao' END AS finalidade
                FROM fotoedificio f
                JOIN photo_theme pt ON pt.codtipofoto = f.codtipofoto
                JOIN imovel i ON i.codedificio = f.codedificio
                JOIN operacaoimovel oi ON oi.codimovel = i.codimovel
                JOIN tipoimovel t ON t.codtipoimovel = i.codtipoimovel
                JOIN bairro b ON b.codbairro = i.codbairro
                JOIN cidade c ON c.codcidade = b.codcidade
                {$buildingScoreJoin}
                WHERE f.situacao_cadastro <> 'E' AND f.coduserdesativacao IS NULL
                  AND NULLIF(TRIM(f.nome_imagem), '') IS NOT NULL
                  AND i.exibesite = 'S'
                  AND oi.codoperacao IN (4, 5)
                  AND oi.datacancelamento IS NULL
                  AND CASE oi.codoperacao
                      WHEN 4 THEN COALESCE(i.valorlocacao, 0)
                      ELSE COALESCE(i.valorvenda, 0)
                  END > 0
                  {$buildingFeedbackWhere}
            ),
            unique_images AS (
                SELECT DISTINCT ON (
                    theme,
                    photo_source,
                    CASE WHEN photo_source = 'edificio' THEN codedificio ELSE codimovel END,
                    nome_imagem
                ) *
                FROM candidates
                ORDER BY
                    theme,
                    photo_source,
                    CASE WHEN photo_source = 'edificio' THEN codedificio ELSE codimovel END,
                    nome_imagem,
                    photo_date DESC NULLS LAST,
                    codimovel DESC
            ),
            unique_photos AS (
                SELECT DISTINCT ON (theme, codimovel)
                    *
                FROM unique_images
                ORDER BY theme, codimovel, photo_source DESC, photo_date DESC NULLS LAST, nome_imagem
            ),
            ranked AS (
                SELECT *, ROW_NUMBER() OVER (
                    PARTITION BY theme
                    ORDER BY score DESC, likes DESC, photo_date DESC NULLS LAST, codimovel DESC
                ) AS position
                FROM unique_photos
            )
            SELECT theme, photo_id, codimovel, codedificio, tipo, bairro, cidade,
                   nome_imagem, photo_source, finalidade, likes, dislikes, score
            FROM ranked
            WHERE position <= :candidate_limit
            ORDER BY theme, position
        ";

        $statement = $this->pdo->prepare($sql);
        $statement->bindValue(':candidate_limit', $candidateLimit, PDO::PARAM_INT);
        if ($feedbackAvailable) {
            $statement->bindValue(':property_visitor_hash', $visitorHash ?? str_repeat('0', 64));
            $statement->bindValue(':building_visitor_hash', $visitorHash ?? str_repeat('0', 64));
        }
        $statement->execute();

        $definitions = [
            'piscinas' => ['title' => 'Piscinas para mergulhar de cabeça', 'description' => 'Um refresco para os olhos — e talvez o seu próximo endereço.'],
            'jardins' => ['title' => 'Jardins para respirar', 'description' => 'Verde, sombra e espaço para a vida acontecer lá fora.'],
            'vistas' => ['title' => 'Vistas que fazem parar', 'description' => 'Janelas e varandas que transformam a paisagem em parte da casa.'],
            'varandas' => ['title' => 'Varandas para viver lá fora', 'description' => 'Café demorado, conversa boa e um pouco mais de horizonte.'],
            'gourmet' => ['title' => 'Espaços feitos para receber', 'description' => 'Ambientes onde reunir as pessoas já faz parte da planta.'],
            'fachadas' => ['title' => 'Fachadas que roubam a cena', 'description' => 'A primeira impressão também pode ser inesquecível.'],
            'cozinhas' => ['title' => 'Cozinhas que inspiram', 'description' => 'Para quem sabe que os melhores encontros começam por aqui.'],
        ];
        $photosByTheme = [];
        foreach ($statement->fetchAll() as $photo) {
            $theme = $photo['theme'];
            if (!isset($definitions[$theme])) continue;
            $photosByTheme[$theme][] = $photo;
        }

        $galleries = [];
        foreach ($definitions as $theme => $definition) {
            if (($photosByTheme[$theme] ?? []) === []) continue;
            $galleries[] = $definition + [
                'key' => $theme,
                'photos' => array_slice($photosByTheme[$theme], 0, $limitPerGallery),
                'reserves' => array_slice($photosByTheme[$theme], $limitPerGallery),
            ];
        }

        return $galleries;
    }

    public function photoFeedbackAvailable(): bool
    {
        $statement = $this->pdo->query(
            "SELECT to_regclass('public.homepage_photo_score') IS NOT NULL
                AND to_regclass('public.homepage_photo_reaction') IS NOT NULL"
        );
        return (bool) $statement->fetchColumn();
    }

    public function recordPhotoReaction(string $source, int $photoId, string $visitorHash, int $reaction): array
    {
        if (!$this->photoFeedbackAvailable()) {
            throw new RuntimeException('A avaliação de fotos ainda não está disponível.');
        }
        if (!in_array($source, ['imovel', 'edificio'], true) || $photoId < 1 || !in_array($reaction, [-1, 1], true)) {
            throw new InvalidArgumentException('Avaliação inválida.');
        }

        $table = $source === 'imovel' ? 'fotoimovel' : 'fotoedificio';
        $idColumn = $source === 'imovel' ? 'codfotoimovel' : 'codfotoedificio';
        $exists = $this->pdo->prepare(
            "SELECT 1 FROM {$table}
             WHERE {$idColumn} = :photo AND situacao_cadastro <> 'E' AND coduserdesativacao IS NULL
             LIMIT 1"
        );
        $exists->execute(['photo' => $photoId]);
        if (!$exists->fetchColumn()) {
            throw new InvalidArgumentException('Foto não encontrada.');
        }

        $statement = $this->pdo->prepare(
            "INSERT INTO homepage_photo_reaction (source, photo_id, visitor_hash, reaction)
             VALUES (:source, :photo, :visitor, :reaction)
             ON CONFLICT (source, photo_id, visitor_hash)
             DO UPDATE SET reaction = EXCLUDED.reaction, updated_at = CURRENT_TIMESTAMP"
        );
        $statement->execute([
            'source' => $source,
            'photo' => $photoId,
            'visitor' => $visitorHash,
            'reaction' => $reaction,
        ]);

        $score = $this->pdo->prepare(
            'SELECT likes, dislikes, score
             FROM homepage_photo_score
             WHERE source = :source AND photo_id = :photo'
        );
        $score->execute(['source' => $source, 'photo' => $photoId]);
        return $score->fetch() ?: ['likes' => 0, 'dislikes' => 0, 'score' => 0];
    }

    public function find(int $propertyId, string $purpose = ''): ?array
    {
        $operation = $purpose === 'locacao' ? 4 : ($purpose === 'venda' ? 5 : null);
        $operationWhere = $operation === null ? 'oi.codoperacao IN (4, 5)' : 'oi.codoperacao = :operation';
        $sql = "
            SELECT DISTINCT ON (i.codimovel)
                i.codimovel,
                i.codedificio,
                t.desctipoimovel AS tipo,
                b.descbairro AS bairro,
                c.desccidade AS cidade,
                i.valorvenda,
                i.valorlocacao,
                i.valorcondominio,
                i.iptu,
                i.dormitorios,
                i.suite,
                i.banheiros,
                i.garagem,
                i.garagemcoberta,
                i.andar,
                i.mobiliado,
                i.elevador,
                i.portaria24horas,
                i.piscina,
                i.churrasqueira,
                i.quintal,
                i.escritorio,
                i.lavabo,
                i.areaservico,
                i.areaconstruida,
                i.terrenototal,
                i.infoarea,
                i.infopredio,
                i.pontoreferencia,
                i.complemento,
                i.observacao,
                oi.codempresa,
                it.texto1,
                it.texto2,
                ed.descedificio AS edificio
            FROM imovel i
            JOIN operacaoimovel oi ON oi.codimovel = i.codimovel
            JOIN tipoimovel t ON t.codtipoimovel = i.codtipoimovel
            JOIN bairro b ON b.codbairro = i.codbairro
            JOIN cidade c ON c.codcidade = b.codcidade
            LEFT JOIN imoveltexto it ON it.codimovel = i.codimovel
            LEFT JOIN edificio ed ON ed.codedificio = i.codedificio
            WHERE i.codimovel = :property
              AND i.exibesite = 'S'
              AND {$operationWhere}
              AND oi.datacancelamento IS NULL
            ORDER BY i.codimovel, oi.datainicio DESC NULLS LAST
        ";
        $statement = $this->pdo->prepare($sql);
        $params = ['property' => $propertyId];
        if ($operation !== null) $params['operation'] = $operation;
        $statement->execute($params);
        $property = $statement->fetch();

        if (!$property) {
            return null;
        }

        $operations = $this->pdo->prepare(
            "SELECT DISTINCT codoperacao
             FROM operacaoimovel
             WHERE codimovel = :property
               AND codoperacao IN (4, 5)
               AND datacancelamento IS NULL
             ORDER BY codoperacao"
        );
        $operations->execute(['property' => $propertyId]);
        $property['purposes'] = array_map(
            static fn ($code) => (int) $code === 4 ? 'locacao' : 'venda',
            $operations->fetchAll(PDO::FETCH_COLUMN)
        );

        $photos = $this->photos($propertyId, $property['codedificio'] ? (int) $property['codedificio'] : null);
        $property['photos'] = $photos;

        $features = $this->pdo->prepare(
            "SELECT DISTINCT item.descitem
             FROM imoveldiferenciais d
             JOIN item ON item.coditem = d.coditem
             WHERE d.codimovel = :property
               AND NULLIF(TRIM(item.descitem), '') IS NOT NULL
             ORDER BY item.descitem"
        );
        $features->execute(['property' => $propertyId]);
        $property['diferenciais'] = $features->fetchAll(PDO::FETCH_COLUMN);

        return $property;
    }

    public function sitemapProperties(): array
    {
        return $this->pdo->query(
            "SELECT DISTINCT
                i.codimovel,
                t.desctipoimovel AS tipo,
                b.descbairro AS bairro,
                c.desccidade AS cidade,
                CASE oi.codoperacao WHEN 4 THEN 'locacao' ELSE 'venda' END AS finalidade,
                i.dataalteracao AS alterado_em
             FROM imovel i
             JOIN operacaoimovel oi ON oi.codimovel = i.codimovel
             JOIN tipoimovel t ON t.codtipoimovel = i.codtipoimovel
             JOIN bairro b ON b.codbairro = i.codbairro
             JOIN cidade c ON c.codcidade = b.codcidade
             WHERE i.exibesite = 'S'
               AND oi.codoperacao IN (4, 5)
               AND oi.datacancelamento IS NULL
               AND CASE oi.codoperacao
                   WHEN 4 THEN COALESCE(i.valorlocacao, 0)
                   ELSE COALESCE(i.valorvenda, 0)
               END > 0
             ORDER BY i.codimovel"
        )->fetchAll();
    }

    public function createInquiry(array $property, string $purpose, array $data): int
    {
        $operation = $purpose === 'locacao' ? 4 : 5;
        // Mesmo formato do formulário do site antigo, que o ERP importa: empresa Mundial (2),
        // atendimento genérico, telefone também em atendidofone2 e referência do imóvel na mensagem.
        $message = (int) $property['codimovel'] . ' / ' . $data['message'];
        $duplicate = $this->pdo->prepare(
            "SELECT codatendimento
             FROM atendimento
             WHERE codimovel = :property
               AND atendidofone1 = :phone
               AND atendidoemail = :email
               AND dataatendimento = CURRENT_DATE
               AND (LOCALTIMESTAMP - (dataatendimento + horaatendimento))
                   BETWEEN INTERVAL '0 seconds' AND INTERVAL '5 minutes'
             ORDER BY codatendimento DESC
             LIMIT 1"
        );
        $duplicate->execute([
            'property' => (int) $property['codimovel'],
            'phone' => $data['phone'],
            'email' => $data['email'],
        ]);
        $existing = $duplicate->fetchColumn();
        if ($existing !== false) {
            return (int) $existing;
        }

        $statement = $this->pdo->prepare(
            "INSERT INTO atendimento (
                dataatendimento, horaatendimento, atendidonome, atendidofone1, atendidofone2,
                atendidoemail, observacao, codoperacao, codimovel, codedificio,
                codempresa, atendimentogenerico
             ) VALUES (
                CURRENT_DATE, LOCALTIME, :name, :phone, :phone_secondary,
                :email, :message, :operation, :property, :building,
                2, 'S'
             )
             RETURNING codatendimento"
        );
        $statement->execute([
            'name' => $data['name'],
            'phone' => $data['phone'],
            'phone_secondary' => $data['phone'],
            'email' => $data['email'],
            'message' => $message,
            'operation' => $operation,
            'property' => (int) $property['codimovel'],
            'building' => $property['codedificio'] ?: null,
        ]);

        return (int) $statement->fetchColumn();
    }

    private function photos(int $propertyId, ?int $buildingId): array
    {
        $statement = $this->pdo->prepare(
            "SELECT f.codfotoimovel AS id, f.nome_imagem AS name, f.descricao,
                    COALESCE(NULLIF(TRIM(tf.desctipofoto), ''), NULLIF(TRIM(f.descricao), '')) AS type_description,
                    'imovel' AS source
             FROM fotoimovel f
             LEFT JOIN tipofoto tf ON tf.codtipofoto = f.codtipofoto
             WHERE f.codimovel = :property AND f.situacao_cadastro <> 'E' AND f.coduserdesativacao IS NULL
               AND COALESCE(tf.exibesite, 'S') = 'S'
             ORDER BY f.ordemexibicao NULLS LAST, f.codfotoimovel"
        );
        $statement->execute(['property' => $propertyId]);
        $photos = $statement->fetchAll();

        if ($buildingId === null) {
            return $photos;
        }

        $statement = $this->pdo->prepare(
            "SELECT f.codfotoedificio AS id, f.nome_imagem AS name, f.descricao,
                    COALESCE(NULLIF(TRIM(tf.desctipofoto), ''), NULLIF(TRIM(f.descricao), '')) AS type_description,
                    'edificio' AS source
             FROM fotoedificio f
             LEFT JOIN tipofoto tf ON tf.codtipofoto = f.codtipofoto
             WHERE f.codedificio = :building AND f.situacao_cadastro <> 'E' AND f.coduserdesativacao IS NULL
               AND COALESCE(tf.exibesite, 'S') = 'S'
             ORDER BY f.ordemexibicao NULLS LAST, f.codfotoedificio"
        );
        $statement->execute(['building' => $buildingId]);
        return array_merge($photos, $statement->fetchAll());
    }
}
