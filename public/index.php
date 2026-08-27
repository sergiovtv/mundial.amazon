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
$photoVisitorHash = $hasFilters ? null : anonymousPhotoVisitorHash();

try {
    $repository = new PropertyRepository(Database::connect());
    $whatsappPhone = $repository->siteParameter('var_whatsapp');
    $options = $repository->options();
    $neighborhoods = $repository->neighborhoods(ctype_digit((string) $filters['cidade']) ? (int) $filters['cidade'] : null);
    $result = $repository->search($filters, $page);
    $visualGalleries = $hasFilters ? [] : $repository->visualGalleries(8, $photoVisitorHash);
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
    if (!empty($property['foto_imovel'])) {
        return cachedPhotoUrl(sprintf(
            '%d/%s',
            (int) $property['codimovel'],
            (string) $property['foto_imovel']
        ));
    }

    if (!empty($property['codedificio']) && !empty($property['foto_edificio'])) {
        return cachedPhotoUrl(sprintf(
            'Edificios/%d/%s',
            (int) $property['codedificio'],
            (string) $property['foto_edificio']
        ));
    }

    return null;
}

function visualPhotoUrl(array $photo): string
{
    if ($photo['photo_source'] === 'edificio') {
        return cachedPhotoUrl(sprintf('Edificios/%d/%s', (int) $photo['codedificio'], (string) $photo['nome_imagem']));
    }
    return cachedPhotoUrl(sprintf('%d/%s', (int) $photo['codimovel'], (string) $photo['nome_imagem']));
}

function visualPhotoPayload(array $photo, string $galleryTitle): array
{
    return [
        'photoId' => (int) $photo['photo_id'],
        'source' => (string) $photo['photo_source'],
        'imageUrl' => visualPhotoUrl($photo),
        'propertyUrl' => visualPropertyPhotoPath($photo),
        'type' => (string) $photo['tipo'],
        'neighborhood' => (string) $photo['bairro'],
        'ariaLabel' => 'Ver ' . $photo['tipo'] . ' em ' . $photo['bairro'],
        'alt' => $galleryTitle . ' — ' . $photo['tipo'] . ' em ' . $photo['bairro'],
    ];
}

function visualPropertyPhotoPath(array $photo): string
{
    $photoReference = (string) $photo['photo_source'] . '-' . (int) $photo['photo_id'];
    return propertyPath($photo, (string) $photo['finalidade']) . '?foto=' . rawurlencode($photoReference) . '#galeria';
}

$heroImage = null;
if ($visualGalleries !== [] && isset($visualGalleries[0]['photos'][0])) {
    $heroImage = visualPhotoUrl($visualGalleries[0]['photos'][0]);
} elseif (isset($result['items'][0])) {
    $heroImage = propertyPhotoUrl($result['items'][0]);
}
?>
<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Encontre seu imóvel | Mundial Imóveis</title>
    <link rel="icon" type="image/png" sizes="64x64" href="/favicon.png">
    <link rel="apple-touch-icon" sizes="180x180" href="/apple-touch-icon.png">
    <meta name="description" content="Imóveis à venda e para locação em Ribeirão Preto e região. Encontre casas, apartamentos e terrenos na Mundial Imóveis.">
    <link rel="canonical" href="<?= e($canonicalUrl) ?>">
    <?php if ($hasFilters): ?><meta name="robots" content="noindex,follow"><?php endif; ?>
    <link rel="stylesheet" href="/assets/app.css">
</head>
<body class="site-v2 <?= $hasFilters ? 'listing-page' : 'home-page' ?>">
<header class="site-header">
    <a class="brand" href="/" aria-label="Mundial Imóveis">
        <img src="/img/site/logos/logo.svg" alt="Mundial Imóveis">
    </a>
    <nav class="site-nav" aria-label="Navegação principal">
        <a href="/">Home</a>
        <a href="/?finalidade=venda">Comprar</a>
        <a href="/?finalidade=locacao">Alugar</a>
        <a href="#imoveis">Imóveis</a>
        <a class="nav-client" href="/cliente/">Área do cliente</a>
    </nav>
</header>

