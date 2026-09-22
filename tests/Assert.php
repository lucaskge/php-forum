<?php

declare(strict_types=1);

namespace Tests;

use RuntimeException;

/**
 * Assertions for the built-in runner. The project has no Composer
 * dependencies, so the test harness is a few dozen lines rather than PHPUnit.
 */
final class Assert
{
    public static int $count = 0;

    public static function true(bool $condition, string $message = 'Expected true'): void
    {
        self::$count++;

        if (!$condition) {
            throw new RuntimeException($message);
        }
    }

    public static function false(bool $condition, string $message = 'Expected false'): void
    {
        self::true(!$condition, $message);
    }

    public static function same(mixed $expected, mixed $actual, string $message = ''): void
    {
        self::$count++;

        if ($expected !== $actual) {
            throw new RuntimeException(sprintf(
                "%s\n      expected: %s\n      actual:   %s",
                $message === '' ? 'Values differ' : $message,
                self::export($expected),
                self::export($actual),
            ));
        }
    }

    public static function contains(string $needle, string $haystack, string $message = ''): void
    {
        self::$count++;

        if (!str_contains($haystack, $needle)) {
            throw new RuntimeException(sprintf(
                "%s\n      looking for: %s\n      inside:      %s",
                $message === '' ? 'Substring not found' : $message,
                $needle,
                mb_substr($haystack, 0, 400),
            ));
        }
    }

    public static function notContains(string $needle, string $haystack, string $message = ''): void
    {
        self::$count++;

        if (str_contains($haystack, $needle)) {
            throw new RuntimeException(sprintf(
                "%s\n      found unexpectedly: %s\n      inside: %s",
                $message === '' ? 'Substring present' : $message,
                $needle,
                mb_substr($haystack, 0, 400),
            ));
        }
    }

    public static function null(mixed $value, string $message = 'Expected null'): void
    {
        self::true($value === null, $message);
    }

    public static function notNull(mixed $value, string $message = 'Expected a value'): void
    {
        self::true($value !== null, $message);
    }

    private static function export(mixed $value): string
    {
        return match (true) {
            is_string($value) => '"' . $value . '"',
            is_bool($value) => $value ? 'true' : 'false',
            $value === null => 'null',
            is_array($value) => json_encode($value, JSON_UNESCAPED_SLASHES) ?: 'array',
            default => (string) $value,
        };
    }
}
