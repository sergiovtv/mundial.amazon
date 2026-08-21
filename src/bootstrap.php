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
