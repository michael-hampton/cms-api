<?php

declare(strict_types=1);

namespace App\Tests\Unit\Services\Subscriptions\Communications;

use App\Models\Subscription;
use App\Models\SubscriptionCommunication;
use App\Models\SubscriptionPricingChange;
use App\Repositories\Subscriptions\SubscriptionCommunicationRepository;
use App\Services\Subscriptions\Communications\SubscriptionCommunicationSender;
use App\Services\Subscriptions\Communications\SubscriptionItdCommunicationService;
use App\Tests\Functional\Controllers\FunctionalTestCase;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;

class SubscriptionItdCommunicationServiceTest extends FunctionalTestCase
{
    use MockeryPHPUnitIntegration;

    private SubscriptionCommunicationRepository $communications;
    private SubscriptionCommunicationSender $sender;
    private SubscriptionItdCommunicationService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->communications = Mockery::mock(SubscriptionCommunicationRepository::class);
        $this->sender = Mockery::mock(SubscriptionCommunicationSender::class);

        $this->service = new SubscriptionItdCommunicationService(
            $this->communications,
            $this->sender,
        );
    }

    public function test_it_throws_when_active_itd_communication_is_missing(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/Active ITD communication/');

        $pricingChange = $this->makePricingChange();
        $oldSubscription = $this->makeSubscription(id: 100, planId: 10, price: 9.99);
        $newSubscription = $this->makeSubscription(id: 101, planId: 10, price: 12.99);

        $this->communications
            ->shouldReceive('findActiveByKey')
            ->once()
            ->with('itd_price_rise_default')
            ->andReturn(null);

        $this->sender
            ->shouldReceive('send')
            ->never();

        $this->service->generateForPriceRise(
            pricingChange: $pricingChange,
            oldSubscription: $oldSubscription,
            newSubscription: $newSubscription,
            transitionId: 500,
            letterCode: 'ITD_DD_PRICE_RISE',
        );
    }

    public function test_it_sends_itd_price_rise_communication_with_metadata_and_dedupe_key(): void
    {
        $pricingChange = $this->makePricingChange();
        $oldSubscription = $this->makeSubscription(id: 100, planId: 10, price: 9.99);
        $newSubscription = $this->makeSubscription(id: 101, planId: 10, price: 12.99);
        $communication = $this->makeCommunication(id: 55);

        $this->communications
            ->shouldReceive('findActiveByKey')
            ->once()
            ->with('itd_price_rise_default')
            ->andReturn($communication);

        $this->sender
            ->shouldReceive('send')
            ->once()
            ->with(
                $newSubscription,
                $communication,
                null,
                Mockery::on(function (array $metadata): bool {
                    return $metadata['letter_code'] === 'ITD_DD_PRICE_RISE'
                        && $metadata['pricing_change_id'] === 77
                        && $metadata['transition_id'] === 500
                        && $metadata['old_subscription_id'] === 100
                        && $metadata['new_subscription_id'] === 101
                        && $metadata['old_plan_id'] === 10
                        && $metadata['new_plan_id'] === 10
                        && $metadata['old_price'] === 9.99
                        && $metadata['new_price'] === 12.99
                        && $metadata['currency'] === 'GBP'
                        && $metadata['effective_date'] === '2026-08-01';
                }),
                'pricing-change:77:transition:500:itd'
            );

        $this->service->generateForPriceRise(
            pricingChange: $pricingChange,
            oldSubscription: $oldSubscription,
            newSubscription: $newSubscription,
            transitionId: 500,
            letterCode: 'ITD_DD_PRICE_RISE',
        );
    }

    private function makePricingChange(): SubscriptionPricingChange
    {
        $change = Mockery::mock(SubscriptionPricingChange::class)->makePartial();
        $change->id = 77;
        $change->old_price = 9.99;
        $change->new_price = 12.99;
        $change->currency = 'GBP';
        $change->effective_date = new \DateTime('2026-08-01');

        return $change;
    }

    private function makeSubscription(
        int $id,
        int $planId,
        float $price
    ): Subscription {
        $subscription = Mockery::mock(Subscription::class)->makePartial();

        $subscription->id = $id;
        $subscription->plan_id = $planId;
        $subscription->price = $price;
        $subscription->currency = 'GBP';

        return $subscription;
    }

    private function makeCommunication(int $id): SubscriptionCommunication
    {
        $communication = Mockery::mock(SubscriptionCommunication::class)->makePartial();
        $communication->id = $id;
        $communication->key = 'itd_price_rise_default';
        $communication->type = 'itd';

        return $communication;
    }
}