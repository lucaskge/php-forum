<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Minimal .env reader.
 *
 * Values from the file are kept in a static map rather than pushed into
 * $_ENV/putenv(), so they cannot leak into subprocesses. A real environment
 * variable of the same name wins over the file, which is what lets a container
 * or a systemd unit override DB_HOST and friends without editing .env.
 */
final class Env
{
    /** @var array<string,string> */
    private static array $values = [];

    private static bool $loaded = false;

    public static function load(string $file): void
    {
        self::$loaded = true;

        if (!is_readable($file)) {
            return;
        }

        $lines = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];

        foreach ($lines as $line) {
            $line = trim($line);

            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }

            $parts = explode('=', $line, 2);

            if (count($parts) !== 2) {
                continue;
            }

            $key = trim($parts[0]);
            $value = trim($parts[1]);

            // Strip an inline comment when the value is not quoted.
            if ($value !== '' && $value[0] !== '"' && $value[0] !== "'") {
                $hash = strpos($value, ' #');
                if ($hash !== false) {
                    $value = rtrim(substr($value, 0, $hash));
                }
            }

            $length = strlen($value);
            if ($length >= 2 && (($value[0] === '"' && $value[$length - 1] === '"') || ($value[0] === "'" && $value[$length - 1] === "'"))) {
                $value = substr($value, 1, -1);
            }

            self::$values[$key] = $value;
        }
    }

    public static function get(string $key, ?string $default = null): ?string
    {
        $value = self::fromEnvironment($key) ?? (self::$values[$key] ?? null);

        if ($value === null || $value === '') {
            return $default;
        }

        return $value;
    }

    /** A real environment variable takes precedence over the .env file. */
    private static function fromEnvironment(string $key): ?string
    {
        foreach ([$_ENV[$key] ?? null, $_SERVER[$key] ?? null] as $candidate) {
            if (is_string($candidate) && $candidate !== '') {
                return $candidate;
            }
        }

        $value = getenv($key);

        return is_string($value) && $value !== '' ? $value : null;
    }

    public static function bool(string $key, bool $default = false): bool
    {
        $value = self::get($key);

        if ($value === null) {
            return $default;
        }

        return in_array(strtolower($value), ['1', 'true', 'yes', 'on'], true);
    }

    public static function has(string $key): bool
    {
        return self::get($key) !== null;
    }

    /** Used by `console key:generate` to rewrite a single line in place. */
    public static function set(string $key, string $value): void
    {
        self::$values[$key] = $value;
    }
}
