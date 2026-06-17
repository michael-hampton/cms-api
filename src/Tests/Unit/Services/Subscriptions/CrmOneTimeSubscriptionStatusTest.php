<?php

declare(strict_types=1);

namespace App\Tests\Unit\Services\Subscriptions;

use App\Framework\Authorization\MemberAuthWrapper;
use App\Models\Member;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Repositories\Members\AddressRepository;
use App\Repositories\Members\MemberRepository;
use App\Repositories\Subscriptions\SubscriptionPlanRepository;
use App\Repositories\Subscriptions\SubscriptionRepository;
use App\Services\Shopping\CartService;
use App\Services\Shopping\OneTimeSubscriptionCheckoutService;
use App\Services\Subscriptions\CrmSubscriptionCreationService;
use App\Services\Subscriptions\SubscriptionPaymentService;
use Mockery;
use PHPUnit\Framework\TestCase;

final class CrmOneTimeSubscriptionStatusTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_paid_one_time_subscription_is_activated_when_checkout_leaves_it_pending(): void
    {
        $members = Mockery::mock(MemberRepository::class);
        $plans = Mockery::mock(SubscriptionPlanRepository::class);
        $subscriptions = Mockery::mock(SubscriptionRepository::class);
        $cart = Mockery::mock(CartService::class);
        $checkout = Mockery::mock(OneTimeSubscriptionCheckoutService::class);
        $auth = Mockery::mock(MemberAuthWrapper::class);
        $payments = Mockery::mock(SubscriptionPaymentService::class);
        $addresses = Mockery::mock(AddressRepository::class);

        $member = Mockery::mock(Member::class)->makePartial();
        $member->id = 1;
        $member->site_id = 7;
        $member->first_name = 'Jane';
        $member->last_name = 'Doe';
        $member->email = 'jane@example.com';

        $plan = Mockery::mock(SubscriptionPlan::class)->makePartial();
        $plan->id = 5;
        $plan->site_id = 7;
        $plan->is_active = true;
        $plan->name = 'Annual Print';
        $plan->plan_type = 'onetime';
        $plan->shouldReceive('getDeliveryOptions')->once()->andReturn(['print']);

        $subscription = Mockery::mock(Subscription::class)->makePartial();
        $subscription->id = 42;
        $subscription->status = 'pending';

        $members->expects('find')->with(1)->andReturn($member);
        $plans->expects('find')->with(5)->andReturn($plan);
        $subscriptions->expects('hasActiveSubscriptionToPlan')->with(1, 5)->andReturnFalse();
        $subscriptions->expects('find')->with(42)->twice()->andReturn($subscription);
        $auth->expects('login')->with($member)->once();
        $cart->expects('addSubscriptionToCart')->once();
        $cart->expects('clear')->once();
        $checkout->expects('processCheckout')
            ->withArgs(fn(array $data, int $siteId) => $siteId === 7 && $data['one_time_subscription'] === true)
            ->andReturn(['subscription_id' => 42, 'order_id' => 9]);
        $payments->shouldNotReceive('processStripeSubscriptionPayment');
        $subscription->expects('update')->with(['status' => 'active'])->once();

        $result = (new CrmSubscriptionCreationService(
            $members,
            $plans,
            $subscriptions,
            $cart,
            $checkout,
            $auth,
            $payments,
            $addresses,
        ))->createSubscription(1, 5, 'pm_test', 7);

        self::assertTrue($result['success']);
    }
}
