<?php

declare(strict_types=1);

namespace App\Tests\Unit\Services\Subscriptions;

use App\Framework\Database\Database;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Repositories\Subscriptions\SubscriptionPlanRepository;
use App\Repositories\Subscriptions\SubscriptionRepository;
use App\Services\Billing\PaymentProviders\StripePaymentProcessor;
use App\Services\Subscriptions\Calculators\SubscriptionDateCalculator;
use App\Services\Subscriptions\SubscriptionRenewalService;
use Mockery;
use PHPUnit\Framework\TestCase;

class SubscriptionRenewalServiceTest extends TestCase
{
    private $subscriptionRepository;
    private $planRepository;
    private $stripeProcessor;
    private $dateCalculator;
    private $database;

    private SubscriptionRenewalService $service;

    public function test_subscription_not_found(): void
    {
        $this->subscriptionRepository
            ->shouldReceive('find')
            ->once()
            ->with(1)
            ->andReturn(null);

        $this->expectException(\InvalidArgumentException::class);

        $this->service->renew(1, 200, 'pm_123', 10.0, 1, 10);
    }

    public function test_site_mismatch(): void
    {
        $sub = $this->makeSubscription();
        $sub->site_id = 999;

        $this->subscriptionRepository->shouldReceive('find')->andReturn($sub);

        $this->expectException(\InvalidArgumentException::class);

        $this->service->renew(1, 200, 'pm_123', 10.0, 1, 10);
    }

    private function makeSubscription(string $status = 'active'): object
    {
        $subscription = Mockery::mock(Subscription::class)->makePartial();
        $subscription->id = 1;
        $subscription->status = $status;
        $subscription->delivery_type = 'print';
        $subscription->site_id = 10;
        $subscription->plan_id = 100;

        return $subscription;
    }

    public function test_non_renewable_status_rejected(): void
    {
        $sub = $this->makeSubscription('replaced');

        $this->subscriptionRepository
            ->shouldReceive('find')
            ->once()
            ->andReturn($sub);

        // 🔥 IMPORTANT: prevent Stripe from interfering with validation test
        $this->stripeProcessor
            ->shouldReceive('processSubscriptionPayment')
            ->never();

        $this->expectException(\InvalidArgumentException::class);

        $this->expectExceptionMessage(
            "Subscription cannot be renewed from status: replaced."
        );

        $this->service->renew(
            1,
            200,
            'pm_123',
            10.0,
            1,
            10
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

        $this->service->renew(1, 200, 'pm_123', 10.0, 1, 10);
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

        $this->service->renew(1, 200, 'pm_123', 10.0, 1, 10);
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

    public function test_successful_renewal_flow(): void
    {
        $sub = $this->makeSubscription();
        $plan = $this->makePlan();

        $this->subscriptionRepository
            ->shouldReceive('find')
            ->andReturn($sub);

        $this->planRepository
            ->shouldReceive('find')
            ->andReturn($plan);

        $this->stripeProcessor
            ->shouldReceive('processSubscriptionPayment')
            ->once()
            ->andReturn([
                'success' => true,
                'subscription_id' => 'stripe_sub_999',
            ]);

        $this->database
            ->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(function ($callback) {
                return $callback();
            });

        $this->dateCalculator
            ->shouldReceive('calculateEndDate')
            ->andReturn(new \DateTimeImmutable('+1 month'));

        $mockModel = Mockery::mock(\App\Models\Model::class)->makePartial();
        $mockModel->id = 1;

        $this->subscriptionRepository
            ->shouldReceive('update')
            ->twice()
            ->andReturn($mockModel);

        $this->subscriptionRepository
            ->shouldReceive('createSubscription')
            ->once()
            ->andReturn($mockModel);

        $this->subscriptionRepository
            ->shouldReceive('find')
            ->andReturn($mockModel);

        $result = $this->service->renew(
            1,
            200,
            'pm_123',
            10.0,
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

        $this->service = new SubscriptionRenewalService(
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