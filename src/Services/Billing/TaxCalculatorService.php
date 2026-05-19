<?php

namespace App\Services\Billing;

use App\DTO\Cart\TaxData;
use App\Framework\Support\Logger;
use App\Models\Member;
use App\Services\Billing\Tax\StripeTaxCalculationBuilder;
use App\Services\Billing\Tax\StripeTaxResponseMapper;
use Stripe\Exception\ApiErrorException;
use Stripe\StripeClient;

/**
 * Tax estimate service.
 *
 * ┌─────────────────────────────────────────────────────────────────────────┐
 * │  ESTIMATE / PREVIEW ONLY — NOT THE FINANCIAL SOURCE OF TRUTH           │
 * │                                                                         │
 * │  Final tax authority: Stripe automatic_tax on PaymentIntent /           │
 * │  Subscription / Checkout Session.                                       │
 * │                                                                         │
 * │  Cart calculations are stateless display estimates. They may apply      │
 * │  local exemption overrides before a Stripe Customer record exists.      │
 * │  The authoritative exemption is always the Stripe Customer's            │
 * │  tax_exempt setting resolved at payment time.                           │
 * └─────────────────────────────────────────────────────────────────────────┘
 *
 * Feature flag: config('billing.tax_engine')
 *   'stripe'  — Stripe Tax Calculation API (default)
 *   'legacy'  — internal hardcoded rate table (rollback path)
 */
class TaxCalculatorService
{
    private StripeClient $stripe;

    // -----------------------------------------------------------------------
    // Legacy rate table — retained behind feature flag for rollback only.
    // MUST NOT be used in the Stripe execution path.
    //
    // TODO: move to config/billing_tax_legacy.php before final deletion to
    //       keep the class lean and make the removal diff trivially small.
    // -----------------------------------------------------------------------
    private array $taxRates = [
        'US' => [
            'CA'      => ['rate' => 0.0725,  'includes_shipping' => true,  'jurisdiction' => 'California'],
            'NY'      => ['rate' => 0.0400,  'includes_shipping' => true,  'jurisdiction' => 'New York'],
            'TX'      => ['rate' => 0.0625,  'includes_shipping' => true,  'jurisdiction' => 'Texas'],
            'FL'      => ['rate' => 0.0600,  'includes_shipping' => false, 'jurisdiction' => 'Florida'],
            'WA'      => ['rate' => 0.0650,  'includes_shipping' => true,  'jurisdiction' => 'Washington'],
            'IL'      => ['rate' => 0.0625,  'includes_shipping' => false, 'jurisdiction' => 'Illinois'],
            'PA'      => ['rate' => 0.0600,  'includes_shipping' => false, 'jurisdiction' => 'Pennsylvania'],
            'OH'      => ['rate' => 0.0575,  'includes_shipping' => true,  'jurisdiction' => 'Ohio'],
            'DEFAULT' => ['rate' => 0.0700,  'includes_shipping' => true,  'jurisdiction' => 'United States'],
        ],
        'CA' => [
            'ON'      => ['rate' => 0.13,    'includes_shipping' => true, 'jurisdiction' => 'Ontario (HST)'],
            'QC'      => ['rate' => 0.14975, 'includes_shipping' => true, 'jurisdiction' => 'Quebec (GST+QST)'],
            'BC'      => ['rate' => 0.12,    'includes_shipping' => true, 'jurisdiction' => 'British Columbia (GST+PST)'],
            'AB'      => ['rate' => 0.05,    'includes_shipping' => true, 'jurisdiction' => 'Alberta (GST only)'],
            'NS'      => ['rate' => 0.15,    'includes_shipping' => true, 'jurisdiction' => 'Nova Scotia (HST)'],
            'DEFAULT' => ['rate' => 0.05,    'includes_shipping' => true, 'jurisdiction' => 'Canada (GST)'],
        ],
        'GB' => ['DEFAULT' => ['rate' => 0.20, 'includes_shipping' => true,  'jurisdiction' => 'United Kingdom (VAT)']],
        'DE' => ['DEFAULT' => ['rate' => 0.19, 'includes_shipping' => true,  'jurisdiction' => 'Germany (VAT)']],
        'FR' => ['DEFAULT' => ['rate' => 0.20, 'includes_shipping' => true,  'jurisdiction' => 'France (VAT)']],
        'IT' => ['DEFAULT' => ['rate' => 0.22, 'includes_shipping' => true,  'jurisdiction' => 'Italy (VAT)']],
        'ES' => ['DEFAULT' => ['rate' => 0.21, 'includes_shipping' => true,  'jurisdiction' => 'Spain (VAT)']],
        'NL' => ['DEFAULT' => ['rate' => 0.21, 'includes_shipping' => true,  'jurisdiction' => 'Netherlands (VAT)']],
        'AU' => ['DEFAULT' => ['rate' => 0.10, 'includes_shipping' => true,  'jurisdiction' => 'Australia (GST)']],
        'NZ' => ['DEFAULT' => ['rate' => 0.15, 'includes_shipping' => true,  'jurisdiction' => 'New Zealand (GST)']],
    ];

