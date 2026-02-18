<?php

namespace App\Tests\Unit\Services\Subscriptions;

use App\DTO\Subscriptions\SubscriptionPricing;
use App\Enums\Subscriptions\SubscriptionStatus;
use App\Enums\Subscriptions\SubscriptionType;
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
            ['subscription_plan_id' => 1, 'options' => ['delivery_type' => SubscriptionType::DIGITAL->value]],
            ['subscription_plan_id' => 2, 'options' => ['delivery_type' => SubscriptionType::DIGITAL->value]],
            ['subscription_plan_id' => 3, 'options' => ['delivery_type' => SubscriptionType::PRINTED->value]]
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
            1,
            null
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
            deliveryType: SubscriptionType::DIGITAL->value,
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
            ['subscription_plan_id' => 1, 'options' => ['delivery_type' => SubscriptionType::DIGITAL->value]]
        ];

        $pricing = $this->createMockPricing();

        $this->pricingCalculator->shouldReceive('calculateForCartItem')
            ->once()
            ->andReturn($pricing);

        $this->subscriptionService->shouldReceive('createOneTimeSubscription')
            ->once()
            ->with(
                123,
                1,
                SubscriptionType::DIGITAL->value,
                1,
                null,
                $pricing,
                SubscriptionStatus::PENDING,
                null

            )
            ->andReturn($this->createMockSubscription());

        $result = $this->factory->createPendingSubscriptions($cartItems, [], $member, 1, null);

        $this->assertCount(1, $result);
        $this->assertArrayHasKey('subscription', $result[0]);
        $this->assertArrayHasKey('pricing', $result[0]);
        $this->assertArrayHasKey('meta', $result[0]);
    }

    public function test_create_pending_subscriptions_populates_meta_from_cart_item(): void
    {
        $member = $this->createMockMember();
        $cartItems = [[
            'subscription_plan_id' => 1,
            'options' => ['delivery_type' => SubscriptionType::DIGITAL->value],
            'is_pre_release' => true,
            'release_date' => '2025-06-01',
            'is_preorder' => false,
            'next_issue_id' => 42,
            'next_issue_number' => 7,
            'next_issue_title' => 'Summer Edition',
            'next_issue_on_sale_date' => '2025-05-15',
            'availability_message' => 'Available now',
            'estimated_dispatch' => '2025-04-01',
            'estimated_delivery_from' => '2025-04-03',
            'estimated_delivery_to' => '2025-04-05',
            'estimated_delivery_formatted' => '3–5 Apr',
            'expected_ship_date' => null,
        ]];

        $pricing = $this->createMockPricing();

        $this->pricingCalculator->shouldReceive('calculateForCartItem')
            ->once()
            ->andReturn($pricing);

        $this->subscriptionService->shouldReceive('createOneTimeSubscription')
            ->once()
            ->andReturn($this->createMockSubscription());

        $result = $this->factory->createPendingSubscriptions($cartItems, [], $member, 1, null);

        $meta = $result[0]['meta'];
        $this->assertTrue($meta['is_pre_release']);
        $this->assertEquals('2025-06-01', $meta['release_date']);
        $this->assertEquals(42, $meta['next_issue_id']);
        $this->assertEquals('Summer Edition', $meta['next_issue_title']);
        $this->assertEquals('3–5 Apr', $meta['estimated_delivery_formatted']);
    }

    public function test_create_pending_subscriptions_defaults_meta_keys_to_null(): void
    {
        $member = $this->createMockMember();
        $cartItems = [[
            'subscription_plan_id' => 1,
            'options' => ['delivery_type' => SubscriptionType::DIGITAL->value],
            // No meta fields provided
        ]];

        $this->pricingCalculator->shouldReceive('calculateForCartItem')
            ->once()
            ->andReturn($this->createMockPricing());

        $this->subscriptionService->shouldReceive('createOneTimeSubscription')
            ->once()
            ->andReturn($this->createMockSubscription());

        $result = $this->factory->createPendingSubscriptions($cartItems, [], $member, 1, null);

        $meta = $result[0]['meta'];
        $this->assertNull($meta['is_pre_release']);
        $this->assertNull($meta['next_issue_id']);
        $this->assertNull($meta['estimated_dispatch']);
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