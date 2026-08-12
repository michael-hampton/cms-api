<?php

declare(strict_types=1);

namespace App\Tests\Unit\Services\Subscriptions;

use App\Enums\Subscriptions\SubscriptionPricingChangeStatus;
use App\Events\Subscriptions\SubscriptionPricingChangeScheduled;
use App\Framework\Database\Database;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\SubscriptionPricingChange;
use App\Models\SubscriptionPricingChangeTransition;
use App\Repositories\Subscriptions\SubscriptionPricingChangeRepository;
use App\Repositories\Subscriptions\SubscriptionPricingChangeTransitionRepository;
use App\Repositories\Subscriptions\SubscriptionRepository;
use App\Services\Subscriptions\Communications\SubscriptionItdCommunicationService;
use App\Services\Subscriptions\SubscriptionCancellationService;
use App\Services\Subscriptions\SubscriptionPaymentService;
use App\Services\Subscriptions\SubscriptionPricingChangeService;
use App\Tests\Functional\Controllers\FunctionalTestCase;
use App\Tests\Support\CapturingEventDispatcher;
use App\Tests\Unit\Repositories\Concerns\CreatesTestData;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;

class SubscriptionPricingChangeServiceTest extends FunctionalTestCase
{
    use CreatesTestData;
    use MockeryPHPUnitIntegration;

    private SubscriptionPricingChangeRepository $repository;
    private Database $databaseMock;
    private SubscriptionRepository $subscriptionRepository;
    private SubscriptionCancellationService $cancellationService;
    private SubscriptionPaymentService $paymentService;
    private SubscriptionPricingChangeTransitionRepository $transitionRepository;
    private SubscriptionItdCommunicationService $itdCommunicationService;
    private CapturingEventDispatcher $events;
    private SubscriptionPricingChangeService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->repository = Mockery::mock(SubscriptionPricingChangeRepository::class);
        $this->databaseMock = Mockery::mock(Database::class);
        $this->subscriptionRepository = Mockery::mock(SubscriptionRepository::class);
        $this->cancellationService = Mockery::mock(SubscriptionCancellationService::class);
        $this->paymentService = Mockery::mock(SubscriptionPaymentService::class);
        $this->transitionRepository = Mockery::mock(SubscriptionPricingChangeTransitionRepository::class);
        $this->itdCommunicationService = Mockery::mock(SubscriptionItdCommunicationService::class);

        $this->events = CapturingEventDispatcher::fake();