    public function __construct(
        private readonly StripeTaxCalculationBuilder $payloadBuilder,
        private readonly StripeTaxResponseMapper     $responseMapper,
        ?StripeClient                                $stripeClient = null
    ) {
        $this->stripe = $stripeClient ?? new StripeClient(
            $_ENV['STRIPE_SECRET_KEY'] ?? config('payment.stripe.secret_key')
        );
    }

    // =========================================================================
    // Public API — signatures are frozen. Zero downstream changes required.
    // =========================================================================

    /**
     * Calculate tax for cart items.
     *
     * Estimate only — see class docblock.
     *
     * On the Stripe path, individual line items are forwarded to
     * buildFromLineItems() so per-line granularity is preserved in the
     * Stripe Calculation object. On the legacy path, items are summed to
     * totals before the rate-table lookup — identical to pre-migration behaviour.
     *
     * @param array<int, array{subtotal_cents?: int, shipping_cents?: int}> $items
     */
    public function calculateCartTax(
        array   $items,
        string  $country = 'GB',
        ?string $state = null,
        ?string $postalCode = null,
        ?Member $member = null
    ): TaxData {
        if ($this->isLegacyEngine()) {
            $subtotalCents = 0;
            $shippingCents = 0;

            foreach ($items as $item) {
                $subtotalCents += (int)($item['subtotal_cents'] ?? 0);
                $shippingCents += (int)($item['shipping_cents'] ?? 0);
            }

            return $this->calculateOrderTaxLegacy(
                $subtotalCents,
                $shippingCents,
                $country,
                $state,
                $postalCode,
                $member
            );
        }

        die('yes');

        return $this->calculateCartTaxViaStripe($items, $country, $state, $postalCode, $member);
    }

    /**
     * Calculate tax for an order.
     *
     * Estimate only — see class docblock.
     *
     * When config('billing.tax_engine') === 'stripe' (default):
     *   → calls Stripe Tax Calculation API with a single synthetic line item
     *   → maps response to TaxData
     *   → applies local exemption override if member qualifies
     *
     * When config('billing.tax_engine') === 'legacy':
     *   → uses internal hardcoded rate table (rollback path)
     */
    public function calculateOrderTax(
        int     $subtotalCents,
        int     $shippingCents,
        string  $country = 'GB',
        ?string $state = null,
        ?string $postalCode = null,
        ?Member $member = null
    ): TaxData {
        if ($this->isLegacyEngine()) {
            return $this->calculateOrderTaxLegacy(
                $subtotalCents,
                $shippingCents,
                $country,
                $state,
                $postalCode,
                $member
            );
        }

        return $this->calculateOrderTaxViaStripe(
            $subtotalCents,
            $shippingCents,
            $country,
            $state,
            $postalCode,
            $member
        );
    }

