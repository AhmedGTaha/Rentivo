<?php

declare(strict_types=1);

namespace Rentivo\Support;

/**
 * Immutable pagination state shared by every paginated list in the app.
 *
 * The component only describes the page window; repositories are responsible
 * for applying LIMIT/OFFSET using the values exposed here.
 */
final class Pagination
{
    public readonly int $page;
    public readonly int $perPage;
    public readonly int $total;
    public readonly int $lastPage;

    public function __construct(int $page, int $perPage, int $total)
    {
        $this->perPage = max(1, $perPage);
        $this->total = max(0, $total);
        $this->lastPage = max(1, (int) ceil($this->total / $this->perPage));
        $this->page = min(max(1, $page), $this->lastPage);
    }

    public function offset(): int
    {
        return ($this->page - 1) * $this->perPage;
    }

    public function limit(): int
    {
        return $this->perPage;
    }

    public function hasPages(): bool
    {
        return $this->lastPage > 1;
    }

    public function from(): int
    {
        return $this->total === 0 ? 0 : $this->offset() + 1;
    }

    public function to(): int
    {
        return min($this->offset() + $this->perPage, $this->total);
    }

    /**
     * Page numbers to render, with 0 marking an elision gap.
     *
     * @return list<int>
     */
    public function window(int $each = 1): array
    {
        if ($this->lastPage <= 7) {
            return range(1, $this->lastPage);
        }

        $pages = [1];
        $start = max(2, $this->page - $each);
        $end = min($this->lastPage - 1, $this->page + $each);

        if ($start > 2) {
            $pages[] = 0;
        }

        for ($i = $start; $i <= $end; $i++) {
            $pages[] = $i;
        }

        if ($end < $this->lastPage - 1) {
            $pages[] = 0;
        }

        $pages[] = $this->lastPage;

        return $pages;
    }

    /**
     * Builds a URL for a given page while preserving the current query state.
     *
     * @param array<string,mixed> $query
     */
    public static function url(string $path, array $query, int $page): string
    {
        $query['page'] = $page;
        $query = array_filter(
            $query,
            static fn ($value) => $value !== null && $value !== '' && $value !== []
        );

        return $path . ($query === [] ? '' : '?' . http_build_query($query));
    }
}
