<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/bootstrap.php';

$cacheVersion = trim(env('PHOTO_CACHE_VERSION', 'wm3'), '/');
$requestedPath = (string) ($_GET['path'] ?? '');
$versionPrefix = $cacheVersion . '/';
if (!str_starts_with($requestedPath, $versionPrefix)) {
    http_response_code(404);
    exit;
}

$cache = new WatermarkedImageCache(
    env('PHOTO_ORIGINAL_ROOT', __DIR__ . '/fotos'),
    rtrim(env('PHOTO_CACHE_ROOT', __DIR__ . '/cache'), '/') . '/' . $cacheVersion,
    env('PHOTO_WATERMARK_FILE', __DIR__ . '/img/watermark-mundial.png'),
    env('PHOTO_CENTER_WATERMARK_FILE', __DIR__ . '/img/marca_dagua_150_borda.gif'),
);

$cache->deliver(substr($requestedPath, strlen($versionPrefix)));