    /**
     * Get all supported countries.
     *
     * Configuration-backed. Reflects marketplace operational scope,
     * not Stripe's full geographic coverage.
     */
    public function getSupportedCountries(): array
    {
        return config('billing.tax_supported_countries', array_keys($this->taxRates));
    }

    /**
     * Get states/provinces for a country.
     *
     * Configuration-backed. Returns an empty array for countries without
     * state-level tax variation.
     */
    public function getStatesForCountry(string $country): array
    {
        $configured = config('billing.tax_supported_states', []);

        if (isset($configured[$country])) {
            return $configured[$country];
        }

        // Legacy fallback: derive from rate table for countries not yet in config.
        if (isset($this->taxRates[$country])) {
            $states = array_keys($this->taxRates[$country]);
            return array_values(array_filter($states, fn($s) => $s !== 'DEFAULT'));
        }

        return [];
    }

    /**
     * Get tax rate details for display purposes.
     *
     * Informational / display only.
     * Does NOT call Stripe. Does NOT represent the authoritative tax amount.
     * Final tax is always determined by Stripe at payment time.
     *
     * Returns a TaxData built from the legacy rate table for UI preview.
     * Returns null when the country is unsupported.
     */
    public function getTaxRateInfo(string $country, ?string $state = null): ?TaxData
    {
        $taxRate = $this->getLegacyTaxRate($country, $state);

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
     * Distribute tax across order items proportionally.
     *
     * @deprecated Stripe Tax provides authoritative per-line tax allocation.
     *             Retained for backward compatibility. Not called by Stripe path.
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
        $itemsWithTax   = [];

        foreach ($items as $index => $item) {
            $itemBaseCents = ($item['subtotal_cents'] ?? 0) + ($item['shipping_cents'] ?? 0);

            if ($index === count($items) - 1) {
                $itemTaxCents = $totalTaxCents - $distributedTax;
            } else {
                $itemTaxCents    = (int)round($itemBaseCents * ($totalTaxCents / $totalBaseCents));
                $distributedTax += $itemTaxCents;
            }

            $item['tax_cents'] = $itemTaxCents;
            $itemsWithTax[]    = $item;
        }

        return $itemsWithTax;
    }

    /**
     * Validate tax exemption certificate.
     */
    public function validateTaxExemption(
        Member $member,
        string $certificateNumber,
        string $jurisdiction
    ): bool {
        return !empty($certificateNumber) && strlen($certificateNumber) >= 8;
    }

    // =========================================================================
    // Stripe path (private)
    // =========================================================================

    /**
     * Stripe path for calculateCartTax().
     *
     * Uses buildFromLineItems() so Stripe receives per-item granularity rather
     * than a single collapsed subtotal. This is the reason buildFromLineItems()
     * exists — collapsing to totals here would make that method dead code.
     */
    private function calculateCartTaxViaStripe(
        array   $items,
        string  $country,
        ?string $state,
        ?string $postalCode,
        ?Member $member
    ): TaxData {
        $payload = $this->payloadBuilder->buildFromLineItems(
            $items,
            $this->defaultCurrency(),
            $country,
            $state,
            $postalCode
        );

        try {
            $calculation = $this->stripe->tax->calculations->create($payload);

        } catch (ApiErrorException $e) {
            Logger::warning('Stripe Tax API error in calculateCartTax, falling back to legacy', [
                'country'     => $country,
                'state'       => $state,
                'postal_code' => $postalCode,
                'error'       => $e->getMessage(),
            ]);

            $subtotalCents = (int)array_sum(array_column($items, 'subtotal_cents'));
            $shippingCents = (int)array_sum(array_column($items, 'shipping_cents'));

            return $this->calculateOrderTaxLegacy(
                $subtotalCents,
                $shippingCents,
                $country,
                $state,
                $postalCode,
                $member
            );
        }

        // Pass aggregated totals to the mapper so that taxableAmountCents in
        // the returned DTO reflects the full cart, not an individual line item.
        $subtotalCents = (int)array_sum(array_column($items, 'subtotal_cents'));
        $shippingCents = (int)array_sum(array_column($items, 'shipping_cents'));

        $taxData = $this->responseMapper->map($calculation, $subtotalCents, $shippingCents);

        return $this->applyExemptionOverride($taxData, $member);
    }

