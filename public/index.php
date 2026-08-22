<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/bootstrap.php';

$filters = [
    'codigo' => $_GET['codigo'] ?? '',
    'finalidade' => $_GET['finalidade'] ?? 'venda',
    'tipo' => $_GET['tipo'] ?? '',
    'cidade' => $_GET['cidade'] ?? '',
    'bairro' => $_GET['bairro'] ?? '',
    'dormitorios' => $_GET['dormitorios'] ?? '',
    'preco_min' => $_GET['preco_min'] ?? '',
    'preco_max' => $_GET['preco_max'] ?? '',
];

if ($filters['codigo'] !== '' && ctype_digit((string) $filters['codigo']) && (int) $filters['codigo'] > 0) {
    header('Location: /imovel.php?id=' . (int) $filters['codigo']);
    exit;
}
$page = max(1, (int) ($_GET['pagina'] ?? 1));
$error = null;
$whatsappPhone = '';
$hasFilters = array_filter($filters, static fn ($value, $key) => $key !== 'finalidade' ? $value !== '' : $value !== 'venda', ARRAY_FILTER_USE_BOTH) !== [] || $page > 1;

try {
    $repository = new PropertyRepository(Database::connect());
    $whatsappPhone = $repository->siteParameter('var_whatsapp');
    $options = $repository->options();
    $neighborhoods = $repository->neighborhoods(ctype_digit((string) $filters['cidade']) ? (int) $filters['cidade'] : null);
    $result = $repository->search($filters, $page);
    $visualGalleries = $hasFilters ? [] : $repository->visualGalleries();
} catch (Throwable $exception) {
    $error = env('APP_DEBUG', 'false') === 'true' ? $exception->getMessage() : 'Não foi possível consultar os imóveis agora.';
    $options = ['types' => [], 'cities' => []];
    $neighborhoods = [];
    $result = ['items' => [], 'total' => 0, 'page' => 1, 'pages' => 1, 'priceColumn' => 'valorvenda'];
    $visualGalleries = [];
}

function queryForPage(int $page): string
{
    $query = $_GET;
    $query['pagina'] = $page;
    return '?' . http_build_query($query);
}

$canonicalUrl = siteUrl('/');

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

function visualPhotoUrl(array $photo): string
{
    $baseUrl = rtrim(env('MEDIA_BASE_URL', '/fotos'), '/');
    if ($photo['photo_source'] === 'edificio') {
        return sprintf('%s/Edificios/%d/%s', $baseUrl, (int) $photo['codedificio'], rawurlencode((string) $photo['nome_imagem']));
    }
    return sprintf('%s/%d/%s', $baseUrl, (int) $photo['codimovel'], rawurlencode((string) $photo['nome_imagem']));
}
?>
<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Encontre seu imóvel | Mundial Imóveis</title>
    <meta name="description" content="Imóveis à venda e para locação em Ribeirão Preto e região. Encontre casas, apartamentos e terrenos na Mundial Imóveis.">
    <link rel="canonical" href="<?= e($canonicalUrl) ?>">
    <?php if ($hasFilters): ?><meta name="robots" content="noindex,follow"><?php endif; ?>
    <link rel="stylesheet" href="/assets/app.css">
</head>
<body>
<header class="site-header">
    <a class="brand" href="/">Mundial <span>Imóveis</span></a>
    <a class="secondary-link" href="/cliente/">Área do cliente</a>
</header>

