<?php

namespace App\Services\Billing;

use App\DTO\Cart\TaxData;
use App\Models\Member;

class TaxCalculatorService
{
    private array $taxRates = [
        // US States
        'US' => [
            'CA' => ['rate' => 0.0725, 'includes_shipping' => true, 'jurisdiction' => 'California'],
            'NY' => ['rate' => 0.0400, 'includes_shipping' => true, 'jurisdiction' => 'New York'],
            'TX' => ['rate' => 0.0625, 'includes_shipping' => true, 'jurisdiction' => 'Texas'],
            'FL' => ['rate' => 0.0600, 'includes_shipping' => false, 'jurisdiction' => 'Florida'],
            'WA' => ['rate' => 0.0650, 'includes_shipping' => true, 'jurisdiction' => 'Washington'],
            'IL' => ['rate' => 0.0625, 'includes_shipping' => false, 'jurisdiction' => 'Illinois'],
            'PA' => ['rate' => 0.0600, 'includes_shipping' => false, 'jurisdiction' => 'Pennsylvania'],
            'OH' => ['rate' => 0.0575, 'includes_shipping' => true, 'jurisdiction' => 'Ohio'],
            'DEFAULT' => ['rate' => 0.0700, 'includes_shipping' => true, 'jurisdiction' => 'United States']
        ],
        // Canada Provinces (GST + PST/HST)
        'CA' => [
            'ON' => ['rate' => 0.13, 'includes_shipping' => true, 'jurisdiction' => 'Ontario (HST)'],
            'QC' => ['rate' => 0.14975, 'includes_shipping' => true, 'jurisdiction' => 'Quebec (GST+QST)'],
            'BC' => ['rate' => 0.12, 'includes_shipping' => true, 'jurisdiction' => 'British Columbia (GST+PST)'],
            'AB' => ['rate' => 0.05, 'includes_shipping' => true, 'jurisdiction' => 'Alberta (GST only)'],
            'NS' => ['rate' => 0.15, 'includes_shipping' => true, 'jurisdiction' => 'Nova Scotia (HST)'],
            'DEFAULT' => ['rate' => 0.05, 'includes_shipping' => true, 'jurisdiction' => 'Canada (GST)']
        ],
        // UK (VAT)
        'GB' => [
            'DEFAULT' => ['rate' => 0.20, 'includes_shipping' => true, 'jurisdiction' => 'United Kingdom (VAT)']
        ],
        // EU Countries (VAT)
        'DE' => [
            'DEFAULT' => ['rate' => 0.19, 'includes_shipping' => true, 'jurisdiction' => 'Germany (VAT)']
        ],
        'FR' => [
            'DEFAULT' => ['rate' => 0.20, 'includes_shipping' => true, 'jurisdiction' => 'France (VAT)']
        ],
        'IT' => [
            'DEFAULT' => ['rate' => 0.22, 'includes_shipping' => true, 'jurisdiction' => 'Italy (VAT)']
        ],
        'ES' => [
            'DEFAULT' => ['rate' => 0.21, 'includes_shipping' => true, 'jurisdiction' => 'Spain (VAT)']
        ],
        'NL' => [
            'DEFAULT' => ['rate' => 0.21, 'includes_shipping' => true, 'jurisdiction' => 'Netherlands (VAT)']
        ],
        // Australia (GST)
        'AU' => [
            'DEFAULT' => ['rate' => 0.10, 'includes_shipping' => true, 'jurisdiction' => 'Australia (GST)']
        ],
        // New Zealand (GST)
        'NZ' => [
            'DEFAULT' => ['rate' => 0.15, 'includes_shipping' => true, 'jurisdiction' => 'New Zealand (GST)']
        ]
    ];

    /**
     * Calculate tax for cart items
     */
    public function calculateCartTax(
        array   $items,
        string  $country = 'GB',
        ?string $state = null,
        ?string $postalCode = null,
        ?Member $member = null
    ): TaxData
    {
        $subtotalCents = 0;
        $shippingCents = 0;

        foreach ($items as $item) {
            $subtotalCents += $item['subtotal_cents'] ?? 0;
            $shippingCents += $item['shipping_cents'] ?? 0;
        }

        return $this->calculateOrderTax(
            $subtotalCents,
            $shippingCents,
            $country,
            $state,
            $postalCode,
            $member
        );
    }

