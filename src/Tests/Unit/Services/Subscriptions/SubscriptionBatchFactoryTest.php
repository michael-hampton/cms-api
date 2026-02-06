<?php

namespace App\Tests\Unit\Services\Subscriptions;

use App\DTO\Subscriptions\SubscriptionPricing;
use App\Enums\Subscriptions\SubscriptionStatus;
use App\Models\Member;
use App\Models\Subscription;
use App\Services\Shopping\OneTimeSubscriptionService;
use App\Services\Subscriptions\SubscriptionBatchFactory;
use App\Services\Subscriptions\SubscriptionPricingService;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\TestCase;

class SubscriptionBatchFactoryTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    private SubscriptionBatchFactory $factory;
    private $subscriptionService;
    private $pricingCalculator;

    public function test_create_pending_subscriptions_applies_voucher_once(): void
    {
        $member = $this->createMockMember();

        $cartItems = [
            ['subscription_plan_id' => 1, 'options' => ['delivery_type' => 'digital']],
            ['subscription_plan_id' => 2, 'options' => ['delivery_type' => 'digital']],
            ['subscription_plan_id' => 3, 'options' => ['delivery_type' => 'print']]
        ];

        $pricingWithVoucher = $this->createMockPricing(voucherId: 999);
        $pricingWithoutVoucher = $this->createMockPricing(voucherId: null);

        // First item gets voucher
        $this->pricingCalculator->shouldReceive('calculateForCartItem')
            ->once()
            ->with($cartItems[0], 'SAVE10', $member, Mockery::any())
            ->andReturn($pricingWithVoucher);

        // Subsequent items do NOT get voucher
        $this->pricingCalculator->shouldReceive('calculateForCartItem')
            ->once()
            ->with($cartItems[1], null, $member, Mockery::any())
            ->andReturn($pricingWithoutVoucher);

        $this->pricingCalculator->shouldReceive('calculateForCartItem')
            ->once()
            ->with($cartItems[2], null, $member, Mockery::any())
            ->andReturn($pricingWithoutVoucher);

        $this->subscriptionService->shouldReceive('createOneTimeSubscription')
            ->times(3)
            ->andReturn($this->createMockSubscription());

        $result = $this->factory->createPendingSubscriptions(
            $cartItems,
            ['voucher_code' => 'SAVE10'],
            $member,
            1
        );

        $this->assertCount(3, $result);
    }

    private function createMockMember(): Member
    {
        $member = Mockery::mock(Member::class)->makePartial();
        $member->id = 123;
        return $member;
    }

    private function createMockPricing(?int $voucherId = null): SubscriptionPricing
    {
        return new SubscriptionPricing(
            subtotalCents: 5000,
            discountCents: $voucherId ? 1000 : 0,
            shippingCents: 0,
            taxCents: 0,
            totalCents: 5000,
            deliveryType: 'digital',
            voucherId: $voucherId,
            shippingAddressSnapshot: null
        );
    }

    private function createMockSubscription(): Subscription
    {
        $subscription = Mockery::mock(Subscription::class)->makePartial();
        $subscription->id = 456;
        return $subscription;
    }

    public function test_create_pending_subscriptions_creates_in_pending_status(): void
    {
        $member = $this->createMockMember();
        $cartItems = [
            ['subscription_plan_id' => 1, 'options' => ['delivery_type' => 'digital']]
        ];

        $this->pricingCalculator->shouldReceive('calculateForCartItem')
            ->once()
            ->andReturn($this->createMockPricing());

        $this->subscriptionService->shouldReceive('createOneTimeSubscription')
            ->once()
            ->with(
                123,
                1,
                'digital',
                1,
                null,
                0,
                SubscriptionStatus::PENDING->value,
                null
            )
            ->andReturn($this->createMockSubscription());

        $result = $this->factory->createPendingSubscriptions($cartItems, [], $member, 1);

        $this->assertCount(1, $result);
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->subscriptionService = Mockery::mock(OneTimeSubscriptionService::class);
        $this->pricingCalculator = Mockery::mock(SubscriptionPricingService::class);

        $this->factory = new SubscriptionBatchFactory(
            $this->subscriptionService,
            $this->pricingCalculator
        );
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}