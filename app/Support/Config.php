<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Dot-notation access to the PHP files in /config.
 */
final class Config
{
    /** @var array<string,mixed> */
    private static array $items = [];

    public static function load(string $directory): void
    {
        foreach (glob($directory . '/*.php') ?: [] as $file) {
            $name = basename($file, '.php');
            self::$items[$name] = require $file;
        }
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        $segments = explode('.', $key);
        $value = self::$items;

        foreach ($segments as $segment) {
            if (!is_array($value) || !array_key_exists($segment, $value)) {
                return $default;
            }

            $value = $value[$segment];
        }

        return $value;
    }

    public static function set(string $key, mixed $value): void
    {
        $segments = explode('.', $key);
        $target = &self::$items;

        foreach ($segments as $segment) {
            if (!isset($target[$segment]) || !is_array($target[$segment])) {
                $target[$segment] = [];
            }

            $target = &$target[$segment];
        }

        $target = $value;
    }

    public static function path(string $name): string
    {
        return (string) self::get('app.paths.' . $name, BASE_PATH);
    }
}
