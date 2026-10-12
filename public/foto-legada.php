<?php

declare(strict_types=1);

// Endereços de foto do site antigo (/fotos/... e /fotoszap0/..., usados nos XMLs dos portais)
// redirecionados para a versão com marca d'água da aplicação atual.
require_once __DIR__ . '/../src/bootstrap.php';

$path = (string) ($_GET['path'] ?? '');
if (!preg_match('#^(?:[0-9]+|Edificios/[0-9]+|Bairros/[0-9]+)/[^/\\\\]+\.(?:jpe?g|png|gif|webp)$#i', $path)) {
    http_response_code(404);
    exit;
}

header('Cache-Control: public, max-age=86400');
header('Location: ' . cachedPhotoUrl($path), true, 301);
