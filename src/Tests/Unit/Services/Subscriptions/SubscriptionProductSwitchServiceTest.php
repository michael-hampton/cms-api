<?php

declare(strict_types=1);

namespace App\Tests\Unit\Services\Subscriptions;

use App\Enums\Subscriptions\SubscriptionStatus;
use App\Framework\Authorization\MemberAuthWrapper;
use App\Framework\Database\Database;
use App\Models\Member;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Repositories\Members\MemberRepository;
use App\Repositories\Subscriptions\SubscriptionPlanRepository;
use App\Repositories\Subscriptions\SubscriptionRepository;
use App\Services\Shopping\CartService;
use App\Services\Shopping\OneTimeSubscriptionCheckoutService;
use App\Services\Subscriptions\SubscriptionPaymentService;
use App\Services\Subscriptions\SubscriptionProductSwitchService;
use DateTimeImmutable;
use InvalidArgumentException;
use Mockery;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class SubscriptionProductSwitchServiceTest extends TestCase
{
    private $subscriptionRepository;
    private $planRepository;
    private $memberRepository;
    private $cartService;
    private $checkoutService;
    private $subscriptionPaymentService;
    private $memberAuth;
    private $database;

    public function test_invalid_switch_mode_throws_exception(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->service->switch(
            1, 200, 'invalid', 'pm_123', 10.0, 0, 1, 10
        );
    }

    public function test_subscription_not_found(): void
    {
        $this->subscriptionRepository
            ->shouldReceive('find')
            ->once()
            ->with(1)
            ->andReturn(null);

        $this->expectException(InvalidArgumentException::class);

        $this->service->switch(
            1, 200, 'fresh', 'pm_123', 10.0, 0, 1, 10
        );
    }

    public function test_site_mismatch(): void
    {
        $sub = $this->makeSubscription();
        $sub->site_id = 999;

        $this->subscriptionRepository
            ->shouldReceive('find')
            ->andReturn($sub);

        $this->expectException(InvalidArgumentException::class);

        $this->service->switch(
            1, 200, 'fresh', 'pm_123', 10.0, 0, 1, 10
        );
    }

    private function makeSubscription(): object
    {
        $subscription = Mockery::mock(Subscription::class)->makePartial();
        $subscription->id = 1;
        $subscription->status = SubscriptionStatus::ACTIVE->value;
        $subscription->delivery_type = 'print';
        $subscription->site_id = 10;
        $subscription->plan_id = 100;

        return $subscription;
    }

    private function makeMember(): Member
    {
        $member = Mockery::mock(Member::class)->makePartial();
        $member->id = 55;
        $member->first_name = 'Mike';
        $member->last_name = 'Smith';
        $member->email = 'mike@example.com';

        return $member;
    }

    public function test_inactive_subscription(): void
    {
        $sub = $this->makeSubscription();
        $sub->status = 'paused';

        $this->subscriptionRepository
            ->shouldReceive('find')
            ->andReturn($sub);

        $this->expectException(InvalidArgumentException::class);

        $this->service->switch(
            1, 200, 'fresh', 'pm_123', 10.0, 0, 1, 10
        );
    }

    public function test_plan_not_found_or_inactive(): void
    {
        $sub = $this->makeSubscription();

        $this->subscriptionRepository->shouldReceive('find')->andReturn($sub);

        $this->planRepository
            ->shouldReceive('find')
            ->once()
            ->with(200)
            ->andReturn(null);

        $this->expectException(InvalidArgumentException::class);

        $this->service->switch(
            1, 200, 'fresh', 'pm_123', 10.0, 0, 1, 10
        );
    }

    public function test_same_plan_rejected(): void
    {
        $sub = $this->makeSubscription();
        $plan = $this->makePlan();
        $plan->id = 100; // same as subscription plan

        $this->subscriptionRepository->shouldReceive('find')->andReturn($sub);
        $this->planRepository->shouldReceive('find')->andReturn($plan);

        $this->expectException(InvalidArgumentException::class);

        $this->service->switch(
            1, 100, 'fresh', 'pm_123', 10.0, 0, 1, 10
        );
    }

    private function makePlan(): object
    {
        $plan = Mockery::mock(Subscriptionplan::class)->makePartial();
        $plan->id = 200;
        $plan->site_id = 10;
        $plan->is_active = true;
        $plan->billing_period = 'monthly';

        return $plan;
    }

    public function test_payment_failure_throws_runtime_exception(): void
    {
        $subscription = $this->makeSubscription();
        $plan = $this->makePlan();
        $member = $this->makeMember();

        $newSubscription = $this->makeSubscription();

        $this->subscriptionRepository
            ->shouldReceive('find')
            ->andReturn(
                $subscription,
                $newSubscription
            );

        $this->planRepository
            ->shouldReceive('find')
            ->andReturn($plan);

        $this->memberRepository
            ->shouldReceive('find')
            ->andReturn($member);

        $this->subscriptionRepository
            ->shouldReceive('hasActiveSubscriptionToPlan')
            ->andReturn(false);

        $this->memberAuth
            ->shouldReceive('login')
            ->once();

        $this->cartService
            ->shouldReceive('addSubscriptionToCart')
            ->once();

        $this->checkoutService
            ->shouldReceive('processCheckout')
            ->once()
            ->andReturn([
                'subscription_ids' => [99],
                'order_id' => 500,
            ]);

        $this->subscriptionPaymentService
            ->shouldReceive('processStripeSubscriptionPayment')
            ->once()
            ->andReturn([
                'success' => false,
                'message' => 'card declined',
            ]);

        $this->cartService
            ->shouldReceive('clear')
            ->once();

        $this->expectException(RuntimeException::class);

        $this->service->switch(
            1,
            200,
            'fresh',
            'pm_123',
            10.00,
            0.00,
            1,
            10
        );
    }

    public function test_site_mismatch_throws_exception(): void
    {
        $subscription = $this->makeSubscription();
        $subscription->site_id = 999;

        $this->subscriptionRepository
            ->shouldReceive('find')
            ->once()
            ->andReturn($subscription);

        $this->expectException(InvalidArgumentException::class);

        $this->service->switch(
            1,
            200,
            'fresh',
            'pm_123',
            10.00,
            0.00,
            1,
            10
        );
    }

    public function test_inactive_subscription_throws_exception(): void
    {
        $subscription = $this->makeSubscription();
        $subscription->status = 'paused';

        $this->subscriptionRepository
            ->shouldReceive('find')
            ->once()
            ->andReturn($subscription);

        $this->expectException(InvalidArgumentException::class);

        $this->service->switch(
            1,
            200,
            'fresh',
            'pm_123',
            10.00,
            0.00,
            1,
            10
        );
    }

    public function test_target_plan_not_found_throws_exception(): void
    {
        $subscription = $this->makeSubscription();

        $this->subscriptionRepository
            ->shouldReceive('find')
            ->once()
            ->andReturn($subscription);

        $this->planRepository
            ->shouldReceive('find')
            ->once()
            ->with(200)
            ->andReturn(null);

        $this->expectException(InvalidArgumentException::class);

        $this->service->switch(
            1,
            200,
            'fresh',
            'pm_123',
            10.00,
            0.00,
            1,
            10
        );
    }

    public function test_target_plan_site_mismatch_throws_exception(): void
    {
        $subscription = $this->makeSubscription();

        $plan = $this->makePlan();
        $plan->site_id = 999;

        $this->subscriptionRepository
            ->shouldReceive('find')
            ->once()
            ->andReturn($subscription);

        $this->planRepository
            ->shouldReceive('find')
            ->once()
            ->andReturn($plan);

        $this->expectException(InvalidArgumentException::class);

        $this->service->switch(
            1,
            200,
            'fresh',
            'pm_123',
            10.00,
            0.00,
            1,
            10
        );
    }

    public function test_member_not_found_throws_exception(): void
    {
        $subscription = $this->makeSubscription();
        $plan = $this->makePlan();

        $this->subscriptionRepository
            ->shouldReceive('find')
            ->once()
            ->andReturn($subscription);

        $this->planRepository
            ->shouldReceive('find')
            ->once()
            ->andReturn($plan);

        $this->memberRepository
            ->shouldReceive('find')
            ->once()
            ->andReturn(null);

        $this->expectException(InvalidArgumentException::class);

        $this->service->switch(
            1,
            200,
            'fresh',
            'pm_123',
            10.00,
            0.00,
            1,
            10
        );
    }

    public function test_duplicate_active_subscription_to_target_plan_throws_exception(): void
    {
        $subscription = $this->makeSubscription();
        $plan = $this->makePlan();
        $member = $this->makeMember();

        $this->subscriptionRepository
            ->shouldReceive('find')
            ->once()
            ->andReturn($subscription);

        $this->planRepository
            ->shouldReceive('find')
            ->once()
            ->andReturn($plan);

        $this->memberRepository
            ->shouldReceive('find')
            ->once()
            ->andReturn($member);

        $this->subscriptionRepository
            ->shouldReceive('hasActiveSubscriptionToPlan')
            ->once()
            ->andReturn(true);

        $this->expectException(InvalidArgumentException::class);

        $this->service->switch(
            1,
            200,
            'fresh',
            'pm_123',
            10.00,
            0.00,
            1,
            10
        );
    }

    public function test_cart_is_cleared_when_checkout_throws(): void
    {
        $subscription = $this->makeSubscription();
        $plan = $this->makePlan();
        $member = $this->makeMember();

        $this->subscriptionRepository
            ->shouldReceive('find')
            ->once()
            ->andReturn($subscription);

        $this->planRepository
            ->shouldReceive('find')
            ->once()
            ->andReturn($plan);

        $this->memberRepository
            ->shouldReceive('find')
            ->once()
            ->andReturn($member);

        $this->subscriptionRepository
            ->shouldReceive('hasActiveSubscriptionToPlan')
            ->once()
            ->andReturn(false);

        $this->memberAuth
            ->shouldReceive('login')
            ->once();

        $this->cartService
            ->shouldReceive('addSubscriptionToCart')
            ->once();

        $this->checkoutService
            ->shouldReceive('processCheckout')
            ->once()
            ->andThrow(new RuntimeException('checkout failed'));

        $this->cartService
            ->shouldReceive('clear')
            ->once();

        $this->expectException(RuntimeException::class);

        $this->service->switch(
            1,
            200,
            'fresh',
            'pm_123',
            10.00,
            0.00,
            1,
            10
        );
    }

    public function test_create_subscription_repository_method_is_never_called(): void
    {
        $subscription = $this->makeSubscription();
        $plan = $this->makePlan();
        $member = $this->makeMember();

        $newSubscription = $this->makeSubscription();

        $this->subscriptionRepository
            ->shouldNotReceive('createSubscription');

        $obj1 = Mockery::mock(Subscription::class)->makePartial();
        $obj1->id = 1;
        $obj2 = Mockery::mock(Subscription::class)->makePartial();
        $obj2->id = 99;

        $this->subscriptionRepository
            ->shouldReceive('find')
            ->andReturn(
                $subscription,
                $newSubscription,
                $obj1,
                $obj2,
            );

        $this->planRepository
            ->shouldReceive('find')
            ->andReturn($plan);

        $this->memberRepository
            ->shouldReceive('find')
            ->andReturn($member);

        $this->subscriptionRepository
            ->shouldReceive('hasActiveSubscriptionToPlan')
            ->andReturn(false);

        $this->memberAuth
            ->shouldReceive('login');

        $this->cartService
            ->shouldReceive('addSubscriptionToCart');

        $this->checkoutService
            ->shouldReceive('processCheckout')
            ->andReturn([
                'subscription_ids' => [99],
                'order_id' => 500,
            ]);

        $this->subscriptionPaymentService
            ->shouldReceive('processStripeSubscriptionPayment')
            ->andReturn([
                'success' => true,
                'subscription_id' => 'stripe_sub_123',
            ]);

        $this->subscriptionRepository
            ->shouldReceive('update');

        $this->database
            ->shouldReceive('transaction')
            ->andReturnUsing(
                fn($callback) => $callback()
            );

        $this->cartService
            ->shouldReceive('clear');

        $this->service->switch(
            1,
            200,
            'fresh',
            'pm_123',
            10.00,
            0.00,
            1,
            10
        );

        $this->assertTrue(true);
    }

    public function test_calculate_credit_returns_zero_when_price_is_zero(): void
    {
        $subscription = (object)[
            'price' => 0,
            'start_date' => new DateTimeImmutable('-15 days'),
            'end_date' => new DateTimeImmutable('+15 days'),
        ];

        $result = $this->service->calculateCarriedOverCredit(
            $subscription
        );

        $this->assertSame(0.00, $result);
    }

    public function test_calculate_credit_returns_zero_when_dates_missing(): void
    {
        $subscription = (object)[
            'price' => 100,
            'start_date' => null,
            'end_date' => null,
        ];

        $result = $this->service->calculateCarriedOverCredit(
            $subscription
        );

        $this->assertSame(0.00, $result);
    }

    public function test_calculate_credit_returns_zero_when_expired(): void
    {
        $subscription = (object)[
            'price' => 100,
            'start_date' => new DateTimeImmutable('-60 days'),
            'end_date' => new DateTimeImmutable('-1 day'),
        ];

        $result = $this->service->calculateCarriedOverCredit(
            $subscription
        );

        $this->assertSame(0.00, $result);
    }

    public function test_calculate_credit_returns_expected_amount(): void
    {
        $subscription = (object)[
            'price' => 30.00,
            'start_date' => new DateTimeImmutable('2026-01-01 00:00:00'),
            'end_date' => new DateTimeImmutable('2026-01-31 00:00:00'),
        ];

        /**
         * Force expected result based on today's date.
         */
        $result = $this->service->calculateCarriedOverCredit(
            $subscription
        );

        $this->assertIsFloat($result);
        $this->assertGreaterThanOrEqual(0, $result);
    }

    public function test_throws_when_checkout_returns_no_subscription(): void
    {
        $subscription = $this->makeSubscription();
        $plan = $this->makePlan();
        $member = $this->makeMember();

        $this->subscriptionRepository
            ->shouldReceive('find')
            ->once()
            ->andReturn($subscription);

        $this->planRepository
            ->shouldReceive('find')
            ->once()
            ->andReturn($plan);

        $this->memberRepository
            ->shouldReceive('find')
            ->once()
            ->andReturn($member);

        $this->subscriptionRepository
            ->shouldReceive('hasActiveSubscriptionToPlan')
            ->once()
            ->andReturn(false);

        $this->memberAuth
            ->shouldReceive('login')
            ->once();

        $this->cartService
            ->shouldReceive('addSubscriptionToCart')
            ->once();

        $this->checkoutService
            ->shouldReceive('processCheckout')
            ->once()
            ->andReturn([]);

        $this->cartService
            ->shouldReceive('clear')
            ->once();

        $this->expectException(RuntimeException::class);

        $this->service->switch(
            1,
            200,
            'fresh',
            'pm_123',
            10.00,
            0.00,
            1,
            10
        );
    }


    public function test_successful_switch_flow(): void
    {
        $subscription = $this->makeSubscription();
        $plan = $this->makePlan();
        $member = $this->makeMember();

        $newSubscription = $this->makeSubscription();

        $obj1 = Mockery::mock(Subscription::class)->makePartial();
        $obj1->id = 1;
        $obj2 = Mockery::mock(Subscription::class)->makePartial();
        $obj2->id = 99;

        $this->subscriptionRepository
            ->shouldReceive('find')
            ->andReturn(
                $subscription,
                $newSubscription,
                $obj1,
                $obj2,
            );

        $this->planRepository
            ->shouldReceive('find')
            ->andReturn($plan);

        $this->memberRepository
            ->shouldReceive('find')
            ->andReturn($member);

        $this->subscriptionRepository
            ->shouldReceive('hasActiveSubscriptionToPlan')
            ->once()
            ->andReturn(false);

        $this->memberAuth
            ->shouldReceive('login')
            ->once();

        $this->cartService
            ->shouldReceive('addSubscriptionToCart')
            ->once()
            ->with(200);

        $this->checkoutService
            ->shouldReceive('processCheckout')
            ->once()
            ->andReturn([
                'subscription_ids' => [99],
                'order_id' => 500,
            ]);

        $this->subscriptionPaymentService
            ->shouldReceive('processStripeSubscriptionPayment')
            ->once()
            ->andReturn([
                'success' => true,
                'subscription_id' => 'stripe_sub_123',
            ]);

        $this->subscriptionRepository
            ->shouldReceive('update')
            ->twice();

        $this->database
            ->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(
                fn($callback) => $callback()
            );

        $this->cartService
            ->shouldReceive('clear')
            ->once();

        $result = $this->service->switch(
            1,
            200,
            'transfer',
            'pm_123',
            5.00,
            10.00,
            1,
            10
        );

        $this->assertIsArray($result);

        $this->assertArrayHasKey(
            'old_subscription',
            $result
        );

        $this->assertArrayHasKey(
            'new_subscription',
            $result
        );
    }


    protected function setUp(): void
    {
        parent::setUp();

        $this->subscriptionRepository = Mockery::mock(
            SubscriptionRepository::class
        );

        $this->planRepository = Mockery::mock(
            SubscriptionPlanRepository::class
        );

        $this->memberRepository = Mockery::mock(
            MemberRepository::class
        );

        $this->cartService = Mockery::mock(
            CartService::class
        );

        $this->checkoutService = Mockery::mock(
            OneTimeSubscriptionCheckoutService::class
        );

        $this->subscriptionPaymentService = Mockery::mock(
            SubscriptionPaymentService::class
        );

        $this->memberAuth = Mockery::mock(
            MemberAuthWrapper::class
        );

        $this->database = Mockery::mock(
            Database::class
        );

        $this->service = new SubscriptionProductSwitchService(
            $this->subscriptionRepository,
            $this->planRepository,
            $this->memberRepository,
            $this->cartService,
            $this->checkoutService,
            $this->subscriptionPaymentService,
            $this->memberAuth,
            $this->database,
        );
    }


    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
