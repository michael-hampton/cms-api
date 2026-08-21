<?php

declare(strict_types=1);

namespace App\Tests\Unit\Services\Subscriptions;

use App\Framework\Database\Database;
use App\Models\Subscription;
use App\Services\Subscriptions\SubscriptionRenewalTracker;
use DateTimeImmutable;
use Mockery;
use PHPUnit\Framework\TestCase;

class SubscriptionRenewalTrackerTest extends TestCase
{
    public function test_it_records_first_successful_renewal(): void
    {
        $subscription = $this->makeSubscription();
        $subscription->renewal_count = 0;
        $subscription->first_renewed_at = null;
        $subscription->last_renewed_at = null;

        $subscription->shouldReceive('save')->once()->andReturn(true);

        (new SubscriptionRenewalTracker($this->makeDatabase()))->recordRenewal($subscription);

        $this->assertSame(1, $subscription->renewal_count);
        $this->assertNotNull($subscription->first_renewed_at);
        $this->assertNotNull($subscription->last_renewed_at);
    }

    public function test_it_increments_renewal_count(): void
    {
        $subscription = $this->makeSubscription();
        $subscription->renewal_count = 2;
        $subscription->first_renewed_at = new DateTimeImmutable('2026-02-01 10:00:00');

        $subscription->shouldReceive('save')->once()->andReturn(true);

        (new SubscriptionRenewalTracker($this->makeDatabase()))->recordRenewal($subscription);

        $this->assertSame(3, $subscription->renewal_count);
    }

    public function test_it_sets_first_renewed_at_once(): void
    {
        $firstRenewedAt = new DateTimeImmutable('2026-02-01 10:00:00');
        $subscription = $this->makeSubscription();
        $subscription->renewal_count = 1;
        $subscription->first_renewed_at = $firstRenewedAt;

        $subscription->shouldReceive('save')->once()->andReturn(true);

        (new SubscriptionRenewalTracker($this->makeDatabase()))->recordRenewal($subscription);

        $this->assertEquals($firstRenewedAt, $subscription->first_renewed_at);
    }

    public function test_it_updates_last_renewed_at_on_every_renewal(): void
    {
        $lastRenewedAt = new DateTimeImmutable('2026-02-01 10:00:00');
        $subscription = $this->makeSubscription();
        $subscription->renewal_count = 1;
        $subscription->first_renewed_at = new DateTimeImmutable('2026-02-01 10:00:00');
        $subscription->last_renewed_at = $lastRenewedAt;

        $subscription->shouldReceive('save')->once()->andReturn(true);

        (new SubscriptionRenewalTracker($this->makeDatabase()))->recordRenewal($subscription);

        $this->assertNotSame($lastRenewedAt, $subscription->last_renewed_at);
        $this->assertInstanceOf(\DateTimeInterface::class, $subscription->last_renewed_at);
    }

    public function test_it_records_replacement_renewal_on_old_and_new_subscriptions(): void
    {
        $oldSubscription = $this->makeSubscription();
        $oldSubscription->renewal_count = 2;
        $oldSubscription->first_renewed_at = new DateTimeImmutable('2026-02-01 10:00:00');
        $oldSubscription->last_renewed_at = new DateTimeImmutable('2026-03-01 10:00:00');

        $newSubscription = $this->makeSubscription();
        $newSubscription->renewal_count = 2;
        $newSubscription->first_renewed_at = $oldSubscription->first_renewed_at;
        $newSubscription->last_renewed_at = null;

        $oldSubscription->shouldReceive('save')->once()->andReturn(true);
        $newSubscription->shouldReceive('save')->once()->andReturn(true);

        (new SubscriptionRenewalTracker($this->makeDatabase(runsTransaction: true)))->recordRenewalReplacement($oldSubscription, $newSubscription);

        $this->assertSame(3, $oldSubscription->renewal_count);
        $this->assertSame(3, $newSubscription->renewal_count);
        $this->assertEquals(new DateTimeImmutable('2026-02-01 10:00:00'), $oldSubscription->first_renewed_at);
        $this->assertEquals($oldSubscription->first_renewed_at, $newSubscription->first_renewed_at);
        $this->assertNotNull($oldSubscription->last_renewed_at);
        $this->assertEquals($oldSubscription->last_renewed_at, $newSubscription->last_renewed_at);
    }

    public function test_it_sets_first_renewed_at_for_first_replacement_renewal(): void
    {
        $oldSubscription = $this->makeSubscription();
        $oldSubscription->renewal_count = 0;
        $oldSubscription->first_renewed_at = null;
        $oldSubscription->last_renewed_at = null;

        $newSubscription = $this->makeSubscription();

        $oldSubscription->shouldReceive('save')->once()->andReturn(true);
        $newSubscription->shouldReceive('save')->once()->andReturn(true);

        (new SubscriptionRenewalTracker($this->makeDatabase(runsTransaction: true)))->recordRenewalReplacement($oldSubscription, $newSubscription);

        $this->assertSame(1, $oldSubscription->renewal_count);
        $this->assertSame(1, $newSubscription->renewal_count);
        $this->assertNotNull($oldSubscription->first_renewed_at);
        $this->assertEquals($oldSubscription->first_renewed_at, $newSubscription->first_renewed_at);
    }

    public function test_it_wraps_both_saves_in_a_transaction_and_neither_writes_if_it_fails_to_open(): void
    {
        // Regression test: the two saves must succeed or fail together —
        // previously they were two independent, unwrapped writes with no
        // transaction of the tracker's own, relying entirely on the caller
        // having already opened one. If the transaction fails to open here,
        // neither subscription's save() should ever be called.
        $oldSubscription = $this->makeSubscription();
        $newSubscription = $this->makeSubscription();

        $oldSubscription->shouldNotReceive('save');
        $newSubscription->shouldNotReceive('save');

        $database = Mockery::mock(Database::class);
        $database->shouldReceive('transaction')
            ->once()
            ->andThrow(new \RuntimeException('could not open transaction'));

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('could not open transaction');

        (new SubscriptionRenewalTracker($database))->recordRenewalReplacement($oldSubscription, $newSubscription);
    }

    private function makeSubscription(): Subscription
    {
        return Mockery::mock(Subscription::class)->makePartial();
    }

    private function makeDatabase(bool $runsTransaction = false): Database
    {
        $database = Mockery::mock(Database::class);

        if ($runsTransaction) {
            $database->shouldReceive('transaction')
                ->once()
                ->andReturnUsing(fn (callable $callback) => $callback());
        } else {
            $database->shouldNotReceive('transaction');
        }

        return $database;
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
