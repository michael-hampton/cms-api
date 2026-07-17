<?php

namespace App\Tests\Unit\Services\Billing\Stripe;

use App\Models\Member;
use App\Models\Subscription;
use App\Repositories\Subscriptions\SubscriptionRepository;
use App\Services\Billing\Stripe\PaymentMethodSubscriptionUsageResolver;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\TestCase;
use Stripe\Service\CustomerService;
use Stripe\Service\SubscriptionService;
use Stripe\StripeClient;

class PaymentMethodSubscriptionUsageResolverTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    private function member(): Member
    {
        $member = Mockery::mock(Member::class)->makePartial();
        $member->id = 42;
        $member->stripe_customer_id = 'cus_123';

        return $member;
    }

    private function localSubscription(int $id, string $stripeId): Subscription
    {
        $subscription = Mockery::mock(Subscription::class)->makePartial();
        $subscription->id = $id;
        $subscription->stripe_subscription_id = $stripeId;
        $subscription->plan_name = 'Digital';
        $subscription->site_id = 1;

        return $subscription;
    }

    public function test_usage_groups_local_subscriptions_by_their_live_stripe_default_payment_method(): void
    {
        $member = $this->member();
        $repository = Mockery::mock(SubscriptionRepository::class);
        $repository->shouldReceive('getActiveStripeLinkedSubscriptionsForMember')
            ->once()->with(42)
            ->andReturn(collect_stub([
                $this->localSubscription(1, 'sub_a'),
                $this->localSubscription(2, 'sub_b'),
            ]));

        $customer = (object) ['invoice_settings' => (object) ['default_payment_method' => 'pm_default']];
        $customerService = Mockery::mock(CustomerService::class);
        $customerService->shouldReceive('retrieve')->once()->with('cus_123')->andReturn($customer);

        $subA = (object) ['id' => 'sub_a', 'default_payment_method' => 'pm_specific'];
        $subB = (object) ['id' => 'sub_b', 'default_payment_method' => null];
        $subscriptionService = Mockery::mock(SubscriptionService::class);
        $subscriptionService->shouldReceive('all')
            ->once()
            ->with(['customer' => 'cus_123', 'status' => 'all', 'limit' => 100])
            ->andReturn((object) ['data' => [$subA, $subB]]);

        $stripe = Mockery::mock(StripeClient::class);
        $stripe->customers = $customerService;
        $stripe->subscriptions = $subscriptionService;

        $resolver = new PaymentMethodSubscriptionUsageResolver($stripe, $repository);
        $usage = $resolver->usageByPaymentMethod($member);

        $this->assertSame(1, $usage['pm_specific']['count']);
        $this->assertSame(1, $usage['pm_default']['count']);
        $this->assertSame('sub_a', $usage['pm_specific']['subscriptions'][0]['stripe_subscription_id']);
        $this->assertSame('sub_b', $usage['pm_default']['subscriptions'][0]['stripe_subscription_id']);
    }

    public function test_usage_returns_empty_array_when_member_has_no_local_subscriptions(): void
    {
        $member = $this->member();
        $repository = Mockery::mock(SubscriptionRepository::class);
        $repository->shouldReceive('getActiveStripeLinkedSubscriptionsForMember')
            ->once()->with(42)
            ->andReturn(collect_stub([]));

        $stripe = Mockery::mock(StripeClient::class);
        // No Stripe calls should happen if there's nothing local to correlate.

        $resolver = new PaymentMethodSubscriptionUsageResolver($stripe, $repository);

        $this->assertSame([], $resolver->usageByPaymentMethod($member));
    }

    public function test_usage_fails_closed_to_empty_array_when_stripe_is_unreachable(): void
    {
        $member = $this->member();
        $repository = Mockery::mock(SubscriptionRepository::class);
        $repository->shouldReceive('getActiveStripeLinkedSubscriptionsForMember')
            ->once()->with(42)
            ->andReturn(collect_stub([$this->localSubscription(1, 'sub_a')]));

        $customerService = Mockery::mock(CustomerService::class);
        $customerService->shouldReceive('retrieve')->once()->andThrow(new \Exception('network error'));

        $stripe = Mockery::mock(StripeClient::class);
        $stripe->customers = $customerService;

        $resolver = new PaymentMethodSubscriptionUsageResolver($stripe, $repository);

        $this->assertSame([], $resolver->usageByPaymentMethod($member));
    }

    public function test_reassign_subscriptions_updates_each_stripe_subscription(): void
    {
        $repository = Mockery::mock(SubscriptionRepository::class);

        $subscriptionService = Mockery::mock(SubscriptionService::class);
        $subscriptionService->shouldReceive('update')->once()->with('sub_a', ['default_payment_method' => 'pm_new']);
        $subscriptionService->shouldReceive('update')->once()->with('sub_b', ['default_payment_method' => 'pm_new']);

        $stripe = Mockery::mock(StripeClient::class);
        $stripe->subscriptions = $subscriptionService;

        $resolver = new PaymentMethodSubscriptionUsageResolver($stripe, $repository);
        $resolver->reassignSubscriptions(['sub_a', 'sub_b'], 'pm_new');

        $this->addToAssertionCount(1); // Mockery expectations above are the real assertions.
    }
}

/**
 * Minimal stand-in for App\Framework\Support\Collection so this test file
 * doesn't need to know its exact construction API - it only needs
 * isEmpty()/foreach support, both of which iterator/array already provide.
 */
function collect_stub(array $items): \App\Framework\Support\Collection
{
    return new \App\Framework\Support\Collection($items);
}