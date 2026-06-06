<?php

declare(strict_types=1);

namespace App\Tests\Unit\Services\Subscriptions;

use App\Enums\Subscriptions\SubscriptionPricingChangeStatus;
use App\Events\Subscriptions\SubscriptionPricingChangeScheduled;
use App\Framework\Database\Database;
use App\Models\SubscriptionPlan;
use App\Models\SubscriptionPricingChange;
use App\Repositories\Subscriptions\SubscriptionPricingChangeRepository;
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
    private CapturingEventDispatcher $events;
    private SubscriptionPricingChangeService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->repository = Mockery::mock(SubscriptionPricingChangeRepository::class);
        $this->databaseMock = Mockery::mock(Database::class);
        $this->events = CapturingEventDispatcher::fake();

        $this->service = new SubscriptionPricingChangeService(
            $this->repository,
            $this->databaseMock
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
            ->with(Mockery::on(fn($data) => isset($data['plan_id'])))
            ->andReturn($expectedChange);

        $result = $this->service->schedule($plan, 14.99, $effectiveDate, createdBy: 42);

        $this->assertSame($expectedChange, $result);
        $this->events->assertDispatched(
            SubscriptionPricingChangeScheduled::class,
            fn(SubscriptionPricingChangeScheduled $event): bool => $event->pricingChange === $expectedChange
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

        $this->repository
            ->shouldReceive('findActivePlanChange')
            ->once()
            ->with($plan->id)
            ->andReturn($existingChange);

        $this->service->schedule($plan, 12.00, new \DateTime('+35 days'), createdBy: 1);
    }

    // ── apply() ────────────────────────────────────────────────

//    public function test_it_applies_a_due_price_change_and_updates_the_plan(): void
//    {
//        $plan = $this->makePlan(id: 6, price: 9.99);
//
//        $change = $this->makeChange(
//            planId: 6,
//            oldPrice: 9.99,
//            newPrice: 14.99,
//            status: SubscriptionPricingChangeStatus::Notified->value,
//            effectiveDate: new \DateTime('-1 day'),
//        );
//
//        $change->shouldReceive('isDueToApply')->once()->andReturn(true);
//        $change->shouldReceive('plan')->andReturn($plan);
//
//        $this->repository
//            ->shouldReceive('applyPlanPrice')
//            ->once()
//            ->with($plan, 14.99);
//
//        $this->repository
//            ->shouldReceive('markApplied')
//            ->once()
//            ->with($change);
//
//        $this->service->apply($change);
//    }

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
    ): SubscriptionPlan
    {
        $plan = Mockery::mock(SubscriptionPlan::class)->makePartial();
        $plan->id = $id;
        $plan->price = $price;
        $plan->currency = $currency;

        return $plan;
    }

    private function makeChange(
        int        $planId = 1,
        float      $oldPrice = 9.99,
        float      $newPrice = 14.99,
        string     $status = 'scheduled',
        ?\DateTime $effectiveDate = null,
    ): SubscriptionPricingChange
    {
        $change = Mockery::mock(SubscriptionPricingChange::class)->makePartial();

        $change->id = 1;
        $change->plan_id = $planId;
        $change->old_price = $oldPrice;
        $change->new_price = $newPrice;
        $change->status = $status;
        $change->effective_date = $effectiveDate ?? new \DateTime('+35 days');

        return $change;
    }
}
