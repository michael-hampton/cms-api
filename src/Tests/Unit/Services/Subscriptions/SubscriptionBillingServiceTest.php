<?php

namespace App\Tests\Unit\Services\Subscriptions;

use App\DTO\Stripe\CreateStripeSubscriptionDto;
use App\DTO\Stripe\CreateStripeSubscriptionScheduleDto;
use App\DTO\Stripe\StripeSubscriptionResultDto;
use App\DTO\Subscriptions\SubscriptionPricingStrategyData;
use App\Enums\Subscriptions\SubscriptionStrategyType;
use App\Framework\Database\Database;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\SubscriptionPlanPricing;
use App\Repositories\Subscriptions\SubscriptionRepository;
use App\Services\Billing\Stripe\Contracts\StripeSubscriptionGatewayInterface;
use App\Services\Billing\Stripe\Contracts\StripeSubscriptionScheduleGatewayInterface;
use App\Services\Billing\Stripe\StripeSubscriptionBillingCycleService;
use App\Services\Billing\Stripe\StripeSubscriptionGateway;
use App\Services\Billing\Stripe\StripeSubscriptionScheduleGateway;
use App\Services\Billing\Stripe\SubscriptionPricingStrategyResolver;
use App\Services\Subscriptions\SubscriptionBillingService;
use App\Tests\Functional\Controllers\FunctionalTestCase;
use Mockery as m;

class SubscriptionBillingServiceTest extends FunctionalTestCase
{
    private $subscriptionRepository;
    private $billingCycleService;
    private $databaseMock;
    private SubscriptionBillingService $service;

    private SubscriptionPricingStrategyResolver        $strategyResolver;
    private StripeSubscriptionGatewayInterface         $subscriptionGateway;
    private StripeSubscriptionScheduleGatewayInterface $scheduleGateway;

    public function test_update_billing_date_throws_exception_when_subscription_not_found(): void
    {
        $this->databaseMock->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(function ($callback) {
                return $callback();
            });

        $this->subscriptionRepository->shouldReceive('find')
            ->with(999)
            ->once()
            ->andReturn(null);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Subscription not found');

        $this->service->updateBillingDate(999, 15);
    }

    public function test_update_billing_date_throws_exception_for_non_stripe_subscription(): void
    {
        $subscription = m::mock(Subscription::class);
        $subscription->shouldReceive('hasStripeSubscription')
            ->once()
            ->andReturn(false);

        $this->databaseMock->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(function ($callback) {
                return $callback();
            });

        $this->subscriptionRepository->shouldReceive('find')
            ->with(1)
            ->once()
            ->andReturn($subscription);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Can only update billing date for Stripe subscriptions');

        $this->service->updateBillingDate(1, 15);
    }

    public function test_update_billing_date_throws_exception_for_inactive_subscription(): void
    {
        $subscription = m::mock(Subscription::class)->makePartial();
        $subscription->shouldReceive('hasStripeSubscription')
            ->once()
            ->andReturn(true);
        $subscription->status = 'cancelled';

        $this->databaseMock->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(function ($callback) {
                return $callback();
            });

        $this->subscriptionRepository->shouldReceive('find')
            ->with(1)
            ->once()
            ->andReturn($subscription);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Can only update billing date for active subscriptions');

        $this->service->updateBillingDate(1, 15);
    }

    public function test_update_billing_date_validates_day_range(): void
    {
        $subscription = m::mock(Subscription::class)->makePartial();
        $subscription->shouldReceive('hasStripeSubscription')
            ->once()
            ->andReturn(true);
        $subscription->status = 'active';

        $this->databaseMock->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(function ($callback) {
                return $callback();
            });

        $this->subscriptionRepository->shouldReceive('find')
            ->with(1)
            ->once()
            ->andReturn($subscription);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Day must be between 1 and 31');

        $this->service->updateBillingDate(1, 32);
    }

    public function test_update_billing_date_throws_exception_when_stripe_update_fails(): void
    {
        $subscription = m::mock(Subscription::class)->makePartial();
        $subscription->shouldReceive('hasStripeSubscription')
            ->once()
            ->andReturn(true);
        $subscription->shouldReceive('getStripeSubscriptionId')
            ->once()
            ->andReturn('sub_123');
        $subscription->status = 'active';

        $this->databaseMock->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(function ($callback) {
                return $callback();
            });

        $this->subscriptionRepository->shouldReceive('find')
            ->with(1)
            ->once()
            ->andReturn($subscription);

        $this->billingCycleService->shouldReceive('updateBillingCycleAnchor')
            ->with('sub_123', 15, true)
            ->once()
            ->andReturn([
                'success' => false,
                'message' => 'Stripe API error'
            ]);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Stripe API error');

        $this->service->updateBillingDate(1, 15);
    }

