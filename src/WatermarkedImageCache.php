<?php

declare(strict_types=1);

final class WatermarkedImageCache
{
    private const MAX_PIXELS = 50_000_000;

    public function __construct(
        private readonly string $originalRoot,
        private readonly string $cacheRoot,
        private readonly string $cornerWatermarkFile,
        private readonly string $centerWatermarkFile,
    ) {
    }

    public function deliver(string $requestedPath): never
    {
        try {
            [$source, $cached] = $this->resolvePaths($requestedPath);
            $orientationMarker = $cached . '.orientation-v2';
            $checkedMarker = $cached . '.orientation-checked-v2';
            $cachedExists = is_file($cached);
            $staleOrientation = $cachedExists
                && !is_file($orientationMarker)
                && !is_file($checkedMarker)
                && $this->hasNormalizedPixelsWithStaleOrientation($source);

            if (!$cachedExists || $staleOrientation) {
                $this->generateOnce($source, $cached, $staleOrientation, $orientationMarker, $checkedMarker);
            } elseif (!is_file($checkedMarker)) {
                $this->writeMarker($checkedMarker, 'checked-v2');
            }
            $this->sendFile($cached);
        } catch (InvalidArgumentException) {
            $this->fail(400, 'Caminho de imagem inválido.');
        } catch (OutOfBoundsException) {
            $this->fail(404, 'Foto não encontrada.');
        } catch (Throwable $exception) {
            error_log('Falha ao gerar foto com marca d’água: ' . $exception->getMessage());
            $this->fail(500, 'Não foi possível preparar a foto.');
        }
    }

    private function resolvePaths(string $requestedPath): array
    {
        $relative = rawurldecode($requestedPath);
        $relative = str_replace('\\', '/', $relative);
        $parts = explode('/', trim($relative, '/'));

        if ($parts === [] || in_array('', $parts, true)) {
            throw new InvalidArgumentException();
        }
        foreach ($parts as $part) {
            if ($part === '.' || $part === '..' || str_contains($part, "\0")) {
                throw new InvalidArgumentException();
            }
        }

        $originalRoot = realpath($this->originalRoot);
        if ($originalRoot === false) {
            throw new RuntimeException('Diretório de fotos originais indisponível.');
        }

        $source = realpath($originalRoot . '/' . implode('/', $parts));
        if ($source === false || !is_file($source)) {
            throw new OutOfBoundsException();
        }
        if (!str_starts_with($source, $originalRoot . DIRECTORY_SEPARATOR)) {
            throw new InvalidArgumentException();
        }

        $cached = rtrim($this->cacheRoot, '/') . '/' . implode('/', $parts);
        return [$source, $cached];
    }

    private function generateOnce(
        string $source,
        string $cached,
        bool $forceRefresh = false,
        string $orientationMarker = '',
        string $checkedMarker = '',
    ): void
    {
        $directory = dirname($cached);
        if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
            throw new RuntimeException('Não foi possível criar o diretório de cache.');
        }

        $lockPath = sys_get_temp_dir() . '/mundial-photo-' . hash('sha256', $cached) . '.lock';
        $lock = fopen($lockPath, 'c');
        if ($lock === false || !flock($lock, LOCK_EX)) {
            throw new RuntimeException('Não foi possível bloquear a geração da foto.');
        }