    private function calculateOrderTaxViaStripe(
        int     $subtotalCents,
        int     $shippingCents,
        string  $country,
        ?string $state,
        ?string $postalCode,
        ?Member $member
    ): TaxData {
        $payload = $this->payloadBuilder->buildFromOrderTotals(
            $subtotalCents,
            $shippingCents,
            $this->defaultCurrency(),
            $country,
            $state,
            $postalCode
        );

        try {
            $calculation = $this->stripe->tax->calculations->create($payload);
        } catch (ApiErrorException $e) {
            Logger::warning('Stripe Tax API error in calculateOrderTax, falling back to legacy', [
                'country'     => $country,
                'state'       => $state,
                'postal_code' => $postalCode,
                'error'       => $e->getMessage(),
            ]);

            return $this->calculateOrderTaxLegacy(
                $subtotalCents,
                $shippingCents,
                $country,
                $state,
                $postalCode,
                $member
            );
        }

        $taxData = $this->responseMapper->map($calculation, $subtotalCents, $shippingCents);

        return $this->applyExemptionOverride($taxData, $member);
    }

    /**
     * Apply local exemption override on top of a Stripe-derived TaxData.
     *
     * This override exists because a Stripe Customer record may not yet exist
     * at cart display time — members are linked to Stripe customers only after
     * checkout begins. The authoritative exemption remains the Stripe Customer's
     * tax_exempt setting resolved at payment time.
     */
    private function applyExemptionOverride(TaxData $taxData, ?Member $member): TaxData
    {
        if (!$member || !$this->isTaxExempt($member)) {
            return $taxData;
        }

        return new TaxData(
            rate: 0,
            ratePercentage: 0,
            jurisdiction: $taxData->jurisdiction,
            includesShipping: $taxData->includesShipping,
            taxCents: 0,
            taxableAmountCents: $taxData->taxableAmountCents,
            exempt: true
        );
    }

    // =========================================================================
    // Legacy path (private) — feature-flagged rollback
    // =========================================================================

    /**
     * @param ?string $postalCode Accepted but unused in legacy path. Present to
     *                            keep all internal call sites aligned with the
     *                            public calculateOrderTax() signature so a future
     *                            refactor cannot silently drop it.
     */
    private function calculateOrderTaxLegacy(
        int     $subtotalCents,
        int     $shippingCents,
        string  $country,
        ?string $state,
        ?string $postalCode,
        ?Member $member
    ): TaxData {
        $taxRate = $this->getLegacyTaxRate($country, $state);

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

        $taxableAmountCents = $subtotalCents;
        if ($taxRate['includes_shipping']) {
            $taxableAmountCents += $shippingCents;
        }

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

    private function getLegacyTaxRate(string $country, ?string $state = null): ?array
    {
        if (!isset($this->taxRates[$country])) {
            return null;
        }

        $countryRates = $this->taxRates[$country];

        if ($state && isset($countryRates[$state])) {
            return $countryRates[$state];
        }

        return $countryRates['DEFAULT'] ?? null;
    }

    private function isTaxExempt(Member $member): bool
    {
        return $member->tax_exempt === true
            || $member->organization_type === 'non_profit'
            || $member->organization_type === 'educational';
    }

    // Overridable in tests via anonymous subclass — avoids mocking config().
    protected function isLegacyEngine(): bool
    {
        return config('billing.tax_engine', 'stripe') === 'legacy';
    }

    // Overridable in tests via anonymous subclass.
    protected function defaultCurrency(): string
    {
        return config('billing.default_currency', 'gbp');
    }
}