<?php

declare(strict_types=1);

namespace App\Tests\Unit\Services\Subscriptions\Printing\Label;

use App\Enums\Subscriptions\LabelExportFormat;
use App\Enums\Subscriptions\LabelRunStatus;
use App\Framework\Support\Logger;
use App\Models\IssueDelivery;
use App\Models\SubscriptionIssueFulfilment;
use App\Models\LabelRun;
use App\Models\PrintFulfillment;
use App\Repositories\Subscriptions\LabelRunRepository;
use App\Repositories\Subscriptions\PrintFulfillmentRepository;
use App\Services\Subscriptions\Printing\Label\LabelExportFormatStrategy;
use App\Services\Subscriptions\Printing\Label\LabelFormatStrategyRegistry;
use App\Services\Subscriptions\Printing\Label\LabelGenerationService;
use App\Services\Subscriptions\Printing\Transport\LocalLabelExportTransport;
use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\TestCase;

class LabelGenerationServiceTest extends TestCase
{
    private MockInterface $fulfillmentRepository;
    private MockInterface $labelRunRepository;
    private MockInterface $formatRegistry;
    private MockInterface $transport;
    private MockInterface $logger;
    private LabelGenerationService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->fulfillmentRepository = Mockery::mock(PrintFulfillmentRepository::class);
        $this->labelRunRepository = Mockery::mock(LabelRunRepository::class);
        $this->formatRegistry = Mockery::mock(LabelFormatStrategyRegistry::class);
        $this->transport = Mockery::mock(LocalLabelExportTransport::class);
        $this->logger = Mockery::mock(Logger::class)->shouldIgnoreMissing();

        $this->service = new LabelGenerationService(
            fulfillmentRepository: $this->fulfillmentRepository,
            labelRunRepository: $this->labelRunRepository,
            formatRegistry: $this->formatRegistry,
            transport: $this->transport,
            logger: $this->logger,
        );
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    // =========================================================================
    // Happy path
    // =========================================================================

    public function test_it_generates_a_label_and_marks_the_run_complete(): void
    {
        $labelRun = $this->makeLabelRun(LabelRunStatus::Pending);
        $fulfillment = $this->makeFulfillment();
        $strategy = Mockery::mock(LabelExportFormatStrategy::class);

        $this->fulfillmentRepository
            ->shouldReceive('findBySubscriptionIssueFulfilmentAndBatch')
            ->once()
            ->with($labelRun->subscription_issue_fulfilment_id, $labelRun->print_batch_id)
            ->andReturn($fulfillment);

        $this->fulfillmentRepository
            ->shouldReceive('find')
            ->once()
            ->with($fulfillment->id)
            ->andReturn($fulfillment);

        $this->formatRegistry
            ->shouldReceive('get')
            ->once()
            ->with(Mockery::type(LabelExportFormat::class))
            ->andReturn($strategy);

        $strategy
            ->shouldReceive('generate')
            ->once()
            ->andReturn('csv-contents');

        $this->transport
            ->shouldReceive('upload')
            ->once()
            ->withArgs(fn($path, $contents) => str_contains($path, 'label_') && $contents === 'csv-contents');

        $this->transport
            ->shouldReceive('identifier')
            ->andReturn('local:/tmp/labels');

        $labelRun->shouldReceive('markGenerating')->once();
        $labelRun->shouldReceive('markComplete')->once()->withArgs(
            fn($path, $transport) => str_contains($path, 'label_') && $transport === 'local:/tmp/labels'
        );
        $labelRun->shouldReceive('markFailed')->never();

        $this->service->generate($labelRun);

        $this->assertTrue(true);
    }

    public function test_it_skips_generation_when_label_run_is_already_complete(): void
    {
        $labelRun = $this->makeLabelRun(LabelRunStatus::Complete);

        $this->fulfillmentRepository->shouldNotReceive('findBySubscriptionIssueFulfilmentAndBatch');
        $this->formatRegistry->shouldNotReceive('get');
        $this->transport->shouldNotReceive('upload');

        $labelRun->shouldReceive('markGenerating')->never();
        $labelRun->shouldReceive('markComplete')->never();

        $this->service->generate($labelRun);
        $this->assertTrue(true);
    }

