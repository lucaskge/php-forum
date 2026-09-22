<?php

declare(strict_types=1);

namespace Tests;

use App\Services\AccessControl;
use App\Services\AuthService;
use App\Support\Csrf;
use App\Support\Kernel;
use App\Support\Request;
use App\Support\Response;
use App\Support\Session;
use RuntimeException;

/**
 * Boots the application once and dispatches requests against it, so feature
 * tests exercise the real router, middleware, policies and templates.
 */
final class Harness
{
    private static ?Kernel $kernel = null;

    private static ?string $urlMode = null;

    public static function boot(): Kernel
    {
        if (self::$kernel === null) {
            self::$kernel = new Kernel();
            self::$kernel->boot();
        }

        // Unit tests flip the URL mode; feature tests use whichever mode the
        // case under test pinned, and the configured one otherwise.
        \App\Support\Url::useMode(self::$urlMode ?? (string) \App\Support\Config::get('app.url_mode', 'query'));

        return self::$kernel;
    }

    /** Pins the URL mode for a test; null restores the configured one. */
    public static function urlMode(?string $mode): void
    {
        self::$urlMode = $mode;
        \App\Support\Url::useMode($mode ?? (string) \App\Support\Config::get('app.url_mode', 'query'));
    }

    /** @param array<string,string> $query */
    public static function get(string $path, array $query = []): Response
    {
        return self::dispatch('GET', $path, $query, []);
    }

    /** @param array<string,string> $body */
    public static function post(string $path, array $body = []): Response
    {
        return self::dispatch('POST', $path, [], $body);
    }

    /**
     * @param array<string,string> $query
     * @param array<string,string> $body
     */
    private static function dispatch(string $method, string $path, array $query, array $body): Response
    {
        $kernel = self::boot();

        $uri = $path . ($query === [] ? '' : '?' . http_build_query($query));

        $request = new Request($query, $body, [], [
            'REQUEST_METHOD' => $method,
            'REQUEST_URI' => $uri,
            'REMOTE_ADDR' => '127.0.0.1',
            'HTTP_USER_AGENT' => 'coldwire-tests',
        ]);

        return $kernel->handle($request);
    }

    /**
     * Dispatches with full control over how the server delivered the request,
     * so the rewrite-free entry path can be exercised as it really arrives.
     *
     * @param array<string,string> $query
     */
    public static function raw(string $method, string $uri, array $query = [], string $pathInfo = ''): Response
    {
        $kernel = self::boot();

        $full = $uri . ($query === [] ? '' : '?' . http_build_query($query));

        $server = [
            'REQUEST_METHOD' => $method,
            'REQUEST_URI' => $full,
            'REMOTE_ADDR' => '127.0.0.1',
            'HTTP_USER_AGENT' => 'coldwire-tests',
        ];

        if ($pathInfo !== '') {
            $server['PATH_INFO'] = $pathInfo;
        }

        return $kernel->handle(new Request($query, [], [], $server));
    }

    /**
     * Drops every rate-limit bucket. The limiter is shared state keyed by
     * address, so without this a repeated test run trips its own throttles and
     * a test asserting 403 gets a perfectly correct 429 instead.
     */
    public static function clearThrottles(): void
    {
        try {
            \App\Support\Database::instance()->execute('DELETE FROM rate_limits');
        } catch (\Throwable) {
            // Nothing to clear when the table is not there yet.
        }
    }

    /** Changes a board setting for the duration of a test. */
    public static function setting(string $key, ?string $value): void
    {
        \App\Services\SettingsService::instance()->put($key, $value);
    }

    /** The flash messages queued by the last request, as one string. */
    public static function flashText(): string
    {
        $messages = \App\Support\Flash::drain();

        return implode(' ', array_map(static fn (array $m): string => (string) $m['message'], $messages));
    }

    public static function loginAs(string $username): void
    {
        $user = (new \App\Repositories\UserRepository())->findByUsername($username);

        if ($user === null) {
            throw new RuntimeException('Test fixture missing: no account named ' . $username . '. Run `php bin/console install`.');
        }

        // A fresh session per identity, so state never leaks between tests.
        $_SESSION = [];
        AuthService::instance()->forget();
        AuthService::instance()->login($user, '127.0.0.1');
        AccessControl::instance()->flush();
        Csrf::token();
    }

    public static function logout(): void
    {
        $_SESSION = [];
        AuthService::instance()->forget();
        AuthService::instance()->logout();
        AccessControl::instance()->flush();
    }

    public static function databaseReachable(): bool
    {
        try {
            \App\Support\Database::instance()->scalar('SELECT 1');

            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    public static function seeded(): bool
    {
        try {
            return (int) \App\Support\Database::instance()->scalar('SELECT COUNT(*) FROM users') > 0;
        } catch (\Throwable) {
            return false;
        }
    }
}