    public function test_update_billing_date_successfully(): void
    {
        $subscription = m::mock(Subscription::class)->makePartial();
        $subscription->shouldReceive('hasStripeSubscription')
            ->once()
            ->andReturn(true);
        $subscription->shouldReceive('getStripeSubscriptionId')
            ->once()
            ->andReturn('sub_123');
        $subscription->status = 'active';
        $subscription->metadata = ['existing' => 'data'];
        $subscription->id = 1;

        $this->databaseMock->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(function ($callback) {
                return $callback();
            });

        $this->subscriptionRepository->shouldReceive('find')
            ->with(1)
            ->once()
            ->andReturn($subscription);

        $this->billingCycleService->shouldReceive('updateBillingCycleAnchor')
            ->with('sub_123', 15, true)
            ->once()
            ->andReturn([
                'success' => true,
                'new_billing_date' => '2026-02-15',
                'subscription' => (object)['id' => 'sub_123']
            ]);

        $this->subscriptionRepository->shouldReceive('update')
            ->with(1, m::on(function ($data) {
                return isset($data['next_billing_date'])
                    && $data['next_billing_date'] === '2026-02-15 00:00:00'
                    && isset($data['metadata']['billing_day_of_month'])
                    && $data['metadata']['billing_day_of_month'] === 15
                    && isset($data['metadata']['last_billing_update']);
            }))
            ->once();

        $result = $this->service->updateBillingDate(1, 15);

        $this->assertTrue($result['success']);
        $this->assertEquals('2026-02-15', $result['new_billing_date']);
        $this->assertEquals('Billing date updated successfully', $result['message']);
    }

    public function test_update_billing_date_without_proration(): void
    {
        $subscription = m::mock(Subscription::class)->makePartial();
        $subscription->shouldReceive('hasStripeSubscription')
            ->once()
            ->andReturn(true);
        $subscription->shouldReceive('getStripeSubscriptionId')
            ->once()
            ->andReturn('sub_123');
        $subscription->status = 'active';
        $subscription->metadata = [];
        $subscription->id = 1;

        $this->databaseMock->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(function ($callback) {
                return $callback();
            });

        $this->subscriptionRepository->shouldReceive('find')
            ->with(1)
            ->once()
            ->andReturn($subscription);

        $this->billingCycleService->shouldReceive('updateBillingCycleAnchor')
            ->with('sub_123', 15, false)
            ->once()
            ->andReturn([
                'success' => true,
                'new_billing_date' => '2026-02-15'
            ]);

        $this->subscriptionRepository->shouldReceive('update')
            ->once();

        $result = $this->service->updateBillingDate(1, 15, false);

        $this->assertTrue($result['success']);
    }

    public function test_preview_billing_date_change_returns_error_when_subscription_not_found(): void
    {
        $this->subscriptionRepository->shouldReceive('find')
            ->with(999)
            ->once()
            ->andReturn(null);

        $result = $this->service->previewBillingDateChange(999, 15);

        $this->assertFalse($result['success']);
        $this->assertEquals('Subscription not found', $result['message']);
    }

    public function test_preview_billing_date_change_returns_error_for_non_stripe_subscription(): void
    {
        $subscription = m::mock(Subscription::class);
        $subscription->shouldReceive('hasStripeSubscription')
            ->once()
            ->andReturn(false);

        $this->subscriptionRepository->shouldReceive('find')
            ->with(1)
            ->once()
            ->andReturn($subscription);

        $result = $this->service->previewBillingDateChange(1, 15);

        $this->assertFalse($result['success']);
        $this->assertEquals('Can only preview billing date changes for Stripe subscriptions', $result['message']);
    }

