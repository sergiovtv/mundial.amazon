<?php

declare(strict_types=1);

require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/PropertyRepository.php';
require_once __DIR__ . '/LegacyCustomerCipher.php';
require_once __DIR__ . '/CustomerRepository.php';
require_once __DIR__ . '/BrevoMailer.php';
require_once __DIR__ . '/BoletoService.php';
require_once __DIR__ . '/WatermarkedImageCache.php';

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

function cachedPhotoUrl(string $relativePath): string
{
    $segments = array_values(array_filter(
        explode('/', str_replace('\\', '/', $relativePath)),
        static fn (string $segment): bool => $segment !== ''
    ));

    return '/cache/' . implode('/', array_map('rawurlencode', $segments));
}

function formatCpfCnpj(string $value): ?string
{
    $digits = preg_replace('/\D+/', '', $value) ?? '';
    if (strlen($digits) === 11) {
        return substr($digits, 0, 3) . '.' . substr($digits, 3, 3) . '.' . substr($digits, 6, 3) . '-' . substr($digits, 9, 2);
    }
    if (strlen($digits) === 14) {
        return substr($digits, 0, 2) . '.' . substr($digits, 2, 3) . '.' . substr($digits, 5, 3) . '/' . substr($digits, 8, 4) . '-' . substr($digits, 12, 2);
    }
    return null;
}

function customerSession(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) return;
    session_name('mundial_cliente');
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/cliente',
        'secure' => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

function csrfToken(): string
{
    customerSession();
    if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(24));
    return $_SESSION['csrf'];
}

function validCsrf(): bool
{
    customerSession();
    return isset($_POST['csrf'], $_SESSION['csrf']) && hash_equals((string) $_SESSION['csrf'], (string) $_POST['csrf']);
}

function requireCustomer(): array
{
    customerSession();
    if (empty($_SESSION['customer']) || !is_array($_SESSION['customer'])) {
        header('Location: /cliente/');
        exit;
    }
    return $_SESSION['customer'];
}
