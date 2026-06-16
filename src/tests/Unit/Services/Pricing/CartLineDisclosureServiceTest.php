<?php

namespace App\Tests\Unit\Services\Pricing;

use App\Contracts\ClockInterface;
use App\Services\Pricing\CartLineDisclosureService;
use App\Services\Pricing\PeriodLabelFormatter;
use App\Services\Pricing\PriceDisclosureFormatter;
use App\Services\Pricing\PriceDisclosureTemplateResolver;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class CartLineDisclosureServiceTest extends TestCase
{
    public function test_disclosure_output_is_rebuilt_after_cart_amount_mutation(): void
    {
        $service = $this->service();
        $item = [
            'subscription_plan_id' => 10,
            'quantity' => 1,
            'amount_minor' => 2200,
            'options' => [],
        ];

        $first = $service->enrich(
            item: $item,
            planFacts: ['billing_period' => 'yearly', 'is_one_time' => false],
            locale: 'en_GB',
            currency: 'GBP',
            pricingFacts: ['price' => 22.00],
        );

        $item['amount_minor'] = 1800;

        $second = $service->enrich(
            item: $item,
            planFacts: ['billing_period' => 'yearly', 'is_one_time' => false],
            locale: 'en_GB',
            currency: 'GBP',
            pricingFacts: ['price' => 18.00],
        );

        self::assertStringContainsString('22.00', $first['line_summary']['main_line']);
        self::assertStringContainsString('18.00', $second['line_summary']['main_line']);
        self::assertNotSame($first['line_summary'], $second['line_summary']);
    }

    public function test_trial_and_intro_window_are_both_included_in_renewal_date(): void
    {
        $result = $this->service()->enrich(
            item: [
                'subscription_plan_id' => 10,
                'quantity' => 1,
                'amount_minor' => 2200,
                'options' => [],
            ],
            planFacts: ['billing_period' => 'monthly', 'is_one_time' => false],
            locale: 'en_GB',
            currency: 'GBP',
            pricingFacts: [
                'price' => 22.00,
                'intro_price' => 8.99,
                'intro_cycles' => 1,
                'trial_days' => 14,
                'period_description' => '4 weeks',
            ],
        );

        self::assertStringContainsString('8.99', $result['line_summary']['main_line']);
        self::assertStringContainsString('4 weeks', $result['line_summary']['main_line']);
        self::assertStringContainsString('27 Jul 2026', $result['line_summary']['renewal_line']);
        self::assertStringContainsString('22.00', $result['line_summary']['renewal_line']);
    }

    public function test_numeric_month_period_drives_label_and_renewal_date(): void
    {
        $result = $this->service()->enrich(
            item: [
                'subscription_plan_id' => 10,
                'quantity' => 1,
                'amount_minor' => 2200,
                'options' => [],
            ],
            planFacts: ['billing_period' => 'monthly', 'is_one_time' => false],
            locale: 'en_GB',
            currency: 'GBP',
            pricingFacts: [
                'price' => 22.00,
                'period_description' => '2 months',
            ],
        );

        self::assertStringContainsString('every 2 months', $result['line_summary']['main_line']);
        self::assertStringContainsString('15 Aug 2026', $result['line_summary']['renewal_line']);
    }

    public function test_twenty_eight_day_period_can_render_compact_equivalent(): void
    {
        $result = $this->service()->enrich(
            item: [
                'subscription_plan_id' => 10,
                'quantity' => 1,
                'amount_minor' => 2200,
                'options' => [],
            ],
            planFacts: ['billing_period' => 'monthly', 'is_one_time' => false],
            locale: 'en_GB',
            currency: 'GBP',
            experienceLanguageLines: [
                'en_GB' => [
                    'subscription_without_trial' => ':numeric_period_label / :raw_period_label',
                ],
            ],
            pricingFacts: [
                'price' => 22.00,
                'period_description' => '28 days',
                'period_display_strategy' => 'compact_equivalent',
            ],
        );

        self::assertSame('4 weeks / 28 days', $result['line_summary']['main_line']);
        self::assertStringContainsString('13 Jul 2026', $result['line_summary']['renewal_line']);
    }

    public function test_invalid_optional_context_values_fall_back_without_throwing(): void
    {
        $result = $this->service()->enrich(
            item: [
                'subscription_plan_id' => 10,
                'quantity' => 1,
                'amount_minor' => 999,
                'options' => [],
            ],
            planFacts: ['billing_period' => '', 'is_one_time' => false],
            locale: 'invalid_LOCALE',
            currency: 'ZZZ',
            experienceLanguageLines: [
                'invalid_LOCALE' => ['subscription_without_trial' => 'Broken :missing_token'],
            ],
            pricingFacts: [
                'trial_days' => 'invalid',
                'intro_cycles' => 0,
            ],
        );

        self::assertArrayHasKey('line_summary', $result);
        self::assertStringNotContainsString(':missing_token', $result['line_summary']['main_line']);
        self::assertStringContainsString('ZZZ 9.99', $result['line_summary']['main_line']);
    }

    private function service(): CartLineDisclosureService
    {
        return new CartLineDisclosureService(
            new PriceDisclosureFormatter(new PriceDisclosureTemplateResolver()),
            new PeriodLabelFormatter(),
            new class implements ClockInterface {
                public function now(): DateTimeImmutable
                {
                    return new DateTimeImmutable('2026-06-15 12:00:00 UTC');
                }
            },
        );
    }
}
