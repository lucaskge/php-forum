<?php

declare(strict_types=1);

namespace App\Support;

/**
 * @template T
 */
final class Paginator
{
    /** @var array<int,mixed> */
    private array $items;

    private int $total;

    private int $perPage;

    private int $currentPage;

    private string $baseUrl;

    /** @var array<string,string|int> */
    private array $query;

    /** @param array<int,mixed> $items */
    public function __construct(array $items, int $total, int $perPage, int $currentPage, string $baseUrl = '', array $query = [])
    {
        $this->items = $items;
        $this->total = max(0, $total);
        $this->perPage = max(1, $perPage);
        $this->currentPage = max(1, $currentPage);
        $this->baseUrl = $baseUrl;
        $this->query = $query;
    }

    /** @return array<int,mixed> */
    public function items(): array
    {
        return $this->items;
    }

    public function total(): int
    {
        return $this->total;
    }

    public function perPage(): int
    {
        return $this->perPage;
    }

    public function currentPage(): int
    {
        return $this->currentPage;
    }

    public function lastPage(): int
    {
        return max(1, (int) ceil($this->total / $this->perPage));
    }

    public function hasPages(): bool
    {
        return $this->lastPage() > 1;
    }

    public function firstItemNumber(): int
    {
        return $this->total === 0 ? 0 : (($this->currentPage - 1) * $this->perPage) + 1;
    }

    public function lastItemNumber(): int
    {
        return min($this->total, $this->currentPage * $this->perPage);
    }

    public static function offset(int $page, int $perPage): int
    {
        return (max(1, $page) - 1) * $perPage;
    }

    public function url(int $page): string
    {
        $page = max(1, min($page, $this->lastPage()));
        $query = $this->query;

        if ($page > 1) {
            $query['page'] = $page;
        } else {
            unset($query['page']);
        }

        return Url::withQuery($this->baseUrl, $query);
    }

    public function previousUrl(): ?string
    {
        return $this->currentPage > 1 ? $this->url($this->currentPage - 1) : null;
    }

    public function nextUrl(): ?string
    {
        return $this->currentPage < $this->lastPage() ? $this->url($this->currentPage + 1) : null;
    }

    /**
     * Window of page numbers; `null` entries render as an ellipsis.
     *
     * @return array<int,int|null>
     */
    public function window(int $each = 2): array
    {
        $last = $this->lastPage();
        $current = $this->currentPage;
        $pages = [];

        if ($last <= 7 + ($each * 2)) {
            return range(1, $last);
        }

        $pages[] = 1;

        $start = max(2, $current - $each);
        $end = min($last - 1, $current + $each);

        if ($start > 2) {
            $pages[] = null;
        }

        for ($page = $start; $page <= $end; $page++) {
            $pages[] = $page;
        }

        if ($end < $last - 1) {
            $pages[] = null;
        }

        $pages[] = $last;

        return $pages;
    }
}
