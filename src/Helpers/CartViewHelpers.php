<?php

declare(strict_types=1);

namespace App\Helpers;

/**
 * Stateless view helpers for cart and checkout templates.
 *
 * These are pure functions — no dependencies, no side effects.
 * Load once via require/autoloader; call freely in any template.
 */
final class CartViewHelpers
{
    /**
     * Determine whether a cart item is a free gift.
     *
     * An item qualifies when any of the following is true:
     *   • options.type === 'free_gift'
     *   • options.is_gift === true
     *   • price is exactly 0.00
     */
    public static function isFreeGift(array $item): bool
    {
        $options = $item['options'] ?? [];

        return ($options['type'] ?? '') === 'free_gift'
            || ($options['is_gift'] ?? false) === true
            || (float)($item['price'] ?? 0) === 0.0;
    }

    /**
     * Group a flat list of cart items by merchant, accumulating subtotals.
     *
     * @param array[] $items
     * @return array<int|string, array{id: int|string, name: string, items: array[], subtotal: float}>
     */
    public static function groupByMerchant(array $items): array
    {
        $groups = [];

        foreach ($items as $item) {
            ['id' => $id, 'name' => $name] = self::merchantFromItem($item);

            if (!isset($groups[$id])) {
                $groups[$id] = [
                    'id' => $id,
                    'name' => $name,
                    'items' => [],
                    'subtotal' => 0.0,
                ];
            }

            $groups[$id]['items'][] = $item;
            $groups[$id]['subtotal'] += (float)($item['subtotal'] ?? 0);
        }

        return $groups;
    }

    /**
     * Resolve the merchant display name and ID from a cart item.
     *
     * @return array{id: int|string, name: string}
     */
    public static function merchantFromItem(array $item): array
    {
        $id = $item['merchant_id'] ?? $item['options']['merchant_id'] ?? 0;
        $name = ($id && !empty($item['merchant_name']))
            ? $item['merchant_name']
            : ($id ? 'Merchant ' . $id : 'Direct');

        return ['id' => $id, 'name' => $name];
    }

    /**
     * Derive up-to-two uppercase initials from a merchant name.
     */
    public static function merchantInitials(string $name): string
    {
        $words = array_filter(explode(' ', $name));
        $initials = implode(
            '',
            array_map(static fn(string $w) => strtoupper($w[0]), array_slice($words, 0, 2))
        );

        return $initials ?: '?';
    }
}