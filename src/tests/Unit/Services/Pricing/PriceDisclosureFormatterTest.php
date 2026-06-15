<?php

namespace App\Tests\Unit\Services\Pricing;

use App\DTO\Pricing\PriceDisclosureContext;
use App\Services\Pricing\PriceDisclosureFormatter;
use App\Services\Pricing\PriceDisclosureTemplateResolver;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class PriceDisclosureFormatterTest extends TestCase
{
    private function formatter(): PriceDisclosureFormatter
    {
        return new PriceDisclosureFormatter(new PriceDisclosureTemplateResolver());
    }

    public function test_quantity_adjusted_annual_amount_is_disclosed(): void
    {
        $summary = $this->formatter()->format($this->context(
            itemAmountMinor: 19800,
            renewalAmountMinor: 19800,
            renewalPeriodLabel: 'per year',
        ));

        self::assertStringContainsString('198.00', $summary['main_line']);
        self::assertStringContainsString('per year', $summary['main_line']);
    }

    public function test_trial_with_valid_initial_charge_uses_start_charge_template(): void
    {
        $summary = $this->formatter()->format($this->context(
            initialChargeAmountMinor: 500,
            renewalAmountMinor: 2000,
            trialDays: 14,
            introCycles: 4,
            initialChargePeriodLabel: '4 weeks',
            renewalPeriodLabel: 'per month',
        ));

        self::assertStringContainsString('5.00', $summary['main_line']);
        self::assertStringContainsString('4 weeks', $summary['main_line']);
        self::assertStringContainsString('14 days', $summary['main_line']);
    }

    public function test_trial_without_initial_charge_uses_free_trial_template(): void
    {
        $summary = $this->formatter()->format($this->context(
            initialChargeAmountMinor: null,
            renewalAmountMinor: 2000,
            trialDays: 14,
            initialChargePeriodLabel: null,
        ));

        self::assertStringContainsString('14 days free trial', $summary['main_line']);
    }

    public function test_experience_override_wins_over_store_override(): void
    {
        $summary = $this->formatter()->format($this->context(
            experienceLanguageLines: [
                'en_GB' => ['subscription_without_trial' => 'Experience :item_price :renewal_period_label'],
            ],
            storeLanguageLines: [
                'en_GB' => ['subscription_without_trial' => 'Store :item_price :renewal_period_label'],
            ],
        ));

        self::assertStringStartsWith('Experience', $summary['main_line']);
    }

    public function test_store_override_is_used_when_experience_override_is_missing(): void
    {
        $summary = $this->formatter()->format($this->context(
            storeLanguageLines: [
                'en_GB' => ['subscription_without_trial' => 'Store :item_price :renewal_period_label'],
            ],
        ));

        self::assertStringStartsWith('Store', $summary['main_line']);
    }

    public function test_unknown_tokens_fall_back_without_leaking_placeholder(): void
    {
        $summary = $this->formatter()->format($this->context(
            experienceLanguageLines: [
                'en_GB' => ['subscription_without_trial' => 'Broken :unknown_token'],
            ],
        ));

        self::assertStringNotContainsString(':unknown_token', $summary['main_line']);
        self::assertStringContainsString('9.99', $summary['main_line']);
    }

    public function test_one_time_item_has_nullable_renewal_line(): void
    {
        $summary = $this->formatter()->format($this->context(isRecurring: false));

        self::assertNull($summary['renewal_line']);
    }

    private function context(
        int $itemAmountMinor = 999,
        ?int $initialChargeAmountMinor = null,
        ?int $renewalAmountMinor = 999,
        bool $isRecurring = true,
        ?int $trialDays = null,
        ?int $introCycles = null,
        ?string $initialChargePeriodLabel = null,
        ?string $renewalPeriodLabel = 'per month',
        array $experienceLanguageLines = [],
        array $storeLanguageLines = [],
    ): PriceDisclosureContext {
        return new PriceDisclosureContext(
            locale: 'en_GB',
            currency: 'GBP',
            quantity: 1,
            itemAmountMinor: $itemAmountMinor,
            initialChargeAmountMinor: $initialChargeAmountMinor,
            renewalAmountMinor: $renewalAmountMinor,
            isRecurring: $isRecurring,
            trialDays: $trialDays,
            introCycles: $introCycles,
            initialChargePeriodLabel: $initialChargePeriodLabel,
            introPeriodLabel: $initialChargePeriodLabel,
            renewalPeriodLabel: $renewalPeriodLabel,
            renewalDate: new DateTimeImmutable('2026-07-01 00:00:00 UTC'),
            experienceLanguageLines: $experienceLanguageLines,
            storeLanguageLines: $storeLanguageLines,
        );
    }
}
