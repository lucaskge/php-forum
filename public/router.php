<?php

/**
 * Router script for PHP's built-in development server.
 *
 *   php -S 127.0.0.1:8080 -t public public/router.php
 *
 * Existing files under /public (uploads, robots.txt) are served directly;
 * everything else is handed to the front controller.
 */

declare(strict_types=1);

$path = parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH);
$path = is_string($path) ? $path : '/';
$file = __DIR__ . $path;

if ($path !== '/' && !str_contains($path, '..') && is_file($file)) {
    return false;
}

require __DIR__ . '/index.php';
