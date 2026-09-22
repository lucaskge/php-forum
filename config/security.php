<?php

declare(strict_types=1);

return [
    // Throttles applied by App\Middleware\RateLimitMiddleware and the auth
    // service. `decay` is the window length in seconds.
    'throttles' => [
        'login' => ['limit' => 6, 'decay' => 900],
        'register' => ['limit' => 5, 'decay' => 3600],
        'password-reset' => ['limit' => 5, 'decay' => 3600],
        'post' => ['limit' => 25, 'decay' => 600],
        'chat' => ['limit' => 30, 'decay' => 300],
        'report' => ['limit' => 10, 'decay' => 3600],
        'message' => ['limit' => 20, 'decay' => 3600],
        'search' => ['limit' => 60, 'decay' => 300],
    ],

    'password' => [
        'min_length' => 10,
        'max_length' => 4096,
    ],

    'reset_token_lifetime' => 3600,

    // Sent on every response by App\Support\Response.
    'headers' => [
        'X-Content-Type-Options' => 'nosniff',
        'X-Frame-Options' => 'DENY',
        'Referrer-Policy' => 'strict-origin-when-cross-origin',
        'Permissions-Policy' => 'geolocation=(), microphone=(), camera=(), interest-cohort=()',
        // The application ships zero JavaScript; the CSP enforces that.
        'Content-Security-Policy' => "default-src 'none'; base-uri 'none'; form-action 'self'; frame-ancestors 'none'; img-src 'self' data:; style-src 'self'; font-src 'self'; script-src 'none'",
    ],
];