<main>
    <section class="hero"<?= $heroImage ? ' style="--hero-image:url(\'' . e($heroImage) . '\')"' : '' ?>>
        <div>
            <p class="eyebrow">Mundial Imóveis</p>
            <h1>Seu próximo endereço começa aqui.</h1>
            <p class="hero-desktop-copy">Imóveis para comprar e alugar em Ribeirão Preto e região.</p>
            <p class="hero-mobile-copy">Encontre seu imóvel em Ribeirão Preto e região.</p>
        </div>

        <?php if ($visualGalleries !== []): ?>
            <?php
            $surprisePhoto = $visualGalleries[0]['photos'][0] ?? null;
            $searchPhoto = $visualGalleries[1]['photos'][0] ?? $visualGalleries[0]['photos'][1] ?? $surprisePhoto;
            ?>
            <div class="home-choice" aria-labelledby="home-choice-title">
                <div class="home-choice-heading">
                    <span>Comece por aqui</span>
                    <strong id="home-choice-title">Como você quer encontrar seu imóvel?</strong>
                </div>
                <div class="home-choice-grid">
                    <button class="home-choice-card home-choice-surprise" type="button" data-home-mode="discovery">
                        <?php if ($surprisePhoto): ?><img src="<?= e(visualPhotoUrl($surprisePhoto)) ?>" alt="" decoding="async"><?php endif; ?>
                        <span class="home-choice-shine" aria-hidden="true"></span>
                        <span class="home-choice-content">
                            <small>Uma experiência visual</small>
                            <strong>Quero me surpreender</strong>
                            <span>Explore detalhes que despertam desejo</span>
                            <b aria-hidden="true">↗</b>
                        </span>
                    </button>
                    <div class="home-choice-card home-choice-search">
                        <?php if ($searchPhoto): ?><img src="<?= e(visualPhotoUrl($searchPhoto)) ?>" alt="" decoding="async"><?php endif; ?>
                        <span class="home-choice-search-overlay" aria-hidden="true"></span>
                        <div class="home-choice-content home-quick-search-content">
                            <small>Do seu jeito</small>
                            <strong>Buscar imóveis</strong>
                            <form class="home-quick-search" method="get">
                                <label><span>Operação</span><select name="finalidade"><option value="venda">Comprar</option><option value="locacao">Alugar</option></select></label>
                                <label><span>Tipo</span><select name="tipo"><option value="">Todos os tipos</option><?php foreach ($options['types'] as $type): ?><option value="<?= e($type['id']) ?>"><?= e($type['label']) ?></option><?php endforeach; ?></select></label>
                                <label><span>Local</span><select name="cidade"><option value="">Todas as cidades</option><?php foreach ($options['cities'] as $city): ?><option value="<?= e($city['id']) ?>"><?= e($city['label']) ?></option><?php endforeach; ?></select></label>
                                <button type="submit">Buscar agora <span aria-hidden="true">→</span></button>
                            </form>
                            <button class="home-quick-more" type="button" data-home-mode="search">Mais filtros</button>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <?php if ($visualGalleries !== []): ?>
            <button class="home-mode-back home-search-back" type="button" data-home-mode="choice">
                <span aria-hidden="true">←</span> Escolher outra forma de buscar
            </button>
        <?php endif; ?>

        <div class="purpose-tabs" role="navigation" aria-label="Finalidade do imóvel">
            <a class="<?= $filters['finalidade'] === 'locacao' ? 'is-active' : '' ?>" href="/?finalidade=locacao">Alugar</a>
            <a class="<?= $filters['finalidade'] === 'venda' ? 'is-active' : '' ?>" href="/?finalidade=venda">Comprar</a>
            <a href="#codigo-imovel">Buscar por código</a>
        </div>

        <form class="search-panel is-open" id="property-search" method="get">
            <label>Código do imóvel
                <input id="codigo-imovel" name="codigo" inputmode="numeric" pattern="[0-9]*" value="<?= e($filters['codigo']) ?>" placeholder="Ex.: 62602">
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
    <section class="visual-discovery" id="descobrir" aria-labelledby="visual-discovery-title" hidden>
        <div class="visual-discovery-intro">
            <button class="home-mode-back" type="button" data-home-mode="choice"><span aria-hidden="true">←</span> Escolher outra forma de buscar</button>
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
                <div class="visual-mosaic visual-mosaic-<?= e((string) (($galleryIndex % 3) + 1)) ?>" data-photo-gallery="<?= e($gallery['key']) ?>">
                    <?php foreach ($gallery['photos'] as $photoIndex => $photo): ?>
                        <article class="visual-tile visual-tile-<?= e((string) (($photoIndex % 8) + 1)) ?>" data-photo-id="<?= e($photo['photo_id']) ?>" data-photo-source="<?= e($photo['photo_source']) ?>">
                            <a class="visual-tile-link" href="<?= e(visualPropertyPhotoPath($photo)) ?>" aria-label="Ver <?= e($photo['tipo']) ?> em <?= e($photo['bairro']) ?> nesta foto">
                                <img src="<?= e(visualPhotoUrl($photo)) ?>" alt="<?= e($gallery['title'] . ' — ' . $photo['tipo'] . ' em ' . $photo['bairro']) ?>" loading="lazy" decoding="async">
                                <span class="visual-caption">
                                    <small><?= e($photo['tipo']) ?></small>
                                    <strong><?= e($photo['bairro']) ?></strong>
                                </span>
                            </a>
                            <div class="photo-reactions" aria-label="Avalie esta foto">
                                <button type="button" class="photo-reaction photo-like" data-reaction="1" aria-label="Gostei desta foto" title="Gostei desta foto">
                                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M7 22H3a2 2 0 0 1-2-2v-7a2 2 0 0 1 2-2h4m0 11V11l4-9a3 3 0 0 1 3 3v4h5.3a2 2 0 0 1 2 2.4l-1.5 8A2 2 0 0 1 17.8 21H7Z"/></svg>
                                </button>
                                <button type="button" class="photo-reaction photo-dislike" data-reaction="-1" aria-label="Não gostei desta foto" title="Não gostei desta foto">
                                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M7 2H3a2 2 0 0 0-2 2v7a2 2 0 0 0 2 2h4m0-11v11l4 9a3 3 0 0 0 3-3v-4h5.3a2 2 0 0 0 2-2.4l-1.5-8A2 2 0 0 0 17.8 3H7Z"/></svg>
                                </button>
                            </div>
                        </article>
                    <?php endforeach; ?>
                    <script type="application/json" class="visual-reserves"><?= json_encode(array_map(static fn (array $photo): array => visualPhotoPayload($photo, $gallery['title']), $gallery['reserves']), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?></script>
                </div>
            </section>
        <?php endforeach; ?>
    </section>
    <?php endif; ?>

    <section class="results" id="imoveis">
        <div class="results-heading">
            <div>
                <p class="eyebrow"><?= $hasFilters ? 'Resultado da busca' : 'Seleção Mundial' ?></p>
                <h2><?= $hasFilters ? number_format($result['total'], 0, ',', '.') . ' imóveis encontrados' : 'Imóveis em destaque' ?></h2>
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
<script src="/assets/home-galleries.js" defer></script>
</body>
</html>
