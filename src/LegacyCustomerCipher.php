<?php

declare(strict_types=1);

/** Compatibility with customer data written by the former portal. */
final class LegacyCustomerCipher
{
    private const OFFSET = 126;

    public static function encode(string $value): string
    {
        $encoded = '';
        foreach (str_split($value) as $character) {
            $encoded .= mb_convert_encoding(chr(ord($character) + self::OFFSET), 'UTF-8', 'ISO-8859-1');
        }
        return $encoded;
    }

    public static function decode(string $value): string
    {
        $bytes = mb_convert_encoding($value, 'ISO-8859-1', 'UTF-8');
        $decoded = '';
        foreach (str_split($bytes) as $character) {
            $decoded .= chr((ord($character) - self::OFFSET + 256) % 256);
        }
        return trim(str_replace(["?", "\r", "\n"], '', html_entity_decode($decoded, ENT_QUOTES, 'ISO-8859-1')));
    }
}