        try {
            clearstatcache(true, $cached);
            if (is_file($cached) && !$forceRefresh) {
                return;
            }
            $this->render($source, $cached);
            if ($forceRefresh && $orientationMarker !== '') {
                $this->writeMarker($orientationMarker, 'normalized-v2');
            }
            if ($checkedMarker !== '') $this->writeMarker($checkedMarker, 'checked-v2');
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    private function render(string $source, string $cached): void
    {
        $info = getimagesize($source);
        if ($info === false || empty($info['mime'])) {
            throw new RuntimeException('Formato da foto original não reconhecido.');
        }
        [$width, $height] = $info;
        if ($width < 1 || $height < 1 || $width * $height > self::MAX_PIXELS) {
            throw new RuntimeException('Dimensões da foto original não suportadas.');
        }

        $sourceBytes = file_get_contents($source);
        $cornerWatermarkBytes = file_get_contents($this->cornerWatermarkFile);
        $centerWatermarkBytes = file_get_contents($this->centerWatermarkFile);
        $image = $sourceBytes === false ? false : imagecreatefromstring($sourceBytes);
        $cornerWatermark = $cornerWatermarkBytes === false ? false : imagecreatefromstring($cornerWatermarkBytes);
        $centerWatermark = $centerWatermarkBytes === false ? false : imagecreatefromstring($centerWatermarkBytes);
        if ($image === false || $cornerWatermark === false || $centerWatermark === false) {
            throw new RuntimeException('Não foi possível abrir a foto ou as marcas d’água.');
        }

        try {
            $image = $this->orientJpeg($image, $source, (string) $info['mime']);
            $width = imagesx($image);
            $height = imagesy($image);
            $this->overlayWatermark($image, $centerWatermark, 0.30, 0.40, 'center');
            $this->overlayWatermark($image, $cornerWatermark, 0.10, 0.90, 'bottom-right');

            $temporary = $cached . '.tmp-' . bin2hex(random_bytes(6));
            $saved = match ((string) $info['mime']) {
                'image/jpeg' => imagejpeg($image, $temporary, 88),
                'image/png' => imagepng($image, $temporary, 6),
                'image/webp' => imagewebp($image, $temporary, 86),
                'image/gif' => imagegif($image, $temporary),
                default => throw new RuntimeException('Formato de foto não suportado: ' . $info['mime']),
            };
            if (!$saved || !rename($temporary, $cached)) {
                @unlink($temporary);
                throw new RuntimeException('Não foi possível gravar a foto no cache.');
            }
            chmod($cached, 0664);
        } finally {
            imagedestroy($image);
            imagedestroy($cornerWatermark);
            imagedestroy($centerWatermark);
        }
    }

    private function overlayWatermark(
        GdImage $image,
        GdImage $watermark,
        float $widthRatio,
        float $opacity,
        string $position,
    ): void {
        $imageWidth = imagesx($image);
        $imageHeight = imagesy($image);
        $markWidth = max(48, (int) round($imageWidth * $widthRatio));
        $markHeight = max(1, (int) round(imagesy($watermark) * ($markWidth / imagesx($watermark))));

        $maximumHeightRatio = $position === 'center' ? 0.38 : 0.24;
        if ($markHeight > $imageHeight * $maximumHeightRatio) {
            $markHeight = max(1, (int) round($imageHeight * $maximumHeightRatio));
            $markWidth = max(1, (int) round(imagesx($watermark) * ($markHeight / imagesy($watermark))));
        }

        $scaled = imagecreatetruecolor($markWidth, $markHeight);
        imagealphablending($scaled, false);
        imagesavealpha($scaled, true);
        $transparent = imagecolorallocatealpha($scaled, 0, 0, 0, 127);
        imagefilledrectangle($scaled, 0, 0, $markWidth, $markHeight, $transparent);
        imagecopyresampled(
            $scaled,
            $watermark,
            0,
            0,
            0,
            0,
            $markWidth,
            $markHeight,
            imagesx($watermark),
            imagesy($watermark),
        );

        if ($opacity < 1) {
            imagefilter($scaled, IMG_FILTER_COLORIZE, 0, 0, 0, (int) round(127 * (1 - $opacity)));
        }

        if ($position === 'center') {
            $destinationX = (int) round(($imageWidth - $markWidth) / 2);
            $destinationY = (int) round(($imageHeight - $markHeight) / 2);
        } else {
            $padding = max(12, (int) round(min($imageWidth, $imageHeight) * 0.025));
            $destinationX = $imageWidth - $markWidth - $padding;
            $destinationY = $imageHeight - $markHeight - $padding;
        }

        imagealphablending($image, true);
        imagecopy($image, $scaled, $destinationX, $destinationY, 0, 0, $markWidth, $markHeight);
        imagedestroy($scaled);
    }

    private function orientJpeg(GdImage $image, string $source, string $mime): GdImage
    {
        if ($mime !== 'image/jpeg' || !function_exists('exif_read_data')) {
            return $image;
        }
        $exif = @exif_read_data($source);
        $orientation = (int) ($exif['Orientation'] ?? 1);
        if (in_array($orientation, [6, 8], true) && $this->hasNormalizedPixelsWithStaleOrientation($source, $exif)) {
            return $image;
        }
        $angle = match ($orientation) { 3 => 180, 6 => -90, 8 => 90, default => 0 };
        if ($angle === 0) {
            return $image;
        }
        $rotated = imagerotate($image, $angle, 0);
        if ($rotated === false) {
            return $image;
        }
        imagedestroy($image);
        return $rotated;
    }

    private function hasNormalizedPixelsWithStaleOrientation(string $source, ?array $exif = null): bool
    {
        if (!function_exists('exif_read_data')) return false;
        $exif ??= @exif_read_data($source) ?: [];
        $orientation = (int) ($exif['Orientation'] ?? 1);
        if (!in_array($orientation, [6, 8], true)) return false;

        $embeddedWidth = (int) ($exif['ExifImageWidth'] ?? 0);
        $embeddedHeight = (int) ($exif['ExifImageLength'] ?? 0);
        $size = @getimagesize($source);
        if ($embeddedWidth < 1 || $embeddedHeight < 1 || $size === false) return false;

        $embeddedLandscape = $embeddedWidth > $embeddedHeight;
        $currentLandscape = (int) $size[0] > (int) $size[1];
        return $embeddedLandscape !== $currentLandscape;
    }

    private function writeMarker(string $path, string $value): void
    {
        if (file_put_contents($path, $value, LOCK_EX) === false) {
            throw new RuntimeException('Não foi possível registrar a versão do cache.');
        }
        chmod($path, 0664);
    }

    private function sendFile(string $file): never
    {
        $mime = mime_content_type($file) ?: 'application/octet-stream';
        header('Content-Type: ' . $mime);
        header('Content-Length: ' . (string) filesize($file));
        header('Cache-Control: public, max-age=31536000, immutable');
        header('X-Content-Type-Options: nosniff');
        readfile($file);
        exit;
    }

    private function fail(int $status, string $message): never
    {
        http_response_code($status);
        header('Content-Type: text/plain; charset=UTF-8');
        echo $message;
        exit;
    }
}
