<?php

namespace App\Services\Billing\Tax;

use App\DTO\Cart\TaxData;

/**
 * Maps a Stripe Tax Calculation API response object to the application's TaxData DTO.
 *
 * Responsibility: translation only. No Stripe SDK calls. No side effects.
 * Isolating this mapping means the DTO contract and Stripe response shape
 * can evolve independently — only this class changes when either does.
 *
 * Rounding rule (per ticket): Stripe values are authoritative.
 * No local re-rounding. No rate-based recalculation. Raw Stripe cents are stored.
 */
class StripeTaxResponseMapper
{
    /**
     * Map a Stripe Tax Calculation response to TaxData.
     *
     * Stripe response shape relevant here:
     *   $calculation->tax_amount_exclusive           — tax on line items, smallest currency unit
     *   $calculation->shipping_cost->amount_tax      — tax on shipping (absent when no shipping)
     *   $calculation->tax_breakdown[0]->tax_rate_details->percentage_decimal
     *   $calculation->tax_breakdown[0]->jurisdiction->display_name
     *
     * @param object $calculation   Stripe \Stripe\Tax\Calculation object
     * @param int    $subtotalCents Subtotal passed to Stripe (used for taxableAmountCents)
     * @param int    $shippingCents Shipping passed to Stripe (used for taxableAmountCents)
     */
    public function map(
        object $calculation,
        int $subtotalCents,
        int $shippingCents
    ): TaxData {
        $lineTaxCents = (int)($calculation->tax_amount_exclusive ?? 0);

        $shippingTaxCents = (int)(
            $calculation->shipping_cost->amount_tax
            ?? 0
        );

        $totalTaxCents = (int)(
            $lineTaxCents + $shippingTaxCents
        );

        $taxableAmountCents = $subtotalCents;

        if ($shippingCents > 0) {
            $taxableAmountCents += $shippingCents;
        }

        $ratePercentage = 0.0;
        $jurisdiction = null;
        $includesShipping = $shippingCents > 0;

        $breakdown = $calculation->tax_breakdown ?? [];

        if (!empty($breakdown)) {
            $first = $breakdown[0];

            $ratePercentage = (float)(
                $first->tax_rate_details->percentage_decimal
                ?? 0
            );

            $jurisdiction =
                $first->jurisdiction->display_name
                ?? $first->jurisdiction->name
                ?? null;
        }

        $rate = round(
            $ratePercentage / 100,
            6
        );

        return new TaxData(
            rate: $rate,
            ratePercentage: $ratePercentage,
            jurisdiction: $jurisdiction,
            includesShipping: $includesShipping,
            taxCents: $totalTaxCents,
            taxableAmountCents: $taxableAmountCents,
            exempt: false
        );
    }

    /**
     * Build a zero-tax TaxData for unsupported countries or zero-rate jurisdictions.
     *
     * exempt is intentionally false.
     * A zero-rate jurisdiction (a country that levies no VAT/GST) is not the same
     * as an exempt customer. Conflating the two corrupts downstream analytics and
     * exemption reporting — a zero-rate jurisdiction should show rate=0/tax=0 with
     * exempt=false, while a genuinely exempt member should show exempt=true.
     */
    public function mapZeroTax(string $country): TaxData
    {
        return new TaxData(
            rate: 0,
            ratePercentage: 0,
            jurisdiction: null,
            includesShipping: false,
            taxCents: 0,
            exempt: false
        );
    }
}