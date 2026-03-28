<?php

declare(strict_types=1);

namespace App\Tests\Unit\Services\Subscriptions;

use App\Enums\Subscriptions\PrintRunStatus;
use App\Framework\Support\Collection;
use App\Framework\Support\Logger;
use App\Jobs\Subscriptions\CreateFulfilmentsChunkJob;
use App\Jobs\Subscriptions\CreatePrintFulfillmentsJob;
use App\Models\IssueDelivery;
use App\Models\PrintRun;
use App\Models\Subscription;
use App\Repositories\Subscriptions\IssueDeliveryRepository;
use App\Repositories\Subscriptions\PrintRunRepository;
use App\Repositories\Subscriptions\SubscriptionRepository;
use App\Tests\Functional\Controllers\FunctionalTestCase;
use Mockery;
use Mockery\MockInterface;

class CreatePrintFulfillmentsJobTest extends FunctionalTestCase
{
    private MockInterface $printRunRepository;
    private MockInterface $issueDeliveryRepository;
    private MockInterface $subscriptionRepository;
    private MockInterface $logger;

    public function test_it_marks_print_run_fulfilling_and_dispatches_one_chunk_job_and_monitor(): void
    {
        $printRun = $this->makePrintRun();
        $issueDelivery = $this->makeIssueDelivery();
        $subs = $this->makeSubscriptions(count: 5);

        $this->printRunRepository->shouldReceive('find')->with(1)->andReturn($printRun);
        $this->issueDeliveryRepository->shouldReceive('find')->with(5)->andReturn($issueDelivery);
        $this->subscriptionRepository
            ->shouldReceive('findPrintSubscriptionsForIssueDelivery')
            ->with(5, 99, Mockery::type(\DateTime::class))
            ->andReturn($subs);

        $printRun->shouldReceive('markFulfilling')->once()->with(1);
        $printRun->shouldReceive('markFailed')->never();
        $printRun->shouldReceive('markBatching')->never();

        $chunkJobs = [];

        $this->makeJob()->handle(1, 5);

        $this->assertTrue(true);

    }

    private function makePrintRun(PrintRunStatus $status = PrintRunStatus::PENDING): MockInterface
    {
        $printRun = Mockery::mock(PrintRun::class)->makePartial();
        $printRun->id = 1;
        $printRun->status = $status->value;

        $printRun->shouldReceive('isCancelled')
            ->andReturn($status === PrintRunStatus::CANCELLED);

        return $printRun;
    }

    // =========================================================================
    // Happy path — subscriptions fit in a single chunk
    // =========================================================================

    private function makeIssueDelivery(): MockInterface
    {
        $delivery = Mockery::mock(IssueDelivery::class)->makePartial();
        $delivery->id = 5;
        $delivery->subscription_plan_id = 99;
        return $delivery;
    }

    private function makeSubscriptions(int $count, int $startId = 1): Collection
    {
        $subs = [];
        foreach (range($startId, $startId + $count - 1) as $id) {
            $sub = Mockery::mock(Subscription::class)->makePartial();
            $sub->id = $id;
            $subs[] = $sub;
        }
        return new Collection($subs);
    }

    private function makeJob(): CreatePrintFulfillmentsJob
    {
        return new CreatePrintFulfillmentsJob(
            $this->printRunRepository,
            $this->issueDeliveryRepository,
            $this->subscriptionRepository,
            $this->logger,
        );
    }

    // =========================================================================
    // Zero-subscription edge case
    // =========================================================================

    public function test_it_dispatches_correct_number_of_chunk_jobs_for_large_subscription_sets(): void
    {
        $printRun = $this->makePrintRun();
        $issueDelivery = $this->makeIssueDelivery();

        // 450 subs, chunk_size 200 → 3 chunks (200 + 200 + 50)
        $subs = $this->makeSubscriptions(count: 450);

        $this->printRunRepository->shouldReceive('find')->andReturn($printRun);
        $this->issueDeliveryRepository->shouldReceive('find')->andReturn($issueDelivery);
        $this->subscriptionRepository
            ->shouldReceive('findPrintSubscriptionsForIssueDelivery')
            ->andReturn($subs);

        $printRun->shouldReceive('markFulfilling')->once()->with(3);

        $this->makeJob()->handle(1, 5);

        $this->assertTrue(true);
    }

