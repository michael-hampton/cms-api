<?php

declare(strict_types=1);

namespace App\Tests\Unit\Services\Subscriptions;

use App\Actions\Subscriptions\Print\CreatePrintFulfillmentAction;
use App\Enums\Subscriptions\PrintRunStatus;
use App\Framework\Container;
use App\Framework\Support\Logger;
use App\Jobs\Subscriptions\CreateFulfilmentsChunkJob;
use App\Models\IssueDelivery;
use App\Models\PrintRun;
use App\Models\Subscription;
use App\Repositories\Subscriptions\IssueDeliveryRepository;
use App\Repositories\Subscriptions\PrintRunRepository;
use App\Repositories\Subscriptions\SubscriptionRepository;
use App\Services\Workflow\WorkflowRunRecorder;
use App\Services\Workflow\WorkflowRunRecorderFactory;
use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\TestCase;

class CreateFulfilmentsChunkJobTest extends TestCase
{
    private MockInterface $printRunRepository;
    private MockInterface $issueDeliveryRepository;
    private MockInterface $subscriptionRepository;
    private MockInterface $fulfillmentAction;
    private MockInterface $logger;

    protected function setUp(): void
    {
        parent::setUp();

        $this->printRunRepository = Mockery::mock(PrintRunRepository::class);
        $this->issueDeliveryRepository = Mockery::mock(IssueDeliveryRepository::class);
        $this->subscriptionRepository = Mockery::mock(SubscriptionRepository::class);
        $this->fulfillmentAction = Mockery::mock(CreatePrintFulfillmentAction::class);
        $this->logger = Mockery::mock(Logger::class)->shouldIgnoreMissing();

        $container = Container::getInstance();
        $recorder = Mockery::mock(WorkflowRunRecorder::class)->shouldIgnoreMissing();
        $recorderFactory = Mockery::mock(WorkflowRunRecorderFactory::class)->shouldIgnoreMissing();
        $recorderFactory->shouldReceive('forPrintRun')->andReturn($recorder)->byDefault();

        $container->instance(PrintRunRepository::class, $this->printRunRepository);
        $container->instance(IssueDeliveryRepository::class, $this->issueDeliveryRepository);
        $container->instance(SubscriptionRepository::class, $this->subscriptionRepository);
        $container->instance(CreatePrintFulfillmentAction::class, $this->fulfillmentAction);
        $container->instance(Logger::class, $this->logger);
        $container->instance(WorkflowRunRecorderFactory::class, $recorderFactory);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    // =========================================================================
    // Happy path
    // =========================================================================

    public function test_it_creates_fulfillments_for_all_subscriptions_in_the_chunk(): void
    {
        $printRun = $this->makePrintRun(totalChunks: 1, fulfilledChunks: 0);
        $issueDelivery = $this->makeIssueDelivery();
        $subscription = $this->makeSubscription(id: 10);

        $this->printRunRepository->shouldReceive('find')->with(1)->andReturn($printRun);
        $this->issueDeliveryRepository->shouldReceive('find')->with(5)->andReturn($issueDelivery);
        $this->subscriptionRepository->shouldReceive('find')->with(10)->andReturn($subscription);

        $this->fulfillmentAction
            ->shouldReceive('execute')
            ->once()
            ->with($subscription, $issueDelivery);

        $printRun->shouldReceive('incrementFulfilledChunks')->once()->andReturn(1);
        $printRun->shouldReceive('allChunksComplete')->once()->andReturn(true);

        $job = CreateFulfilmentsChunkJob::for(1, 5, [10], 0);
        $job->__wakeup();
        $job->handle();

        $this->assertTrue(true);
    }

    public function test_it_does_not_fire_all_fulfillments_created_when_chunks_remain(): void
    {
        $printRun = $this->makePrintRun(totalChunks: 3, fulfilledChunks: 0);
        $issueDelivery = $this->makeIssueDelivery();
        $subscription = $this->makeSubscription(id: 10);

        $this->printRunRepository->shouldReceive('find')->andReturn($printRun);
        $this->issueDeliveryRepository->shouldReceive('find')->andReturn($issueDelivery);
        $this->subscriptionRepository->shouldReceive('find')->andReturn($subscription);
        $this->fulfillmentAction->shouldReceive('execute')->once();

        $printRun->shouldReceive('incrementFulfilledChunks')->once()->andReturn(1);
        $printRun->shouldReceive('allChunksComplete')->once()->andReturn(false);

        $job = CreateFulfilmentsChunkJob::for(1, 5, [10], 0);
        $job->__wakeup();
        $job->handle();

        $this->assertTrue(true);
    }

    // =========================================================================
    // Guard conditions
    // =========================================================================

    public function test_it_returns_early_when_print_run_not_found(): void
    {
        $this->printRunRepository->shouldReceive('find')->andReturn(null);

        $this->issueDeliveryRepository->shouldNotReceive('find');
        $this->fulfillmentAction->shouldNotReceive('execute');

        $job = CreateFulfilmentsChunkJob::for(1, 5, [], 0);
        $job->__wakeup();
        $job->handle();

        $this->assertTrue(true);
    }

    public function test_it_returns_early_when_print_run_is_cancelled(): void
    {
        $printRun = $this->makePrintRun(totalChunks: 1, fulfilledChunks: 0, status: PrintRunStatus::CANCELLED);

        $this->printRunRepository->shouldReceive('find')->andReturn($printRun);
        $this->issueDeliveryRepository->shouldNotReceive('find');
        $this->fulfillmentAction->shouldNotReceive('execute');

        $job = CreateFulfilmentsChunkJob::for(1, 5, [], 0);
        $job->__wakeup();
        $job->handle();

        $this->assertTrue(true);
    }

    public function test_it_returns_early_when_issue_delivery_not_found(): void
    {
        $printRun = $this->makePrintRun(totalChunks: 1, fulfilledChunks: 0);

        $this->printRunRepository->shouldReceive('find')->andReturn($printRun);
        $this->issueDeliveryRepository->shouldReceive('find')->andReturn(null);

        $this->fulfillmentAction->shouldNotReceive('execute');

        $job = CreateFulfilmentsChunkJob::for(1, 5, [], 0);
        $job->__wakeup();
        $job->handle();

        $this->assertTrue(true);
    }

    // =========================================================================
    // Per-subscription failure isolation
    // =========================================================================

    public function test_it_continues_processing_remaining_subscriptions_when_one_fails(): void
    {
        $printRun = $this->makePrintRun(totalChunks: 1, fulfilledChunks: 0);
        $issueDelivery = $this->makeIssueDelivery();
        $sub1 = $this->makeSubscription(id: 10);
        $sub2 = $this->makeSubscription(id: 11);

        $this->printRunRepository->shouldReceive('find')->andReturn($printRun);
        $this->issueDeliveryRepository->shouldReceive('find')->andReturn($issueDelivery);

        $this->subscriptionRepository
            ->shouldReceive('find')->with(10)->andReturn($sub1)
            ->shouldReceive('find')->with(11)->andReturn($sub2);

        $this->fulfillmentAction
            ->shouldReceive('execute')->with($sub1, $issueDelivery)
            ->andThrow(new \RuntimeException('Bad address'));

        $this->fulfillmentAction
            ->shouldReceive('execute')->with($sub2, $issueDelivery)
            ->once();

        $printRun->shouldReceive('incrementFulfilledChunks')->once()->andReturn(1);
        $printRun->shouldReceive('allChunksComplete')->once()->andReturn(true);

        $job = CreateFulfilmentsChunkJob::for(1, 5, [10, 11], 0);
        $job->__wakeup();
        $job->handle();

        $this->assertTrue(true);
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    private function makePrintRun(
        int            $totalChunks,
        int            $fulfilledChunks,
        PrintRunStatus $status = PrintRunStatus::FULFILLING,
    ): MockInterface
    {
        $printRun = Mockery::mock(PrintRun::class)->makePartial();

        $printRun->id = 1;
        $printRun->total_chunks = $totalChunks;
        $printRun->fulfilled_chunks_count = $fulfilledChunks;
        $printRun->status = $status->value;

        $printRun->shouldReceive('isCancelled')
            ->andReturn($status === PrintRunStatus::CANCELLED);

        return $printRun;
    }

    private function makeIssueDelivery(): MockInterface
    {
        $delivery = Mockery::mock(IssueDelivery::class)->makePartial();
        $delivery->id = 5;
        return $delivery;
    }

    private function makeSubscription(int $id): MockInterface
    {
        $sub = Mockery::mock(Subscription::class)->makePartial();
        $sub->id = $id;
        return $sub;
    }
}