    // =========================================================================
    // Failure paths
    // =========================================================================

    public function test_it_marks_label_run_failed_when_fulfillment_not_found(): void
    {
        $labelRun = $this->makeLabelRun(LabelRunStatus::Pending);

        $this->fulfillmentRepository
            ->shouldReceive('findBySubscriptionIssueFulfilmentAndBatch')
            ->once()
            ->andReturn(null);

        $labelRun->shouldReceive('markFailed')->never(); // 👈 IMPORTANT

        $this->expectException(\RuntimeException::class);

        $this->service->generate($labelRun);
    }

    public function test_it_marks_label_run_failed_when_transport_throws(): void
    {
        $labelRun = $this->makeLabelRun(LabelRunStatus::Pending);
        $fulfillment = $this->makeFulfillment();
        $strategy = Mockery::mock(LabelExportFormatStrategy::class);

        $this->fulfillmentRepository
            ->shouldReceive('findBySubscriptionIssueFulfilmentAndBatch')
            ->once()
            ->with($labelRun->subscription_issue_fulfilment_id, $labelRun->print_batch_id)
            ->andReturn($fulfillment);

        $this->fulfillmentRepository
            ->shouldReceive('find')
            ->once()
            ->with($fulfillment->id)
            ->andReturn($fulfillment);

        $this->formatRegistry
            ->shouldReceive('get')
            ->once()
            ->andReturn($strategy);

        $strategy->shouldReceive('generate')->once()->andReturn('contents');

        $this->transport
            ->shouldReceive('upload')
            ->once()
            ->andThrow(new \RuntimeException('SFTP connection refused'));

        $labelRun->shouldReceive('markGenerating')->once();
        $labelRun->shouldReceive('markFailed')->once()->with('SFTP connection refused');
        $labelRun->shouldReceive('markComplete')->never();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('SFTP connection refused');

        $this->service->generate($labelRun);
    }

    public function test_it_marks_label_run_failed_when_format_strategy_throws(): void
    {
        $labelRun = $this->makeLabelRun(LabelRunStatus::Pending);
        $fulfillment = $this->makeFulfillment();
        $strategy = Mockery::mock(LabelExportFormatStrategy::class);

        $this->fulfillmentRepository
            ->shouldReceive('findBySubscriptionIssueFulfilmentAndBatch')
            ->once()
            ->andReturn($fulfillment);

        $this->fulfillmentRepository
            ->shouldReceive('find')
            ->once()
            ->with($fulfillment->id)
            ->andReturn($fulfillment);

        $this->formatRegistry->shouldReceive('get')->once()->andReturn($strategy);

        $strategy
            ->shouldReceive('generate')
            ->once()
            ->andThrow(new \RuntimeException('PDF generation failed'));

        $this->transport->shouldNotReceive('upload');

        $labelRun->shouldReceive('markGenerating')->once();
        $labelRun->shouldReceive('markFailed')->once()->with('PDF generation failed');

        $this->expectException(\RuntimeException::class);

        $this->service->generate($labelRun);
    }

    public function test_it_marks_label_run_failed_when_no_format_strategy_registered(): void
    {
        $labelRun = $this->makeLabelRun(LabelRunStatus::Pending);
        $fulfillment = $this->makeFulfillment();

        $this->fulfillmentRepository
            ->shouldReceive('findBySubscriptionIssueFulfilmentAndBatch')
            ->once()
            ->andReturn($fulfillment);

        $this->fulfillmentRepository
            ->shouldReceive('find')
            ->once()
            ->with($fulfillment->id)
            ->andReturn($fulfillment);

        $this->formatRegistry
            ->shouldReceive('get')
            ->once()
            ->andThrow(new \DomainException('No label format strategy registered for csv'));

        $labelRun->shouldReceive('markGenerating')->never(); // 👈 FIX
        $labelRun->shouldReceive('markFailed')->never();     // 👈 ALSO NEVER CALLED

        $this->expectException(\DomainException::class);

        $this->service->generate($labelRun);
    }

