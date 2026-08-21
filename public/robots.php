<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/bootstrap.php';

header('Content-Type: text/plain; charset=UTF-8');
echo "User-agent: *\n";
echo "Allow: /\n";
echo "Disallow: /*?*preco_\n";
echo "Disallow: /*?*pagina=\n";
echo 'Sitemap: ' . siteUrl('/sitemap.xml') . "\n";
