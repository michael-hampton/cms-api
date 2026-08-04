<?php

namespace App\Tests\Unit\Actions\Stripe;

use App\Actions\Stripe\HandleSubscriptionUpdated;
use App\Models\Subscription;
use App\Repositories\Subscriptions\SubscriptionRepository;
use App\Services\Billing\Stripe\StripeWebhookSegmentHandler;
use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\TestCase;
use Stripe\Event;
use Stripe\Subscription as StripeSubscription;

class HandleSubscriptionUpdatedTest extends TestCase
{
    private MockInterface $repo;
    private MockInterface $segmentHandler;
    private HandleSubscriptionUpdated $handler;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repo = Mockery::mock(SubscriptionRepository::class);
        $this->segmentHandler = Mockery::mock(StripeWebhookSegmentHandler::class);
        $this->segmentHandler->shouldReceive('onSubscriptionUpdated')->byDefault();
        $this->handler = new HandleSubscriptionUpdated($this->repo, $this->segmentHandler);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function makeEvent(
        string $status,
        ?int $currentPeriodStart = null,
        ?int $currentPeriodEnd = null,
        bool $cancelAtPeriodEnd = false,
        string|object|null $scheduleId = null,
        string $stripeId = 'sub_test',
    ): Event {
        $sub = new StripeSubscription($stripeId);
        $sub->status = $status;
        $sub->current_period_start = $currentPeriodStart;
        $sub->current_period_end = $currentPeriodEnd;
        $sub->cancel_at_period_end = $cancelAtPeriodEnd;
        $sub->schedule = $scheduleId;

        $event = new Event();
        $event->data = (object) ['object' => $sub];

        return $event;
    }

    private function makeLocalSubscription(array $props = []): MockInterface
    {
        $subscription = Mockery::mock(Subscription::class)->makePartial();
        $subscription->id = $props['id'] ?? 1;
        $subscription->status = $props['status'] ?? 'active';
        $subscription->current_period_start = $props['current_period_start'] ?? null;
        $subscription->current_period_end = $props['current_period_end'] ?? null;
        $subscription->cancel_at_period_end = $props['cancel_at_period_end'] ?? false;
        $subscription->cancelled_at = $props['cancelled_at'] ?? null;
        $subscription->stripe_schedule_id = $props['stripe_schedule_id'] ?? null;

        return $subscription;
    }

    private function expectUpdate(MockInterface $subscription, array $expectedSubset): void
    {
        $this->repo
            ->shouldReceive('update')
            ->once()
            ->with(
                $subscription->id,
                Mockery::on(function (array $changes) use ($expectedSubset) {
                    foreach ($expectedSubset as $key => $value) {
                        if (!array_key_exists($key, $changes) || $changes[$key] !== $value) {
                            return false;
                        }
                    }
                    return true;
                })
            );
    }

    // ── Tests ─────────────────────────────────────────────────────────────────

    public function test_it_silently_skips_unknown_subscription(): void
    {
        $event = $this->makeEvent('active', stripeId: 'sub_unknown');

        $this->repo
            ->shouldReceive('findSubscriptionByStripeId')
            ->once()
            ->with('sub_unknown')
            ->andReturn(null);

        $this->repo->shouldNotReceive('update');

        $this->handler->handle($event);

        // Must not throw; no update issued
        $this->assertTrue(true);

        $this->assertTrue(true);
    }

    public function test_it_maps_past_due_stripe_status(): void
    {
        $subscription = $this->makeLocalSubscription();
        $event = $this->makeEvent('past_due');

        $this->repo->shouldReceive('findSubscriptionByStripeId')->once()->andReturn($subscription);

        $this->expectUpdate($subscription, ['status' => 'past_due']);

        $this->handler->handle($event);

        $this->assertTrue(true);
    }

    public function test_it_maps_active_stripe_status(): void
    {
        $subscription = $this->makeLocalSubscription();
        $event = $this->makeEvent('active');

        $this->repo->shouldReceive('findSubscriptionByStripeId')->once()->andReturn($subscription);

        $this->expectUpdate($subscription, ['status' => 'active']);

        $this->handler->handle($event);

        $this->assertTrue(true);
    }

