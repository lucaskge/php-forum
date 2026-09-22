<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Builds every link the application emits.
 *
 * Two URL modes are supported, chosen by `APP_URL_MODE`:
 *
 *   path      /forum/general
 *             The tidiest, and what you want when you can configure the server:
 *             it needs the front controller to receive unknown paths, through
 *             mod_rewrite, nginx try_files, or the bundled development router.
 *
 *   pathinfo  /index.php/forum/general
 *             Almost as tidy and needs no rewriting: the path rides along after
 *             the script name and arrives as PATH_INFO. Works on most Apache
 *             and PHP-FPM setups out of the box.
 *
 *   query     /index.php?r=/forum/general   (the safe default)
 *             Works on anything that can run a PHP file at all. The slashes are
 *             left as slashes — they are legal in a query string, and encoding
 *             them to %2F only makes the address unreadable.
 *
 * Nothing else in the codebase knows which mode is active: routes are declared
 * with logical paths and every link is built here.
 */
final class Url
{
    public const MODE_QUERY = 'query';

    public const MODE_PATH = 'path';

    public const MODE_PATH_INFO = 'pathinfo';

    /** @var array<int,string> */
    public const MODES = [self::MODE_PATH, self::MODE_PATH_INFO, self::MODE_QUERY];

    /** The query parameter carrying the logical path in query mode. */
    public const ROUTE_PARAMETER = 'r';

    /** @var array<string,string> */
    private static array $named = [];

    private static ?string $mode = null;

    public static function register(string $name, string $uri): void
    {
        self::$named[$name] = $uri;
    }

    public static function mode(): string
    {
        if (self::$mode === null) {
            self::$mode = self::normaliseMode((string) Config::get('app.url_mode', self::MODE_QUERY));
        }

        return self::$mode;
    }

    /** Used by the tests to exercise each mode. */
    public static function useMode(string $mode): void
    {
        self::$mode = self::normaliseMode($mode);
    }

    /** Anything unrecognised falls back to the mode that always works. */
    public static function normaliseMode(string $mode): string
    {
        return in_array($mode, self::MODES, true) ? $mode : self::MODE_QUERY;
    }

    /** The script the front controller is reached through, in query mode. */
    public static function entrypoint(): string
    {
        return (string) Config::get('app.entrypoint', '/index.php');
    }

    /**
     * Turns a logical path into a URL the browser can request.
     *
     * @param array<string,string|int|null> $query
     */
    public static function to(string $path, array $query = [], string $fragment = ''): string
    {
        $path = '/' . ltrim($path, '/');
        $query = array_filter($query, static fn ($value): bool => $value !== null && $value !== '');
        $fragment = $fragment === '' ? '' : '#' . ltrim($fragment, '#');

        $mode = self::mode();
        $suffix = ($query === [] ? '' : '?' . http_build_query($query)) . $fragment;

        if ($mode === self::MODE_PATH) {
            return self::encodePath($path) . $suffix;
        }

        $entrypoint = self::entrypoint();

        if ($mode === self::MODE_PATH_INFO) {
            return $entrypoint . ($path === '/' ? '' : self::encodePath($path)) . $suffix;
        }

        // The board index needs no route parameter at all.
        if ($path === '/') {
            return $entrypoint . $suffix;
        }

        // Built by hand rather than with http_build_query: the separators of a
        // path are meaningful and legal here, and escaping them to %2F turns a
        // readable address into noise.
        $url = $entrypoint . '?' . self::ROUTE_PARAMETER . '=' . self::encodePath($path);

        if ($query !== []) {
            $url .= '&' . http_build_query($query);
        }

        return $url . $fragment;
    }

    /**
     * Percent-encodes each segment while leaving the separators alone, so a
     * slug stays readable and a stray character in it still cannot break out
     * of its segment.
     */
    private static function encodePath(string $path): string
    {
        return implode('/', array_map('rawurlencode', explode('/', $path)));
    }

    /**
     * @param array<string,string|int> $parameters Route placeholders.
     * @param array<string,string|int|null> $query Query string values.
     */
    public static function route(string $name, array $parameters = [], array $query = [], string $fragment = ''): string
    {
        $uri = self::$named[$name] ?? '/';

        foreach ($parameters as $key => $value) {
            // Raw here; the encoding happens once, in to(), per URL mode.
            $uri = preg_replace(
                '/\{' . preg_quote((string) $key, '/') . '(?::[^}]*)?\}/',
                str_replace(['/', '%'], ['', ''], (string) $value),
                $uri,
            ) ?? $uri;
        }

        // Any placeholder left unfilled would produce a broken link.
        $uri = preg_replace('/\{[^}]*\}/', '', $uri) ?? $uri;

        return self::to($uri === '' ? '/' : $uri, $query, $fragment);
    }

    /** The logical path behind a named route, without building a URL. */
    public static function pathOf(string $name): string
    {
        return self::$named[$name] ?? '/';
    }

    public static function absolute(string $path): string
    {
        $base = (string) Config::get('app.url');

        return $base . (str_starts_with($path, '/') ? $path : self::to($path));
    }

    /**
     * Adds query values to an already-built URL, preserving what is there.
     *
     * @param array<string,string|int|null> $parameters
     */
    public static function withQuery(string $url, array $parameters): string
    {
        $parameters = array_filter($parameters, static fn ($value): bool => $value !== null && $value !== '');

        if ($parameters === []) {
            return $url;
        }

        [$base, $fragment] = array_pad(explode('#', $url, 2), 2, null);
        $separator = str_contains((string) $base, '?') ? '&' : '?';

        return $base . $separator . http_build_query($parameters) . ($fragment === null ? '' : '#' . $fragment);
    }
}