        $this->service = new SubscriptionPricingChangeService(
            $this->repository,
            $this->databaseMock,
            $this->subscriptionRepository,
            $this->cancellationService,
            $this->paymentService,
            $this->transitionRepository,
            $this->itdCommunicationService,
        );
    }

    // ── schedule() ─────────────────────────────────────────────

    public function test_it_schedules_a_price_change_and_dispatches_event(): void
    {
        $plan = $this->createSubscriptionPlan();
        $effectiveDate = new \DateTime('+35 days');
        $expectedChange = Mockery::mock(SubscriptionPricingChange::class);

        $this->repository
            ->shouldReceive('findActivePlanChange')
            ->once()
            ->with($plan->id)
            ->andReturn(null);

        $this->databaseMock
            ->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(fn($callback) => $callback());

        $this->repository
            ->shouldReceive('create')
            ->once()
            ->with(Mockery::on(function (array $data) use ($plan): bool {
                return $data['plan_id'] === $plan->id
                    && $data['new_price'] === 14.99
                    && $data['requires_subscription_replacement'] === false
                    && $data['itd_required'] === false
                    && $data['itd_letter_code'] === null;
            }))
            ->andReturn($expectedChange);

        $result = $this->service->schedule($plan, 14.99, $effectiveDate, createdBy: 42);

        $this->assertSame($expectedChange, $result);

        $this->events->assertDispatched(
            SubscriptionPricingChangeScheduled::class,
            fn(SubscriptionPricingChangeScheduled $event): bool => $event->pricingChange === $expectedChange
        );
    }

    public function test_it_schedules_a_mid_term_direct_debit_price_rise(): void
    {
        $plan = $this->makePlan(id: 10, price: 9.99);
        $effectiveDate = new \DateTime('+35 days');
        $expectedChange = Mockery::mock(SubscriptionPricingChange::class);

        $this->repository
            ->shouldReceive('findActivePlanChange')
            ->once()
            ->with(10)
            ->andReturn(null);

        $this->databaseMock
            ->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(fn($callback) => $callback());

        $this->repository
            ->shouldReceive('create')
            ->once()
            ->with(Mockery::on(function (array $data): bool {
                return $data['plan_id'] === 10
                    && $data['old_price'] === 9.99
                    && $data['new_price'] === 12.99
                    && $data['requires_subscription_replacement'] === true
                    && $data['itd_required'] === true
                    && $data['itd_letter_code'] === 'ITD_DD_PRICE_RISE';
            }))
            ->andReturn($expectedChange);

        $result = $this->service->schedule(
            plan: $plan,
            newPrice: 12.99,
            effectiveDate: $effectiveDate,
            createdBy: 42,
            reason: 'Annual increase',
            requiresSubscriptionReplacement: true,
            itdRequired: true,
            itdLetterCode: 'ITD_DD_PRICE_RISE',
        );

        $this->assertSame($expectedChange, $result);
    }

    public function test_it_rejects_subscription_replacement_for_a_price_reduction(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/only valid for price increases/');

        $plan = $this->makePlan(id: 10, price: 9.99);

        $this->repository
            ->shouldReceive('findActivePlanChange')
            ->once()
            ->with(10)
            ->andReturn(null);

        $this->service->schedule(
            plan: $plan,
            newPrice: 7.99,
            effectiveDate: new \DateTime('+35 days'),
            createdBy: 42,
            requiresSubscriptionReplacement: true,
        );
    }

    public function test_it_rejects_itd_without_subscription_replacement(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/ITD notification cannot be required/');

        $plan = $this->makePlan(id: 10, price: 9.99);

        $this->repository
            ->shouldReceive('findActivePlanChange')
            ->once()
            ->with(10)
            ->andReturn(null);

        $this->service->schedule(
            plan: $plan,
            newPrice: 12.99,
            effectiveDate: new \DateTime('+35 days'),
            createdBy: 42,
            requiresSubscriptionReplacement: false,
            itdRequired: true,
            itdLetterCode: 'ITD_DD_PRICE_RISE',
        );
    }

    public function test_it_rejects_itd_without_letter_code(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/ITD letter code is required/');

        $plan = $this->makePlan(id: 10, price: 9.99);

        $this->repository
            ->shouldReceive('findActivePlanChange')
            ->once()
            ->with(10)
            ->andReturn(null);

        $this->service->schedule(
            plan: $plan,
            newPrice: 12.99,
            effectiveDate: new \DateTime('+35 days'),
            createdBy: 42,
            requiresSubscriptionReplacement: true,
            itdRequired: true,
            itdLetterCode: null,
        );
    }

    public function test_it_wraps_schedule_in_a_transaction(): void
    {
        $plan = $this->createSubscriptionPlan();
        $effectiveDate = new \DateTime('+31 days');
        $change = Mockery::mock(SubscriptionPricingChange::class);

        $this->repository
            ->shouldReceive('findActivePlanChange')
            ->once()
            ->andReturn(null);

        $this->repository
            ->shouldReceive('create')
            ->once()
            ->andReturn($change);

        $this->databaseMock
            ->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(fn($callback) => $callback());

        $this->service->schedule($plan, 7.00, $effectiveDate, createdBy: 1);
    }

    public function test_it_rejects_an_effective_date_less_than_30_days_away(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/at least 30 days/');

        $plan = $this->makePlan(id: 3, price: 9.99);

        $this->service->schedule($plan, 12.00, new \DateTime('+29 days'), createdBy: 1);
    }

    public function test_it_rejects_scheduling_when_an_active_change_already_exists(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/already has an active pricing change/');

        $plan = $this->createSubscriptionPlan();
        $existingChange = Mockery::mock(SubscriptionPricingChange::class)->makePartial();
        $existingChange->id = 99;
        $existingChange->status = SubscriptionPricingChangeStatus::Scheduled->value;

        $this->repository
            ->shouldReceive('findActivePlanChange')
            ->once()
            ->with($plan->id)
            ->andReturn($existingChange);

        $this->service->schedule($plan, 12.00, new \DateTime('+35 days'), createdBy: 1);
    }

    // ── apply() ────────────────────────────────────────────────

    public function test_it_applies_a_due_plan_only_price_change_and_updates_the_plan(): void
    {
        $plan = $this->makePlan(id: 6, price: 9.99);

        $change = $this->makeChange(
            planId: 6,
            oldPrice: 9.99,
            newPrice: 14.99,
            status: SubscriptionPricingChangeStatus::Notified->value,
            effectiveDate: new \DateTime('-1 day'),
            requiresSubscriptionReplacement: false,
        );

        $change->shouldReceive('isDueToApply')->once()->andReturn(true);
        $change->shouldReceive('plan')->once()->with(true)->andReturn(
            new class($plan) {
                public function __construct(private SubscriptionPlan $plan) {}
                public function first(): SubscriptionPlan { return $this->plan; }
            }
        );

        $this->databaseMock
            ->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(fn($callback) => $callback());

        $this->repository
            ->shouldReceive('applyPlanPrice')
            ->once()
            ->with($plan, 14.99);

        $this->repository
            ->shouldReceive('markApplied')
            ->once()
            ->with($change);

        $this->repository
            ->shouldReceive('findActiveSubscribersForPlan')
            ->never();

        $this->service->apply($change);
    }

    public function test_it_refuses_to_apply_a_change_not_yet_due(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/not due to apply/');

        $change = $this->makeChange(
            status: SubscriptionPricingChangeStatus::Notified->value,
            effectiveDate: new \DateTime('+10 days'),
        );

        $change->shouldReceive('isDueToApply')->once()->andReturn(false);

        $this->service->apply($change);
    }

    public function test_it_uses_mid_term_workflow_when_replacement_is_required(): void
    {
        $plan = $this->makePlan(id: 6, price: 14.99);

        $change = $this->makeChange(
            id: 77,
            planId: 6,
            oldPrice: 9.99,
            newPrice: 14.99,
            status: SubscriptionPricingChangeStatus::Notified->value,
            effectiveDate: new \DateTime('-1 day'),
            requiresSubscriptionReplacement: true,
            itdRequired: true,
            itdLetterCode: 'ITD_DD_PRICE_RISE',
        );

        $oldSubscription = $this->makeSubscription(
            id: 100,
            memberId: 200,
            siteId: 300,
            planId: 6,
            price: 9.99,
            stripeSubscriptionId: 'sub_old',
        );

        $newSubscription = $this->makeSubscription(
            id: 101,
            memberId: 200,
            siteId: 300,
            planId: 6,
            price: 14.99,
            stripeSubscriptionId: 'sub_new',
        );

        $transition = $this->makeTransition(id: 500);

        $change->shouldReceive('isDueToApply')->once()->andReturn(true);
        $change->shouldReceive('plan')->once()->with(true)->andReturn(
            new class($plan) {
                public function __construct(private SubscriptionPlan $plan) {}
                public function first(): SubscriptionPlan { return $this->plan; }
            }
        );

        $this->repository
            ->shouldReceive('findActiveSubscribersForPlan')
            ->once()
            ->with(6)
            ->andReturn([$oldSubscription]);

        $this->transitionRepository
            ->shouldReceive('findForOldSubscription')
            ->once()
            ->with(77, 100)
            ->andReturn(null);

        $this->transitionRepository
            ->shouldReceive('create')
            ->once()
            ->with(Mockery::on(function (array $data): bool {
                return $data['subscription_pricing_change_id'] === 77
                    && $data['old_subscription_id'] === 100
                    && $data['old_price'] === 9.99
                    && $data['new_price'] === 14.99
                    && $data['itd_required'] === true
                    && $data['itd_letter_code'] === 'ITD_DD_PRICE_RISE';
            }))
            ->andReturn($transition);

        $this->cancellationService
            ->shouldReceive('cancelSubscription')
            ->once()
            ->with(100, Mockery::on(fn(array $options): bool =>
                $options['cancel_at_period_end'] === false
                && $options['create_refund'] === false
            ));

        $this->transitionRepository
            ->shouldReceive('markOldSubscriptionCancelled')
            ->once()
            ->with(500);

        $this->subscriptionRepository
            ->shouldReceive('createSubscription')
            ->once()
            ->with(200, 6, 300, Mockery::on(function (array $data): bool {
                return $data['price'] === 14.99
                    && $data['replacement_reason'] === 'price_rise'
                    && $data['renewed_from_subscription_id'] === 100;
            }))
            ->andReturn($newSubscription);

        $newSubscription->shouldReceive('plan')->once()->with(true)->andReturn(
            new class($plan) {
                public function __construct(private SubscriptionPlan $plan) {}
                public function first(): SubscriptionPlan { return $this->plan; }
            }
        );

        $this->paymentService
            ->shouldReceive('processStripeSubscriptionPayment')
            ->once()
            ->with($newSubscription, $plan, Mockery::on(function (array $data): bool {
                return $data['pricing_change_id'] === 77
                    && $data['old_subscription_id'] === 100
                    && $data['transition_id'] === 500;
            }))
            ->andReturn([
                'success' => true,
                'subscription_id' => 'sub_new',
            ]);

        $this->transitionRepository
            ->shouldReceive('markNewSubscriptionCreated')
            ->once()
            ->with(500, 101, 'sub_new');

        $oldSubscription
            ->shouldReceive('update')
            ->once()
            ->with([
                'replaced_by_subscription_id' => 101,
                'replacement_reason' => 'price_rise',
            ]);

        $this->itdCommunicationService
            ->shouldReceive('generateForPriceRise')
            ->once()
            ->with($change, $oldSubscription, $newSubscription, 500, 'ITD_DD_PRICE_RISE');

        $this->transitionRepository
            ->shouldReceive('markItdGenerated')
            ->once()
            ->with(500);

        $this->transitionRepository
            ->shouldReceive('markCompleted')
            ->once()
            ->with(500);

        $this->databaseMock
            ->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(fn($callback) => $callback());

        $this->repository
            ->shouldReceive('applyPlanPrice')
            ->once()
            ->with($plan, 14.99);

        $this->repository
            ->shouldReceive('markApplied')
            ->once()
            ->with($change);

        $this->service->apply($change);
    }

    public function test_it_refuses_mid_term_workflow_when_change_is_not_a_price_rise(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/not a price rise/');

        $change = $this->makeChange(
            id: 77,
            oldPrice: 14.99,
            newPrice: 9.99,
            status: SubscriptionPricingChangeStatus::Notified->value,
            effectiveDate: new \DateTime('-1 day'),
            requiresSubscriptionReplacement: true,
        );

        $change->shouldReceive('isDueToApply')->once()->andReturn(true);

        $this->repository
            ->shouldReceive('findActiveSubscribersForPlan')
            ->never();

        $this->service->apply($change);
    }

    public function test_it_skips_completed_transition_when_reprocessing_mid_term_workflow(): void
    {
        $plan = $this->makePlan(id: 6, price: 14.99);

        $change = $this->makeChange(
            id: 77,
            planId: 6,
            oldPrice: 9.99,
            newPrice: 14.99,
            status: SubscriptionPricingChangeStatus::Notified->value,
            effectiveDate: new \DateTime('-1 day'),
            requiresSubscriptionReplacement: true,
        );

        $oldSubscription = $this->makeSubscription(
            id: 100,
            memberId: 200,
            siteId: 300,
            planId: 6,
            price: 9.99,
            stripeSubscriptionId: 'sub_old',
        );

        $completedTransition = $this->makeTransition(id: 500);
        $completedTransition->shouldReceive('isCompleted')->once()->andReturn(true);

        $change->shouldReceive('isDueToApply')->once()->andReturn(true);
        $change->shouldReceive('plan')->once()->with(true)->andReturn(
            new class($plan) {
                public function __construct(private SubscriptionPlan $plan) {}
                public function first(): SubscriptionPlan { return $this->plan; }
            }
        );

        $this->repository
            ->shouldReceive('findActiveSubscribersForPlan')
            ->once()
            ->with(6)
            ->andReturn([$oldSubscription]);

        $this->transitionRepository
            ->shouldReceive('findForOldSubscription')
            ->once()
            ->with(77, 100)
            ->andReturn($completedTransition);

        $this->cancellationService
            ->shouldReceive('cancelSubscription')
            ->never();

        $this->subscriptionRepository
            ->shouldReceive('createSubscription')
            ->never();

        $this->paymentService
            ->shouldReceive('processStripeSubscriptionPayment')
            ->never();

        $this->itdCommunicationService
            ->shouldReceive('generateForPriceRise')
            ->never();

        $this->databaseMock
            ->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(fn($callback) => $callback());

        $this->repository
            ->shouldReceive('applyPlanPrice')
            ->once()
            ->with($plan, 14.99);

        $this->repository
            ->shouldReceive('markApplied')
            ->once()
            ->with($change);

        $this->service->apply($change);
    }

    public function test_it_marks_transition_failed_when_mid_term_workflow_throws(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Stripe exploded');

        $plan = $this->makePlan(id: 6, price: 14.99);

        $change = $this->makeChange(
            id: 77,
            planId: 6,
            oldPrice: 9.99,
            newPrice: 14.99,
            status: SubscriptionPricingChangeStatus::Notified->value,
            effectiveDate: new \DateTime('-1 day'),
            requiresSubscriptionReplacement: true,
        );

        $oldSubscription = $this->makeSubscription(
            id: 100,
            memberId: 200,
            siteId: 300,
            planId: 6,
            price: 9.99,
            stripeSubscriptionId: 'sub_old',
        );

        $transition = $this->makeTransition(id: 500);

        $change->shouldReceive('isDueToApply')->once()->andReturn(true);
        $change->shouldReceive('plan')->once()->with(true)->andReturn(
            new class($plan) {
                public function __construct(private SubscriptionPlan $plan) {}
                public function first(): SubscriptionPlan { return $this->plan; }
            }
        );

        $this->repository
            ->shouldReceive('findActiveSubscribersForPlan')
            ->once()
            ->with(6)
            ->andReturn([$oldSubscription]);

        $this->transitionRepository
            ->shouldReceive('findForOldSubscription')
            ->once()
            ->with(77, 100)
            ->andReturn(null);

        $this->transitionRepository
            ->shouldReceive('create')
            ->once()
            ->andReturn($transition);

        $this->subscriptionRepository
            ->shouldReceive('createSubscription')
            ->once()
            ->andThrow(new \RuntimeException('Stripe exploded'));

        $this->cancellationService
            ->shouldReceive('cancelSubscription')
            ->never();

        $this->transitionRepository
            ->shouldReceive('markOldSubscriptionCancelled')
            ->never();

        $this->transitionRepository
            ->shouldReceive('markFailed')
            ->once()
            ->with(500, 'Stripe exploded');

        $this->repository
            ->shouldReceive('applyPlanPrice')
            ->never();

        $this->repository
            ->shouldReceive('markApplied')
            ->never();

        $this->service->apply($change);
    }

    // ── cancel() ───────────────────────────────────────────────

    public function test_it_cancels_a_scheduled_change(): void
    {
        $change = $this->makeChange(
            status: SubscriptionPricingChangeStatus::Scheduled->value
        );

        $change->shouldReceive('isApplied')->once()->andReturn(false);
        $change->shouldReceive('isCancelled')->once()->andReturn(false);

        $this->repository
            ->shouldReceive('markCancelled')
            ->once()
            ->with($change);

        $this->service->cancel($change);
    }

    public function test_it_is_idempotent_when_cancelling_an_already_cancelled_change(): void
    {
        $change = $this->makeChange(
            status: SubscriptionPricingChangeStatus::Cancelled->value
        );

        $change->shouldReceive('isApplied')->once()->andReturn(false);
        $change->shouldReceive('isCancelled')->once()->andReturn(true);

        $this->repository
            ->shouldReceive('markCancelled')
            ->never();

        $this->service->cancel($change);
    }

    public function test_it_refuses_to_cancel_an_applied_change(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/already been applied/');

        $change = $this->makeChange(
            status: SubscriptionPricingChangeStatus::Applied->value
        );

        $change->shouldReceive('isApplied')->once()->andReturn(true);

        $this->service->cancel($change);
    }

    // ── Helpers ────────────────────────────────────────────────

    private function makePlan(
        int    $id = 1,
        float  $price = 9.99,
        string $currency = 'GBP'
    ): SubscriptionPlan {
        $plan = Mockery::mock(SubscriptionPlan::class)->makePartial();
        $plan->id = $id;
        $plan->price = $price;
        $plan->currency = $currency;

        return $plan;
    }

    private function makeChange(
        int        $id = 1,
        int        $planId = 1,
        float      $oldPrice = 9.99,
        float      $newPrice = 14.99,
        string     $status = 'scheduled',
        ?\DateTime $effectiveDate = null,
        bool       $requiresSubscriptionReplacement = false,
        bool       $itdRequired = false,
        ?string    $itdLetterCode = null,
    ): SubscriptionPricingChange {
        $change = Mockery::mock(SubscriptionPricingChange::class)->makePartial();

        $change->id = $id;
        $change->plan_id = $planId;
        $change->old_price = $oldPrice;
        $change->new_price = $newPrice;
        $change->status = $status;
        $change->effective_date = $effectiveDate ?? new \DateTime('+35 days');
        $change->requires_subscription_replacement = $requiresSubscriptionReplacement;
        $change->itd_required = $itdRequired;
        $change->itd_letter_code = $itdLetterCode;
        $change->currency = 'GBP';

        return $change;
    }

    private function makeSubscription(
        int     $id,
        int     $memberId,
        int     $siteId,
        int     $planId,
        float   $price,
        ?string $stripeSubscriptionId = null,
    ): Subscription {
        $subscription = Mockery::mock(Subscription::class)->makePartial();

        $subscription->id = $id;
        $subscription->member_id = $memberId;
        $subscription->site_id = $siteId;
        $subscription->plan_id = $planId;
        $subscription->price = $price;
        $subscription->currency = 'GBP';
        $subscription->payment_subscription_id = $stripeSubscriptionId;
        $subscription->type = 'paid';
        $subscription->delivery_type = 'digital';
        $subscription->account_number = 'ACC-123';
        $subscription->territory_id = null;
        $subscription->territory_override_flag = false;

        return $subscription;
    }

    private function makeTransition(int $id): SubscriptionPricingChangeTransition
    {
        $transition = Mockery::mock(SubscriptionPricingChangeTransition::class)->makePartial();
        $transition->id = $id;

        return $transition;
    }
}