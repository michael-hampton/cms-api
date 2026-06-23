<?php

namespace App\Tests\Unit\Services\Subscriptions;

use App\DTO\Subscriptions\SubscriptionPricing;
use App\Enums\CartItemType;
use App\Enums\Subscriptions\SubscriptionStatus;
use App\Models\Member;
use App\Models\Subscription;
use App\Services\Shopping\OneTimeSubscriptionService;
use App\Services\Subscriptions\MemberResolver;
use App\Services\Subscriptions\RenewalIssueSchedulingService;
use App\Services\Subscriptions\SubscriptionBatchFactory;
use App\Services\Subscriptions\SubscriptionPricingService;
use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\TestCase;

class SubscriptionBatchFactoryTest extends TestCase
{
    private OneTimeSubscriptionService&MockInterface $subscriptionService;
    private SubscriptionPricingService&MockInterface $pricingCalculator;
    private RenewalIssueSchedulingService&MockInterface $renewalIssueSchedulingService;
    private SubscriptionBatchFactory $factory;
    private MemberResolver&MockInterface $memberResolver;

    protected function setUp(): void
    {
        parent::setUp();

        $this->subscriptionService = Mockery::mock(OneTimeSubscriptionService::class);
        $this->pricingCalculator = Mockery::mock(SubscriptionPricingService::class);
        $this->memberResolver = Mockery::mock(MemberResolver::class);
        $this->renewalIssueSchedulingService = Mockery::mock(RenewalIssueSchedulingService::class);

        $this->factory = new SubscriptionBatchFactory(
            $this->subscriptionService,
            $this->pricingCalculator,
            $this->memberResolver,
            $this->renewalIssueSchedulingService,
        );
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_create_pending_subscriptions_returns_one_entry_per_cart_item(): void
    {
        $buyer = $this->makeMember();
        $pricing = $this->makePricing();
        $subscription = $this->makeSubscription();
        $item = $this->makeCartItem(1);

        $this->memberResolver->allows('resolve')->andReturn($buyer);
        $this->pricingCalculator->expects('calculateForCartItem')->andReturn($pricing);
        $this->subscriptionService->allows('createOneTimeSubscription')->andReturn($subscription);
        $this->renewalIssueSchedulingService->shouldNotReceive('scheduleForSubscription');

        $result = $this->factory->createPendingSubscriptions([$item], [], $buyer, 1, null);

        $this->assertCount(1, $result);
        $this->assertSame($subscription, $result[0]['subscription']);
        $this->assertSame($pricing, $result[0]['pricing']);
        $this->assertSame($pricing->totalCents, $result[0]['price_paid_cents']);
    }

    public function test_voucher_is_passed_to_first_non_bundle_item(): void
    {
        $buyer = $this->makeMember();
        $item = $this->makeCartItem(1);
        $pricing = $this->makePricing(voucherId: 123, discountCents: 1000, totalCents: 4000);
        $subscription = $this->makeSubscription();
        $checkoutData = ['voucher_code' => 'SAVE10'];

        $this->memberResolver->allows('resolve')->andReturn($buyer);
        $this->pricingCalculator
            ->expects('calculateForCartItem')
            ->with($item, 'SAVE10', $buyer, $checkoutData)
            ->andReturn($pricing);
        $this->subscriptionService
            ->expects('createOneTimeSubscription')
            ->with(
                $buyer->id,
                1,
                $pricing->deliveryType,
                1,
                123,
                Mockery::on(fn($passedPricing) => $passedPricing->discountCents === 1000),
                SubscriptionStatus::PENDING,
                null,
                null,
            )
            ->andReturn($subscription);
        $this->renewalIssueSchedulingService->shouldNotReceive('scheduleForSubscription');

        $result = $this->factory->createPendingSubscriptions([$item], $checkoutData, $buyer, 1, null);

        $this->assertSame(123, $result[0]['pricing']->voucherId);
        $this->assertSame(4000, $result[0]['price_paid_cents']);
    }

    public function test_voucher_is_not_passed_to_bundle_item(): void
    {
        $buyer = $this->makeMember();
        $bundleItem = $this->makeCartItem(1, ['type' => CartItemType::SUBSCRIPTION_BUNDLE->value]);
        $pricing = $this->makePricing();
        $subscription = $this->makeSubscription();
        $checkoutData = ['voucher_code' => 'SAVE10'];

        $this->memberResolver->allows('resolve')->andReturn($buyer);
        $this->pricingCalculator
            ->expects('calculateForCartItem')
            ->with($bundleItem, null, $buyer, $checkoutData)
            ->andReturn($pricing);
        $this->subscriptionService->allows('createOneTimeSubscription')->andReturn($subscription);
        $this->renewalIssueSchedulingService->shouldNotReceive('scheduleForSubscription');

        $result = $this->factory->createPendingSubscriptions([$bundleItem], $checkoutData, $buyer, 1, null);

        $this->assertCount(1, $result);
    }

    public function test_gift_item_uses_recipient_member_id_and_tracks_buyer(): void
    {
        $buyer = $this->makeMember(1);
        $recipient = $this->makeMember(99);
        $item = $this->makeCartItem(1);
        $pricing = $this->makePricing();
        $subscription = $this->makeSubscription();
        $checkoutData = [
            'is_gift' => '1',
            'recipient_email' => 'gift@example.com',
            'recipient_first_name' => 'Jane',
            'recipient_last_name' => 'Doe',
        ];

        $this->memberResolver
            ->expects('resolve')
            ->withArgs(function (array $itemData, Member $passedBuyer) use ($buyer) {
                return $passedBuyer->id === $buyer->id
                    && ($itemData['gift_email'] ?? null) === 'gift@example.com'
                    && ($itemData['gift_first_name'] ?? null) === 'Jane'
                    && ($itemData['gift_last_name'] ?? null) === 'Doe';
            })
            ->andReturn($recipient);
        $this->pricingCalculator
            ->expects('calculateForCartItem')
            ->with($item, null, $buyer, $checkoutData)
            ->andReturn($pricing);
        $this->subscriptionService
            ->expects('createOneTimeSubscription')
            ->with(
                $recipient->id,
                1,
                $pricing->deliveryType,
                1,
                null,
                $pricing,
                SubscriptionStatus::PENDING,
                null,
                $buyer->id,
            )
            ->andReturn($subscription);
        $this->renewalIssueSchedulingService->shouldNotReceive('scheduleForSubscription');

        $result = $this->factory->createPendingSubscriptions([$item], $checkoutData, $buyer, 1, null);

        $this->assertSame($subscription, $result[0]['subscription']);
    }

    public function test_start_date_is_passed_from_item_options_to_subscription_service(): void
    {
        $buyer = $this->makeMember();
        $item = $this->makeCartItem(1, ['start_date' => '2025-01-01']);
        $pricing = $this->makePricing();
        $subscription = $this->makeSubscription();

        $this->memberResolver->allows('resolve')->andReturn($buyer);
        $this->pricingCalculator->allows('calculateForCartItem')->andReturn($pricing);
        $this->subscriptionService
            ->expects('createOneTimeSubscription')
            ->with(
                $buyer->id,
                1,
                $pricing->deliveryType,
                1,
                null,
                $pricing,
                SubscriptionStatus::PENDING,
                '2025-01-01',
                null,
            )
            ->andReturn($subscription);
        $this->renewalIssueSchedulingService->shouldNotReceive('scheduleForSubscription');

        $result = $this->factory->createPendingSubscriptions([$item], [], $buyer, 1, null);

        $this->assertSame($subscription, $result[0]['subscription']);
    }

    public function test_metadata_from_cart_item_is_preserved(): void
    {
        $buyer = $this->makeMember();
        $item = array_merge($this->makeCartItem(1), [
            'is_pre_release' => true,
            'release_date' => '2025-06-01',
            'is_preorder' => true,
            'next_issue_id' => 42,
            'next_issue_title' => 'Summer Edition',
            'estimated_dispatch' => '2025-04-01',
        ]);
        $pricing = $this->makePricing();
        $subscription = $this->makeSubscription();

        $this->memberResolver->allows('resolve')->andReturn($buyer);
        $this->pricingCalculator->allows('calculateForCartItem')->andReturn($pricing);
        $this->subscriptionService->allows('createOneTimeSubscription')->andReturn($subscription);
        $this->renewalIssueSchedulingService->shouldNotReceive('scheduleForSubscription');

        $result = $this->factory->createPendingSubscriptions([$item], [], $buyer, 1, null);
        $meta = $result[0]['meta'];

        $this->assertTrue($meta['is_pre_release']);
        $this->assertTrue($meta['is_preorder']);
        $this->assertSame('2025-06-01', $meta['release_date']);
        $this->assertSame(42, $meta['next_issue_id']);
        $this->assertSame('Summer Edition', $meta['next_issue_title']);
        $this->assertSame('2025-04-01', $meta['estimated_dispatch']);
    }

    public function test_empty_cart_items_returns_empty_array(): void
    {
        $buyer = $this->makeMember();
        $this->renewalIssueSchedulingService->shouldNotReceive('scheduleForSubscription');

        $result = $this->factory->createPendingSubscriptions([], [], $buyer, 1, null);

        $this->assertSame([], $result);
    }

    private function makeMember(int $id = 1): Member&MockInterface
    {
        $member = Mockery::mock(Member::class)->makePartial();
        $member->id = $id;

        return $member;
    }

    private function makeSubscription(int $id = 1): Subscription&MockInterface
    {
        $sub = Mockery::mock(Subscription::class)->makePartial();
        $sub->id = $id;

        return $sub;
    }

    private function makePricing(
        ?int $voucherId = null,
        string $deliveryType = 'digital',
        int $subtotalCents = 5000,
        int $discountCents = 0,
        int $shippingCents = 0,
        int $taxCents = 0,
        int $totalCents = 5000,
    ): SubscriptionPricing {
        return new SubscriptionPricing(
            subtotalCents: $subtotalCents,
            discountCents: $discountCents,
            shippingCents: $shippingCents,
            taxCents: $taxCents,
            totalCents: $totalCents,
            deliveryType: $deliveryType,
            voucherId: $voucherId,
        );
    }

    private function makeCartItem(int $planId = 1, array $options = []): array
    {
        return [
            'subscription_plan_id' => $planId,
            'options' => $options,
        ];
    }
}
