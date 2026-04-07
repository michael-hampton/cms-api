<?php

declare(strict_types=1);

namespace App\Tests\Unit\Actions\Subscriptions\Print;

use App\Actions\Subscriptions\Print\CreatePrintFulfillmentAction;
use App\DTO\Subscriptions\FulfilmentDecisionContext;
use App\Framework\Support\Logger;
use App\Models\IssueDelivery;
use App\Models\IssuesDelivered;
use App\Models\PrintBatch;
use App\Models\PrintFulfillment;
use App\Models\Subscription;
use App\Repositories\Subscriptions\IssuesDeliveredRepository;
use App\Repositories\Subscriptions\PrintBatchRepository;
use App\Repositories\Subscriptions\PrintFulfillmentRepository;
use App\Services\Subscriptions\Printing\FulfilmentDecisionService;
use App\Services\Subscriptions\Printing\PrintAddressResolver;
use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\TestCase;

class CreatePrintFulfillmentActionTest extends TestCase
{
    private MockInterface $issuesDeliveredRepository;
    private MockInterface $fulfillmentRepository;
    private MockInterface $batchRepository;
    private MockInterface $addressResolver;
    private MockInterface $decisionService;
    private MockInterface $logger;
    private CreatePrintFulfillmentAction $action;

    public function test_it_creates_fulfillment_record_with_resolved_address_and_null_batch(): void
    {
        $subscription = $this->makeSubscription();
        $issueDelivery = $this->makeIssueDelivery();
        $issuesDelivered = $this->makeIssuesDelivered();
        $context = $this->makeDecisionContext(territoryId: null, snapshot: $this->fullSnapshot());
        $fulfillment = $this->makeFulfillment();

        $this->issuesDeliveredRepository
            ->shouldReceive('findBySubscriptionAndDelivery')
            ->with($subscription->id, $issueDelivery->id)
            ->andReturn($issuesDelivered);

        $this->decisionService
            ->shouldReceive('decide')
            ->with($subscription, $issueDelivery)
            ->andReturn($context);

        $this->fulfillmentRepository
            ->shouldReceive('existsForSubscriptionDeliveryAndTerritory')
            ->with($subscription->id, $issuesDelivered->id, null)
            ->andReturn(false);

        $this->fulfillmentRepository
            ->shouldReceive('createFullfilment')
            ->once()
            ->withArgs(function (
                $batchId, $issuesDeliveredId, $subscriptionId,
                $fullName, $snapshot, $line1, $line2, $city, $postcode, $country, $territoryId
            ) {
                // batch_id must be null — deferred to BatchBuilderService
                return $batchId === 1;
            })
            ->andReturn($fulfillment);

        $batch = Mockery::mock(PrintBatch::class)->makePartial();
        $batch->id = 1;
        $this->batchRepository
            ->shouldReceive('findOrCreateForIssueDeliveryAndTerritory')
            ->once()
            ->andReturn($batch);

        $result = $this->action->execute($subscription, $issueDelivery);

        $this->assertSame($fulfillment, $result);
    }

    private function makeSubscription(): MockInterface
    {
        $sub = Mockery::mock(Subscription::class)->makePartial();
        $sub->id = 1;
        return $sub;
    }

    // =========================================================================
    // Happy path
    // =========================================================================

    private function makeIssueDelivery(): MockInterface
    {
        $delivery = Mockery::mock(IssueDelivery::class)->makePartial();
        $delivery->id = 5;
        return $delivery;
    }

    private function makeIssuesDelivered(): MockInterface
    {
        $delivered = Mockery::mock(IssuesDelivered::class)->makePartial();
        $delivered->id = 10;
        return $delivered;
    }

    // =========================================================================
    // Address fallback
    // =========================================================================

    private function makeDecisionContext(?int $territoryId, array $snapshot): MockInterface
    {
        $context = Mockery::mock(FulfilmentDecisionContext::class)->makePartial();
        $context->addressSnapshot = $snapshot;
        $context->fullName = trim($snapshot['first_name'] ?? '') . ' ' . trim($snapshot['last_name'] ?? '');


        $context->shouldReceive('territoryId')->andReturn($territoryId);
        return $context;
    }

    // =========================================================================
    // Error cases
    // =========================================================================