    public function test_it_maps_trialing_stripe_status(): void
    {
        $subscription = $this->makeLocalSubscription();
        $event = $this->makeEvent('trialing');

        $this->repo->shouldReceive('findSubscriptionByStripeId')->once()->andReturn($subscription);

        $this->expectUpdate($subscription, ['status' => 'trialing']);

        $this->handler->handle($event);

        $this->assertTrue(true);
    }

    public function test_it_maps_canceled_stripe_status(): void
    {
        $subscription = $this->makeLocalSubscription();
        $event = $this->makeEvent('canceled');

        $this->repo->shouldReceive('findSubscriptionByStripeId')->once()->andReturn($subscription);

        $this->expectUpdate($subscription, ['status' => 'cancelled']);

        $this->handler->handle($event);

        $this->assertTrue(true);
    }

    public function test_it_syncs_current_period_dates_when_present(): void
    {
        $subscription = $this->makeLocalSubscription();
        $start = 1700000000;
        $end   = 1702592000;

        $event = $this->makeEvent('active', currentPeriodStart: $start, currentPeriodEnd: $end);

        $this->repo->shouldReceive('findSubscriptionByStripeId')->once()->andReturn($subscription);

        $this->expectUpdate($subscription, [
            'current_period_start' => date('Y-m-d H:i:s', $start),
            'current_period_end'   => date('Y-m-d H:i:s', $end),
        ]);

        $this->handler->handle($event);

        $this->assertTrue(true);
    }

    public function test_it_omits_period_dates_from_update_when_stripe_sends_null(): void
    {
        $subscription = $this->makeLocalSubscription([
            'current_period_start' => '2024-01-01 00:00:00',
            'current_period_end'   => '2024-02-01 00:00:00',
        ]);

        $event = $this->makeEvent('active', currentPeriodStart: null, currentPeriodEnd: null);

        $this->repo->shouldReceive('findSubscriptionByStripeId')->once()->andReturn($subscription);

        // Neither date key should appear in the update payload
        $this->repo
            ->shouldReceive('update')
            ->once()
            ->with(
                $subscription->id,
                Mockery::on(function (array $changes) {
                    return !array_key_exists('current_period_start', $changes)
                        && !array_key_exists('current_period_end', $changes);
                })
            );

        $this->handler->handle($event);

        $this->assertTrue(true);
    }

    public function test_it_sets_cancel_at_period_end_and_cancelled_at_for_non_schedule_subscription(): void
    {
        $subscription = $this->makeLocalSubscription(['cancelled_at' => null]);
        $event = $this->makeEvent('active', cancelAtPeriodEnd: true, scheduleId: null);

        $this->repo->shouldReceive('findSubscriptionByStripeId')->once()->andReturn($subscription);

        $this->repo
            ->shouldReceive('update')
            ->once()
            ->with(
                $subscription->id,
                Mockery::on(function (array $changes) {
                    return $changes['cancel_at_period_end'] === true
                        && isset($changes['cancelled_at'])
                        && $changes['cancelled_at'] !== null;
                })
            );

        $this->handler->handle($event);

        $this->assertTrue(true);
    }

    public function test_it_does_not_overwrite_cancelled_at_when_already_set(): void
    {
        $originalTime = '2024-01-01 12:00:00';
        $subscription = $this->makeLocalSubscription(['cancelled_at' => $originalTime]);
        $event = $this->makeEvent('active', cancelAtPeriodEnd: true, scheduleId: null);

        $this->repo->shouldReceive('findSubscriptionByStripeId')->once()->andReturn($subscription);

        // cancelled_at must NOT be in the update payload (it was already set)
        $this->repo
            ->shouldReceive('update')
            ->once()
            ->with(
                $subscription->id,
                Mockery::on(fn(array $changes) => !array_key_exists('cancelled_at', $changes))
            );

        $this->handler->handle($event);

        $this->assertTrue(true);
    }

    public function test_it_skips_cancel_fields_for_schedule_managed_subscription(): void
    {
        $subscription = $this->makeLocalSubscription();
        $event = $this->makeEvent('active', cancelAtPeriodEnd: true, scheduleId: 'sched_abc');

        $this->repo->shouldReceive('findSubscriptionByStripeId')->once()->andReturn($subscription);

        // cancel_at_period_end must NOT appear — schedule-managed subscriptions skip that block
        $this->repo
            ->shouldReceive('update')
            ->once()
            ->with(
                $subscription->id,
                Mockery::on(fn(array $changes) => !array_key_exists('cancel_at_period_end', $changes))
            );

        $this->handler->handle($event);

        $this->assertTrue(true);
    }

