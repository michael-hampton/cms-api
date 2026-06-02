<?php

declare(strict_types=1);

namespace App\Tests\Unit\Services\Subscriptions;

use App\Framework\Database\Database;
use App\Framework\Support\Collection;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\SubscriptionPlanPricing;
use App\Repositories\Subscriptions\SubscriptionPlanPricingRepository;
use App\Repositories\Subscriptions\SubscriptionPlanRepository;
use App\Repositories\Subscriptions\SubscriptionRepository;
use App\Services\Subscriptions\Calculators\SubscriptionDateCalculator;
use App\Services\Subscriptions\SubscriptionPaymentService;
use App\Services\Subscriptions\SubscriptionRenewalService;
use App\Services\Subscriptions\SubscriptionRenewalTracker;
use DateTimeImmutable;
use InvalidArgumentException;
use Mockery;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class SubscriptionRenewalServiceTest extends TestCase
{
    private $subscriptionRepository;
    private $planRepository;
    private $pricingRepository;
    private $subscriptionPaymentService;
    private $dateCalculator;
    private $renewalTracker;
    private $database;

    private SubscriptionRenewalService $service;

    // ── renew() — validation ──────────────────────────────────────────────────

    public function test_subscription_not_found(): void
    {
        $this->subscriptionRepository
            ->shouldReceive('find')
            ->once()
            ->with(1)
            ->andReturn(null);

        $this->expectException(InvalidArgumentException::class);

        $this->service->renew(1, 200, 'pm_123', 10.0, 1, 10, 300, 'print');
    }

    public function test_site_mismatch(): void
    {
        $sub = $this->makeSubscription();
        $sub->site_id = 999;

        $this->subscriptionRepository->shouldReceive('find')->andReturn($sub);

        $this->expectException(InvalidArgumentException::class);

        $this->service->renew(1, 200, 'pm_123', 10.0, 1, 10, 300, 'print');
    }

    public function test_non_renewable_status_rejected(): void
    {
        $sub = $this->makeSubscription('replaced');

        $this->subscriptionRepository
            ->shouldReceive('find')
            ->once()
            ->andReturn($sub);

        $this->subscriptionPaymentService
            ->shouldReceive('processStripeSubscriptionPayment')
            ->never();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(
            "Subscription cannot be renewed from status: replaced."
        );

        $this->service->renew(1, 200, 'pm_123', 10.0, 1, 10, 300, 'print');
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

        $this->service->renew(1, 200, 'pm_123', 10.0, 1, 10, 300, 'print');
    }

    // ── renew() — agent path (paymentMethodId provided) ──────────────────────

    public function test_payment_failure_throws_runtime_exception(): void
    {
        $sub  = $this->makeSubscription();
        $plan = $this->makePlan();

        $this->subscriptionRepository->shouldReceive('find')->andReturn($sub);
        $this->planRepository->shouldReceive('find')->andReturn($plan);
        $this->pricingRepository->shouldReceive('find')->with(300)->andReturn($this->makePricingTier());

        $this->subscriptionPaymentService
            ->shouldReceive('processStripeSubscriptionPayment')
            ->once()
            ->andReturn(['success' => false, 'message' => 'card declined']);

        $this->expectException(RuntimeException::class);

        $this->service->renew(1, 200, 'pm_123', 9.99, 1, 10, 300, 'print');
    }

    public function test_successful_renewal_flow(): void
    {
        $sub  = $this->makeSubscription();
        $plan = $this->makePlan();

        $this->subscriptionRepository->shouldReceive('find')->andReturn($sub);
        $this->planRepository->shouldReceive('find')->andReturn($plan);
        $this->pricingRepository->shouldReceive('find')->with(300)->andReturn($this->makePricingTier());

        $this->subscriptionPaymentService
            ->shouldReceive('processStripeSubscriptionPayment')
            ->once()
            ->andReturn(['success' => true, 'subscription_id' => 'stripe_sub_999']);

        $mockModel = $this->makeModel();

        $this->database
            ->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(fn($cb) => $cb());

        $this->dateCalculator
            ->shouldReceive('calculateEndDate')
            ->andReturn(new DateTimeImmutable('+1 month'));

        $this->subscriptionRepository->shouldReceive('update')->twice()->andReturn($mockModel);
        $this->subscriptionRepository->shouldReceive('createSubscription')->once()->andReturn($mockModel);
        $this->renewalTracker->shouldReceive('recordRenewalReplacement')->once()->with($sub, $mockModel);
        $this->subscriptionRepository->shouldReceive('find')->andReturn($mockModel);

        $result = $this->service->renew(1, 200, 'pm_123', 9.99, 1, 10, 300, 'print');

        $this->assertIsArray($result);
        $this->assertArrayHasKey('old_subscription', $result);
        $this->assertArrayHasKey('new_subscription', $result);
    }

    public function test_renewal_uses_plan_price_when_pricing_tier_is_missing(): void
    {
        $sub  = $this->makeSubscription();
        $plan = $this->makePlan();
        $plan->price = 12.34;

        $this->subscriptionRepository->shouldReceive('find')->andReturn($sub);
        $this->planRepository->shouldReceive('find')->andReturn($plan);
        $this->pricingRepository->shouldReceive('find')->never();

        $this->subscriptionPaymentService
            ->shouldReceive('processStripeSubscriptionPayment')
            ->once()
            ->withArgs(fn($subscription, $resolvedPlan, array $data): bool =>
                $subscription === $sub
                && $resolvedPlan === $plan
                && $data['amount'] === 12.34
            )
            ->andReturn(['success' => true]);

        $mockModel = $this->makeModel();

        $this->database
            ->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(fn($cb) => $cb());

        $this->dateCalculator
            ->shouldReceive('calculateEndDate')
            ->andReturn(new DateTimeImmutable('+1 month'));

        $this->subscriptionRepository->shouldReceive('update')->twice()->andReturn($mockModel);
        $this->subscriptionRepository
            ->shouldReceive('createSubscription')
            ->once()
            ->withArgs(fn(
                int $memberId,
                int $planId,
                int $siteId,
                array $additionalData,
            ): bool =>
                $additionalData['price'] === 12.34
                && $additionalData['price_paid_cents'] === 1234
                && $additionalData['subscription_plan_pricing_id'] === null
            )
            ->andReturn($mockModel);
        $this->renewalTracker->shouldReceive('recordRenewalReplacement')->once()->with($sub, $mockModel);
        $this->subscriptionRepository->shouldReceive('find')->andReturn($mockModel);

        $result = $this->service->renew(1, 200, 'pm_123', null, 1, 10);

        $this->assertArrayHasKey('new_subscription', $result);
    }

    // ── renew() — automated path (paymentMethodId null) ───────────────────────

    public function test_automated_renewal_skips_stripe_charge(): void
    {
        $sub  = $this->makeSubscription();
        $plan = $this->makePlan();

        $this->subscriptionRepository->shouldReceive('find')->andReturn($sub);
        $this->planRepository->shouldReceive('find')->andReturn($plan);
        $this->pricingRepository->shouldReceive('find')->with(300)->andReturn($this->makePricingTier());

        // No Stripe charge must be attempted in the automated path
        $this->subscriptionPaymentService
            ->shouldReceive('processStripeSubscriptionPayment')
            ->never();

        $mockModel = $this->makeModel();

        $this->database
            ->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(fn($cb) => $cb());

        $this->dateCalculator
            ->shouldReceive('calculateEndDate')
            ->andReturn(new DateTimeImmutable('+1 month'));

        $this->subscriptionRepository->shouldReceive('update')->twice()->andReturn($mockModel);
        $this->subscriptionRepository->shouldReceive('createSubscription')->once()->andReturn($mockModel);
        $this->renewalTracker->shouldReceive('recordRenewalReplacement')->once()->with($sub, $mockModel);
        $this->subscriptionRepository->shouldReceive('find')->andReturn($mockModel);

        $result = $this->service->renew(
            subscriptionId:  1,
            planId:          200,
            paymentMethodId: null,   // automated path
            amountPaid:      9.99,
            agentId:         null,   // no acting agent
            siteId:          10,
            pricingId:       300,
            offerType:       'print',
        );

        $this->assertArrayHasKey('old_subscription', $result);
        $this->assertArrayHasKey('new_subscription', $result);
    }

    public function test_automated_renewal_still_validates_subscription_status(): void
    {
        $sub = $this->makeSubscription('replaced');

        $this->subscriptionRepository->shouldReceive('find')->once()->andReturn($sub);

        $this->subscriptionPaymentService
            ->shouldReceive('processStripeSubscriptionPayment')
            ->never();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(
            "Subscription cannot be renewed from status: replaced."
        );

        $this->service->renew(1, 200, null, 10.0, null, 10, 300, 'print');
    }

    public function test_tracks_renewal_when_subscription_renews(): void
    {
        $sub = $this->makeSubscription();
        $sub->renewal_count = 2;
        $sub->first_renewed_at = new DateTimeImmutable('2026-02-01 10:00:00');

        $plan = $this->makePlan();
        $newSubscription = $this->makeModel();

        $this->subscriptionRepository->shouldReceive('find')->andReturn($sub, $sub);
        $this->planRepository->shouldReceive('find')->andReturn($plan);
        $this->pricingRepository->shouldReceive('find')->with(300)->andReturn($this->makePricingTier());
        $this->subscriptionPaymentService
            ->shouldReceive('processStripeSubscriptionPayment')
            ->once()
            ->andReturn(['success' => true]);

        $this->database
            ->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(fn($cb) => $cb());

        $this->dateCalculator
            ->shouldReceive('calculateEndDate')
            ->andReturn(new DateTimeImmutable('+1 month'));

        $this->subscriptionRepository->shouldReceive('update')->twice()->andReturn($newSubscription);
        $this->subscriptionRepository
            ->shouldReceive('createSubscription')
            ->once()
            ->withArgs(function (
                int $memberId,
                int $planId,
                int $siteId,
                array $additionalData,
            ): bool {
                return $additionalData['renewal_count'] === 2
                    && $additionalData['first_renewed_at'] === '2026-02-01 10:00:00';
            })
            ->andReturn($newSubscription);

        $this->renewalTracker
            ->shouldReceive('recordRenewalReplacement')
            ->once()
            ->with($sub, $newSubscription);

        $result = $this->service->renew(1, 200, 'pm_123', 9.99, 1, 10, 300, 'print');

        $this->assertSame($newSubscription, $result['new_subscription']);
    }

    // ── processRenewals() ─────────────────────────────────────────────────────

    public function test_process_renewals_returns_zero_counts_when_nothing_due(): void
    {
        $this->subscriptionRepository
            ->shouldReceive('findAllDueForRenewal')
            ->once()
            ->andReturn(new Collection([]));

        $result = $this->service->processRenewals();

        $this->assertSame(0, $result['processed']);
        $this->assertSame(0, $result['successful']);
        $this->assertSame(0, $result['failed']);
        $this->assertSame([], $result['errors']);
    }

    public function test_process_renewals_renews_each_due_subscription(): void
    {
        $service = Mockery::mock(SubscriptionRenewalService::class, [
            $this->subscriptionRepository,
            $this->planRepository,
            $this->pricingRepository,
            $this->subscriptionPaymentService,
            $this->dateCalculator,
            $this->renewalTracker,
            $this->database,
        ])->makePartial();

        $sub1 = $this->makeSubscription();
        $sub1->id      = 1;
        $sub1->plan_id = 100;
        $sub1->site_id = 10;
        $sub1->price   = 9.99;

        $sub2 = $this->makeSubscription();
        $sub2->id      = 2;
        $sub2->plan_id = 101;
        $sub2->site_id = 10;
        $sub2->price   = 19.99;

        $this->subscriptionRepository
            ->shouldReceive('findAllDueForRenewal')
            ->once()
            ->andReturn(new Collection([$sub1, $sub2]));

        $renewResult = ['old_subscription' => new \stdClass(), 'new_subscription' => new \stdClass()];

        $service
            ->shouldReceive('renew')
            ->twice()
            ->andReturn($renewResult);

        $result = $service->processRenewals();

        $this->assertSame(2, $result['processed']);
        $this->assertSame(2, $result['successful']);
        $this->assertSame(0, $result['failed']);
        $this->assertSame([], $result['errors']);
    }

    public function test_process_renewals_continues_after_single_failure(): void
    {
        $service = Mockery::mock(SubscriptionRenewalService::class, [
            $this->subscriptionRepository,
            $this->planRepository,
            $this->pricingRepository,
            $this->subscriptionPaymentService,
            $this->dateCalculator,
            $this->renewalTracker,
            $this->database,
        ])->makePartial();

        $failing = $this->makeSubscription();
        $failing->id      = 10;
        $failing->plan_id = 100;
        $failing->site_id = 10;
        $failing->price   = 9.99;

        $passing = $this->makeSubscription();
        $passing->id      = 11;
        $passing->plan_id = 101;
        $passing->site_id = 10;
        $passing->price   = 19.99;

        $this->subscriptionRepository
            ->shouldReceive('findAllDueForRenewal')
            ->once()
            ->andReturn(new Collection([$failing, $passing]));

        $renewResult = ['old_subscription' => new \stdClass(), 'new_subscription' => new \stdClass()];

        $service
            ->shouldReceive('renew')
            ->twice()
            ->andReturnUsing(function (int $subscriptionId) use ($renewResult) {
                if ($subscriptionId === 10) {
                    throw new \RuntimeException('Payment failed');
                }
                return $renewResult;
            });

        $result = $service->processRenewals();

        $this->assertSame(2, $result['processed']);
        $this->assertSame(1, $result['successful']);
        $this->assertSame(1, $result['failed']);
        $this->assertCount(1, $result['errors']);
        $this->assertStringContainsString('10', $result['errors'][0]);
    }

    public function test_process_renewals_collects_all_errors_without_aborting(): void
    {
        $sub1 = $this->makeSubscription('replaced');
        $sub1->id      = 20;
        $sub1->plan_id = 100;
        $sub1->price   = 9.99;

        $sub2 = $this->makeSubscription('replaced');
        $sub2->id      = 21;
        $sub2->plan_id = 101;
        $sub2->price   = 9.99;

        $this->subscriptionRepository
            ->shouldReceive('findAllDueForRenewal')
            ->once()
            ->andReturn(new Collection([$sub1, $sub2]));

        // Both have non-renewable status — planRepository is never reached
        $this->planRepository->shouldReceive('find')->never();
        $this->database->shouldReceive('transaction')->never();

        $result = $this->service->processRenewals();

        $this->assertSame(2, $result['processed']);
        $this->assertSame(0, $result['successful']);
        $this->assertSame(2, $result['failed']);
        $this->assertCount(2, $result['errors']);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function makeSubscription(string $status = 'active'): Subscription
    {
        $subscription = Mockery::mock(Subscription::class)->makePartial();
        $subscription->id            = 1;
        $subscription->status        = $status;
        $subscription->delivery_type = 'print';
        $subscription->site_id       = 10;
        $subscription->plan_id       = 100;

        return $subscription;
    }

    private function makePlan(): SubscriptionPlan
    {
        $plan = Mockery::mock(SubscriptionPlan::class)->makePartial();
        $plan->id             = 200;
        $plan->site_id        = 10;
        $plan->is_active      = true;
        $plan->billing_period = 'monthly';

        return $plan;
    }

    private function makePricingTier(): SubscriptionPlanPricing
    {
        $pricingTier = Mockery::mock(SubscriptionPlanPricing::class)->makePartial();
        $pricingTier->id = 300;
        $pricingTier->plan_id = 200;
        $pricingTier->is_active = true;
        $pricingTier->price = 9.99;
        $pricingTier->sale_price = null;

        return $pricingTier;
    }

    private function makeModel(): Subscription
    {
        $model = Mockery::mock(Subscription::class)->makePartial();
        $model->id = 1;

        return $model;
    }

    // ── Lifecycle ─────────────────────────────────────────────────────────────

    protected function setUp(): void
    {
        parent::setUp();

        $this->subscriptionRepository     = Mockery::mock(SubscriptionRepository::class);
        $this->planRepository             = Mockery::mock(SubscriptionPlanRepository::class);
        $this->pricingRepository          = Mockery::mock(SubscriptionPlanPricingRepository::class);
        $this->subscriptionPaymentService = Mockery::mock(SubscriptionPaymentService::class);
        $this->dateCalculator             = Mockery::mock(SubscriptionDateCalculator::class);
        $this->renewalTracker             = Mockery::mock(SubscriptionRenewalTracker::class);
        $this->database                   = Mockery::mock(Database::class);

        $this->service = new SubscriptionRenewalService(
            $this->subscriptionRepository,
            $this->planRepository,
            $this->pricingRepository,
            $this->subscriptionPaymentService,
            $this->dateCalculator,
            $this->renewalTracker,
            $this->database,
        );
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
