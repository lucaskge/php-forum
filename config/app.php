<?php

declare(strict_types=1);

use App\Support\Env;

return [
    'name' => Env::get('APP_NAME', 'Coldwire'),
    'env' => Env::get('APP_ENV', 'production'),
    'debug' => Env::bool('APP_DEBUG', false),
    'url' => rtrim((string) Env::get('APP_URL', 'http://localhost:8080'), '/'),
    'key' => Env::get('APP_KEY', ''),
    'timezone' => Env::get('APP_TIMEZONE', 'UTC'),

    // How links are built:
    //   path      /forum/general             — needs mod_rewrite or try_files
    //   pathinfo  /index.php/forum/general   — no rewriting, still readable
    //   query     /index.php?r=/forum/general — works on anything (default)
    'url_mode' => Env::get('APP_URL_MODE', 'query'),

    // The script links point at in query mode, relative to the document root.
    'entrypoint' => Env::get('APP_ENTRYPOINT', '/index.php'),

    'paths' => [
        'root' => BASE_PATH,
        'app' => BASE_PATH . '/app',
        'config' => BASE_PATH . '/config',
        'database' => BASE_PATH . '/database',
        'public' => BASE_PATH . '/public',
        'routes' => BASE_PATH . '/routes',
        'storage' => BASE_PATH . '/storage',
        'templates' => BASE_PATH . '/templates',
        'themes' => BASE_PATH . '/templates/themes',
        'uploads' => BASE_PATH . '/public/uploads',
        'logs' => BASE_PATH . '/storage/logs',
        'cache' => BASE_PATH . '/storage/cache',
    ],
];
