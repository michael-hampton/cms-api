<?php

declare(strict_types=1);

namespace App\Tests\Unit\Services\Subscriptions;

use App\DTO\Subscriptions\ReplacementEligibilityResult;
use App\Models\FulfilmentReplacement;
use App\Models\Subscription;
use App\Repositories\Subscriptions\FulfilmentReplacementRepository;
use App\Repositories\Subscriptions\SubscriptionRepository;
use App\Services\Subscriptions\FulfilmentReplacementEligibilityService;
use Mockery;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for FulfilmentReplacementEligibilityService.
 *
 * Both canRequest() and canRequestForIssues() are exercised. The bulk path
 * shares subscription-level guards with the single path, so the test matrix
 * for canRequest() is the primary coverage vehicle; canRequestForIssues()
 * tests focus on bulk-specific behaviour (one query for open replacements,
 * mixed true/false results in the same call).
 */
class FulfilmentReplacementEligibilityServiceTest extends TestCase
{
    private FulfilmentReplacementRepository $replacementRepository;
    private SubscriptionRepository $subscriptionRepository;
    private FulfilmentReplacementEligibilityService $service;

    // ── canRequest — subscription guards ──────────────────────────────────────

    public function test_denied_when_subscription_not_found(): void
    {
        $this->subscriptionRepository
            ->shouldReceive('find')->once()->with(1)->andReturn(null);

        $result = $this->service->canRequest(1, 100, 1);

        $this->assertFalse($result->canRequestReplacement);
        $this->assertStringContainsString('not found', $result->blockedReason);
    }

    public function test_denied_when_subscription_belongs_to_wrong_site(): void
    {
        $subscription = $this->makeSubscription(siteId: 99);
        $this->subscriptionRepository->shouldReceive('find')->once()->andReturn($subscription);

        $result = $this->service->canRequest(1, 100, 1);

        $this->assertFalse($result->canRequestReplacement);
        $this->assertStringContainsString('site', $result->blockedReason);
    }

    public function test_denied_when_subscription_is_not_active(): void
    {
        $subscription = $this->makeSubscription(status: 'cancelled');
        $this->subscriptionRepository->shouldReceive('find')->once()->andReturn($subscription);

        $result = $this->service->canRequest(1, 100, 1);

        $this->assertFalse($result->canRequestReplacement);
        $this->assertStringContainsString('active', $result->blockedReason);
    }

    public function test_denied_when_subscription_is_paused(): void
    {
        $subscription = $this->makeSubscription(status: 'paused');
        $this->subscriptionRepository->shouldReceive('find')->once()->andReturn($subscription);

        $result = $this->service->canRequest(1, 100, 1);

        $this->assertFalse($result->canRequestReplacement);
        $this->assertStringContainsString('active', $result->blockedReason);
    }

    public function test_denied_when_subscription_is_digital(): void
    {
        $subscription = $this->makeSubscription(deliveryType: 'digital');
        $this->subscriptionRepository->shouldReceive('find')->once()->andReturn($subscription);

        $result = $this->service->canRequest(1, 100, 1);

        $this->assertFalse($result->canRequestReplacement);
        $this->assertStringContainsString('print', $result->blockedReason);
    }

    // ── canRequest — issue-level guards ───────────────────────────────────────

    public function test_denied_when_issue_does_not_belong_to_subscription(): void
    {
        $this->stubActiveSubscription();

        $this->replacementRepository
            ->shouldReceive('issueExistsForSubscription')
            ->once()->with(100, 1)->andReturn(false);

        $result = $this->service->canRequest(1, 100, 1);

        $this->assertFalse($result->canRequestReplacement);
        $this->assertStringContainsString('does not belong', $result->blockedReason);
    }

    public function test_denied_when_issue_has_not_been_dispatched(): void
    {
        $this->stubActiveSubscription();

        $this->replacementRepository
            ->shouldReceive('issueExistsForSubscription')->once()->andReturn(true);
        $this->replacementRepository
            ->shouldReceive('issueDeliveryWasDispatched')->once()->with(100)->andReturn(false);

        $result = $this->service->canRequest(1, 100, 1);

        $this->assertFalse($result->canRequestReplacement);
        $this->assertStringContainsString('dispatched', $result->blockedReason);
    }

