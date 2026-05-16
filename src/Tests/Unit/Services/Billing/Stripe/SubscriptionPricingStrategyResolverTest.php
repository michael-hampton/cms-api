<?php

namespace App\Tests\Unit\Services\Billing\Stripe;

use App\Models\SubscriptionPlanPricing;
use App\Services\Billing\Stripe\SubscriptionPricingStrategyResolver;
use Mockery as m;
use PHPUnit\Framework\TestCase;

class SubscriptionPricingStrategyResolverTest extends TestCase
{
    private SubscriptionPricingStrategyResolver $resolver;

    protected function setUp(): void
    {
        parent::setUp();
        $this->resolver = new SubscriptionPricingStrategyResolver();
    }

    protected function tearDown(): void
    {
        m::close();
        parent::tearDown();
    }

    // ── Standard (no trial, no intro) ────────────────────────────────────────

    public function test_standard_pricing_when_no_trial_and_no_intro(): void
    {
        $pricing = $this->makePricing(trialDays: null, introPrice: null, introCycles: null);

        $result = $this->resolver->resolve($pricing);

        $this->assertFalse($result->hasTrial);
        $this->assertNull($result->trialDays);
        $this->assertFalse($result->hasIntroPricing);
        $this->assertNull($result->introPrice);
        $this->assertNull($result->introCycles);
        $this->assertTrue($result->isStandard());
    }

    // ── Trial only ───────────────────────────────────────────────────────────

    public function test_trial_pricing_when_trial_days_set_and_no_intro(): void
    {
        $pricing = $this->makePricing(trialDays: 14, introPrice: null, introCycles: null);

        $result = $this->resolver->resolve($pricing);

        $this->assertTrue($result->hasTrial);
        $this->assertSame(14, $result->trialDays);
        $this->assertFalse($result->hasIntroPricing);
        $this->assertFalse($result->isStandard());
    }

    // ── Intro pricing only ───────────────────────────────────────────────────

    public function test_intro_pricing_when_intro_price_and_cycles_set_with_no_trial(): void
    {
        $pricing = $this->makePricing(trialDays: null, introPrice: 1.00, introCycles: 1);

        $result = $this->resolver->resolve($pricing);

        $this->assertFalse($result->hasTrial);
        $this->assertTrue($result->hasIntroPricing);
        $this->assertSame(1.00, $result->introPrice);
        $this->assertSame(1, $result->introCycles);
        $this->assertFalse($result->isStandard());
    }

    // ── Trial + intro ─────────────────────────────────────────────────────────

    public function test_both_trial_and_intro_pricing_when_all_fields_set(): void
    {
        $pricing = $this->makePricing(trialDays: 7, introPrice: 0.99, introCycles: 3);

        $result = $this->resolver->resolve($pricing);

        $this->assertTrue($result->hasTrial);
        $this->assertSame(7, $result->trialDays);
        $this->assertTrue($result->hasIntroPricing);
        $this->assertSame(0.99, $result->introPrice);
        $this->assertSame(3, $result->introCycles);
        $this->assertFalse($result->isStandard());
    }

    // ── Edge cases ───────────────────────────────────────────────────────────

    public function test_no_intro_pricing_when_intro_price_set_but_cycles_null(): void
    {
        // Represents a partially-saved or invalid tier — cycles missing means
        // the intro phase is not actionable; resolver must not treat it as intro.
        $pricing = $this->makePricing(trialDays: null, introPrice: 1.00, introCycles: null);

        $result = $this->resolver->resolve($pricing);

        $this->assertFalse($result->hasIntroPricing);
    }

    public function test_no_intro_pricing_when_cycles_set_but_intro_price_null(): void
    {
        $pricing = $this->makePricing(trialDays: null, introPrice: null, introCycles: 2);

        $result = $this->resolver->resolve($pricing);

        $this->assertFalse($result->hasIntroPricing);
    }

    public function test_no_trial_when_trial_days_is_zero(): void
    {
        // trial_days = 0 is treated the same as null — no trial
        $pricing = $this->makePricing(trialDays: 0, introPrice: null, introCycles: null);

        $result = $this->resolver->resolve($pricing);

        $this->assertFalse($result->hasTrial);
        $this->assertTrue($result->isStandard());
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    private function makePricing(
        ?int   $trialDays,
        ?float $introPrice,
        ?int   $introCycles,
    ): SubscriptionPlanPricing {
        $pricing = m::mock(SubscriptionPlanPricing::class)->makePartial();

        $pricing->trial_days   = $trialDays;
        $pricing->intro_price  = $introPrice;
        $pricing->intro_cycles = $introCycles;

        // Wire hasTrial() and hasIntroPricing() to the real model methods
        // by delegating to the actual field values via makePartial()
        $pricing->shouldReceive('hasTrial')
            ->andReturnUsing(fn () => $trialDays !== null && $trialDays > 0);

        $pricing->shouldReceive('hasIntroPricing')
            ->andReturnUsing(fn () => $introPrice !== null && $introCycles !== null && $introCycles > 0);

        return $pricing;
    }
}