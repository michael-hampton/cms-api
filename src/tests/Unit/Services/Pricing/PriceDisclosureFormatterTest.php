<?php

namespace App\Tests\Unit\Services\Pricing;

use App\DTO\Pricing\PriceDisclosureContext;
use App\Services\Pricing\PriceDisclosureFormatter;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class PriceDisclosureFormatterTest extends TestCase
{
    public function test_it_multiplies_the_disclosed_amount_by_line_quantity(): void
    {
        $summary = (new PriceDisclosureFormatter())->format(new PriceDisclosureContext(
            locale: 'en_GB',
            currency: 'GBP',
            quantity: 2,
            unitAmountMinor: 499,
            billingPeriod: 'monthly',
        ));

        self::assertStringContainsString('9.98', $summary['main_line']);
        self::assertStringContainsString('per month', $summary['main_line']);
    }

    public function test_it_formats_trial_and_renewal_disclosures(): void
    {
        $summary = (new PriceDisclosureFormatter())->format(new PriceDisclosureContext(
            locale: 'en_GB',
            currency: 'GBP',
            quantity: 1,
            unitAmountMinor: 1200,
            billingPeriod: 'yearly',
            trialDays: 14,
            renewalDate: new DateTimeImmutable('2026-07-01'),
        ));

        self::assertStringContainsString('14-day free trial', $summary['main_line']);
        self::assertStringContainsString('per year', $summary['main_line']);
        self::assertStringContainsString('1 Jul 2026', $summary['renewal_line']);
    }

    public function test_experience_copy_overrides_are_applied_to_resolved_tokens(): void
    {
        $summary = (new PriceDisclosureFormatter())->format(new PriceDisclosureContext(
            locale: 'en_GB',
            currency: 'GBP',
            quantity: 1,
            unitAmountMinor: 999,
            billingPeriod: 'quarterly',
            copyOverrides: [
                'recurring' => 'Pay {amount}, billed {period}',
                'renewal_without_date' => 'Future payments are {amount} {period}',
            ],
        ));

        self::assertStringContainsString('Pay', $summary['main_line']);
        self::assertStringContainsString('per 3 months', $summary['main_line']);
        self::assertStringContainsString('Future payments', $summary['renewal_line']);
    }

    public function test_one_time_lines_have_no_renewal_copy(): void
    {
        $summary = (new PriceDisclosureFormatter())->format(new PriceDisclosureContext(
            locale: 'en_GB',
            currency: 'GBP',
            quantity: 1,
            unitAmountMinor: 2500,
            billingPeriod: 'monthly',
            isRecurring: false,
        ));

        self::assertNull($summary['renewal_line']);
    }
}
