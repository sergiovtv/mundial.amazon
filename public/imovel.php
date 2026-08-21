<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/bootstrap.php';

$propertyId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$purpose = ($_GET['finalidade'] ?? 'venda') === 'locacao' ? 'locacao' : 'venda';
$property = null;
$error = null;

try {
    if (!$propertyId || $propertyId < 1) {
        http_response_code(404);
    } else {
        $property = (new PropertyRepository(Database::connect()))->find($propertyId, $purpose);
        if ($property === null) {
            http_response_code(404);
        }
    }
} catch (Throwable $exception) {
    http_response_code(500);
    $error = env('APP_DEBUG', 'false') === 'true' ? $exception->getMessage() : 'Não foi possível carregar este imóvel agora.';
}

function detailPhotoUrl(array $property, array $photo): string
{
    $baseUrl = rtrim(env('MEDIA_BASE_URL', '/fotos'), '/');
    $folder = $photo['source'] === 'edificio'
        ? 'Edificios/' . (int) $property['codedificio']
        : (string) (int) $property['codimovel'];
    return $baseUrl . '/' . $folder . '/' . rawurlencode((string) $photo['name']);
}

function yes(mixed $value): bool
{
    return in_array(strtoupper(trim((string) $value)), ['S', '1'], true) || (is_numeric($value) && (int) $value > 0);
}

$priceColumn = $purpose === 'locacao' ? 'valorlocacao' : 'valorvenda';
$description = $property ? trim(implode("\n\n", array_filter([$property['texto1'], $property['texto2']]))) : '';
?>
<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= $property ? e($property['tipo'] . ' em ' . $property['bairro']) : 'Imóvel não encontrado' ?> | Mundial Imóveis</title>
    <link rel="stylesheet" href="/assets/app.css">
</head>
<body>
<header class="site-header">
    <a class="brand" href="/">Mundial <span>Imóveis</span></a>
    <a class="back-link" href="/">← Voltar para a busca</a>
</header>

<main class="detail-page">
<?php if ($error): ?>
    <div class="detail-message alert"><?= e($error) ?></div>
<?php elseif ($property === null): ?>
    <section class="detail-message empty-state">
        <p class="eyebrow">Imóvel indisponível</p>
        <h1>Não encontramos essa referência.</h1>
        <p>O imóvel pode ter saído do site ou não estar disponível para essa finalidade.</p>
        <a class="button-link" href="/">Ver outros imóveis</a>
    </section>
<?php else: ?>
    <section class="detail-heading">
        <div>
            <p class="eyebrow"><?= e($purpose === 'locacao' ? 'Para alugar' : 'Para comprar') ?> · Ref. <?= e($property['codimovel']) ?></p>
            <h1><?= e($property['tipo']) ?> em <?= e($property['bairro']) ?></h1>
            <p><?= e($property['cidade']) ?><?= $property['edificio'] ? ' · ' . e($property['edificio']) : '' ?></p>
        </div>
        <strong class="detail-price"><?= money($property[$priceColumn]) ?></strong>
    </section>

    <?php if ($property['photos']): ?>
        <?php $photoCount = count($property['photos']); ?>
        <section class="property-gallery" data-gallery aria-label="Fotos do imóvel">
            <div class="gallery-stage">
                <?php foreach ($property['photos'] as $index => $photo): ?>
                    <img
                        class="gallery-slide<?= $index === 0 ? ' is-active' : '' ?>"
                        src="<?= e(detailPhotoUrl($property, $photo)) ?>"
                        alt="<?= e(($photo['descricao'] ?: $property['tipo']) . ' — foto ' . ($index + 1)) ?>"
                        data-gallery-image
                        <?= $index ? 'loading="lazy"' : '' ?>
                    >
                <?php endforeach; ?>
                <?php if ($photoCount > 1): ?>
                    <button class="gallery-arrow gallery-prev" type="button" data-gallery-prev aria-label="Foto anterior">‹</button>
                    <button class="gallery-arrow gallery-next" type="button" data-gallery-next aria-label="Próxima foto">›</button>
                <?php endif; ?>
                <span class="gallery-counter"><b data-gallery-current>1</b> / <?= $photoCount ?></span>
                <button class="gallery-expand" type="button" data-gallery-expand>Ver todas as fotos</button>
            </div>

            <?php if ($photoCount > 1): ?>
                <div class="gallery-thumbs" aria-label="Escolher foto">
                    <?php foreach ($property['photos'] as $index => $photo): ?>
                        <button class="gallery-thumb<?= $index === 0 ? ' is-active' : '' ?>" type="button" data-gallery-thumb="<?= $index ?>" aria-label="Mostrar foto <?= $index + 1 ?>">
                            <img src="<?= e(detailPhotoUrl($property, $photo)) ?>" alt="" loading="lazy">
                        </button>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>

        <dialog class="gallery-lightbox" data-gallery-lightbox aria-label="Visualizador de fotos">
            <button class="lightbox-close" type="button" data-gallery-close aria-label="Fechar galeria">×</button>
            <button class="lightbox-arrow lightbox-prev" type="button" data-gallery-prev aria-label="Foto anterior">‹</button>
            <img data-gallery-lightbox-image src="<?= e(detailPhotoUrl($property, $property['photos'][0])) ?>" alt="<?= e($property['tipo']) ?>">
            <button class="lightbox-arrow lightbox-next" type="button" data-gallery-next aria-label="Próxima foto">›</button>
            <span class="lightbox-counter"><b data-gallery-lightbox-current>1</b> / <?= $photoCount ?></span>
        </dialog>
    <?php else: ?>
        <div class="gallery-placeholder">Imóvel sem fotos disponíveis</div>
    <?php endif; ?>

    <div class="detail-layout">
        <div>
            <section class="fact-grid" aria-label="Características principais">
                <?php foreach ([
                    'dormitorios' => 'Dormitórios', 'suite' => 'Suítes', 'banheiros' => 'Banheiros',
                    'garagem' => 'Vagas', 'areaconstruida' => 'Área construída', 'terrenototal' => 'Área do terreno'
                ] as $field => $label): ?>
                    <?php if ((float) $property[$field] > 0): ?>
                        <div><strong><?= e(number_format((float) $property[$field], $field === 'areaconstruida' || $field === 'terrenototal' ? 0 : 0, ',', '.')) ?><?= in_array($field, ['areaconstruida', 'terrenototal'], true) ? ' m²' : '' ?></strong><span><?= e($label) ?></span></div>
                    <?php endif; ?>
                <?php endforeach; ?>
            </section>

            <?php if ($description !== ''): ?>
                <section class="detail-section"><h2>Sobre o imóvel</h2><div class="property-description"><?= nl2br(e($description)) ?></div></section>
            <?php endif; ?>

            <?php
            $extras = [];
            foreach (['mobiliado' => 'Mobiliado', 'elevador' => 'Elevador', 'portaria24horas' => 'Portaria 24 horas', 'piscina' => 'Piscina', 'churrasqueira' => 'Churrasqueira', 'quintal' => 'Quintal', 'escritorio' => 'Escritório', 'lavabo' => 'Lavabo', 'areaservico' => 'Área de serviço'] as $field => $label) {
                if (yes($property[$field])) $extras[] = $label;
            }
            $extras = array_values(array_unique(array_merge($extras, $property['diferenciais'])));
            ?>
            <?php if ($extras): ?>
                <section class="detail-section"><h2>Comodidades</h2><ul class="amenities"><?php foreach ($extras as $extra): ?><li><?= e($extra) ?></li><?php endforeach; ?></ul></section>
            <?php endif; ?>

            <section class="detail-section"><h2>Localização</h2><p><?= e($property['bairro']) ?>, <?= e($property['cidade']) ?>. O endereço completo é informado durante o atendimento.</p></section>
        </div>

        <aside class="contact-card">
            <p class="eyebrow">Gostou deste imóvel?</p>
            <h2>Fale com a Mundial</h2>
            <p>Informe a referência <strong><?= e($property['codimovel']) ?></strong> e receba mais detalhes ou agende uma visita.</p>
            <?php $phone = env('CONTACT_PHONE', ''); ?>
            <?php if ($phone !== ''): ?><a class="button-link" href="tel:<?= e(preg_replace('/[^0-9+]/', '', $phone)) ?>">Ligar para <?= e($phone) ?></a><?php endif; ?>
            <a class="secondary-link" href="/">Continuar buscando</a>
        </aside>
    </div>
