<?php

declare(strict_types=1);

final class WatermarkedImageCache
{
    private const MAX_PIXELS = 50_000_000;

    public function __construct(
        private readonly string $originalRoot,
        private readonly string $cacheRoot,
        private readonly string $watermarkFile,
    ) {
    }

    public function deliver(string $requestedPath): never
    {
        try {
            [$source, $cached] = $this->resolvePaths($requestedPath);
            if (!is_file($cached)) {
                $this->generateOnce($source, $cached);
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

    private function generateOnce(string $source, string $cached): void
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
            if (is_file($cached)) {
                return;
            }
            $this->render($source, $cached);
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
        $watermarkBytes = file_get_contents($this->watermarkFile);
        $image = $sourceBytes === false ? false : imagecreatefromstring($sourceBytes);
        $watermark = $watermarkBytes === false ? false : imagecreatefromstring($watermarkBytes);
        if ($image === false || $watermark === false) {
            throw new RuntimeException('Não foi possível abrir a foto ou a marca d’água.');
        }

        try {
            $image = $this->orientJpeg($image, $source, (string) $info['mime']);
            $width = imagesx($image);
            $height = imagesy($image);
            $markWidth = max(70, (int) round($width * 0.18));
            $markWidth = min($markWidth, (int) round($width * 0.28));
            $markHeight = max(1, (int) round(imagesy($watermark) * ($markWidth / imagesx($watermark))));
            if ($markHeight > $height * 0.35) {
                $markHeight = (int) round($height * 0.35);
                $markWidth = max(1, (int) round(imagesx($watermark) * ($markHeight / imagesy($watermark))));
            }

            $scaled = imagecreatetruecolor($markWidth, $markHeight);
            imagealphablending($scaled, false);
            imagesavealpha($scaled, true);
            $transparent = imagecolorallocatealpha($scaled, 0, 0, 0, 127);
            imagefilledrectangle($scaled, 0, 0, $markWidth, $markHeight, $transparent);
            imagecopyresampled($scaled, $watermark, 0, 0, 0, 0, $markWidth, $markHeight, imagesx($watermark), imagesy($watermark));
            imagealphablending($image, true);

            $padding = max(12, (int) round(min($width, $height) * 0.025));
            imagecopy($image, $scaled, $width - $markWidth - $padding, $height - $markHeight - $padding, 0, 0, $markWidth, $markHeight);
            imagedestroy($scaled);

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
            imagedestroy($watermark);
        }
    }

    private function orientJpeg(GdImage $image, string $source, string $mime): GdImage
    {
        if ($mime !== 'image/jpeg' || !function_exists('exif_read_data')) {
            return $image;
        }
        $exif = @exif_read_data($source);
        $orientation = (int) ($exif['Orientation'] ?? 1);
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
