<?php

declare(strict_types=1);

use App\Support\Env;

return [
    'name' => Env::get('SESSION_NAME', 'coldwire_session'),
    'lifetime' => (int) Env::get('SESSION_LIFETIME', '7200'),
    'secure' => Env::bool('SESSION_SECURE', false),
    'http_only' => true,
    'same_site' => Env::get('SESSION_SAMESITE', 'Lax'),
    'path' => '/',
];
