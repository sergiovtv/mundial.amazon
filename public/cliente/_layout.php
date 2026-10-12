<?php

declare(strict_types=1);

function customerHeader(string $title, ?array $customer = null): void
{
    ?><!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
    <title><?= e($title) ?> | Mundial Imóveis</title>
    <link rel="icon" type="image/png" sizes="64x64" href="/favicon.png">
    <link rel="apple-touch-icon" sizes="180x180" href="/apple-touch-icon.png">
    <link rel="stylesheet" href="/assets/app.css">
    <script src="/assets/cpf-cnpj-mask.js" defer></script>
</head>
<body class="customer-body">
<header class="site-header customer-header">
    <a class="brand" href="/" aria-label="Mundial Imóveis">
        <img src="/img/site/logos/logo.svg" alt="Mundial Imóveis">
    </a>
    <?php if ($customer): ?>
    <nav class="customer-nav" aria-label="Área do cliente">
        <a href="/cliente/painel.php">Meus documentos</a>
        <form method="post" action="/cliente/logout.php">
            <input type="hidden" name="csrf" value="<?= e(csrfToken()) ?>">
            <button type="submit" class="link-button">Sair</button>
        </form>
    </nav>
    <?php else: ?><a class="secondary-link" href="/">Voltar ao site</a><?php endif; ?>
</header>
<?php
}

function customerFooter(): void
{
    ?></body></html><?php
}
