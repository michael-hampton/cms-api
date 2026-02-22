<?php

namespace App\DTO\Pages;

use App\Enums\Pages\PageFilterType;

/**
 * Carries every parameter needed to query a paginated page listing.
 *
 * Use the named constructors instead of `new PageFilterDto()` directly — they
 * encode intent and validate the filter axis at construction time.
 */
final class PageFilterDto
{
    /**
     * @param string $filterType 'author' | 'category' | 'tag'
     * @param int $filterId Primary ID of the filter entity.
     * @param string $sort 'latest' | 'oldest' | 'title'
     * @param string $status Row status (e.g. 'published').
     * @param int $perPage Rows per page.
     * @param int $currentPage 1-based page number.
     * @param array $secondary Optional secondary filter: ['category' => id] or ['author' => id].
     */
    private function __construct(
        public readonly PageFilterType $filterType,
        public readonly int            $filterId,
        public readonly string         $sort,
        public readonly string         $status,
        public readonly int            $perPage,
        public readonly int            $currentPage,
        public readonly array          $secondary,
    )
    {
    }

    // -------------------------------------------------------------------------
    // Factory
    // -------------------------------------------------------------------------

    /**
     * @param array<string, int|string> $secondary Raw secondary filter values; IDs are cast to int.
     */
    public static function make(
        PageFilterType $filterType,
        int            $filterId,
        string         $sort = 'latest',
        string         $status = 'published',
        int            $perPage = 12,
        int            $currentPage = 1,
        array          $secondary = [],
    ): self
    {
        return new self(
            filterType: $filterType,
            filterId: $filterId,
            sort: $sort,
            status: $status,
            perPage: $perPage,
            currentPage: max(1, $currentPage),
            secondary: self::castSecondary($secondary),
        );
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /**
     * Cast secondary filter ID values to int so the repository never receives
     * a raw string from $_GET.
     *
     * @param array<string, int|string> $secondary
     * @return array<string, int>
     */
    private static function castSecondary(array $secondary): array
    {
        return array_map(fn($v) => (int)$v, $secondary);
    }

    /**
     * Sanitise a raw $_GET 'page' value to a valid 1-based page number.
     * Keeps this concern out of every controller.
     */
    public static function sanitisePage(mixed $raw): int
    {
        return max(1, (int)$raw);
    }
}