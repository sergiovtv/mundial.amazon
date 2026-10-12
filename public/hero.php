<?php

declare(strict_types=1);

// Foto de fundo da home sem marca d'água. Entrega apenas a foto escolhida por
// PropertyRepository::heroPhoto(); os demais originais continuam protegidos.
require_once __DIR__ . '/../src/bootstrap.php';

const HERO_MAX_WIDTH = 1920;

try {
    $photo = (new PropertyRepository(Database::connect()))->heroPhoto();
    $name = basename((string) ($photo['nome_imagem'] ?? ''));
    if ($photo === null || $name === '' || $name === '.' || $name === '..') throw new OutOfBoundsException();

    $originalRoot = realpath(env('PHOTO_ORIGINAL_ROOT', __DIR__ . '/fotos'));
    $source = $originalRoot ? realpath($originalRoot . '/' . (int) $photo['codimovel'] . '/' . $name) : false;
    if ($source === false || !str_starts_with($source, $originalRoot . DIRECTORY_SEPARATOR) || !is_file($source)) {
        throw new OutOfBoundsException();
    }

    $cacheDir = sys_get_temp_dir() . '/mundial-hero';
    $cached = $cacheDir . '/' . (int) $photo['codimovel'] . '-' . preg_replace('/[^A-Za-z0-9._-]/', '_', $name)
        . '-' . filemtime($source) . '.jpg';

    if (!is_file($cached)) {
        $image = imagecreatefromstring((string) file_get_contents($source));
        if ($image === false) throw new RuntimeException('Foto inválida.');

        $orientation = (int) (@exif_read_data($source)['Orientation'] ?? 1);
        $rotation = [3 => 180, 6 => -90, 8 => 90][$orientation] ?? 0;
        if ($rotation !== 0) $image = imagerotate($image, $rotation, 0);

        if (imagesx($image) > HERO_MAX_WIDTH) {
            $image = imagescale($image, HERO_MAX_WIDTH, -1, IMG_BICUBIC);
        }

        if (!is_dir($cacheDir)) mkdir($cacheDir, 0775, true);
        $temporary = $cached . '.' . bin2hex(random_bytes(4));
        imageinterlace($image, true);
        imagejpeg($image, $temporary, 82);
        rename($temporary, $cached);
    }

    header('Content-Type: image/jpeg');
    header('Content-Length: ' . (int) filesize($cached));
    header('Cache-Control: public, max-age=86400');
    readfile($cached);
} catch (OutOfBoundsException) {
    http_response_code(404);
} catch (Throwable $exception) {
    error_log('Foto de fundo da home: ' . $exception->getMessage());
    http_response_code(500);
}