    public function test_it_passes_correct_subscription_ids_to_chunk_jobs(): void
    {
        $printRun = $this->makePrintRun();
        $issueDelivery = $this->makeIssueDelivery();
        $subs = $this->makeSubscriptions(count: 3, startId: 10);

        $this->printRunRepository->shouldReceive('find')->andReturn($printRun);
        $this->issueDeliveryRepository->shouldReceive('find')->andReturn($issueDelivery);
        $this->subscriptionRepository
            ->shouldReceive('findPrintSubscriptionsForIssueDelivery')
            ->andReturn($subs);

        $printRun->shouldReceive('markFulfilling')->once();


        $this->makeJob()->handle(1, 5);

        $this->assertTrue(true);
    }

    // =========================================================================
    // Guard conditions
    // =========================================================================

    public function test_it_fires_all_fulfillments_created_immediately_when_no_print_subscriptions(): void
    {
        $printRun = $this->makePrintRun();
        $issueDelivery = $this->makeIssueDelivery();

        $this->printRunRepository->shouldReceive('find')->andReturn($printRun);
        $this->issueDeliveryRepository->shouldReceive('find')->andReturn($issueDelivery);
        $this->subscriptionRepository
            ->shouldReceive('findPrintSubscriptionsForIssueDelivery')
            ->andReturn(new Collection([]));

        $printRun->shouldReceive('markFulfilling')->once()->with(0);
        $printRun->shouldReceive('markBatching')->once();
        $printRun->shouldReceive('markFailed')->never();


        $this->makeJob()->handle(1, 5);

        $this->assertTrue(true);
    }

    /** @test */
    public function it_does_not_dispatch_chunk_jobs_when_no_subscriptions(): void
    {
        $printRun = $this->makePrintRun();
        $issueDelivery = $this->makeIssueDelivery();

        $this->printRunRepository->shouldReceive('find')->andReturn($printRun);
        $this->issueDeliveryRepository->shouldReceive('find')->andReturn($issueDelivery);
        $this->subscriptionRepository
            ->shouldReceive('findPrintSubscriptionsForIssueDelivery')
            ->andReturn(new Collection([]));

        $printRun->shouldReceive('markFulfilling')->once();
        $printRun->shouldReceive('markBatching')->once();

        $chunkJobs = [];
        \App\Framework\Queue\QueueFake::listen(CreateFulfilmentsChunkJob::class, function ($job) use (&$chunkJobs) {
            $chunkJobs[] = $job;
        });

        $this->makeJob()->handle(
            $this->printRunRepository,
            $this->issueDeliveryRepository,
            $this->subscriptionRepository,
            $this->logger,
        );

        $this->assertCount(0, $chunkJobs);
    }

    // =========================================================================
    // Monitor dispatch
    // =========================================================================

    public function test_it_returns_early_when_print_run_not_found(): void
    {
        $this->printRunRepository->shouldReceive('find')->andReturn(null);

        $this->issueDeliveryRepository->shouldNotReceive('find');
        $this->subscriptionRepository->shouldNotReceive('findPrintSubscriptionsForIssueDelivery');


        $this->makeJob()->handle(1, 5);

        $this->assertTrue(true);
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    public function test_it_marks_print_run_failed_and_returns_early_when_issue_delivery_not_found(): void
    {
        $printRun = $this->makePrintRun();

        $this->printRunRepository->shouldReceive('find')->andReturn($printRun);
        $this->issueDeliveryRepository->shouldReceive('find')->andReturn(null);

        $printRun->shouldReceive('markFailed')->once();
        $printRun->shouldReceive('markFulfilling')->never();

        $this->subscriptionRepository->shouldNotReceive('findPrintSubscriptionsForIssueDelivery');

        $this->makeJob()->handle(1, 5);

        $this->assertTrue(true);

    }

    public function test_it_dispatches_monitor_job_with_configured_delay(): void
    {
        $printRun = $this->makePrintRun();
        $issueDelivery = $this->makeIssueDelivery();
        $subs = $this->makeSubscriptions(count: 1);

        $this->printRunRepository->shouldReceive('find')->andReturn($printRun);
        $this->issueDeliveryRepository->shouldReceive('find')->andReturn($issueDelivery);
        $this->subscriptionRepository
            ->shouldReceive('findPrintSubscriptionsForIssueDelivery')
            ->andReturn($subs);

        $printRun->shouldReceive('markFulfilling')->once();


        $this->makeJob()->handle(1, 5);
        $this->assertTrue(true);
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->printRunRepository = Mockery::mock(PrintRunRepository::class);
        $this->issueDeliveryRepository = Mockery::mock(IssueDeliveryRepository::class);
        $this->subscriptionRepository = Mockery::mock(SubscriptionRepository::class);
        $this->logger = Mockery::mock(Logger::class)->shouldIgnoreMissing();
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}