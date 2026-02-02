<?php

namespace App\Services\Billing;

/**
 * Splits a flat list of checkout items into groups keyed by merchant.
 *
 * Rules:
 *   - Items with a merchant_id are grouped by that merchant.
 *   - Items with no merchant_id (null) are collected into a single
 *     "system" group keyed by the string '__system__'.
 *   - Bundle items are split exactly like any other item; a 'bundle_id'
 *     key in metadata is preserved so they remain traceable after the split.
 *   - The returned structure is an associative array:
 *       [
 *           <merchant_id|'__system__'> => [
 *               'merchant_id' => int|null,
 *               'items'       => [ ...item arrays... ],
 *           ],
 *           ...
 *       ]
 */
class CheckoutSplittingService
{
    public const SYSTEM_MERCHANT_KEY = '__system__';

    /**
     * @param array $items Each item must have at least:
     *                          - product_id
     *                          - product_name
     *                          - quantity
     *                          - unit_price
     *                          - merchant_id (int|null)
     *                         Optionally: bundle_id, tax, subtotal, total, metadata
     * @return array  Merchant-keyed groups as described above.
     */
    public function splitByMerchant(array $items): array
    {
        $groups = [];

        foreach ($items as $item) {
            $merchantId = $item['merchant_id'] ?? null;
            $key = $merchantId ?? self::SYSTEM_MERCHANT_KEY;

            if (!isset($groups[$key])) {
                $groups[$key] = [
                    'merchant_id' => $merchantId,
                    'stripe_group_key' => $merchantId
                        ? 'merchant_' . $merchantId
                        : 'system',
                    'items' => [],
                ];
            }

            // Ensure bundle_id survives in metadata if present
            if (isset($item['bundle_id'])) {
                $item['metadata'] = array_merge($item['metadata'] ?? [], [
                    'bundle_id' => $item['bundle_id'],
                ]);
            }

            $groups[$key]['items'][] = $item;
        }

        return $groups;
    }

    /**
     * Convenience: returns only the merchant keys that are actual merchants
     * (i.e. excludes the system bucket).
     */
    public function getMerchantKeys(array $groups): array
    {
        return array_filter(
            array_keys($groups),
            fn($key) => $key !== self::SYSTEM_MERCHANT_KEY
        );
    }

    /**
     * Returns true if the system (no-merchant) bucket exists and has items.
     */
    public function hasSystemOrder(array $groups): bool
    {
        return isset($groups[self::SYSTEM_MERCHANT_KEY])
            && !empty($groups[self::SYSTEM_MERCHANT_KEY]['items']);
    }
}