<?php

namespace App\Tests\Unit\Factories\Stripe;

use App\DTO\Stripe\CreateStripeSubscriptionScheduleDto;
use App\Factories\Stripe\StripeSchedulePhaseFactory;
use PHPUnit\Framework\TestCase;

class StripeSchedulePhaseFactoryTest extends TestCase
{
    private StripeSchedulePhaseFactory $factory;

    protected function setUp(): void
    {
        parent::setUp();
        $this->factory = new StripeSchedulePhaseFactory();
    }

    public function test_builds_two_phases_for_intro_pricing(): void
    {
        $dto = $this->makeDto(introPriceId: 'price_intro_123', recurringPriceId: 'price_rec_456', introCycles: 1);

        $phases = $this->factory->buildPhases($dto);

        $this->assertCount(2, $phases);
    }

    public function test_intro_phase_uses_intro_price_and_correct_iterations(): void
    {
        $dto = $this->makeDto(introPriceId: 'price_intro_123', recurringPriceId: 'price_rec_456', introCycles: 3);

        $phases = $this->factory->buildPhases($dto);

        $introPhase = $phases[0];
        $this->assertSame('price_intro_123', $introPhase['items'][0]['price']);
        $this->assertSame(3, $introPhase['iterations']);
    }

    public function test_recurring_phase_uses_recurring_price_with_no_iterations(): void
    {
        $dto = $this->makeDto(introPriceId: 'price_intro_123', recurringPriceId: 'price_rec_456', introCycles: 1);

        $phases = $this->factory->buildPhases($dto);

        $recurringPhase = $phases[1];
        $this->assertSame('price_rec_456', $recurringPhase['items'][0]['price']);
        $this->assertArrayNotHasKey('iterations', $recurringPhase);
    }

    public function test_intro_phase_is_first_recurring_phase_is_second(): void
    {
        $dto = $this->makeDto(introPriceId: 'price_intro', recurringPriceId: 'price_rec', introCycles: 2);

        $phases = $this->factory->buildPhases($dto);

        $this->assertSame('price_intro', $phases[0]['items'][0]['price']);
        $this->assertSame('price_rec',   $phases[1]['items'][0]['price']);
    }

    public function test_intro_cycles_value_is_passed_through_correctly(): void
    {
        foreach ([1, 2, 6, 12] as $cycles) {
            $dto    = $this->makeDto('price_intro', 'price_rec', $cycles);
            $phases = $this->factory->buildPhases($dto);

            $this->assertSame($cycles, $phases[0]['iterations'], "Failed for {$cycles} cycles");
        }
    }

    // ── Helper ───────────────────────────────────────────────────────────────

    private function makeDto(
        string $introPriceId,
        string $recurringPriceId,
        int    $introCycles,
    ): CreateStripeSubscriptionScheduleDto {
        return new CreateStripeSubscriptionScheduleDto(
            stripeCustomerId:  'cus_test',
            introPriceId:      $introPriceId,
            recurringPriceId:  $recurringPriceId,
            introCycles:       $introCycles,
            subscriptionId:    1,
            planId:            1,
            memberId:          1,
            siteId:            1,
            trialDays:         null,
        );
    }
}