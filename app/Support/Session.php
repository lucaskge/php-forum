<?php

declare(strict_types=1);

namespace App\Support;

final class Session
{
    private static bool $started = false;

    public static function start(): void
    {
        if (self::$started || PHP_SAPI === 'cli') {
            self::$started = true;

            if (PHP_SAPI === 'cli' && !isset($_SESSION)) {
                $_SESSION = [];
            }

            return;
        }

        $config = Config::get('session');

        session_name((string) $config['name']);
        session_set_cookie_params([
            'lifetime' => 0,
            'path' => (string) $config['path'],
            'domain' => '',
            'secure' => (bool) $config['secure'],
            'httponly' => (bool) $config['http_only'],
            'samesite' => (string) $config['same_site'],
        ]);

        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_only_cookies', '1');
        ini_set('session.gc_maxlifetime', (string) $config['lifetime']);

        session_start();
        self::$started = true;

        // Idle timeout: a session untouched for longer than the lifetime is
        // discarded rather than silently resurrected.
        $lifetime = (int) $config['lifetime'];
        $last = (int) ($_SESSION['__last_activity'] ?? 0);

        if ($last > 0 && (time() - $last) > $lifetime) {
            self::invalidate();
        }

        $_SESSION['__last_activity'] = time();
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        return $_SESSION[$key] ?? $default;
    }

    public static function put(string $key, mixed $value): void
    {
        $_SESSION[$key] = $value;
    }

    public static function has(string $key): bool
    {
        return isset($_SESSION[$key]);
    }

    public static function forget(string $key): void
    {
        unset($_SESSION[$key]);
    }

    public static function pull(string $key, mixed $default = null): mixed
    {
        $value = self::get($key, $default);
        self::forget($key);

        return $value;
    }

    public static function regenerate(): void
    {
        if (PHP_SAPI !== 'cli' && session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }
    }

    public static function invalidate(): void
    {
        $_SESSION = [];

        if (PHP_SAPI !== 'cli' && session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }
    }

    public static function id(): string
    {
        return PHP_SAPI === 'cli' ? 'cli' : (string) session_id();
    }
}