<?php endif; ?>
</main>
<?php if ($property && $property['photos']): ?>
<script>
(() => {
    const gallery = document.querySelector('[data-gallery]');
    const slides = [...gallery.querySelectorAll('[data-gallery-image]')];
    const thumbs = [...gallery.querySelectorAll('[data-gallery-thumb]')];
    const current = gallery.querySelector('[data-gallery-current]');
    const lightbox = document.querySelector('[data-gallery-lightbox]');
    const lightboxImage = lightbox.querySelector('[data-gallery-lightbox-image]');
    const lightboxCurrent = lightbox.querySelector('[data-gallery-lightbox-current]');
    let active = 0;
    let touchStart = 0;

    const show = (next) => {
        active = (next + slides.length) % slides.length;
        slides.forEach((slide, index) => slide.classList.toggle('is-active', index === active));
        thumbs.forEach((thumb, index) => thumb.classList.toggle('is-active', index === active));
        current.textContent = String(active + 1);
        lightboxCurrent.textContent = String(active + 1);
        lightboxImage.src = slides[active].src;
        lightboxImage.alt = slides[active].alt;
        thumbs[active]?.scrollIntoView({behavior: 'smooth', block: 'nearest', inline: 'center'});
    };

    document.querySelectorAll('[data-gallery-prev]').forEach(button => button.addEventListener('click', () => show(active - 1)));
    document.querySelectorAll('[data-gallery-next]').forEach(button => button.addEventListener('click', () => show(active + 1)));
    thumbs.forEach(button => button.addEventListener('click', () => show(Number(button.dataset.galleryThumb))));
    gallery.querySelector('[data-gallery-expand]').addEventListener('click', () => lightbox.showModal());
    lightbox.querySelector('[data-gallery-close]').addEventListener('click', () => lightbox.close());
    lightbox.addEventListener('click', event => { if (event.target === lightbox) lightbox.close(); });
    lightbox.addEventListener('keydown', event => {
        if (event.key === 'ArrowLeft') show(active - 1);
        if (event.key === 'ArrowRight') show(active + 1);
    });
    gallery.addEventListener('touchstart', event => touchStart = event.changedTouches[0].clientX, {passive: true});
    gallery.addEventListener('touchend', event => {
        const distance = event.changedTouches[0].clientX - touchStart;
        if (Math.abs(distance) > 45) show(active + (distance < 0 ? 1 : -1));
    }, {passive: true});
})();
</script>
<?php endif; ?>
</body>
</html>