    public function test_it_stores_string_schedule_id(): void
    {
        $subscription = $this->makeLocalSubscription();
        $event = $this->makeEvent('active', scheduleId: 'sched_xyz');

        $this->repo->shouldReceive('findSubscriptionByStripeId')->once()->andReturn($subscription);

        $this->expectUpdate($subscription, ['stripe_schedule_id' => 'sched_xyz']);

        $this->handler->handle($event);

        $this->assertTrue(true);
    }

    public function test_it_nulls_schedule_id_when_schedule_has_been_released(): void
    {
        $subscription = $this->makeLocalSubscription(['stripe_schedule_id' => 'sched_old']);
        $event = $this->makeEvent('active', scheduleId: null);

        $this->repo->shouldReceive('findSubscriptionByStripeId')->once()->andReturn($subscription);

        $this->expectUpdate($subscription, ['stripe_schedule_id' => null]);

        $this->handler->handle($event);

        $this->assertTrue(true);
    }

    public function test_it_stores_object_schedule_id_using_id_property(): void
    {
        $subscription = $this->makeLocalSubscription();
        $scheduleObject = (object) ['id' => 'sched_from_object'];
        $event = $this->makeEvent('active', scheduleId: $scheduleObject);

        $this->repo->shouldReceive('findSubscriptionByStripeId')->once()->andReturn($subscription);

        $this->expectUpdate($subscription, ['stripe_schedule_id' => 'sched_from_object']);

        $this->handler->handle($event);

        $this->assertTrue(true);
    }

    public function test_it_evaluates_segment_assignment_after_update(): void
    {
        $subscription = $this->makeLocalSubscription();
        $updated = $this->makeLocalSubscription(['id' => $subscription->id, 'status' => 'active']);
        $event = $this->makeEvent('active');

        $this->repo->shouldReceive('findSubscriptionByStripeId')->once()->andReturn($subscription);
        $this->repo->shouldReceive('update')->once()->andReturn($updated);

        $this->segmentHandler = Mockery::mock(StripeWebhookSegmentHandler::class);
        $this->segmentHandler->shouldReceive('onSubscriptionUpdated')
            ->once()
            ->with($updated);
        $this->handler = new HandleSubscriptionUpdated($this->repo, $this->segmentHandler);

        $this->handler->handle($event);

        $this->assertTrue(true);
    }

    public function test_it_falls_back_to_local_subscription_when_update_returns_null(): void
    {
        $subscription = $this->makeLocalSubscription();
        $event = $this->makeEvent('active');

        $this->repo->shouldReceive('findSubscriptionByStripeId')->once()->andReturn($subscription);
        $this->repo->shouldReceive('update')->once()->andReturn(null);

        $this->segmentHandler = Mockery::mock(StripeWebhookSegmentHandler::class);
        $this->segmentHandler->shouldReceive('onSubscriptionUpdated')
            ->once()
            ->with($subscription);
        $this->handler = new HandleSubscriptionUpdated($this->repo, $this->segmentHandler);

        $this->handler->handle($event);

        $this->assertTrue(true);
    }

    public function test_it_does_not_fail_when_segment_evaluation_throws(): void
    {
        $subscription = $this->makeLocalSubscription();
        $event = $this->makeEvent('active');

        $this->repo->shouldReceive('findSubscriptionByStripeId')->once()->andReturn($subscription);
        $this->repo->shouldReceive('update')->once()->andReturn($subscription);

        $this->segmentHandler = Mockery::mock(StripeWebhookSegmentHandler::class);
        $this->segmentHandler->shouldReceive('onSubscriptionUpdated')
            ->once()
            ->andThrow(new \RuntimeException('boom'));
        $this->handler = new HandleSubscriptionUpdated($this->repo, $this->segmentHandler);

        // Must not throw — segmentation failure is non-critical.
        $this->handler->handle($event);
        $this->assertTrue(true);
    }
}