<main>
    <section class="hero">
        <div>
            <p class="eyebrow">Ribeirão Preto e região</p>
            <h1>Um novo jeito de encontrar seu lugar.</h1>
            <p>Busque entre imóveis reais da Mundial com filtros rápidos e informações objetivas.</p>
        </div>

        <form class="search-panel" method="get">
            <label>Código do imóvel
                <input name="codigo" inputmode="numeric" pattern="[0-9]*" value="<?= e($filters['codigo']) ?>" placeholder="Ex.: 62602">
            </label>
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

    <?php if ($visualGalleries !== []): ?>
    <section class="visual-discovery" aria-labelledby="visual-discovery-title">
        <div class="visual-discovery-intro">
            <p class="eyebrow">Explore com os olhos</p>
            <h2 id="visual-discovery-title">Às vezes, a escolha começa por um detalhe.</h2>
            <p>Passeie por ambientes reais dos nossos imóveis. Encontrou um que chamou sua atenção? É só entrar.</p>
        </div>

        <?php foreach ($visualGalleries as $galleryIndex => $gallery): ?>
            <section class="visual-gallery" aria-labelledby="gallery-<?= e($gallery['key']) ?>">
                <div class="visual-gallery-heading">
                    <div>
                        <span><?= str_pad((string) ($galleryIndex + 1), 2, '0', STR_PAD_LEFT) ?></span>
                        <h3 id="gallery-<?= e($gallery['key']) ?>"><?= e($gallery['title']) ?></h3>
                    </div>
                    <p><?= e($gallery['description']) ?></p>
                </div>
                <div class="visual-mosaic visual-mosaic-<?= e((string) (($galleryIndex % 3) + 1)) ?>">
                    <?php foreach ($gallery['photos'] as $photoIndex => $photo): ?>
                        <a class="visual-tile visual-tile-<?= e((string) (($photoIndex % 8) + 1)) ?>" href="<?= e(propertyPath($photo, $photo['finalidade'])) ?>" aria-label="Ver <?= e($photo['tipo']) ?> em <?= e($photo['bairro']) ?>">
                            <img src="<?= e(visualPhotoUrl($photo)) ?>" alt="<?= e($gallery['title'] . ' — ' . $photo['tipo'] . ' em ' . $photo['bairro']) ?>" loading="lazy" decoding="async">
                            <span>
                                <small><?= e($photo['tipo']) ?></small>
                                <strong><?= e($photo['bairro']) ?></strong>
                            </span>
                        </a>
                    <?php endforeach; ?>
                </div>
            </section>
        <?php endforeach; ?>
    </section>
    <?php endif; ?>

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
                    <a class="property-card-link" href="<?= e(propertyPath($property, (string) $filters['finalidade'])) ?>" aria-label="Ver <?= e($property['tipo']) ?> em <?= e($property['bairro']) ?>">
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
                    </a>
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
<?php if ($whatsappPhone !== ''): ?>
<a class="whatsapp-float" href="<?= e(whatsappUrl($whatsappPhone, 'Olá! Gostaria de informações sobre os imóveis da Mundial.')) ?>" target="_blank" rel="noopener noreferrer" aria-label="Falar com a Mundial pelo WhatsApp">
    <svg viewBox="0 0 32 32" aria-hidden="true"><path fill="currentColor" d="M16.04 3A12.9 12.9 0 0 0 5.1 22.73L3.3 29l6.43-1.69A12.96 12.96 0 1 0 16.04 3Zm0 2.18a10.77 10.77 0 0 1 9.14 16.47 10.78 10.78 0 0 1-14.64 3.98l-.46-.27-3.82 1 1.02-3.72-.3-.48a10.77 10.77 0 0 1 9.06-16.98Zm-4.7 4.78c-.24 0-.63.09-.96.45-.33.36-1.26 1.23-1.26 3s1.29 3.49 1.47 3.73c.18.24 2.54 3.88 6.15 5.44.86.37 1.53.59 2.05.76.86.27 1.64.23 2.26.14.69-.1 2.12-.87 2.42-1.71.3-.84.3-1.56.21-1.71-.09-.15-.33-.24-.69-.42-.36-.18-2.12-1.05-2.45-1.17-.33-.12-.57-.18-.81.18-.24.36-.93 1.17-1.14 1.41-.21.24-.42.27-.78.09-.36-.18-1.52-.56-2.9-1.79a10.87 10.87 0 0 1-2-2.49c-.21-.36-.02-.55.16-.73.16-.16.36-.42.54-.63.18-.21.24-.36.36-.6.12-.24.06-.45-.03-.63-.09-.18-.81-1.95-1.11-2.67-.29-.7-.59-.6-.81-.61h-.69Z"/></svg>
    <span>WhatsApp</span>
</a>
<?php endif; ?>
</body>
</html>
