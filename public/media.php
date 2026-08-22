<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/bootstrap.php';

$cache = new WatermarkedImageCache(
    env('PHOTO_ORIGINAL_ROOT', __DIR__ . '/fotos'),
    env('PHOTO_CACHE_ROOT', __DIR__ . '/cache'),
    env('PHOTO_WATERMARK_FILE', __DIR__ . '/img/watermark-mundial.png'),
);

$cache->deliver((string) ($_GET['path'] ?? ''));
