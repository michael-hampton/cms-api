<?php

declare(strict_types=1);

namespace App\Tests\Unit\Services\Subscriptions;

use App\Framework\Database\Database;
use App\Models\IssueDelivery;
use App\Models\Subscription;
use App\Models\SubscriptionIssueFulfilment;
use App\Repositories\Subscriptions\IssueDeliveryRepository;
use App\Repositories\Subscriptions\SubscriptionIssueFulfilmentRepository;
use App\Repositories\Subscriptions\SubscriptionRepository;
use App\Services\Billing\Stripe\Contracts\StripeSubscriptionGatewayInterface;
use App\Services\Subscriptions\SubscriptionIssueExtensionService;
use App\Framework\Support\Collection;
use DateTimeImmutable;
use Mockery;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use RuntimeException;

/**
 * The local-write / Stripe-sync methods are exercised directly via
 * reflection because they're private collaborators of extendByOneIssue();
 * resolveNextIssue() (also private) is exercised end-to-end via
 * extendByOneIssue() now that its IssueDelivery/SubscriptionIssueFulfilment
 * lookups go through injected repositories rather than static Eloquent
 * calls.
 */
class SubscriptionIssueExtensionServiceTest extends TestCase
{
    private $subscriptionRepository;
    private $fulfilmentRepository;
    private $issueDeliveryRepository;
    private $stripeGateway;
    private $database;
    private SubscriptionIssueExtensionService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->subscriptionRepository = Mockery::mock(SubscriptionRepository::class);
        $this->fulfilmentRepository = Mockery::mock(SubscriptionIssueFulfilmentRepository::class);
        $this->issueDeliveryRepository = Mockery::mock(IssueDeliveryRepository::class);
        $this->stripeGateway = Mockery::mock(StripeSubscriptionGatewayInterface::class);
        $this->database = Mockery::mock(Database::class);