    public function test_denied_when_open_pending_replacement_exists(): void
    {
        $this->stubActiveSubscription();
        $this->stubIssueExistsAndDispatched();

        $this->replacementRepository
            ->shouldReceive('hasOpenReplacement')->once()->with(1, 100)->andReturn(true);

        $result = $this->service->canRequest(1, 100, 1);

        $this->assertFalse($result->canRequestReplacement);
        $this->assertStringContainsString('already in progress', $result->blockedReason);
    }

    public function test_denied_when_open_queued_replacement_exists(): void
    {
        // hasOpenReplacement encapsulates the status check; we just test it returns true.
        $this->stubActiveSubscription();
        $this->stubIssueExistsAndDispatched();

        $this->replacementRepository
            ->shouldReceive('hasOpenReplacement')->once()->andReturn(true);

        $result = $this->service->canRequest(1, 100, 1);

        $this->assertFalse($result->canRequestReplacement);
    }

    public function test_denied_when_open_dispatched_replacement_exists(): void
    {
        $this->stubActiveSubscription();
        $this->stubIssueExistsAndDispatched();

        $this->replacementRepository
            ->shouldReceive('hasOpenReplacement')->once()->andReturn(true);

        $result = $this->service->canRequest(1, 100, 1);

        $this->assertFalse($result->canRequestReplacement);
    }

    public function test_allowed_when_failed_replacement_exists(): void
    {
        // Failed replacements do not count as open — hasOpenReplacement returns false.
        $this->stubActiveSubscription();
        $this->stubIssueExistsAndDispatched();

        $this->replacementRepository
            ->shouldReceive('hasOpenReplacement')->once()->andReturn(false);

        $result = $this->service->canRequest(1, 100, 1);

        $this->assertTrue($result->canRequestReplacement);
        $this->assertNull($result->blockedReason);
    }

    public function test_allowed_when_rejected_replacement_exists(): void
    {
        $this->stubActiveSubscription();
        $this->stubIssueExistsAndDispatched();

        $this->replacementRepository
            ->shouldReceive('hasOpenReplacement')->once()->andReturn(false);

        $result = $this->service->canRequest(1, 100, 1);

        $this->assertTrue($result->canRequestReplacement);
    }

    public function test_allowed_when_cancelled_replacement_exists(): void
    {
        $this->stubActiveSubscription();
        $this->stubIssueExistsAndDispatched();

        $this->replacementRepository
            ->shouldReceive('hasOpenReplacement')->once()->andReturn(false);

        $result = $this->service->canRequest(1, 100, 1);

        $this->assertTrue($result->canRequestReplacement);
    }

    public function test_allowed_for_valid_print_dispatched_issue_with_no_open_replacement(): void
    {
        $this->stubActiveSubscription();
        $this->stubIssueExistsAndDispatched();

        $this->replacementRepository
            ->shouldReceive('hasOpenReplacement')->once()->with(1, 100)->andReturn(false);

        $result = $this->service->canRequest(1, 100, 1);

        $this->assertTrue($result->canRequestReplacement);
        $this->assertNull($result->blockedReason);
    }

    // ── canRequestForIssues — bulk behaviour ──────────────────────────────────

    public function test_bulk_returns_empty_array_for_empty_issue_list(): void
    {
        $results = $this->service->canRequestForIssues(1, [], 1);

        $this->assertSame([], $results);
    }

    public function test_bulk_denies_all_when_subscription_not_found(): void
    {
        $this->subscriptionRepository
            ->shouldReceive('find')->once()->andReturn(null);

        $results = $this->service->canRequestForIssues(1, [10, 20], 1);

        $this->assertFalse($results[10]->canRequestReplacement);
        $this->assertFalse($results[20]->canRequestReplacement);
        $this->assertStringContainsString('not found', $results[10]->blockedReason);
    }

    public function test_bulk_denies_all_for_digital_subscription(): void
    {
        $subscription = $this->makeSubscription(deliveryType: 'digital');
        $this->subscriptionRepository->shouldReceive('find')->once()->andReturn($subscription);

        $results = $this->service->canRequestForIssues(1, [10, 20, 30], 1);

        $this->assertCount(3, $results);
        foreach ($results as $result) {
            $this->assertFalse($result->canRequestReplacement);
        }
    }

