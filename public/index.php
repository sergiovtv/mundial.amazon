<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/bootstrap.php';

$filters = [
    'finalidade' => $_GET['finalidade'] ?? 'venda',
    'tipo' => $_GET['tipo'] ?? '',
    'cidade' => $_GET['cidade'] ?? '',
    'bairro' => $_GET['bairro'] ?? '',
    'dormitorios' => $_GET['dormitorios'] ?? '',
    'preco_min' => $_GET['preco_min'] ?? '',
    'preco_max' => $_GET['preco_max'] ?? '',
];
$page = max(1, (int) ($_GET['pagina'] ?? 1));
$error = null;

try {
    $repository = new PropertyRepository(Database::connect());
    $options = $repository->options();
    $neighborhoods = $repository->neighborhoods(ctype_digit((string) $filters['cidade']) ? (int) $filters['cidade'] : null);
    $result = $repository->search($filters, $page);
} catch (Throwable $exception) {
    $error = env('APP_DEBUG', 'false') === 'true' ? $exception->getMessage() : 'Não foi possível consultar os imóveis agora.';
    $options = ['types' => [], 'cities' => []];
    $neighborhoods = [];
    $result = ['items' => [], 'total' => 0, 'page' => 1, 'pages' => 1, 'priceColumn' => 'valorvenda'];
}

function queryForPage(int $page): string
{
    $query = $_GET;
    $query['pagina'] = $page;
    return '?' . http_build_query($query);
}

function propertyPhotoUrl(array $property): ?string
{
    $baseUrl = rtrim(env('MEDIA_BASE_URL', '/fotos'), '/');

    if (!empty($property['foto_imovel'])) {
        return sprintf(
            '%s/%d/%s',
            $baseUrl,
            (int) $property['codimovel'],
            rawurlencode((string) $property['foto_imovel'])
        );
    }

    if (!empty($property['codedificio']) && !empty($property['foto_edificio'])) {
        return sprintf(
            '%s/Edificios/%d/%s',
            $baseUrl,
            (int) $property['codedificio'],
            rawurlencode((string) $property['foto_edificio'])
        );
    }

    return null;
}
?>
<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Encontre seu imóvel | Mundial Imóveis</title>
    <link rel="stylesheet" href="/assets/app.css">
</head>
<body>
<header class="site-header">
    <a class="brand" href="/">Mundial <span>Imóveis</span></a>
    <span class="environment">Ambiente <?= e(env('APP_ENV', 'local')) ?></span>
</header>

<main>
    <section class="hero">
        <div>
            <p class="eyebrow">Ribeirão Preto e região</p>
            <h1>Um novo jeito de encontrar seu lugar.</h1>
            <p>Busque entre imóveis reais da Mundial com filtros rápidos e informações objetivas.</p>
        </div>

        <form class="search-panel" method="get">
            <label>Finalidade
                <select name="finalidade">
                    <option value="venda" <?= $filters['finalidade'] === 'venda' ? 'selected' : '' ?>>Comprar</option>
                    <option value="locacao" <?= $filters['finalidade'] === 'locacao' ? 'selected' : '' ?>>Alugar</option>
                </select>
            </label>
            <label>Tipo de imóvel
                <select name="tipo">
                    <option value="">Todos os tipos</option>
                    <?php foreach ($options['types'] as $type): ?>
                        <option value="<?= e($type['id']) ?>" <?= (string) $filters['tipo'] === (string) $type['id'] ? 'selected' : '' ?>><?= e($type['label']) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>Cidade
                <select name="cidade">
                    <option value="">Todas as cidades</option>
                    <?php foreach ($options['cities'] as $city): ?>
                        <option value="<?= e($city['id']) ?>" <?= (string) $filters['cidade'] === (string) $city['id'] ? 'selected' : '' ?>><?= e($city['label']) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>Bairro
                <select name="bairro">
                    <option value="">Todos os bairros</option>
                    <?php foreach ($neighborhoods as $neighborhood): ?>
                        <option value="<?= e($neighborhood['id']) ?>" <?= (string) $filters['bairro'] === (string) $neighborhood['id'] ? 'selected' : '' ?>><?= e($neighborhood['label']) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>Dormitórios
                <select name="dormitorios">
                    <option value="">Qualquer</option>
                    <?php foreach ([1, 2, 3, 4] as $bedrooms): ?>
                        <option value="<?= $bedrooms ?>" <?= (string) $filters['dormitorios'] === (string) $bedrooms ? 'selected' : '' ?>><?= $bedrooms ?> ou mais</option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>Preço mínimo
                <input name="preco_min" inputmode="numeric" value="<?= e($filters['preco_min']) ?>" placeholder="R$ 0">
            </label>
            <label>Preço máximo
                <input name="preco_max" inputmode="numeric" value="<?= e($filters['preco_max']) ?>" placeholder="Sem limite">
            </label>
            <button type="submit">Buscar imóveis</button>
        </form>
    </section>

    <section class="results">
        <div class="results-heading">
            <div>
                <p class="eyebrow">Resultado da busca</p>
                <h2><?= number_format($result['total'], 0, ',', '.') ?> imóveis encontrados</h2>
            </div>
            <a href="/">Limpar filtros</a>
        </div>

        <?php if ($error): ?>
            <div class="alert"><?= e($error) ?></div>
        <?php endif; ?>

        <div class="property-grid">
            <?php foreach ($result['items'] as $property): ?>
                <?php $photoUrl = propertyPhotoUrl($property); ?>
                <article class="property-card">
                    <div class="property-image">
                        <span>Imóvel sem foto</span>
                        <?php if ($photoUrl !== null): ?>
                            <img
                                src="<?= e($photoUrl) ?>"
                                alt="<?= e($property['tipo'] . ' em ' . $property['bairro']) ?>"
                                loading="lazy"
                                decoding="async"
                                onerror="this.style.display='none'"
                            >
                        <?php endif; ?>
                        <small>Ref. <?= e($property['codimovel']) ?></small>
                    </div>
                    <div class="property-body">
                        <p class="property-type"><?= e($property['tipo']) ?></p>
                        <h3><?= e($property['bairro']) ?>, <?= e($property['cidade']) ?></h3>
                        <div class="features">
                            <?php if ($property['dormitorios'] !== null): ?><span><?= e($property['dormitorios']) ?> dorm.</span><?php endif; ?>
                            <?php if ($property['suite']): ?><span><?= e($property['suite']) ?> suíte(s)</span><?php endif; ?>
                            <?php if ($property['garagem']): ?><span><?= e($property['garagem']) ?> vaga(s)</span><?php endif; ?>
                            <?php if ($property['area']): ?><span><?= e(number_format((float) $property['area'], 0, ',', '.')) ?> m²</span><?php endif; ?>
                        </div>
                        <strong><?= money($property[$result['priceColumn']]) ?></strong>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>

        <?php if ($result['pages'] > 1): ?>
            <nav class="pagination" aria-label="Paginação">
                <?php if ($result['page'] > 1): ?><a href="<?= e(queryForPage($result['page'] - 1)) ?>">Anterior</a><?php endif; ?>
                <span>Página <?= e($result['page']) ?> de <?= e($result['pages']) ?></span>
                <?php if ($result['page'] < $result['pages']): ?><a href="<?= e(queryForPage($result['page'] + 1)) ?>">Próxima</a><?php endif; ?>
            </nav>
        <?php endif; ?>
    </section>
</main>
</body>
</html>
