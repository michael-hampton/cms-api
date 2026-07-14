<?php

declare(strict_types=1);

namespace App\Tests\Unit\Services\Subscriptions\BackIssue;

use App\Framework\Database\Database;
use App\Framework\Support\Collection;
use App\Framework\Support\Logger;
use App\Models\IssueDelivery;
use App\Models\PrintBatch;
use App\Models\PrintFulfillment;
use App\Models\SubscriptionIssueFulfilment;
use App\Repositories\Subscriptions\IssueDeliveryRepository;
use App\Repositories\Subscriptions\PrintBatchRepository;
use App\Repositories\Subscriptions\PrintFulfillmentRepository;
use App\Repositories\Subscriptions\SubscriptionIssueFulfilmentRepository;
use App\Services\Subscriptions\BackIssue\BackIssueReplacementCopyDispatchService;
use App\Services\Subscriptions\Printing\Format\PrintExportFormatStrategy;
use App\Services\Subscriptions\Printing\Transport\PrintExportTransport;
use Mockery;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for BackIssueReplacementCopyDispatchService ("the replacement
 * copy process").
 *
 * Verifies:
 *   - Extraction always starts from unfulfilled back_issue rows
 *     (SubscriptionIssueFulfilmentRepository::findUnfulfilledBackIssues).
 *   - Nothing is uploaded or written back when there is nothing outstanding.
 *   - Rows are grouped by their PrintFulfillment batch and exported through
 *     the real PrintExportFormatStrategy/PrintExportTransport — the same
 *     vendor-facing shape the standard pipeline uses.
 *   - On success: only the extracted PrintFulfillment rows are marked
 *     exported (not the whole batch), the writeback runs inside
 *     Database::transaction(), and each corresponding SubscriptionIssueFulfilment
 *     is marked fulfilled.
 *   - On a failed vendor upload: the exception propagates and no row in
 *     that group is written back.
 */
class BackIssueReplacementCopyDispatchServiceTest extends TestCase
{
    private SubscriptionIssueFulfilmentRepository $fulfilmentRepository;
    private PrintFulfillmentRepository $printFulfillmentRepository;
    private PrintBatchRepository $printBatchRepository;
    private IssueDeliveryRepository $issueDeliveryRepository;
    private PrintExportFormatStrategy $formatStrategy;
    private PrintExportTransport $transport;
    private Database $database;
    private Logger $logger;
    private BackIssueReplacementCopyDispatchService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->fulfilmentRepository = Mockery::mock(SubscriptionIssueFulfilmentRepository::class);
        $this->printFulfillmentRepository = Mockery::mock(PrintFulfillmentRepository::class);
        $this->printBatchRepository = Mockery::mock(PrintBatchRepository::class);
        $this->issueDeliveryRepository = Mockery::mock(IssueDeliveryRepository::class);
        $this->formatStrategy = Mockery::mock(PrintExportFormatStrategy::class);
        $this->transport = Mockery::mock(PrintExportTransport::class);
        $this->database = Mockery::mock(Database::class);
        $this->logger = Mockery::mock(Logger::class)->shouldIgnoreMissing();

        $this->service = new BackIssueReplacementCopyDispatchService(
            $this->fulfilmentRepository,
            $this->printFulfillmentRepository,
            $this->printBatchRepository,
            $this->issueDeliveryRepository,
            $this->formatStrategy,
            $this->transport,
            $this->database,
            $this->logger,
        );
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_does_nothing_when_no_back_issues_are_outstanding(): void
    {
        $this->fulfilmentRepository
            ->shouldReceive('findUnfulfilledBackIssues')
            ->once()
            ->andReturn(new Collection([]));

        $this->printFulfillmentRepository->shouldNotReceive('findBySubscriptionIssueFulfilmentIds');
        $this->transport->shouldNotReceive('upload');
        $this->database->shouldNotReceive('transaction');

        $result = $this->service->dispatch();

        $this->assertSame(0, $result);
    }

    public function test_exports_via_the_real_print_format_strategy_and_marks_rows_fulfilled(): void
    {
        $subscriptionIssueFulfilments = new Collection([
            $this->makeSubscriptionIssueFulfilment(10),
            $this->makeSubscriptionIssueFulfilment(11),
        ]);
        $printFulfilments = new Collection([
            $this->makePrintFulfilment(1, 10, 200),
            $this->makePrintFulfilment(2, 11, 200),
        ]);
        $batch = $this->makeBatch(200, 300);
        $issue = $this->makeIssue(300, 'Issue #42');

        $this->fulfilmentRepository
            ->shouldReceive('findUnfulfilledBackIssues')
            ->once()
            ->andReturn($subscriptionIssueFulfilments);

        $this->printFulfillmentRepository
            ->shouldReceive('findBySubscriptionIssueFulfilmentIds')
            ->once()
            ->with([10, 11])
            ->andReturn($printFulfilments);

        $this->printBatchRepository->shouldReceive('find')->once()->with(200)->andReturn($batch);
        $this->issueDeliveryRepository->shouldReceive('find')->once()->with(300)->andReturn($issue);

        $this->formatStrategy
            ->shouldReceive('generate')
            ->once()
            ->with(200, Mockery::on(fn ($rows) => count($rows) === 2), ['id' => 300, 'title' => 'Issue #42'])
            ->andReturn('csv-contents');

        $this->formatStrategy->shouldReceive('extension')->andReturn('csv');

        $this->transport
            ->shouldReceive('upload')
            ->once()
            ->with(Mockery::type('string'), 'csv-contents');

        $this->database
            ->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(fn (callable $callback) => $callback());

        $this->printFulfillmentRepository
            ->shouldReceive('markExported')
            ->once()
            ->with(Mockery::on(function ($ids) {
                $sorted = $ids;
                sort($sorted);
                return $sorted === [1, 2];
            }));

        $this->fulfilmentRepository->shouldReceive('markFulfilled')->once()->with(10, Mockery::type(\DateTimeInterface::class));
        $this->fulfilmentRepository->shouldReceive('markFulfilled')->once()->with(11, Mockery::type(\DateTimeInterface::class));

        $result = $this->service->dispatch();

        $this->assertSame(2, $result);
    }

    public function test_upload_failure_propagates_and_nothing_is_written_back(): void
    {
        $subscriptionIssueFulfilments = new Collection([$this->makeSubscriptionIssueFulfilment(10)]);
        $printFulfilments = new Collection([$this->makePrintFulfilment(1, 10, 200)]);
        $batch = $this->makeBatch(200, 300);
        $issue = $this->makeIssue(300, 'Issue #42');

        $this->fulfilmentRepository->shouldReceive('findUnfulfilledBackIssues')->once()->andReturn($subscriptionIssueFulfilments);
        $this->printFulfillmentRepository->shouldReceive('findBySubscriptionIssueFulfilmentIds')->once()->andReturn($printFulfilments);
        $this->printBatchRepository->shouldReceive('find')->once()->andReturn($batch);
        $this->issueDeliveryRepository->shouldReceive('find')->once()->andReturn($issue);

        $this->formatStrategy->shouldReceive('generate')->once()->andReturn('csv-contents');
        $this->formatStrategy->shouldReceive('extension')->andReturn('csv');

        $this->transport
            ->shouldReceive('upload')
            ->once()
            ->andThrow(new \RuntimeException('vendor unreachable'));

        $this->database->shouldNotReceive('transaction');
        $this->printFulfillmentRepository->shouldNotReceive('markExported');
        $this->fulfilmentRepository->shouldNotReceive('markFulfilled');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('vendor unreachable');

        $this->service->dispatch();
    }

    public function test_row_missing_a_print_fulfilment_is_skipped_without_failing_the_run(): void
    {
        // e.g. a digital-delivery back-issue order — nothing to physically dispatch.
        $subscriptionIssueFulfilments = new Collection([$this->makeSubscriptionIssueFulfilment(10)]);

        $this->fulfilmentRepository->shouldReceive('findUnfulfilledBackIssues')->once()->andReturn($subscriptionIssueFulfilments);
        $this->printFulfillmentRepository
            ->shouldReceive('findBySubscriptionIssueFulfilmentIds')
            ->once()
            ->with([10])
            ->andReturn(new Collection([]));

        $this->transport->shouldNotReceive('upload');
        $this->database->shouldNotReceive('transaction');

        $result = $this->service->dispatch();

        $this->assertSame(0, $result);
    }

    private function makeSubscriptionIssueFulfilment(int $id): SubscriptionIssueFulfilment
    {
        $fulfilment = Mockery::mock(SubscriptionIssueFulfilment::class)->makePartial();
        $fulfilment->id = $id;

        return $fulfilment;
    }

    private function makePrintFulfilment(int $id, int $subscriptionIssueFulfilmentId, int $batchId): PrintFulfillment
    {
        $fulfilment = Mockery::mock(PrintFulfillment::class)->makePartial();
        $fulfilment->id = $id;
        $fulfilment->subscription_issue_fulfilment_id = $subscriptionIssueFulfilmentId;
        $fulfilment->batch_id = $batchId;

        return $fulfilment;
    }

    private function makeBatch(int $id, int $issueDeliveryId): PrintBatch
    {
        $batch = Mockery::mock(PrintBatch::class)->makePartial();
        $batch->id = $id;
        $batch->issue_delivery_id = $issueDeliveryId;

        return $batch;
    }

    private function makeIssue(int $id, string $title): IssueDelivery
    {
        $issue = Mockery::mock(IssueDelivery::class)->makePartial();
        $issue->id = $id;
        $issue->issue_title = $title;

        return $issue;
    }
}