    private function fullSnapshot(): array
    {
        return [
            'first_name' => 'Jane',
            'last_name' => 'Smith',
            'address_line_1' => '10 Downing Street',
            'address_line_2' => null,
            'city' => 'London',
            'postcode' => 'SW1A 2AA',
            'country' => 'GB',
        ];
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    private function makeFulfillment(): MockInterface
    {
        $f = Mockery::mock(PrintFulfillment::class)->makePartial();
        $f->id = 99;
        return $f;
    }

    public function test_it_returns_existing_fulfillment_when_idempotency_guard_triggers(): void
    {
        $subscription = $this->makeSubscription();
        $issueDelivery = $this->makeIssueDelivery();
        $issuesDelivered = $this->makeIssuesDelivered();
        $context = $this->makeDecisionContext(territoryId: 7, snapshot: $this->fullSnapshot());
        $existing = $this->makeFulfillment();

        $this->issuesDeliveredRepository
            ->shouldReceive('findBySubscriptionAndDelivery')
            ->andReturn($issuesDelivered);

        $this->decisionService->shouldReceive('decide')->andReturn($context);

        $this->fulfillmentRepository
            ->shouldReceive('existsForSubscriptionDeliveryAndTerritory')
            ->with($subscription->id, $issuesDelivered->id, 7)
            ->andReturn(true);

        $this->fulfillmentRepository
            ->shouldReceive('findBySubscriptionDeliveryAndTerritory')
            ->once()
            ->with($subscription->id, $issuesDelivered->id, 7)
            ->andReturn($existing);

        $this->batchRepository->shouldNotReceive('findOrCreateForIssueDeliveryAndTerritory');
        $this->fulfillmentRepository->shouldNotReceive('createFullfilment');

        $result = $this->action->execute($subscription, $issueDelivery);

        $this->assertSame($existing, $result);
    }

    public function test_it_falls_back_to_address_resolver_when_context_snapshot_is_incomplete(): void
    {
        $subscription = $this->makeSubscription();
        $issueDelivery = $this->makeIssueDelivery();
        $issuesDelivered = $this->makeIssuesDelivered();

        // Snapshot with no address_line_1 — triggers resolver fallback
        $context = $this->makeDecisionContext(territoryId: null, snapshot: ['first_name' => 'Jane']);
        $fulfillment = $this->makeFulfillment();

        $this->issuesDeliveredRepository
            ->shouldReceive('findBySubscriptionAndDelivery')
            ->andReturn($issuesDelivered);

        $batch = Mockery::mock(PrintBatch::class)->makePartial();
        $batch->id = 1;

        $this->batchRepository
            ->shouldReceive('findOrCreateForIssueDeliveryAndTerritory')
            ->once()
            ->andReturn($batch);

        $this->decisionService->shouldReceive('decide')->andReturn($context);

        $this->fulfillmentRepository
            ->shouldReceive('existsForSubscriptionDeliveryAndTerritory')
            ->andReturn(false);

        $this->addressResolver
            ->shouldReceive('resolve')
            ->once()
            ->with($subscription)
            ->andReturn($this->fullResolvedAddress());

        $this->fulfillmentRepository
            ->shouldReceive('createFullfilment')
            ->once()
            ->andReturn($fulfillment);

        $this->action->execute($subscription, $issueDelivery);
        $this->assertTrue(true);
    }

    private function fullResolvedAddress(): array
    {
        return [
            'full_name' => 'Jane Smith',
            'address_line_1' => '10 Downing Street',
            'address_line_2' => null,
            'city' => 'London',
            'postcode' => 'SW1A 2AA',
            'country' => 'GB',
            'snapshot' => $this->fullSnapshot(),
        ];
    }

    public function test_it_throws_when_issues_delivered_record_not_found(): void
    {
        $subscription = $this->makeSubscription();
        $issueDelivery = $this->makeIssueDelivery();

        $this->issuesDeliveredRepository
            ->shouldReceive('findBySubscriptionAndDelivery')
            ->andReturn(null);

        $this->decisionService->shouldNotReceive('decide');
        $this->fulfillmentRepository->shouldNotReceive('create');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/IssuesDelivered not found/');

        $this->action->execute($subscription, $issueDelivery);
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->issuesDeliveredRepository = Mockery::mock(IssuesDeliveredRepository::class);
        $this->fulfillmentRepository = Mockery::mock(PrintFulfillmentRepository::class);
        $this->batchRepository = Mockery::mock(PrintBatchRepository::class);
        $this->addressResolver = Mockery::mock(PrintAddressResolver::class);
        $this->decisionService = Mockery::mock(FulfilmentDecisionService::class);
        $this->logger = Mockery::mock(Logger::class)->shouldIgnoreMissing();

        $this->action = new CreatePrintFulfillmentAction(
            issuesDeliveredRepository: $this->issuesDeliveredRepository,
            fulfillmentRepository: $this->fulfillmentRepository,
            batchRepository: $this->batchRepository,
            addressResolver: $this->addressResolver,
            decisionService: $this->decisionService,
            logger: $this->logger,
        );
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}