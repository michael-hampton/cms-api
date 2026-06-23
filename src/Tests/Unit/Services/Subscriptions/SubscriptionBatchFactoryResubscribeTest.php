<?php

namespace App\Tests\Unit\Services\Subscriptions;

use App\DTO\Subscriptions\SubscriptionPricing;
use App\Enums\Subscriptions\SubscriptionStatus;
use App\Models\Member;
use App\Models\Subscription;
use App\Repositories\Subscriptions\SubscriptionRepository;
use App\Services\Shopping\OneTimeSubscriptionService;
use App\Services\Subscriptions\MemberResolver;
use App\Services\Subscriptions\RenewalIssueSchedulingService;
use App\Services\Subscriptions\SubscriptionBatchFactory;
use App\Services\Subscriptions\SubscriptionPricingService;
use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\TestCase;

final class SubscriptionBatchFactoryResubscribeTest extends TestCase
{
    private OneTimeSubscriptionService&MockInterface $subscriptionService;
    private SubscriptionPricingService&MockInterface $pricingCalculator;
    private MemberResolver&MockInterface $memberResolver;
    private SubscriptionRepository&MockInterface $subscriptionRepository;
    private RenewalIssueSchedulingService&MockInterface $renewalIssueSchedulingService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->subscriptionService = Mockery::mock(OneTimeSubscriptionService::class);
        $this->pricingCalculator = Mockery::mock(SubscriptionPricingService::class);
        $this->memberResolver = Mockery::mock(MemberResolver::class);
        $this->subscriptionRepository = Mockery::mock(SubscriptionRepository::class);
        $this->renewalIssueSchedulingService = Mockery::mock(RenewalIssueSchedulingService::class);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_it_tags_new_subscription_with_source_when_resubscribing_same_member_site_and_plan(): void
    {
        $buyer = $this->member(10);
        $source = $this->subscription(88, 10, 5, 123);
        $newSubscription = $this->subscription(99, 10, 5, 123);
        $pricing = $this->pricing();
        $item = ['subscription_plan_id' => 123, 'options' => []];
        $checkoutData = ['resubscribe_from_subscription_id' => 88];

        $this->memberResolver->expects('resolve')->andReturn($buyer);
        $this->pricingCalculator
            ->expects('calculateForCartItem')
            ->with($item, null, $buyer, $checkoutData)
            ->andReturn($pricing);
        $this->subscriptionService
            ->expects('createOneTimeSubscription')
            ->with(
                $buyer->id,
                123,
                $pricing->deliveryType,
                5,
                null,
                $pricing,
                SubscriptionStatus::PENDING,
                null,
                null,
            )
            ->andReturn($newSubscription);
        $this->subscriptionRepository->expects('find')->with(88)->andReturn($source);
        $newSubscription
            ->expects('update')
            ->with([
                'renewed_from_subscription_id' => 88,
                'replacement_reason' => 'resubscribe',
            ]);
        $this->renewalIssueSchedulingService
            ->expects('scheduleForSubscription')
            ->with($newSubscription)
            ->andReturn([
                'created' => 1,
                'existing' => 0,
                'skipped' => 0,
            ]);

        $factory = $this->factory();
        $result = $factory->createPendingSubscriptions([$item], $checkoutData, $buyer, 5, null);

        self::assertSame($newSubscription, $result[0]['subscription']);
    }

    public function test_it_does_not_tag_new_subscription_when_source_belongs_to_another_member(): void
    {
        $buyer = $this->member(10);
        $source = $this->subscription(88, 999, 5, 123);
        $newSubscription = $this->subscription(99, 10, 5, 123);
        $pricing = $this->pricing();
        $item = ['subscription_plan_id' => 123, 'options' => []];
        $checkoutData = ['resubscribe_from_subscription_id' => 88];

        $this->memberResolver->allows('resolve')->andReturn($buyer);
        $this->pricingCalculator->allows('calculateForCartItem')->andReturn($pricing);
        $this->subscriptionService->allows('createOneTimeSubscription')->andReturn($newSubscription);
        $this->subscriptionRepository->expects('find')->with(88)->andReturn($source);
        $newSubscription->shouldNotReceive('update');
        $this->renewalIssueSchedulingService->shouldNotReceive('scheduleForSubscription');

        $this->factory()->createPendingSubscriptions([$item], $checkoutData, $buyer, 5, null);

        $this->assertTrue(true);
    }

    public function test_it_does_not_lookup_source_subscription_for_gift_checkout(): void
    {
        $buyer = $this->member(10);
        $recipient = $this->member(20);
        $newSubscription = $this->subscription(99, 20, 5, 123);
        $pricing = $this->pricing();
        $item = ['subscription_plan_id' => 123, 'options' => []];
        $checkoutData = [
            'is_gift' => '1',
            'recipient_email' => 'gift@example.com',
            'resubscribe_from_subscription_id' => 88,
        ];

        $this->memberResolver->allows('resolve')->andReturn($recipient);
        $this->pricingCalculator->allows('calculateForCartItem')->andReturn($pricing);
        $this->subscriptionService->allows('createOneTimeSubscription')->andReturn($newSubscription);
        $this->subscriptionRepository->shouldNotReceive('find');
        $newSubscription->shouldNotReceive('update');
        $this->renewalIssueSchedulingService->shouldNotReceive('scheduleForSubscription');

        $this->factory()->createPendingSubscriptions([$item], $checkoutData, $buyer, 5, null);

        $this->assertTrue(true);
    }

    private function factory(): SubscriptionBatchFactory
    {
        return new SubscriptionBatchFactory(
            $this->subscriptionService,
            $this->pricingCalculator,
            $this->memberResolver,
            $this->renewalIssueSchedulingService,
            $this->subscriptionRepository,
        );
    }

    private function member(int $id): Member&MockInterface
    {
        $member = Mockery::mock(Member::class)->makePartial();
        $member->id = $id;

        return $member;
    }

    private function subscription(int $id, int $memberId, int $siteId, int $planId): Subscription&MockInterface
    {
        $subscription = Mockery::mock(Subscription::class)->makePartial();
        $subscription->id = $id;
        $subscription->member_id = $memberId;
        $subscription->site_id = $siteId;
        $subscription->plan_id = $planId;

        return $subscription;
    }

    private function pricing(): SubscriptionPricing
    {
        return new SubscriptionPricing(
            subtotalCents: 5000,
            discountCents: 0,
            shippingCents: 0,
            taxCents: 0,
            totalCents: 5000,
            deliveryType: 'digital',
            voucherId: null,
        );
    }
}