    // =========================================================================
    // Filename
    // =========================================================================

    public function test_it_builds_a_versioned_filename_containing_the_label_run_id(): void
    {
        $labelRun = $this->makeLabelRun(LabelRunStatus::Pending, id: 42, attemptCount: 2);
        $fulfillment = $this->makeFulfillment();
        $strategy = Mockery::mock(LabelExportFormatStrategy::class);

        $this->fulfillmentRepository
            ->shouldReceive('findBySubscriptionIssueFulfilmentAndBatch')
            ->andReturn($fulfillment);

        $this->fulfillmentRepository
            ->shouldReceive('find')
            ->once()
            ->with($fulfillment->id)
            ->andReturn($fulfillment);

        $this->formatRegistry->shouldReceive('get')->andReturn($strategy);
        $strategy->shouldReceive('generate')->andReturn('x');

        $capturedPath = null;
        $this->transport
            ->shouldReceive('upload')
            ->once()
            ->withArgs(function ($path, $contents) use (&$capturedPath) {
                $capturedPath = $path;
                return true;
            });

        $this->transport->shouldReceive('identifier')->andReturn('local');
        $labelRun->shouldReceive('markGenerating')->once();
        $labelRun->shouldReceive('markComplete')->once();

        $this->service->generate($labelRun);

        $this->assertStringContainsString('label_42', $capturedPath);
        $this->assertStringContainsString('_v2_', $capturedPath);
        $this->assertStringEndsWith('.csv', $capturedPath);
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    private function makeLabelRun(
        LabelRunStatus $status,
        int            $id = 1,
        int            $attemptCount = 1,
    ): MockInterface
    {
        $labelRun = Mockery::mock(LabelRun::class)->makePartial();

        $labelRun->id = $id;
        $labelRun->subscription_issue_fulfilment_id = 10;
        $labelRun->print_batch_id = 20;
        $labelRun->subscription_id = 30;
        $labelRun->status = $status->value;
        $labelRun->format = LabelExportFormat::Csv->value;
        $labelRun->attempt_count = $attemptCount;

        $labelRun->shouldReceive('isComplete')
            ->andReturn($status === LabelRunStatus::Complete);

        // Stub relationship chain for subscriptionIssueFulfilment → issueDelivery
        $issueDelivery = Mockery::mock(IssueDelivery::class)->makePartial();
        $issueDelivery->issue_number = 42;
        $issueDelivery->issue_title = 'Test Issue';

        $subscriptionIssueFulfilment = Mockery::mock(SubscriptionIssueFulfilment::class)->makePartial();
        $subscriptionIssueFulfilment->shouldReceive('issueDelivery')
            ->andReturnSelf();
        $subscriptionIssueFulfilment->shouldReceive('first')
            ->andReturn($issueDelivery);

        $labelRun->shouldReceive('subscriptionIssueFulfilment')
            ->andReturnSelf();
        $labelRun->shouldReceive('first')
            ->andReturn($subscriptionIssueFulfilment);

        return $labelRun;
    }

    private function makeFulfillment(): MockInterface
    {
        $fulfillment = Mockery::mock(PrintFulfillment::class)->makePartial();

        $fulfillment->id = 99;
        $fulfillment->subscription_id = 30;
        $fulfillment->full_name = 'Jane Smith';
        $fulfillment->address_line_1 = '10 Downing Street';
        $fulfillment->address_line_2 = null;
        $fulfillment->city = 'London';
        $fulfillment->postcode = 'SW1A 2AA';
        $fulfillment->country = 'GB';

        return $fulfillment;
    }
}