    public function test_preview_billing_date_change_successfully(): void
    {
        $subscription = m::mock(Subscription::class);
        $subscription->shouldReceive('hasStripeSubscription')
            ->once()
            ->andReturn(true);
        $subscription->shouldReceive('getStripeSubscriptionId')
            ->once()
            ->andReturn('sub_123');

        $this->subscriptionRepository->shouldReceive('find')
            ->with(1)
            ->once()
            ->andReturn($subscription);

        $expectedPreview = [
            'success' => true,
            'current_period_end' => '2026-02-01',
            'new_billing_date' => '2026-02-15',
            'proration_amount' => 5.50,
            'is_credit' => false,
            'days_difference' => 14
        ];

        $this->billingCycleService->shouldReceive('calculateBillingDateProration')
            ->with('sub_123', 15)
            ->once()
            ->andReturn($expectedPreview);

        $result = $this->service->previewBillingDateChange(1, 15);

        $this->assertTrue($result['success']);
        $this->assertEquals('2026-02-15', $result['new_billing_date']);
        $this->assertEquals(5.50, $result['proration_amount']);
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->subscriptionRepository = m::mock(SubscriptionRepository::class);
        $this->billingCycleService = m::mock(StripeSubscriptionBillingCycleService::class);
        $this->databaseMock = m::mock(Database::class);
        $this->strategyResolver    = m::mock(SubscriptionPricingStrategyResolver::class);
        $this->subscriptionGateway = m::mock(StripeSubscriptionGateway::class);
        $this->scheduleGateway     = m::mock(StripeSubscriptionScheduleGateway::class);

        $this->service = new SubscriptionBillingService(
            $this->subscriptionRepository,
            $this->billingCycleService,
            $this->databaseMock,
            $this->strategyResolver,
            $this->scheduleGateway,
            $this->subscriptionGateway,
        );
    }

    // ── STANDARD ─────────────────────────────────────────────────────────────

    public function test_standard_strategy_calls_subscription_gateway_create(): void
    {
        [$subscription, $plan, $pricing] = $this->makeModels(stripePriceId: 'price_std');

        $this->strategyResolver
            ->shouldReceive('resolve')
            ->with($pricing, 0)
            ->andReturn($this->makeStrategy(SubscriptionStrategyType::STANDARD));

        $this->subscriptionGateway
            ->shouldReceive('create')
            ->once()
            ->with(m::on(fn (CreateStripeSubscriptionDto $dto) =>
                $dto->stripePriceId    === 'price_std'
                && $dto->stripeCustomerId === 'cus_test'
                && $dto->trialDays        === null
            ))
            ->andReturn($this->makeResult());

        $this->scheduleGateway->shouldNotReceive('create');

        $result = $this->service->createSubscription($subscription, $plan, $pricing, 'cus_test');

        $this->assertSame('sub_test', $result->stripeSubscriptionId);
    }

    // ── TRIAL ─────────────────────────────────────────────────────────────────

    public function test_trial_strategy_calls_create_with_trial_and_passes_trial_days(): void
    {
        [$subscription, $plan, $pricing] = $this->makeModels(stripePriceId: 'price_std');

        $this->strategyResolver
            ->shouldReceive('resolve')
            ->with($pricing, 14)
            ->andReturn($this->makeStrategy(SubscriptionStrategyType::TRIAL, trialDays: 14));

        $this->subscriptionGateway
            ->shouldReceive('createWithTrial')
            ->once()
            ->with(m::on(fn (CreateStripeSubscriptionDto $dto) =>
                $dto->stripePriceId === 'price_std'
                && $dto->trialDays  === 14
            ))
            ->andReturn($this->makeResult('trialing'));

        $this->subscriptionGateway->shouldNotReceive('create');
        $this->scheduleGateway->shouldNotReceive('create');

        $result = $this->service->createSubscription($subscription, $plan, $pricing, 'cus_test', 14);

        $this->assertSame('trialing', $result->status);
    }

    // ── INTRO ─────────────────────────────────────────────────────────────────

    public function test_intro_strategy_calls_schedule_gateway_with_both_price_ids(): void
    {
        [$subscription, $plan, $pricing] = $this->makeModels(
            stripePriceId:      'price_std',
            stripeIntroPriceId: 'price_intro',
            introCycles:        1,
        );

        $this->strategyResolver
            ->shouldReceive('resolve')
            ->andReturn($this->makeStrategy(SubscriptionStrategyType::INTRO));

        $this->scheduleGateway
            ->shouldReceive('create')
            ->once()
            ->with(m::on(fn (CreateStripeSubscriptionScheduleDto $dto) =>
                $dto->recurringPriceId === 'price_std'
                && $dto->introPriceId  === 'price_intro'
                && $dto->introCycles   === 1
                && $dto->trialDays     === 0
            ))
            ->andReturn($this->makeResult(scheduleId: 'sched_test'));

        $this->subscriptionGateway->shouldNotReceive('create');
        $this->subscriptionGateway->shouldNotReceive('createWithTrial');

        $result = $this->service->createSubscription($subscription, $plan, $pricing, 'cus_test');

        $this->assertSame('sched_test', $result->stripeScheduleId);
    }

