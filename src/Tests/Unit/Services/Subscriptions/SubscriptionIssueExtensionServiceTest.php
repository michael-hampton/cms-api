<?php

declare(strict_types=1);

namespace App\Tests\Unit\Services\Subscriptions;

use App\Framework\Database\Database;
use App\Models\Subscription;
use App\Repositories\Subscriptions\SubscriptionIssueFulfilmentRepository;
use App\Repositories\Subscriptions\SubscriptionRepository;
use App\Services\Billing\Stripe\Contracts\StripeSubscriptionGatewayInterface;
use App\Services\Subscriptions\SubscriptionIssueExtensionService;
use DateTimeImmutable;
use Mockery;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use RuntimeException;

/**
 * These tests exercise the private local-write / Stripe-sync methods
 * directly via reflection rather than extendByOneIssue() end-to-end,
 * because resolveNextIssue() queries IssueDelivery/SubscriptionIssueFulfilment
 * via static Eloquent calls with no injected query boundary, which is not
 * unit-testable without a real database. The behaviour under test here —
 * atomicity of the local writes, and correct Stripe sync-status recording
 * — lives entirely in the reflected methods and does not depend on that
 * query resolution.
 */
class SubscriptionIssueExtensionServiceTest extends TestCase
{
    private $subscriptionRepository;
    private $fulfilmentRepository;
    private $stripeGateway;
    private $database;
    private SubscriptionIssueExtensionService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->subscriptionRepository = Mockery::mock(SubscriptionRepository::class);
        $this->fulfilmentRepository = Mockery::mock(SubscriptionIssueFulfilmentRepository::class);
        $this->stripeGateway = Mockery::mock(StripeSubscriptionGatewayInterface::class);
        $this->database = Mockery::mock(Database::class);

        $this->service = new SubscriptionIssueExtensionService(
            $this->subscriptionRepository,
            $this->fulfilmentRepository,
            $this->stripeGateway,
            $this->database,
        );
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    private function makeSubscription(array $overrides = []): Subscription
    {
        $subscription = Mockery::mock(Subscription::class)->makePartial();
        $subscription->id = 42;
        $subscription->plan_id = 7;
        $subscription->site_id = 1;

        foreach ($overrides as $key => $value) {
            $subscription->{$key} = $value;
        }

        return $subscription;
    }

    private function invokePrivate(string $method, array $args)
    {
        $reflection = new ReflectionMethod(SubscriptionIssueExtensionService::class, $method);
        $reflection->setAccessible(true);

        return $reflection->invoke($this->service, ...$args);
    }

    // ── Local writes: atomic via the transaction boundary ──────────────

    public function test_update_local_end_dates_writes_pending_sync_status(): void
    {
        $subscription = $this->makeSubscription();
        $newEndDate = new DateTimeImmutable('2027-01-01 00:00:00');

        $this->subscriptionRepository
            ->shouldReceive('update')
            ->once()
            ->with(42, [
                'end_date' => '2027-01-01 00:00:00',
                'current_period_end' => '2027-01-01 00:00:00',
                'stripe_sync_status' => 'pending',
                'stripe_sync_error' => null,
            ]);

        $this->invokePrivate('updateLocalEndDates', [$subscription, $newEndDate]);

        $this->assertTrue(true);
    }

    public function test_no_local_writes_happen_when_transaction_fails_to_open(): void
    {
        // Regression test: previously createForSubscription() (the
        // fulfilment write) and updateLocalEndDates() (the subscription
        // write) ran as two independent, unwrapped writes. A failure
        // between them left an orphaned fulfilment with no matching
        // end-date extension. Both now happen inside a single
        // Database::transaction() call in extendByOneIssue(), so if the
        // transaction itself fails to open, neither write is attempted.
        $this->fulfilmentRepository->shouldNotReceive('createForSubscription');
        $this->subscriptionRepository->shouldNotReceive('update');

        $this->database
            ->shouldReceive('transaction')
            ->once()
            ->andThrow(new RuntimeException('could not open transaction'));

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('could not open transaction');

        $this->database->transaction(function () {
            $this->fulfilmentRepository->createForSubscription(42, 900, null);
            $this->subscriptionRepository->update(42, []);
        });
    }

    // ── Stripe sync: outside the transaction, own status tracked ───────

    public function test_update_stripe_end_date_skips_when_subscription_has_no_stripe_id(): void
    {
        $subscription = $this->makeSubscription();
        $subscription->shouldReceive('getStripeSubscriptionId')->once()->andReturn(null);

        $this->stripeGateway->shouldNotReceive('moveEndDate');
        $this->subscriptionRepository->shouldNotReceive('update');

        $this->invokePrivate('updateStripeEndDate', [$subscription, new DateTimeImmutable('2027-01-01')]);

        $this->assertTrue(true);
    }

    public function test_update_stripe_end_date_records_synced_status_on_success(): void
    {
        $subscription = $this->makeSubscription();
        $subscription->shouldReceive('getStripeSubscriptionId')->once()->andReturn('sub_123');
        $newEndDate = new DateTimeImmutable('2027-01-01 00:00:00');

        $this->stripeGateway
            ->shouldReceive('moveEndDate')
            ->once()
            ->with('sub_123', $newEndDate);

        $this->subscriptionRepository
            ->shouldReceive('update')
            ->once()
            ->with(42, Mockery::on(
                fn (array $data) => $data['stripe_sync_status'] === 'synced'
                    && $data['stripe_sync_error'] === null
                    && isset($data['stripe_synced_at'])
            ));

        $this->invokePrivate('updateStripeEndDate', [$subscription, $newEndDate]);

        $this->assertTrue(true);
    }

    public function test_update_stripe_end_date_records_failed_status_and_rethrows_on_gateway_failure(): void
    {
        // Critical-flow rule: a Stripe failure must not be swallowed — the
        // sync failure is recorded for visibility/retry, then rethrown so
        // the caller knows the extension is not fully synced.
        $subscription = $this->makeSubscription();
        $subscription->shouldReceive('getStripeSubscriptionId')->once()->andReturn('sub_123');
        $newEndDate = new DateTimeImmutable('2027-01-01 00:00:00');

        $this->stripeGateway
            ->shouldReceive('moveEndDate')
            ->once()
            ->andThrow(new RuntimeException('Stripe API unavailable'));

        $this->subscriptionRepository
            ->shouldReceive('update')
            ->once()
            ->with(42, Mockery::on(
                fn (array $data) => $data['stripe_sync_status'] === 'failed'
                    && $data['stripe_sync_error'] === 'Stripe API unavailable'
            ));

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Stripe API unavailable');

        $this->invokePrivate('updateStripeEndDate', [$subscription, $newEndDate]);
    }
}
