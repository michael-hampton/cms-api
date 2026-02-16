<?php

namespace App\Enums\Subscriptions;

enum SubscriptionSortOption: string
{
    case PRICE_LOW_TO_HIGH = 'price_asc';
    case PRICE_HIGH_TO_LOW = 'price_desc';
    case NAME_A_TO_Z = 'name_asc';
    case NAME_Z_TO_A = 'name_desc';
    case NEWEST_FIRST = 'created_desc';
    case OLDEST_FIRST = 'created_asc';

    public function label(): string
    {
        return match ($this) {
            self::PRICE_LOW_TO_HIGH => 'Price: Low to High',
            self::PRICE_HIGH_TO_LOW => 'Price: High to Low',
            self::NAME_A_TO_Z => 'Name: A to Z',
            self::NAME_Z_TO_A => 'Name: Z to A',
            self::NEWEST_FIRST => 'Newest First',
            self::OLDEST_FIRST => 'Oldest First',
        };
    }

    public function orderByClause(): array
    {
        return match ($this) {
            self::PRICE_LOW_TO_HIGH => ['price', 'asc'],
            self::PRICE_HIGH_TO_LOW => ['price', 'desc'],
            self::NAME_A_TO_Z => ['name', 'asc'],
            self::NAME_Z_TO_A => ['name', 'desc'],
            self::NEWEST_FIRST => ['created_at', 'desc'],
            self::OLDEST_FIRST => ['created_at', 'asc'],
        };
    }
}