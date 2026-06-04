<?php

namespace App\Services\Product;

/**
 * Dedicated collaborator for sanitising and validating product filter inputs.
 *
 * Lives here because filter validation is a calculation/decision concern
 * that changes independently of the service and repository layers.
 */
final class FilterInputSanitiser
{
    private const ALLOWED_SORT_COLUMNS = [
        'created_at', 'price', 'name', 'sale_price', 'discount_percentage',
    ];

    private const ALLOWED_SORT_ORDERS = ['asc', 'desc'];

    private const MAX_PER_PAGE = 96;
    private const DEFAULT_PER_PAGE = 12;
    private const MAX_PAGE = 10_000;
    private const MAX_IDS = 100;
    private const MAX_PRICE = 1_000_000;

    /**
     * Returns a sanitised, type-safe filter array safe for use in SearchCriteria.
     *
     * @param array<string, mixed> $raw
     * @return array<string, mixed>
     */
    public function sanitise(array $raw): array
    {
        return [
            'category_ids' => $this->sanitiseIntList($raw['category_ids'] ?? ''),
            'brand_ids' => $this->sanitiseIntList($raw['brand_ids'] ?? ''),
            'spec_ids' => $this->sanitiseIntList($raw['spec_ids'] ?? ''),
            'sort_by' => $this->sanitiseSortColumn($raw['sort_by'] ?? 'created_at'),
            'sort_order' => $this->sanitiseSortOrder($raw['sort_order'] ?? 'desc'),
            'page' => $this->sanitisePage((int)($raw['page'] ?? 1)),
            'per_page' => $this->sanitisePerPage((int)($raw['per_page'] ?? self::DEFAULT_PER_PAGE)),
            'search' => $this->sanitiseSearch($raw['q'] ?? ''),
            'min_price' => $this->sanitisePrice($raw['min_price'] ?? null),
            'max_price' => $this->sanitisePrice($raw['max_price'] ?? null),
            'on_sale' => $this->sanitiseBool($raw['on_sale'] ?? ''),
            'min_rating' => $this->sanitiseRating($raw['min_rating'] ?? null),
            'min_discount' => $this->sanitiseDiscount($raw['min_discount'] ?? null),
            'has_voucher' => $this->sanitiseBool($raw['has_voucher'] ?? ''),
            'region_set_ids' => $this->sanitiseIntList($raw['region_set_ids'] ?? ''),
        ];
    }

    /**
     * Converts a comma-separated string of IDs to an array of positive integers.
     * Silently drops anything that isn't a positive integer to prevent injection.
     *
     * @return int[]
     */
    private function sanitiseIntList(mixed $raw): array
    {
        if (empty($raw)) {
            return [];
        }

        $parts = is_array($raw) ? $raw : explode(',', (string)$raw);

        return array_values(
            array_filter(
                array_map(
                    fn($v) => filter_var(trim((string)$v), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]),
                    array_slice($parts, 0, self::MAX_IDS)
                ),
                fn($v) => $v !== false
            )
        );
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    /**
     * Only allows whitelisted column names to prevent ORDER BY injection.
     */
    private function sanitiseSortColumn(mixed $raw): string
    {
        $value = strtolower(trim((string)$raw));

        return in_array($value, self::ALLOWED_SORT_COLUMNS, true) ? $value : 'created_at';
    }

    private function sanitiseSortOrder(mixed $raw): string
    {
        $value = strtolower(trim((string)$raw));

        return in_array($value, self::ALLOWED_SORT_ORDERS, true) ? $value : 'desc';
    }

    private function sanitisePage(int $raw): int
    {
        return max(1, min($raw, self::MAX_PAGE));
    }

    private function sanitisePerPage(int $raw): int
    {
        $allowed = [12, 24, 48, 96];

        return in_array($raw, $allowed, true) ? $raw : self::DEFAULT_PER_PAGE;
    }

    private function sanitiseSearch(mixed $raw): string
    {
        return mb_substr(strip_tags(trim((string)$raw)), 0, 200);
    }

    private function sanitisePrice(mixed $raw): ?float
    {
        if ($raw === null || $raw === '') {
            return null;
        }

        $value = filter_var($raw, FILTER_VALIDATE_FLOAT);

        if ($value === false || $value < 0) {
            return null;
        }

        return round(min($value, self::MAX_PRICE), 2);
    }

    private function sanitiseBool(mixed $raw): bool
    {
        return in_array((string)$raw, ['1', 'true', 'yes', 'on'], true);
    }

    /**
     * Rating must be an integer 1–5.
     */
    private function sanitiseRating(mixed $raw): ?int
    {
        if ($raw === null || $raw === '') {
            return null;
        }

        $value = filter_var($raw, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 5]]);

        return $value !== false ? $value : null;
    }

    /**
     * Discount must be an integer 1–100.
     */
    private function sanitiseDiscount(mixed $raw): ?int
    {
        if ($raw === null || $raw === '') {
            return null;
        }

        $value = filter_var($raw, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 100]]);

        return $value !== false ? $value : null;
    }

    /**
     * Sanitises and validates a product/modal ID from a route parameter.
     * Returns null if the value is not a positive integer.
     */
    public function sanitiseId(mixed $raw): ?int
    {
        $id = filter_var($raw, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

        return $id !== false ? $id : null;
    }
}