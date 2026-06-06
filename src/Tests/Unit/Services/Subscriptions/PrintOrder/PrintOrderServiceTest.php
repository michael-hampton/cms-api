<?php

declare(strict_types=1);

namespace App\Tests\Unit\Services\Subscriptions\PrintOrder;

use App\DTO\Subscriptions\PrintOrder\PrintOrderLine;
use App\DTO\Subscriptions\PrintOrder\PrintOrderRecord;
use App\DTO\Subscriptions\PrintOrder\PrintOrderResult;
use App\Enums\Subscriptions\PrintRegion;
use App\Events\Subscriptions\PrintOrderGenerated;
use App\Framework\Support\Collection;
use App\Framework\Support\Logger;
use App\Models\IssueDelivery;
use App\Models\IssueDeliveryRegion;
use App\Repositories\Subscriptions\IssueDeliveryRegionRepository;
use App\Repositories\Subscriptions\PrintOrderRepository;
use App\Services\Subscriptions\PrintOrder\PrintOrderCalculator;
use App\Services\Subscriptions\PrintOrder\PrintOrderService;
use App\Tests\Support\CapturingEventDispatcher;
use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\TestCase;

final class PrintOrderServiceTest extends TestCase
{
    private MockInterface&PrintOrderRepository           $repository;
    private MockInterface&IssueDeliveryRegionRepository  $regionRepository;
    private MockInterface&PrintOrderCalculator           $calculator;
    private MockInterface&Logger                         $logger;
    private PrintOrderService                            $service;
    private CapturingEventDispatcher                     $events;

