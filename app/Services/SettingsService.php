<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\SettingRepository;

/**
 * Board settings live in the database so administrators can change them at
 * runtime. Values are read once per request and cached in memory.
 */
final class SettingsService
{
    private static ?SettingsService $instance = null;

    private SettingRepository $repository;

    /** @var array<string,string|null>|null */
    private ?array $cache = null;

    public function __construct(?SettingRepository $repository = null)
    {
        $this->repository = $repository ?? new SettingRepository();
    }

    public static function instance(): SettingsService
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /** @return array<string,string|null> */
    public function all(): array
    {
        if ($this->cache === null) {
            $this->cache = $this->repository->map();
        }

        return $this->cache;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $value = $this->all()[$key] ?? null;

        return $value === null ? $default : $value;
    }

    public function string(string $key, string $default = ''): string
    {
        return (string) $this->get($key, $default);
    }

    public function int(string $key, int $default = 0): int
    {
        $value = $this->get($key);

        return $value === null || !is_numeric($value) ? $default : (int) $value;
    }

    public function bool(string $key, bool $default = false): bool
    {
        $value = $this->get($key);

        if ($value === null) {
            return $default;
        }

        return in_array(strtolower((string) $value), ['1', 'true', 'yes', 'on'], true);
    }

    public function put(string $key, ?string $value): void
    {
        $this->repository->put($key, $value);
        $this->cache = null;
    }

    /** @param array<string,string|null> $values */
    public function putMany(array $values): void
    {
        foreach ($values as $key => $value) {
            $this->repository->put($key, $value);
        }

        $this->cache = null;
    }

    /** @return array<string,array<int,array<string,mixed>>> */
    public function grouped(): array
    {
        return $this->repository->grouped();
    }

    public function postsPerPage(): int
    {
        return max(5, min(100, $this->int('posts_per_page', 15)));
    }

    public function topicsPerPage(): int
    {
        return max(5, min(100, $this->int('topics_per_page', 25)));
    }

    public function itemsPerPage(): int
    {
        return max(5, min(100, $this->int('items_per_page', 20)));
    }

    public function onlineWindowSeconds(): int
    {
        return max(60, $this->int('online_window_minutes', 15) * 60);
    }
}
