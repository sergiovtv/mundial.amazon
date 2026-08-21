<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/bootstrap.php';

header('Content-Type: application/xml; charset=UTF-8');

$properties = [];
try {
    $properties = (new PropertyRepository(Database::connect()))->sitemapProperties();
} catch (Throwable) {
    http_response_code(503);
}

echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
  <url><loc><?= e(siteUrl('/')) ?></loc><changefreq>daily</changefreq><priority>1.0</priority></url>
<?php foreach ($properties as $property): ?>
  <url>
    <loc><?= e(siteUrl(propertyPath($property, $property['finalidade']))) ?></loc>
<?php if (!empty($property['alterado_em'])): ?>    <lastmod><?= e(substr((string) $property['alterado_em'], 0, 10)) ?></lastmod>
<?php endif; ?>    <changefreq>weekly</changefreq>
    <priority>0.8</priority>
  </url>
<?php endforeach; ?>
</urlset>
