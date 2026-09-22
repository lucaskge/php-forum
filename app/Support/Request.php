<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Immutable view over the current HTTP request. Every accessor returns a
 * trimmed string by default; callers opt into other shapes explicitly.
 */
final class Request
{
    /** @var array<string,mixed> */
    private array $query;

    /** @var array<string,mixed> */
    private array $body;

    /** @var array<string,mixed> */
    private array $files;

    /** @var array<string,string> */
    private array $server;

    /** @var array<string,string> */
    private array $routeParameters = [];

    /**
     * @param array<string,mixed> $query
     * @param array<string,mixed> $body
     * @param array<string,mixed> $files
     * @param array<string,mixed> $server
     */
    public function __construct(array $query, array $body, array $files, array $server)
    {
        $this->query = $query;
        $this->body = $body;
        $this->files = $files;
        $this->server = array_map(static fn ($v): string => is_scalar($v) ? (string) $v : '', $server);
    }

    public static function capture(): self
    {
        return new self($_GET, $_POST, $_FILES, $_SERVER);
    }

    public function method(): string
    {
        return strtoupper($this->server['REQUEST_METHOD'] ?? 'GET');
    }

    public function isPost(): bool
    {
        return $this->method() === 'POST';
    }

    /**
     * The logical path being requested, whichever way the server delivered it:
     * the `r` parameter (no rewrite rules needed), PATH_INFO, or the request
     * URI when rewriting is in place.
     */
    public function path(): string
    {
        $routed = $this->query[Url::ROUTE_PARAMETER] ?? null;

        if (is_string($routed) && $routed !== '') {
            return $this->normalisePath($routed);
        }

        $pathInfo = $this->server['PATH_INFO'] ?? '';

        if ($pathInfo !== '') {
            return $this->normalisePath($pathInfo);
        }

        $uri = $this->server['REQUEST_URI'] ?? '/';
        $path = parse_url($uri, PHP_URL_PATH);
        $path = $this->normalisePath(is_string($path) ? $path : '/');

        $entrypoint = rtrim(Url::entrypoint(), '/');

        if ($entrypoint !== '') {
            // Reaching /index.php directly is the board index.
            if ($path === $entrypoint) {
                return '/';
            }

            // /index.php/forum/general — the path rides after the script name.
            // Some servers do not populate PATH_INFO, so it is taken from the
            // request URI as well.
            if (str_starts_with($path, $entrypoint . '/')) {
                return $this->normalisePath(substr($path, strlen($entrypoint)));
            }
        }

        return $path;
    }

    private function normalisePath(string $path): string
    {
        $path = rawurldecode($path);

        // Defensive: a traversal segment can never be part of a logical path.
        $path = str_replace(["\0", '\\'], '', $path);
        $path = '/' . trim($path, '/');

        return $path === '//' ? '/' : $path;
    }

    public function fullUrl(): string
    {
        return Config::get('app.url') . ($this->server['REQUEST_URI'] ?? '/');
    }

    public function queryString(): string
    {
        $query = $this->query;
        unset($query['page'], $query[Url::ROUTE_PARAMETER]);

        return http_build_query($query);
    }

    public function input(string $key, ?string $default = null): ?string
    {
        $value = $this->body[$key] ?? $this->query[$key] ?? null;

        if (is_array($value) || $value === null) {
            return $default;
        }

        $value = trim((string) $value);

        return $value === '' ? $default : $value;
    }

    /** Raw body value with newlines preserved (post/message content). */
    public function text(string $key, string $default = ''): string
    {
        $value = $this->body[$key] ?? $default;

        if (!is_string($value)) {
            return $default;
        }

        return Str::normaliseWhitespace($value);
    }

    public function int(string $key, int $default = 0): int
    {
        $value = $this->input($key);

        return $value === null || !is_numeric($value) ? $default : (int) $value;
    }

    public function bool(string $key): bool
    {
        $value = $this->body[$key] ?? $this->query[$key] ?? null;

        if ($value === null) {
            return false;
        }

        return in_array(strtolower((string) $value), ['1', 'true', 'on', 'yes'], true);
    }

    /** @return array<int,string> */
    public function array(string $key): array
    {
        $value = $this->body[$key] ?? $this->query[$key] ?? [];

        if (!is_array($value)) {
            return [];
        }

        return array_values(array_map(static fn ($v): string => is_scalar($v) ? trim((string) $v) : '', $value));
    }

    public function has(string $key): bool
    {
        return array_key_exists($key, $this->body) || array_key_exists($key, $this->query);
    }

    public function filled(string $key): bool
    {
        return $this->input($key) !== null;
    }

    /** @return array<string,mixed>|null */
    public function file(string $key): ?array
    {
        $file = $this->files[$key] ?? null;

        if (!is_array($file) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            return null;
        }

        return $file;
    }

    public function page(): int
    {
        return max(1, (int) ($this->routeParameters['page'] ?? $this->query['page'] ?? 1));
    }

    public function ip(): string
    {
        $ip = $this->server['REMOTE_ADDR'] ?? '0.0.0.0';

        return filter_var($ip, FILTER_VALIDATE_IP) === false ? '0.0.0.0' : $ip;
    }

    public function userAgent(): string
    {
        return mb_substr($this->server['HTTP_USER_AGENT'] ?? '', 0, 255);
    }

    public function referer(): ?string
    {
        $referer = $this->server['HTTP_REFERER'] ?? '';

        return $referer === '' ? null : $referer;
    }

    /**
     * Only same-origin referers are considered safe to bounce back to.
     */
    public function safeReferer(string $fallback = '/'): string
    {
        $referer = $this->referer();

        if ($referer === null) {
            return $fallback;
        }

        $host = parse_url($referer, PHP_URL_HOST);
        $appHost = parse_url((string) Config::get('app.url'), PHP_URL_HOST);

        if ($host !== null && $host !== $appHost) {
            return $fallback;
        }

        $path = parse_url($referer, PHP_URL_PATH) ?: '/';
        $query = parse_url($referer, PHP_URL_QUERY);

        return $path . ($query !== null ? '?' . $query : '');
    }

    /** @param array<string,string> $parameters */
    public function setRouteParameters(array $parameters): void
    {
        $this->routeParameters = $parameters;
    }

    public function route(string $key, ?string $default = null): ?string
    {
        return $this->routeParameters[$key] ?? $default;
    }

    /** @return array<string,string> */
    public function routeParameters(): array
    {
        return $this->routeParameters;
    }

    /** @return array<string,mixed> */
    public function all(): array
    {
        $query = $this->query;
        unset($query[Url::ROUTE_PARAMETER]);

        return array_merge($query, $this->body);
    }
}