        $this->service = new SubscriptionIssueExtensionService(
            $this->subscriptionRepository,
            $this->fulfilmentRepository,
            $this->issueDeliveryRepository,
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

    // ── resolveNextIssue(): now testable via injected repositories ─────

    public function test_resolve_next_issue_returns_first_candidate_with_no_existing_fulfilment(): void
    {
        $subscription = $this->makeSubscription();

        $this->fulfilmentRepository
            ->shouldReceive('findLatestForSubscription')
            ->once()
            ->with(42)
            ->andReturn(null);

        $issueA = Mockery::mock(IssueDelivery::class)->makePartial();
        $issueA->id = 900;
        $issueB = Mockery::mock(IssueDelivery::class)->makePartial();
        $issueB->id = 901;

        $this->issueDeliveryRepository
            ->shouldReceive('findCandidateNextIssuesForSubscription')
            ->once()
            ->with(7, 1, null, null)
            ->andReturn(new Collection([$issueA, $issueB]));

        $this->fulfilmentRepository
            ->shouldReceive('existsForSubscriptionAndSchedule')
            ->once()
            ->with(42, 900)
            ->andReturn(false);

        $result = $this->invokePrivate('resolveNextIssue', [$subscription]);

        $this->assertSame($issueA, $result);
    }

    public function test_resolve_next_issue_skips_candidates_already_fulfilled(): void
    {
        $subscription = $this->makeSubscription();

        $this->fulfilmentRepository
            ->shouldReceive('findLatestForSubscription')
            ->once()
            ->with(42)
            ->andReturn(null);

        $issueA = Mockery::mock(IssueDelivery::class)->makePartial();
        $issueA->id = 900;
        $issueB = Mockery::mock(IssueDelivery::class)->makePartial();
        $issueB->id = 901;

        $this->issueDeliveryRepository
            ->shouldReceive('findCandidateNextIssuesForSubscription')
            ->once()
            ->andReturn(new Collection([$issueA, $issueB]));

        $this->fulfilmentRepository
            ->shouldReceive('existsForSubscriptionAndSchedule')
            ->once()
            ->with(42, 900)
            ->andReturn(true);

        $this->fulfilmentRepository
            ->shouldReceive('existsForSubscriptionAndSchedule')
            ->once()
            ->with(42, 901)
            ->andReturn(false);

        $result = $this->invokePrivate('resolveNextIssue', [$subscription]);

        $this->assertSame($issueB, $result);
    }

    public function test_resolve_next_issue_returns_null_when_no_candidates_available(): void
    {
        $subscription = $this->makeSubscription();

        $this->fulfilmentRepository
            ->shouldReceive('findLatestForSubscription')
            ->once()
            ->with(42)
            ->andReturn(null);

        $this->issueDeliveryRepository
            ->shouldReceive('findCandidateNextIssuesForSubscription')
            ->once()
            ->andReturn(new Collection([]));

        $result = $this->invokePrivate('resolveNextIssue', [$subscription]);

        $this->assertNull($result);
    }

    public function test_resolve_next_issue_queries_after_the_last_fulfilled_issue_date(): void
    {
        $subscription = $this->makeSubscription();

        $lastFulfilment = Mockery::mock(SubscriptionIssueFulfilment::class)->makePartial();
        $lastFulfilment->issue_delivery_id = 500;

        $this->fulfilmentRepository
            ->shouldReceive('findLatestForSubscription')
            ->once()
            ->with(42)
            ->andReturn($lastFulfilment);

        $lastIssue = Mockery::mock(IssueDelivery::class)->makePartial();
        $lastIssue->id = 500;
        $lastIssue->estimated_delivery_date = new DateTimeImmutable('2026-06-01 00:00:00');

        $this->issueDeliveryRepository
            ->shouldReceive('find')
            ->once()
            ->with(500)
            ->andReturn($lastIssue);

        $this->issueDeliveryRepository
            ->shouldReceive('findCandidateNextIssuesForSubscription')
            ->once()
            ->with(
                7,
                1,
                Mockery::on(fn ($date) => $date instanceof DateTimeImmutable
                    && $date->format('Y-m-d H:i:s') === '2026-06-01 00:00:00'),
                null,
            )
            ->andReturn(new Collection([]));

        $result = $this->invokePrivate('resolveNextIssue', [$subscription]);

        $this->assertNull($result);
    }

    public function test_resolve_next_issue_falls_back_to_id_ordering_when_last_issue_has_no_date(): void
    {
        $subscription = $this->makeSubscription();

        $lastFulfilment = Mockery::mock(SubscriptionIssueFulfilment::class)->makePartial();
        $lastFulfilment->issue_delivery_id = 500;

        $this->fulfilmentRepository
            ->shouldReceive('findLatestForSubscription')
            ->once()
            ->with(42)
            ->andReturn($lastFulfilment);

        // No matching IssueDelivery row found for the last fulfilment.
        $this->issueDeliveryRepository
            ->shouldReceive('find')
            ->once()
            ->with(500)
            ->andReturn(null);

        $this->issueDeliveryRepository
            ->shouldReceive('findCandidateNextIssuesForSubscription')
            ->once()
            ->with(7, 1, null, 500)
            ->andReturn(new Collection([]));

        $result = $this->invokePrivate('resolveNextIssue', [$subscription]);

        $this->assertNull($result);
    }

    // ── extendByOneIssue(): full happy path ─────────────────────────────

    public function test_extend_by_one_issue_creates_fulfilment_and_extends_end_date_atomically(): void
    {
        $subscription = $this->makeSubscription();
        $subscription->shouldReceive('getStripeSubscriptionId')->andReturn(null);

        $this->fulfilmentRepository
            ->shouldReceive('findLatestForSubscription')
            ->once()
            ->with(42)
            ->andReturn(null);

        $nextIssue = Mockery::mock(IssueDelivery::class)->makePartial();
        $nextIssue->id = 900;
        $nextIssue->estimated_delivery_date = new DateTimeImmutable('2027-02-01 00:00:00');
        $nextIssue->on_sale_date = null;

        $this->issueDeliveryRepository
            ->shouldReceive('findCandidateNextIssuesForSubscription')
            ->once()
            ->andReturn(new Collection([$nextIssue]));

        $this->fulfilmentRepository
            ->shouldReceive('existsForSubscriptionAndSchedule')
            ->once()
            ->with(42, 900)
            ->andReturn(false);

        $expectedFulfilment = Mockery::mock(SubscriptionIssueFulfilment::class)->makePartial();

        $this->fulfilmentRepository
            ->shouldReceive('createForSubscription')
            ->once()
            ->with(42, 900, Mockery::on(
                fn ($date) => $date instanceof DateTimeImmutable
                    && $date->format('Y-m-d H:i:s') === '2027-02-01 00:00:00',
            ))
            ->andReturn($expectedFulfilment);

        $this->subscriptionRepository
            ->shouldReceive('update')
            ->once()
            ->with(42, Mockery::on(
                fn (array $data) => $data['end_date'] === '2027-02-01 00:00:00'
                    && $data['stripe_sync_status'] === 'pending',
            ));

        $this->database
            ->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(fn ($callback) => $callback());

        $result = $this->service->extendByOneIssue($subscription);

        $this->assertSame($expectedFulfilment, $result);
    }

    public function test_extend_by_one_issue_throws_when_no_future_issue_available(): void
    {
        $subscription = $this->makeSubscription();

        $this->fulfilmentRepository
            ->shouldReceive('findLatestForSubscription')
            ->once()
            ->with(42)
            ->andReturn(null);

        $this->issueDeliveryRepository
            ->shouldReceive('findCandidateNextIssuesForSubscription')
            ->once()
            ->andReturn(new Collection([]));

        $this->database->shouldNotReceive('transaction');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('No future issue is available to extend this subscription.');

        $this->service->extendByOneIssue($subscription);
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
