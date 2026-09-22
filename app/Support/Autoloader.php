<?php

declare(strict_types=1);

namespace App\Support;

/**
 * PSR-4 autoloader for the `App\` namespace. The project deliberately ships
 * without a Composer dependency so it can be deployed by copying files.
 */
final class Autoloader
{
    public static function register(string $baseDirectory, string $prefix = 'App\\'): void
    {
        spl_autoload_register(static function (string $class) use ($baseDirectory, $prefix): void {
            if (!str_starts_with($class, $prefix)) {
                return;
            }

            $relative = substr($class, strlen($prefix));
            $file = $baseDirectory . '/' . str_replace('\\', '/', $relative) . '.php';

            if (is_file($file)) {
                require $file;
            }
        });
    }
}
