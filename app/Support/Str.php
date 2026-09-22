<?php

declare(strict_types=1);

namespace App\Support;

final class Str
{
    public static function slug(string $value, string $separator = '-'): string
    {
        $value = self::transliterate($value);
        $value = strtolower($value);
        $value = preg_replace('/[^a-z0-9]+/', $separator, $value) ?? '';
        $value = trim($value, $separator);

        return $value === '' ? 'item' : $value;
    }

    public static function transliterate(string $value): string
    {
        $map = [
            'á' => 'a', 'à' => 'a', 'ã' => 'a', 'â' => 'a', 'ä' => 'a', 'å' => 'a',
            'é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e',
            'í' => 'i', 'ì' => 'i', 'î' => 'i', 'ï' => 'i',
            'ó' => 'o', 'ò' => 'o', 'õ' => 'o', 'ô' => 'o', 'ö' => 'o',
            'ú' => 'u', 'ù' => 'u', 'û' => 'u', 'ü' => 'u',
            'ç' => 'c', 'ñ' => 'n', 'ß' => 'ss',
        ];

        return strtr(mb_strtolower($value, 'UTF-8'), $map);
    }

    public static function limit(string $value, int $limit = 160, string $end = '…'): string
    {
        $value = trim(preg_replace('/\s+/u', ' ', $value) ?? '');

        if (mb_strlen($value, 'UTF-8') <= $limit) {
            return $value;
        }

        return rtrim(mb_substr($value, 0, $limit, 'UTF-8')) . $end;
    }

    public static function random(int $bytes = 32): string
    {
        return bin2hex(random_bytes($bytes));
    }

    public static function initials(string $value): string
    {
        $value = trim($value);

        return $value === '' ? '?' : mb_strtoupper(mb_substr($value, 0, 2, 'UTF-8'), 'UTF-8');
    }

    /**
     * Deterministic hue for a username, used by the generated default avatars.
     */
    public static function hue(string $value): int
    {
        return (int) (hexdec(substr(md5($value), 0, 6)) % 360);
    }

    public static function normaliseWhitespace(string $value): string
    {
        $value = str_replace(["\r\n", "\r"], "\n", $value);
        $value = preg_replace('/\n{4,}/', "\n\n\n", $value) ?? $value;

        return trim($value);
    }

    public static function maskEmail(string $email): string
    {
        $parts = explode('@', $email, 2);

        if (count($parts) !== 2) {
            return '***';
        }

        $name = $parts[0];
        $visible = mb_substr($name, 0, 2, 'UTF-8');

        return $visible . str_repeat('*', max(1, mb_strlen($name, 'UTF-8') - 2)) . '@' . $parts[1];
    }
}
