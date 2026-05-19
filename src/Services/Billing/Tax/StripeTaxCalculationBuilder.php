<?php

namespace App\Services\Billing\Tax;

/**
 * Builds the payload for Stripe's tax.calculations.create endpoint.
 *
 * Responsibility: shape only. No Stripe SDK calls. No side effects.
 * If cart structure changes independently of tax logic, this is the only
 * class that changes.
 */
class StripeTaxCalculationBuilder
{
    /**
     * Build a Stripe Tax Calculation payload from flat subtotal + shipping cents.
     *
     * Used by calculateOrderTax() where individual line items are not available —
     * only the cart totals are known. A single synthetic line item represents the
     * subtotal, and shipping is passed as a separate Stripe shipping_cost entry.
     *
     * @param int    $subtotalCents  Cart subtotal in smallest currency unit (e.g. pence / cents)
     * @param int    $shippingCents  Shipping cost in smallest currency unit
     * @param string $currency       ISO 4217 lowercase currency code (e.g. "gbp")
     * @param string $country        ISO 3166-1 alpha-2 country code (e.g. "GB")
     * @param string|null $state     State / province code where applicable (e.g. "CA")
     * @param string|null $postalCode Postal / ZIP code
     *
     * @return array Payload ready to pass to $stripe->tax->calculations->create()
     */
    public function buildFromOrderTotals(
        int     $subtotalCents,
        int     $shippingCents,
        string  $currency,
        string  $country,
        ?string $state,
        ?string $postalCode
    ): array
    {
        $payload = [
            'currency'        => strtolower($currency),
            'customer_details' => [
                'address' => $this->buildAddress($country, $state, $postalCode),
                'address_source' => 'shipping',
            ],
            'line_items' => [
                [
                    'amount'    => $subtotalCents,
                    'reference' => 'cart-subtotal',
                ],
            ],
        ];

        // Stripe Tax accepts shipping as a dedicated shipping_cost object.
        // Only include it when there is a non-zero shipping amount; omitting
        // the key entirely is cleaner than sending amount=0.
        if ($shippingCents > 0) {
            $payload['shipping_cost'] = [
                'amount' => $shippingCents,
            ];
        }

        return $payload;
    }

    /**
     * Build a Stripe Tax Calculation payload from individual line items.
     *
     * Used by calculateCartTax() where per-item breakdowns are available.
     * Each item gets its own reference so Stripe can return per-line tax
     * breakdowns if needed in future.
     *
     * @param array<int, array{subtotal_cents: int, shipping_cents?: int}> $items
     * @param string $currency
     * @param string $country
     * @param string|null $state
     * @param string|null $postalCode
     *
     * @return array
     */
    public function buildFromLineItems(
        array   $items,
        string  $currency,
        string  $country,
        ?string $state,
        ?string $postalCode
    ): array
    {
        $lineItems    = [];
        $totalShipping = 0;

        foreach ($items as $index => $item) {
            $subtotal = (int)($item['subtotal_cents'] ?? 0);
            $shipping = (int)($item['shipping_cents'] ?? 0);

            if ($subtotal > 0) {
                $lineItems[] = [
                    'amount'    => $subtotal,
                    'reference' => sprintf('cart-item-%d', $index),
                ];
            }

            $totalShipping += $shipping;
        }

        // Stripe requires at least one line item.
        if (empty($lineItems)) {
            $lineItems[] = [
                'amount'    => 0,
                'reference' => 'cart-empty',
            ];
        }

        $payload = [
            'currency'        => strtolower($currency),
            'customer_details' => [
                'address' => $this->buildAddress($country, $state, $postalCode),
                'address_source' => 'shipping',
            ],
            'line_items' => $lineItems,
        ];

        if ($totalShipping > 0) {
            $payload['shipping_cost'] = [
                'amount' => $totalShipping,
            ];
        }

        return $payload;
    }

    private function buildAddress(string $country, ?string $state, ?string $postalCode): array
    {
        $address = ['country' => strtoupper($country)];

        if ($state !== null && $state !== '') {
            $address['state'] = $state;
        }

        if ($postalCode !== null && $postalCode !== '') {
            $address['postal_code'] = $postalCode;
        }

        return $address;
    }
}