    // ── TRIAL_INTRO ───────────────────────────────────────────────────────────

    public function test_trial_intro_strategy_calls_schedule_gateway_with_trial_days(): void
    {
        [$subscription, $plan, $pricing] = $this->makeModels(
            stripePriceId:      'price_std',
            stripeIntroPriceId: 'price_intro',
            introCycles:        3,
        );

        $this->strategyResolver
            ->shouldReceive('resolve')
            ->andReturn($this->makeStrategy(SubscriptionStrategyType::TRIAL_INTRO, trialDays: 7));

        $this->scheduleGateway
            ->shouldReceive('create')
            ->once()
            ->with(m::on(fn (CreateStripeSubscriptionScheduleDto $dto) =>
                $dto->trialDays    === 7
                && $dto->introCycles === 3
            ))
            ->andReturn($this->makeResult(scheduleId: 'sched_test'));

        $result = $this->service->createSubscription($subscription, $plan, $pricing, 'cus_test', 7);

        $this->assertSame('sched_test', $result->stripeScheduleId);
    }

    // ── Missing price ID guards ───────────────────────────────────────────────

    public function test_throws_when_stripe_price_id_missing_for_standard(): void
    {
        [$subscription, $plan, $pricing] = $this->makeModels(stripePriceId: null);

        $this->strategyResolver
            ->shouldReceive('resolve')
            ->andReturn($this->makeStrategy(SubscriptionStrategyType::STANDARD));

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('no stripe_price_id');

        $this->service->createSubscription($subscription, $plan, $pricing, 'cus_test');
    }

    public function test_throws_when_stripe_intro_price_id_missing_for_intro(): void
    {
        [$subscription, $plan, $pricing] = $this->makeModels(
            stripePriceId:      'price_std',
            stripeIntroPriceId: null,   // not synced yet
            introCycles:        1,
        );

        $this->strategyResolver
            ->shouldReceive('resolve')
            ->andReturn($this->makeStrategy(SubscriptionStrategyType::INTRO));

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('no stripe_intro_price_id');

        $this->service->createSubscription($subscription, $plan, $pricing, 'cus_test');
    }


    // ── Helpers ───────────────────────────────────────────────────────────────

    private function makeModels(
        ?string $stripePriceId      = 'price_std',
        ?string $stripeIntroPriceId = null,
        ?int    $introCycles        = null,
    ): array {
        $subscription = m::mock(Subscription::class)->makePartial();
        $subscription->id        = 1;
        $subscription->plan_id   = 1;
        $subscription->member_id = 1;
        $subscription->site_id   = 1;

        $plan = m::mock(SubscriptionPlan::class)->makePartial();
        $plan->id = 1;

        $pricing = m::mock(SubscriptionPlanPricing::class)->makePartial();
        $pricing->id                    = 1;
        $pricing->stripe_price_id       = $stripePriceId;
        $pricing->stripe_intro_price_id = $stripeIntroPriceId;
        $pricing->intro_cycles          = $introCycles;

        return [$subscription, $plan, $pricing];
    }

    private function makeStrategy(
        SubscriptionStrategyType $type,
        ?int                     $trialDays = null,
    ): SubscriptionPricingStrategyData {
        return new SubscriptionPricingStrategyData(
            type:            $type,
            hasTrial:        $trialDays !== null,
            trialDays:       $trialDays,
            hasIntroPricing: in_array($type, [SubscriptionStrategyType::INTRO, SubscriptionStrategyType::TRIAL_INTRO]),
            introPrice:      null,
            introCycles:     null,
        );
    }

    private function makeResult(
        string  $status     = 'active',
        ?string $scheduleId = null,
    ): StripeSubscriptionResultDto {
        return new StripeSubscriptionResultDto(
            stripeSubscriptionId:      'sub_test',
            stripeScheduleId:          $scheduleId,
            status:                    $status,
            stripeCustomerId:          'cus_test',
            currentPeriodStart:        time(),
            currentPeriodEnd:          time() + 2592000,
            latestInvoiceId:           'in_test',
            paymentIntentId:           'pi_test',
            paymentIntentClientSecret: null,
            requiresAction:            false,
        );
    }

    protected function tearDown(): void
    {
        m::close();
        parent::tearDown();
    }
}