    protected function setUp(): void
    {
        parent::setUp();

        $this->repository       = Mockery::mock(PrintOrderRepository::class);
        $this->regionRepository = Mockery::mock(IssueDeliveryRegionRepository::class);
        $this->calculator       = Mockery::mock(PrintOrderCalculator::class);
        $this->logger           = Mockery::mock(Logger::class)->shouldIgnoreMissing();
        $this->events           = CapturingEventDispatcher::fake();

        $this->service = new PrintOrderService(
            $this->repository,
            $this->regionRepository,
            $this->calculator,
            $this->logger,
        );
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    // ── Eligibility guards ────────────────────────────────────────────────────

    public function test_throws_when_print_order_date_is_missing(): void
    {
        $issue = $this->makeIssue(id: 1, printOrderDate: null, printOrderDone: false);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/no print_order_date/i');

        $this->service->generate($issue);
    }

    public function test_throws_when_print_order_already_done(): void
    {
        $issue = $this->makeIssue(id: 1, printOrderDate: '2024-03-15', printOrderDone: true);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/already been generated/i');

        $this->service->generate($issue);
    }

    // ── Non-regional path ─────────────────────────────────────────────────────

    public function test_non_regional_delegates_counts_to_repository_and_calculator(): void
    {
        $issue  = $this->makeIssue(
            id: 10,
            printOrderDate: '2024-03-15',
            printOrderDone: false,
            printOverrun: 10,
            additionalStock: 5,
            exportOverrun: 8,
        );
        $result = $this->makeResult(issueDeliveryId: 10, ukSubs: 100, exportSubs: 50);

        $this->regionRepository
            ->shouldReceive('findByIssueDelivery')
            ->once()
            ->with(10)
            ->andReturn(new Collection([]));

        $this->repository
            ->shouldReceive('countSubscribersByRegion')
            ->once()
            ->with($issue)
            ->andReturn(['uk' => 100, 'export' => 50]);

        $this->calculator
            ->shouldReceive('calculateNonRegional')
            ->once()
            ->with(10, 100, 50, 10, 5, 8)
            ->andReturn($result);

        $this->repository
            ->shouldReceive('markPrintOrderDone')
            ->once()
            ->with($issue, 150); // 100 + 50

        $returned = $this->service->generate($issue);

        $this->assertSame($result, $returned);
        $this->events->assertDispatched(
            PrintOrderGenerated::class,
            fn(PrintOrderGenerated $event): bool => $event->issueDelivery === $issue
                && $event->result === $result
        );
    }

    public function test_non_regional_marks_print_order_done_with_correct_subscriber_total(): void
    {
        $issue  = $this->makeIssue(id: 5, printOrderDate: '2024-03-15');
        $result = $this->makeResult(issueDeliveryId: 5, ukSubs: 200, exportSubs: 75);

        $this->regionRepository->shouldReceive('findByIssueDelivery')->andReturn(new Collection([]));
        $this->repository->shouldReceive('countSubscribersByRegion')->andReturn(['uk' => 200, 'export' => 75]);
        $this->calculator->shouldReceive('calculateNonRegional')->andReturn($result);

        $this->repository
            ->shouldReceive('markPrintOrderDone')
            ->once()
            ->with($issue, 275); // 200 + 75 (no surplus)

        $this->service->generate($issue);

        $this->assertTrue(true);
    }

    // ── Regional path ─────────────────────────────────────────────────────────

    public function test_regional_delegates_per_edition_counts_to_repository(): void
    {
        $issue    = $this->makeIssue(id: 20, printOrderDate: '2024-03-15');
        $edition1 = $this->makeEdition(id: 100, ukSurplus: 5, exportSurplus: 2);
        $edition2 = $this->makeEdition(id: 101, ukSurplus: 3, exportSurplus: 1);
        $result   = $this->makeResult(issueDeliveryId: 20, ukSubs: 140, exportSubs: 30);

        $this->regionRepository
            ->shouldReceive('findByIssueDelivery')
            ->with(20)
            ->andReturn(new Collection([$edition1, $edition2]));

        $this->repository
            ->shouldReceive('countSubscribersByRegionForEdition')
            ->once()
            ->with($issue, $edition1)
            ->andReturn(['uk' => 80, 'export' => 20]);

        $this->repository
            ->shouldReceive('countSubscribersByRegionForEdition')
            ->once()
            ->with($issue, $edition2)
            ->andReturn(['uk' => 60, 'export' => 10]);

        $this->calculator
            ->shouldReceive('calculateRegional')
            ->once()
            ->with(20, [
                ['regional_edition_id' => 100, 'uk_subscribers' => 80, 'export_subscribers' => 20, 'uk_surplus' => 5, 'export_surplus' => 2],
                ['regional_edition_id' => 101, 'uk_subscribers' => 60, 'export_subscribers' => 10, 'uk_surplus' => 3, 'export_surplus' => 1],
            ])
            ->andReturn($result);

        $this->repository->shouldReceive('markPrintOrderDone')->once();

        $this->service->generate($issue);

        $this->assertTrue(true);
    }

    public function test_regional_does_not_call_non_regional_count_method(): void
    {
        $issue   = $this->makeIssue(id: 20, printOrderDate: '2024-03-15');
        $edition = $this->makeEdition(id: 100, ukSurplus: 0, exportSurplus: 0);
        $result  = $this->makeResult(issueDeliveryId: 20, ukSubs: 10, exportSubs: 5);

        $this->regionRepository
            ->shouldReceive('findByIssueDelivery')
            ->andReturn(new Collection([$edition]));

        $this->repository
            ->shouldReceive('countSubscribersByRegionForEdition')
            ->andReturn(['uk' => 10, 'export' => 5]);

        $this->repository
            ->shouldNotReceive('countSubscribersByRegion');

        $this->calculator->shouldReceive('calculateRegional')->andReturn($result);
        $this->repository->shouldReceive('markPrintOrderDone');

        $this->service->generate($issue);

        $this->assertTrue(true);
    }

    // ── Event emission ────────────────────────────────────────────────────────

//    public function test_emits_print_order_generated_event_on_success(): void
//    {
//        $issue  = $this->makeIssue(id: 30, printOrderDate: '2024-03-15');
//        $result = $this->makeResult(issueDeliveryId: 30, ukSubs: 50, exportSubs: 25);
//
//        $this->regionRepository->shouldReceive('findByIssueDelivery')->andReturn(new Collection([]));
//        $this->repository->shouldReceive('countSubscribersByRegion')->andReturn(['uk' => 50, 'export' => 25]);
//        $this->calculator->shouldReceive('calculateNonRegional')->andReturn($result);
//        $this->repository->shouldReceive('markPrintOrderDone');
//
//        // Capture events — we verify the type and payload below.
//        $firedEvents = [];
//        $origEvent   = null;
//
//        // Patch the global event() helper if available, or verify via
//        // a side-effect: markPrintOrderDone was called (the event fires after it).
//        // The true integration test of event dispatch is in a feature test;
//        // here we verify the service calls the right collaborators in order.
//        $this->repository
//            ->shouldReceive('markPrintOrderDone')
//            ->once()
//            ->ordered();
//
//        // Generate — if markPrintOrderDone was called exactly once in the
//        // correct order, the event path was reached.
//        $returned = $this->service->generate($issue);
//        $this->assertSame($result, $returned);
//    }

    // ── Return value ──────────────────────────────────────────────────────────

    public function test_returns_the_result_from_calculator(): void
    {
        $issue  = $this->makeIssue(id: 99, printOrderDate: '2024-03-15');
        $result = $this->makeResult(issueDeliveryId: 99, ukSubs: 10, exportSubs: 5);

        $this->regionRepository->shouldReceive('findByIssueDelivery')->andReturn(new Collection([]));
        $this->repository->shouldReceive('countSubscribersByRegion')->andReturn(['uk' => 10, 'export' => 5]);
        $this->calculator->shouldReceive('calculateNonRegional')->andReturn($result);
        $this->repository->shouldReceive('markPrintOrderDone');

        $this->assertSame($result, $this->service->generate($issue));
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function makeIssue(
        int     $id,
        ?string $printOrderDate  = '2024-03-15',
        bool    $printOrderDone  = false,
        int     $printOverrun    = 0,
        int     $additionalStock = 0,
        int     $exportOverrun   = 0,
    ): IssueDelivery {
        $issue = Mockery::mock(IssueDelivery::class)->makePartial();
        $issue->id                = $id;
        $issue->print_order_date  = $printOrderDate;
        $issue->print_order_done  = $printOrderDone;
        $issue->print_overrun     = $printOverrun;
        $issue->additional_stock  = $additionalStock;
        $issue->export_overrun    = $exportOverrun;
        $issue->on_sale_date      = null;
        $issue->issue_title       = "Test Issue #{$id}";

        return $issue;
    }

    private function makeEdition(int $id, int $ukSurplus, int $exportSurplus): IssueDeliveryRegion
    {
        $edition = Mockery::mock(IssueDeliveryRegion::class)->makePartial();
        $edition->id             = $id;
        $edition->uk_surplus     = $ukSurplus;
        $edition->export_surplus = $exportSurplus;
        $edition->territory_id   = $id * 10;

        return $edition;
    }

    private function makeResult(int $issueDeliveryId, int $ukSubs, int $exportSubs): PrintOrderResult
    {
        $ukLine     = new PrintOrderLine(PrintRegion::UK,     $ukSubs,     0);
        $exportLine = new PrintOrderLine(PrintRegion::Export, $exportSubs, 0);
        $record     = new PrintOrderRecord($issueDeliveryId, null, $ukLine, $exportLine);

        return new PrintOrderResult($issueDeliveryId, [$record]);
    }
}
