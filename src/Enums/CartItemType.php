<?php

namespace App\Enums;

/**
 * Cart item types.
 *
 * Replaces magic strings scattered throughout the codebase.
 */
enum CartItemType: string
{
    case PRODUCT = 'product';
    case OFFER = 'offer';
    case BUNDLE = 'bundle';
    case SUBSCRIPTION = 'subscription';
    case SUBSCRIPTION_BUNDLE = 'subscription_bundle';

    /**
     * Get all valid type strings for validation.
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}