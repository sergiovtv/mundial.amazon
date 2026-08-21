<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/bootstrap.php';

session_start();

$propertyId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$purposeParam = (string) ($_GET['finalidade'] ?? '');
$purpose = in_array($purposeParam, ['locacao', 'alugar'], true) ? 'locacao' : (in_array($purposeParam, ['venda', 'comprar'], true) ? 'venda' : '');
$selectedPurpose = $purpose;
$property = null;
$error = null;
$whatsappPhone = '';
$formErrors = [];
$form = ['name' => '', 'phone' => '', 'email' => '', 'message' => ''];
$sent = isset($_GET['contato']) && $_GET['contato'] === 'enviado';

if (empty($_SESSION['contact_csrf'])) {
    $_SESSION['contact_csrf'] = bin2hex(random_bytes(24));
}

try {
    $repository = new PropertyRepository(Database::connect());
    $whatsappPhone = $repository->siteParameter('var_whatsapp');
    if (!$propertyId || $propertyId < 1) {
        http_response_code(404);
    } else {
        $property = $repository->find($propertyId, $purpose);
        if ($property !== null && $purpose === '' && count($property['purposes']) === 1) {
            $selectedPurpose = $property['purposes'][0];
        }
        if ($property === null) {
            http_response_code(404);
        } elseif ($_SERVER['REQUEST_METHOD'] === 'GET' && !isset($_GET['contato'])) {
            $canonicalPath = propertyPath($property, $purpose);
            $requestPath = parse_url((string) ($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH);
            if ($requestPath !== $canonicalPath) {
                header('Location: ' . $canonicalPath, true, 301);
                exit;
            }
        } elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $selectedPurpose = (string) ($_POST['purpose'] ?? $purpose);
            $form = [
                'name' => trim((string) ($_POST['name'] ?? '')),
                'phone' => trim((string) ($_POST['phone'] ?? '')),
                'email' => trim((string) ($_POST['email'] ?? '')),
                'message' => trim((string) ($_POST['message'] ?? '')),
            ];

            if (!hash_equals($_SESSION['contact_csrf'], (string) ($_POST['csrf'] ?? ''))) $formErrors[] = 'Sessão expirada. Atualize a página e tente novamente.';
            if ((string) ($_POST['website'] ?? '') !== '') $formErrors[] = 'Não foi possível enviar o formulário.';
            if (!in_array($selectedPurpose, $property['purposes'], true)) $formErrors[] = 'Escolha se deseja comprar ou alugar o imóvel.';
            if (mb_strlen($form['name']) < 2 || mb_strlen($form['name']) > 50) $formErrors[] = 'Informe seu nome.';
            if (strlen(preg_replace('/\D/', '', $form['phone'])) < 10 || mb_strlen($form['phone']) > 20) $formErrors[] = 'Informe um telefone válido com DDD.';
            if (!filter_var($form['email'], FILTER_VALIDATE_EMAIL) || mb_strlen($form['email']) > 50) $formErrors[] = 'Informe um e-mail válido.';
            if (mb_strlen($form['message']) > 1000) $formErrors[] = 'A mensagem deve ter no máximo 1.000 caracteres.';

            if ($formErrors === []) {
                if ($form['message'] === '') {
                    $form['message'] = sprintf(
                        'Olá, tenho interesse no imóvel ref. %d anunciado para %s e gostaria de receber mais informações.',
                        $propertyId,
                        $selectedPurpose === 'locacao' ? 'locação' : 'venda'
                    );
                }
                $repository->createInquiry($property, $selectedPurpose, $form);
                $_SESSION['contact_csrf'] = bin2hex(random_bytes(24));
                header('Location: ' . propertyPath($property, $selectedPurpose) . '?contato=enviado#contato', true, 303);
                exit;
            }
        } elseif ($selectedPurpose !== '') {
            $form['message'] = sprintf(
                'Olá, tenho interesse no imóvel ref. %d anunciado para %s e gostaria de receber mais informações.',
                $propertyId,
                $selectedPurpose === 'locacao' ? 'locação' : 'venda'
            );
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
$canonicalUrl = $property ? siteUrl(propertyPath($property, $purpose)) : siteUrl('/imovel');
$metaDescription = $property
    ? sprintf('%s %s em %s, %s. Referência %d.', $property['tipo'], $purpose === '' ? 'para venda ou locação' : ($purpose === 'locacao' ? 'para alugar' : 'para comprar'), $property['bairro'], $property['cidade'], $property['codimovel'])
    : 'Consulte os imóveis disponíveis na Mundial Imóveis.';
$socialImage = $property && $property['photos'] ? detailPhotoUrl($property, $property['photos'][0]) : null;
if ($socialImage !== null && !preg_match('#^https?://#i', $socialImage)) {
    $socialImage = siteUrl($socialImage);
}
?>
<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= $property ? e($property['tipo'] . ' em ' . $property['bairro']) : 'Imóvel não encontrado' ?> | Mundial Imóveis</title>
    <meta name="description" content="<?= e($metaDescription) ?>">
    <link rel="canonical" href="<?= e($canonicalUrl) ?>">
    <?php if (!$property): ?><meta name="robots" content="noindex,follow"><?php endif; ?>
    <?php if ($property): ?>
    <meta property="og:type" content="website">
    <meta property="og:title" content="<?= e($property['tipo'] . ' em ' . $property['bairro']) ?> | Mundial Imóveis">
    <meta property="og:description" content="<?= e($metaDescription) ?>">
    <meta property="og:url" content="<?= e($canonicalUrl) ?>">
    <?php if ($socialImage): ?><meta property="og:image" content="<?= e($socialImage) ?>"><?php endif; ?>
    <?php endif; ?>
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
            <p class="eyebrow"><?php if ($purpose === 'locacao'): ?>Para alugar<?php elseif ($purpose === 'venda'): ?>Para comprar<?php else: ?><?= count($property['purposes']) > 1 ? 'Venda e locação' : ($property['purposes'][0] === 'locacao' ? 'Para alugar' : 'Para comprar') ?><?php endif; ?> · Ref. <?= e($property['codimovel']) ?></p>
            <h1><?= e($property['tipo']) ?> em <?= e($property['bairro']) ?></h1>
            <p><?= e($property['cidade']) ?><?= $property['edificio'] ? ' · ' . e($property['edificio']) : '' ?></p>
        </div>
        <div class="detail-prices">
            <?php if (($purpose === '' || $purpose === 'venda') && in_array('venda', $property['purposes'], true)): ?><div><span>Venda</span><strong><?= money($property['valorvenda']) ?></strong></div><?php endif; ?>
            <?php if (($purpose === '' || $purpose === 'locacao') && in_array('locacao', $property['purposes'], true)): ?><div><span>Locação</span><strong><?= money($property['valorlocacao']) ?><small>/mês</small></strong></div><?php endif; ?>
        </div>
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

        <aside class="contact-card" id="contato">
            <p class="eyebrow">Gostou deste imóvel?</p>
            <h2>Fale com a Mundial</h2>
            <p>Envie seus dados para receber mais informações ou agendar uma visita. Ref. <strong><?= e($property['codimovel']) ?></strong>.</p>

            <?php if ($sent): ?>
                <div class="form-success" role="status"><strong>Mensagem enviada!</strong><br>Nossa equipe entrará em contato.</div>
            <?php endif; ?>
            <?php if ($formErrors): ?>
                <div class="form-errors" role="alert"><?php foreach ($formErrors as $formError): ?><span><?= e($formError) ?></span><?php endforeach; ?></div>
            <?php endif; ?>

            <form class="contact-form" method="post" action="<?= e(propertyPath($property, $purpose)) ?>#contato">
                <input type="hidden" name="csrf" value="<?= e($_SESSION['contact_csrf']) ?>">
                <label class="honey-field" aria-hidden="true">Website<input name="website" tabindex="-1" autocomplete="off"></label>
                <?php if (count($property['purposes']) > 1): ?>
                    <fieldset class="purpose-choice">
                        <legend>Tenho interesse em</legend>
                        <label><input type="radio" name="purpose" value="venda" <?= $selectedPurpose === 'venda' ? 'checked' : '' ?> required> Comprar</label>
                        <label><input type="radio" name="purpose" value="locacao" <?= $selectedPurpose === 'locacao' ? 'checked' : '' ?> required> Alugar</label>
                    </fieldset>
                <?php else: ?>
                    <input type="hidden" name="purpose" value="<?= e($property['purposes'][0]) ?>">
                <?php endif; ?>
                <label>Nome<input name="name" maxlength="50" autocomplete="name" value="<?= e($form['name']) ?>" required></label>
                <label>Telefone<input name="phone" maxlength="20" inputmode="tel" autocomplete="tel" placeholder="(16) 99999-9999" value="<?= e($form['phone']) ?>" required></label>
                <label>E-mail<input name="email" type="email" maxlength="50" autocomplete="email" value="<?= e($form['email']) ?>" required></label>
                <label>Mensagem<textarea name="message" maxlength="1000" rows="4" data-contact-message><?= e($form['message']) ?></textarea></label>
                <button type="submit">Enviar mensagem</button>
            </form>

            <a class="secondary-link" href="/">Continuar buscando</a>
        </aside>
    </div>
<?php endif; ?>
</main>
<?php $whatsappMessage = $property
    ? 'Olá! Gostaria de informações sobre o imóvel de referência ' . $property['codimovel'] . '.'
    : 'Olá! Gostaria de informações sobre os imóveis da Mundial.'; ?>
<?php if ($whatsappPhone !== ''): ?>
<a class="whatsapp-float" href="<?= e(whatsappUrl($whatsappPhone, $whatsappMessage)) ?>" target="_blank" rel="noopener noreferrer" aria-label="Falar com a Mundial pelo WhatsApp">
    <svg viewBox="0 0 32 32" aria-hidden="true"><path fill="currentColor" d="M16.04 3A12.9 12.9 0 0 0 5.1 22.73L3.3 29l6.43-1.69A12.96 12.96 0 1 0 16.04 3Zm0 2.18a10.77 10.77 0 0 1 9.14 16.47 10.78 10.78 0 0 1-14.64 3.98l-.46-.27-3.82 1 1.02-3.72-.3-.48a10.77 10.77 0 0 1 9.06-16.98Zm-4.7 4.78c-.24 0-.63.09-.96.45-.33.36-1.26 1.23-1.26 3s1.29 3.49 1.47 3.73c.18.24 2.54 3.88 6.15 5.44.86.37 1.53.59 2.05.76.86.27 1.64.23 2.26.14.69-.1 2.12-.87 2.42-1.71.3-.84.3-1.56.21-1.71-.09-.15-.33-.24-.69-.42-.36-.18-2.12-1.05-2.45-1.17-.33-.12-.57-.18-.81.18-.24.36-.93 1.17-1.14 1.41-.21.24-.42.27-.78.09-.36-.18-1.52-.56-2.9-1.79a10.87 10.87 0 0 1-2-2.49c-.21-.36-.02-.55.16-.73.16-.16.36-.42.54-.63.18-.21.24-.36.36-.6.12-.24.06-.45-.03-.63-.09-.18-.81-1.95-1.11-2.67-.29-.7-.59-.6-.81-.61h-.69Z"/></svg>
    <span>WhatsApp</span>
</a>
<?php endif; ?>
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
<?php if ($property && count($property['purposes']) > 1): ?>
<script>
(() => {
    const message = document.querySelector('[data-contact-message]');
    const choices = document.querySelectorAll('input[name="purpose"]');
    const templates = {
        venda: <?= json_encode(sprintf('Olá, tenho interesse no imóvel ref. %d anunciado para venda e gostaria de receber mais informações.', $propertyId), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>,
        locacao: <?= json_encode(sprintf('Olá, tenho interesse no imóvel ref. %d anunciado para locação e gostaria de receber mais informações.', $propertyId), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>
    };
    choices.forEach(choice => choice.addEventListener('change', () => {
        if (choice.checked) message.value = templates[choice.value];
    }));
})();
</script>
<?php endif; ?>
</body>
</html>
