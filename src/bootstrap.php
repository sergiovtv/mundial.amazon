<?php

declare(strict_types=1);

require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/PropertyRepository.php';

function env(string $name, ?string $default = null): string
{
    $value = getenv($name);

    if ($value === false || $value === '') {
        if ($default !== null) {
            return $default;
        }

        throw new RuntimeException("Variável obrigatória não definida: {$name}");
    }

    return $value;
}

function e(string|int|float|null $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function money(float|int|string|null $value): string
{
    $number = (float) $value;
    return $number > 0 ? 'R$ ' . number_format($number, 2, ',', '.') : 'Consulte';
}

function whatsappUrl(string $phone, string $message): string
{
    $digits = preg_replace('/\D+/', '', $phone);
    return 'https://wa.me/' . $digits . '?text=' . rawurlencode($message);
}

function slug(string|int|float|null $value): string
{
    $text = trim((string) $value);
    if ($text === '') return 'imovel';

    if (function_exists('transliterator_transliterate')) {
        $text = transliterator_transliterate('Any-Latin; Latin-ASCII; Lower()', $text);
    } else {
        $converted = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text);
        $text = strtolower($converted !== false ? $converted : $text);
    }

    $text = preg_replace('/[^a-z0-9]+/', '-', $text) ?? '';
    return trim($text, '-') ?: 'imovel';
}

function propertyPath(array $property, string $purpose = 'venda'): string
{
    $purpose = $purpose === 'locacao' ? 'alugar' : ($purpose === 'venda' ? 'comprar' : 'codigo');
    return sprintf(
        '/imovel/%s/%s/%s/%s/%d',
        $purpose,
        slug($property['tipo'] ?? ''),
        slug($property['cidade'] ?? ''),
        slug($property['bairro'] ?? ''),
        (int) ($property['codimovel'] ?? 0)
    );
}

function siteUrl(string $path = '/'): string
{
    $configured = rtrim(env('APP_URL', ''), '/');
    if ($configured !== '') return $configured . '/' . ltrim($path, '/');

    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = preg_replace('/[^a-zA-Z0-9.:-]/', '', (string) ($_SERVER['HTTP_HOST'] ?? 'localhost'));
    return $scheme . '://' . ($host ?: 'localhost') . '/' . ltrim($path, '/');
}