    public function test_bulk_returns_mixed_results_for_mixed_issue_list(): void
    {
        $this->stubActiveSubscription();

        // Issue 10: exists, dispatched, no open replacement → allowed
        // Issue 20: exists, NOT dispatched → denied
        // Issue 30: exists, dispatched, open replacement → denied

        $this->replacementRepository
            ->shouldReceive('findOpenReplacementsForIssues')
            ->once()
            ->with(1, [10, 20, 30])
            ->andReturn(collect([$this->makeReplacement(issueDeliveryId: 30)]));

        $this->replacementRepository
            ->shouldReceive('issueExistsForSubscription')
            ->with(10, 1)->andReturn(true);
        $this->replacementRepository
            ->shouldReceive('issueExistsForSubscription')
            ->with(20, 1)->andReturn(true);
        $this->replacementRepository
            ->shouldReceive('issueExistsForSubscription')
            ->with(30, 1)->andReturn(true);

        $this->replacementRepository
            ->shouldReceive('issueDeliveryWasDispatched')
            ->with(10)->andReturn(true);
        $this->replacementRepository
            ->shouldReceive('issueDeliveryWasDispatched')
            ->with(20)->andReturn(false);
        $this->replacementRepository
            ->shouldReceive('issueDeliveryWasDispatched')
            ->with(30)->andReturn(true);

        $results = $this->service->canRequestForIssues(1, [10, 20, 30], 1);

        $this->assertTrue($results[10]->canRequestReplacement);
        $this->assertFalse($results[20]->canRequestReplacement);
        $this->assertStringContainsString('dispatched', $results[20]->blockedReason);
        $this->assertFalse($results[30]->canRequestReplacement);
        $this->assertStringContainsString('already in progress', $results[30]->blockedReason);
    }

    public function test_bulk_only_fetches_open_replacements_once(): void
    {
        $this->stubActiveSubscription();

        // findOpenReplacementsForIssues must be called exactly once for the page.
        $this->replacementRepository
            ->shouldReceive('findOpenReplacementsForIssues')
            ->once()
            ->andReturn(collect([]));

        $this->replacementRepository
            ->shouldReceive('issueExistsForSubscription')->andReturn(true);
        $this->replacementRepository
            ->shouldReceive('issueDeliveryWasDispatched')->andReturn(true);

        $this->service->canRequestForIssues(1, [10, 20, 30], 1);

        $this->assertTrue(true);
    }

    // ── Test data helpers ─────────────────────────────────────────────────────

    private function makeSubscription(
        int    $siteId       = 1,
        string $status       = 'active',
        string $deliveryType = 'print',
    ): object {
        $sub = Mockery::mock(Subscription::class)->makePartial();
        $sub->id            = 1;
        $sub->site_id       = $siteId;
        $sub->status        = $status;
        $sub->delivery_type = $deliveryType;
        return $sub;
    }

    private function makeReplacement(int $issueDeliveryId): object
    {
        $r = Mockery::mock(FulfilmentReplacement::class)->makePartial();
        $r->issue_delivery_id = $issueDeliveryId;
        return $r;
    }

    /**
     * Return a minimal iterable collection for repository stubs.
     */
    private function makeCollection(array $items): object
    {
        $collection = Mockery::mock(\App\Framework\Support\Collection::class)->makePartial();
        $collection->shouldReceive('getIterator')
            ->andReturn(new \ArrayIterator($items));

        // Allow foreach by implementing \IteratorAggregate via the mock
        foreach ($items as $item) {
            // populated above
        }

        // Simple approach: return a real array-backed stub the service can foreach over.
        // If Collection is not iterable in tests, use a plain array-like object.
        return new class($items) implements \IteratorAggregate {
            public function __construct(private array $items) {}
            public function getIterator(): \ArrayIterator { return new \ArrayIterator($this->items); }
        };
    }

    private function stubActiveSubscription(): void
    {
        $subscription = $this->makeSubscription();
        $this->subscriptionRepository->shouldReceive('find')->andReturn($subscription);
    }

    private function stubIssueExistsAndDispatched(): void
    {
        $this->replacementRepository
            ->shouldReceive('issueExistsForSubscription')->andReturn(true);
        $this->replacementRepository
            ->shouldReceive('issueDeliveryWasDispatched')->andReturn(true);
    }

    // ── Lifecycle ─────────────────────────────────────────────────────────────

    protected function setUp(): void
    {
        parent::setUp();

        $this->replacementRepository  = Mockery::mock(FulfilmentReplacementRepository::class);
        $this->subscriptionRepository = Mockery::mock(SubscriptionRepository::class);

        $this->service = new FulfilmentReplacementEligibilityService(
            $this->subscriptionRepository,
            $this->replacementRepository,
        );
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}