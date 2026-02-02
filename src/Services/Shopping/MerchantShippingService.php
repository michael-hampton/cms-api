<?php

namespace App\Services\Shopping;

/**
 * Calculates shipping cost per merchant order group.
 *
 * - Each group gets exactly one shipment.
 * - Cost is derived only from items within that group.
 * - The system (no-merchant) group is treated as a single "system shipment".
 *
 * Consolidation config (`consolidate_shipping`) is persisted and exposed via
 * `isConsolidationEnabled()` for downstream consumers, but does not alter
 * the calculation in this iteration. Each group always produces exactly one
 * shipment regardless of the flag. Per-merchant multi-parcel consolidation
 * is deferred to a future ticket.
 */
class MerchantShippingService
{
    private array $config;

    public function __construct(?array $config = null)
    {
        $this->config = $config ?? [
            'free_shipping_threshold' => 100.00,
            'default_rate' => 10.00,
            'consolidate_shipping' => false,
            'rates_by_country' => [
                'US' => 10.00,
                'CA' => 15.00,
                'GB' => 12.00,
                'AU' => 20.00,
            ],
        ];
    }

    /**
     * @param array $groups Output of CheckoutSplittingService::splitByMerchant()
     * @param string $country Destination country code
     * @return array  Keyed by merchant key, value = shipping cost (float)
     */
    public function calculatePerGroup(array $groups, string $country = 'US'): array
    {
        $result = [];

        foreach ($groups as $key => $group) {
            $subtotal = 0.0;
            foreach ($group['items'] as $item) {

                $subtotal += ($item['price'] * ($item['quantity'] ?? 1));
            }

            $result[$key] = $this->calculateForSubtotal($subtotal, $country);
        }

        return $result;
    }

    /**
     * Whether consolidation is enabled in current config.
     */
    public function isConsolidationEnabled(): bool
    {
        return (bool)($this->config['consolidate_shipping'] ?? false);
    }

    /**
     * Returns the full config (useful for tests / inspection).
     */
    public function getConfig(): array
    {
        return $this->config;
    }

    private function calculateForSubtotal(float $subtotal, string $country): float
    {
        if ($subtotal >= $this->config['free_shipping_threshold']) {
            return 0.00;
        }

        return $this->config['rates_by_country'][$country]
            ?? $this->config['default_rate'];
    }
}