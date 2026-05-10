<?php

declare(strict_types=1);

namespace App\Tests\Unit\Services\Subscriptions;

use App\Enums\Subscriptions\SubscriptionStatus;
use App\Framework\Database\Database;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Repositories\Subscriptions\SubscriptionPlanRepository;
use App\Repositories\Subscriptions\SubscriptionRepository;
use App\Services\Billing\PaymentProviders\StripePaymentProcessor;
use App\Services\Subscriptions\Calculators\SubscriptionDateCalculator;
use App\Services\Subscriptions\SubscriptionProductSwitchService;
use Mockery;
use PHPUnit\Framework\TestCase;

class SubscriptionProductSwitchServiceTest extends TestCase
{
    private $subscriptionRepository;
    private $planRepository;
    private $stripeProcessor;
    private $dateCalculator;
    private $database;

    private SubscriptionProductSwitchService $service;

    public function test_invalid_switch_mode_throws_exception(): void
    {
        $this->expectException(\InvalidArgumentException::class);

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

        $this->expectException(\InvalidArgumentException::class);

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

        $this->expectException(\InvalidArgumentException::class);

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

    public function test_inactive_subscription(): void
    {
        $sub = $this->makeSubscription();
        $sub->status = 'paused';

        $this->subscriptionRepository
            ->shouldReceive('find')
            ->andReturn($sub);

        $this->expectException(\InvalidArgumentException::class);

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

        $this->expectException(\InvalidArgumentException::class);

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

        $this->expectException(\InvalidArgumentException::class);

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
        $sub = $this->makeSubscription();
        $plan = $this->makePlan();

        $this->subscriptionRepository->shouldReceive('find')->andReturn($sub);
        $this->planRepository->shouldReceive('find')->andReturn($plan);

        $this->stripeProcessor
            ->shouldReceive('processSubscriptionPayment')
            ->once()
            ->andReturn([
                'success' => false,
                'message' => 'card declined',
            ]);

        $this->expectException(\RuntimeException::class);

        $this->service->switch(
            1, 200, 'fresh', 'pm_123', 10.0, 0, 1, 10
        );
    }

    public function test_successful_switch_flow(): void
    {
        $sub = $this->makeSubscription();
        $plan = $this->makePlan();

        $this->subscriptionRepository->shouldReceive('find')->andReturn($sub);
        $this->planRepository->shouldReceive('find')->andReturn($plan);

        $this->stripeProcessor
            ->shouldReceive('processSubscriptionPayment')
            ->once()
            ->andReturn([
                'success' => true,
                'subscription_id' => 'stripe_sub_123',
            ]);

        // transaction wrapper executes callback immediately
        $this->database
            ->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(function ($callback) {
                return $callback();
            });

        $this->dateCalculator
            ->shouldReceive('calculateEndDate')
            ->andReturn(new \DateTimeImmutable('+1 month'));

        $this->subscriptionRepository
            ->shouldReceive('update')
            ->twice()
            ->andReturn($sub);

        $newSub = Mockery::mock(Subscription::class)->makePartial();
        $newSub->id = 99;
        $newSub->member_id = 55;

        $this->subscriptionRepository
            ->shouldReceive('createSubscription')
            ->once()
            ->andReturn($newSub);

        $this->subscriptionRepository
            ->shouldReceive('find')
            ->andReturn((object)['id' => 1]);

        $result = $this->service->switch(
            1,
            200,
            'fresh',
            'pm_123',
            10.0,
            0.0,
            1,
            10
        );

        $this->assertIsArray($result);
        $this->assertArrayHasKey('old_subscription', $result);
        $this->assertArrayHasKey('new_subscription', $result);
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->subscriptionRepository = Mockery::mock(SubscriptionRepository::class);
        $this->planRepository = Mockery::mock(SubscriptionPlanRepository::class);
        $this->stripeProcessor = Mockery::mock(StripePaymentProcessor::class);
        $this->dateCalculator = Mockery::mock(SubscriptionDateCalculator::class);
        $this->database = Mockery::mock(Database::class);

        $this->service = new SubscriptionProductSwitchService(
            $this->subscriptionRepository,
            $this->planRepository,
            $this->stripeProcessor,
            $this->dateCalculator,
            $this->database,
        );
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}