    /**
     * Calculate tax for an order
     */
    public function calculateOrderTax(
        int     $subtotalCents,
        int     $shippingCents,
        string  $country = 'GB',
        ?string $state = null,
        ?string $postalCode = null,
        ?Member $member = null
    ): TaxData
    {
        $taxRate = $this->getTaxRate($country, $state);

        if (!$taxRate) {
            return new TaxData(
                rate: 0,
                ratePercentage: 0,
                jurisdiction: null,
                includesShipping: false,
                taxCents: 0,
                exempt: true
            );
        }

        // Check if member is tax exempt
        if ($member && $this->isTaxExempt($member)) {
            return new TaxData(
                rate: 0,
                ratePercentage: 0,
                jurisdiction: $taxRate['jurisdiction'],
                includesShipping: $taxRate['includes_shipping'],
                taxCents: 0,
                exempt: true
            );
        }

        $taxableAmountCents = $this->calculateTaxableAmount(
            $subtotalCents,
            $shippingCents,
            $taxRate
        );

        $taxCents = (int)round($taxableAmountCents * $taxRate['rate']);

        return new TaxData(
            rate: $taxRate['rate'],
            ratePercentage: round($taxRate['rate'] * 100, 2),
            jurisdiction: $taxRate['jurisdiction'],
            includesShipping: $taxRate['includes_shipping'],
            taxCents: $taxCents,
            taxableAmountCents: $taxableAmountCents
        );
    }

    /**
     * Get applicable tax rate
     */
    private function getTaxRate(string $country, ?string $state = null): ?array
    {
        // Country not supported
        if (!isset($this->taxRates[$country])) {
            return null;
        }

        $countryRates = $this->taxRates[$country];

        // Try state/province specific rate
        if ($state && isset($countryRates[$state])) {
            return $countryRates[$state];
        }

        // Fall back to country default
        if (isset($countryRates['DEFAULT'])) {
            return $countryRates['DEFAULT'];
        }

        return null;
    }

    /**
     * Check if member is tax exempt
     */
    private function isTaxExempt(Member $member): bool
    {
        return $member->tax_exempt === true
            || $member->organization_type === 'non_profit'
            || $member->organization_type === 'educational';
    }

    /**
     * Calculate taxable amount based on rate rules
     */
    private function calculateTaxableAmount(
        int   $subtotalCents,
        int   $shippingCents,
        array $taxRate
    ): int
    {
        $taxableAmountCents = $subtotalCents;

        // Add shipping to taxable amount if rate includes shipping
        if ($taxRate['includes_shipping']) {
            $taxableAmountCents += $shippingCents;
        }

        return $taxableAmountCents;
    }

    /**
     * Distribute tax across order items proportionally
     */
    public function distributeTaxToItems(array $items, int $totalTaxCents): array
    {
        $totalBaseCents = 0;

        foreach ($items as $item) {
            $totalBaseCents += ($item['subtotal_cents'] ?? 0) + ($item['shipping_cents'] ?? 0);
        }

        if ($totalBaseCents === 0 || $totalTaxCents === 0) {
            return array_map(function ($item) {
                $item['tax_cents'] = 0;
                return $item;
            }, $items);
        }

        $distributedTax = 0;
        $itemsWithTax = [];

        foreach ($items as $index => $item) {
            $itemBaseCents = ($item['subtotal_cents'] ?? 0) + ($item['shipping_cents'] ?? 0);

            // Last item gets remaining tax to handle rounding
            if ($index === count($items) - 1) {
                $itemTaxCents = $totalTaxCents - $distributedTax;
            } else {
                $itemTaxCents = (int)round($itemBaseCents * ($totalTaxCents / $totalBaseCents));
                $distributedTax += $itemTaxCents;
            }

            $item['tax_cents'] = $itemTaxCents;
            $itemsWithTax[] = $item;
        }

        return $itemsWithTax;
    }

    /**
     * Get all supported countries
     */
    public function getSupportedCountries(): array
    {
        return array_keys($this->taxRates);
    }

    /**
     * Get states/provinces for a country
     */
    public function getStatesForCountry(string $country): array
    {
        if (!isset($this->taxRates[$country])) {
            return [];
        }

        $states = array_keys($this->taxRates[$country]);
        return array_filter($states, fn($state) => $state !== 'DEFAULT');
    }

    /**
     * Get tax rate details for display
     */
    public function getTaxRateInfo(string $country, ?string $state = null): ?TaxData
    {
        $taxRate = $this->getTaxRate($country, $state);

        if (!$taxRate) {
            return null;
        }

        return new TaxData(
            rate: $taxRate['rate'],
            ratePercentage: round($taxRate['rate'] * 100, 2),
            jurisdiction: $taxRate['jurisdiction'],
            includesShipping: $taxRate['includes_shipping']
        );
    }

    /**
     * Validate tax exemption certificate
     */
    public function validateTaxExemption(
        Member $member,
        string $certificateNumber,
        string $jurisdiction
    ): bool
    {
        // This would integrate with tax validation services
        // For now, just check if certificate exists and is valid
        return !empty($certificateNumber) && strlen($certificateNumber) >= 8;
    }
}