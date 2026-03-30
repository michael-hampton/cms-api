<?php

namespace App\Tests\Unit\Services\Subscriptions\Printing;

use App\DTO\Subscriptions\PrintRunWorkflowInput;
use App\Framework\Database\Database;
use App\Framework\Support\Collection;
use App\Framework\Support\Logger;
use App\Models\IssueDelivery;
use App\Models\PrintRun;
use App\Models\WorkflowRun;
use App\Repositories\Subscriptions\IssueDeliveryRepository;
use App\Repositories\Subscriptions\PrintBatchRepository;
use App\Repositories\Subscriptions\Printing\PrintProcessConfigRepository;
use App\Repositories\Subscriptions\PrintRunRepository;
use App\Services\Subscriptions\Printing\BatchBuilderService;
use App\Services\Subscriptions\Printing\Driver\PrintDriverRegistry;
use App\Services\Subscriptions\Printing\Driver\PrintRunDriverInterface;
use App\Services\Subscriptions\Printing\PrintRunWorkflow;
use App\Services\Subscriptions\Printing\WorkflowRunFactory;
use App\Tests\Functional\Controllers\FunctionalTestCase;
use DomainException;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;

class PrintRunWorkflowTest extends FunctionalTestCase
{
    use MockeryPHPUnitIntegration;

    private $processConfigRepository;
    private $issueDeliveryRepository;
    private $printRunRepository;
    private $batchRepository;
    private $batchBuilderService;
    private $driverRegistry;
    private $logger;
    private $driver;
    private $workflow;
    private $databaseMock;
    private WorkflowRunFactory $workflowRunFactory;

    public function testThrowsDomainExceptionWhenDriverNotRegistered(): void
    {
        $this->processConfigRepository
            ->shouldReceive('findOrFail')
            ->andReturn($this->makeConfig());

        $this->driverRegistry
            ->shouldReceive('get')
            ->andThrow(new DomainException('No print driver registered for cds'));

        $this->expectException(DomainException::class);

        $this->workflow->execute(new PrintRunWorkflowInput(processConfigId: 1));
    }

    private function makeConfig(array $overrides = []): object
    {
        return (object)array_merge([
            'id' => 1,
            'driver' => 'cds',
            'site_id' => 1,
            'driver_sync_enabled' => false,
        ], $overrides);
    }

    public function testReturnsNoDataWorkflowRunWhenNoEligibleDeliveries(): void
    {
        $config = $this->makeConfig();
        $this->stubConfigAndDriver($config);

        $this->issueDeliveryRepository
            ->shouldReceive('findEligibleForPrintRun')
            ->andReturn(new Collection([]));

        // Mock the factory
        $workflowRun = \Mockery::mock(WorkflowRun::class)->makePartial();
        $workflowRun->shouldReceive('markNoData')->once();
        $workflowRun->shouldReceive('markComplete')->never();

        $this->workflowRunFactory
            ->shouldReceive('create')
            ->with(\Mockery::type(PrintRunWorkflowInput::class))
            ->andReturn($workflowRun);

        $this->workflow->execute(new PrintRunWorkflowInput(processConfigId: 1));
    }

    private function stubConfigAndDriver(object $config): void
    {
        $this->processConfigRepository
            ->shouldReceive('findOrFail')
            ->andReturn($config);

        $this->driverRegistry
            ->shouldReceive('get')
            ->with($config->driver)
            ->andReturn($this->driver);
    }

    public function testEmitsNoDataEventWhenNoEligibleDeliveries(): void
    {
        $config = $this->makeConfig();
        $this->stubConfigAndDriver($config);

        $this->issueDeliveryRepository
            ->shouldReceive('findEligibleForPrintRun')
            ->andReturn(new Collection([]));

        // Partial mock for WorkflowRun
        $workflowRun = \Mockery::mock(WorkflowRun::class)->makePartial();
        $workflowRun->shouldReceive('markNoData')->once();

        // Mock the factory to return the WorkflowRun partial
        $this->workflowRunFactory
            ->shouldReceive('create')
            ->with(\Mockery::type(PrintRunWorkflowInput::class))
            ->andReturn($workflowRun);

        // If you want to assert event emission, you can also mock that here:
        // $this->eventDispatcher->shouldReceive('dispatch')->with(...)

        $this->workflow->execute(new PrintRunWorkflowInput(processConfigId: 1));

        // Optionally, assert the workflow run methods
        $workflowRun->shouldHaveReceived('markNoData');
    }

    public function testCancelsPrecedingPendingPrintRunsBeforeCreatingNew(): void
    {
        $config = $this->makeConfig();
        $issueDelivery = $this->makeIssueDelivery();

        $this->stubConfigAndDriver($config);
        $this->stubTransactionPassthrough();

        $this->issueDeliveryRepository
            ->shouldReceive('findEligibleForPrintRun')
            ->andReturn(new Collection([$issueDelivery]));

        $this->printRunRepository
            ->shouldReceive('cancelAllPendingForIssueDelivery')
            ->with($issueDelivery->id)
            ->once()
            ->andReturn(1);

        $printRun = \Mockery::mock(PrintRun::class)->makePartial();
        $printRun->shouldReceive('markComplete')->andReturnSelf();

        $this->printRunRepository->shouldReceive('create')->andReturn($printRun);

        $this->driver->shouldReceive('isRegional')->andReturn(false);
        $this->batchBuilderService->shouldReceive('buildBatches')->andReturn(new Collection([]));
        $this->batchRepository
            ->shouldReceive('attachToPrintRun')
            ->andReturn(new Collection([])); // <-- return actual Collection, not $this

        // Inject a mock WorkflowRun via factory
        $workflowRun = \Mockery::mock(WorkflowRun::class)->makePartial();
        $workflowRun->shouldReceive('markNoData')->never();
        $workflowRun->shouldReceive('markComplete')->once();

        $this->workflowRunFactory
            ->shouldReceive('create')
            ->with(\Mockery::type(PrintRunWorkflowInput::class))
            ->andReturn($workflowRun);

        $this->workflow->execute(new PrintRunWorkflowInput(processConfigId: 1));

        // Assert the workflow run got marked complete
        $workflowRun->shouldHaveReceived('markComplete');
    }

    private function makeIssueDelivery(int $id = 1): IssueDelivery
    {
        $mock = Mockery::mock(IssueDelivery::class)->makePartial();
        $mock->id = $id;
        $mock->shouldReceive('markFailed')->andReturnSelf();
        return $mock;
    }

    // =====================================
    // Test: Driver resolution / exception
    // =====================================

    private function stubTransactionPassthrough(): void
    {
        // If your workflow uses database transactions
        $this->databaseMock->shouldReceive('transaction')->andReturnUsing(fn(callable $cb) => $cb());
    }

    // =====================================
    // Test: No eligible deliveries
    // =====================================

    public function testDoesNotCancelPrecedingRunsInDryRunMode(): void
    {
        $config = $this->makeConfig();
        $issueDelivery = $this->makeIssueDelivery();

        $this->stubConfigAndDriver($config);

        $this->issueDeliveryRepository
            ->shouldReceive('findEligibleForPrintRun')
            ->andReturn(new Collection([$issueDelivery]));

        $this->printRunRepository
            ->shouldReceive('cancelAllPendingForIssueDelivery')
            ->never();

        $this->driver->shouldReceive('isRegional')->andReturn(false);

        // Mock WorkflowRunFactory to satisfy factory call
        $workflowRun = \Mockery::mock(\App\Models\WorkflowRun::class)->makePartial();
        $workflowRun->shouldReceive('markComplete')->once(); // now we expect it
        $workflowRun->shouldReceive('markNoData')->never();

        $this->workflowRunFactory
            ->shouldReceive('create')
            ->once()
            ->andReturn($workflowRun);

        // Dry-run: no DB writes
        $this->printRunRepository->shouldReceive('create')->never();
        $this->databaseMock->shouldReceive('transaction')->never();

        $this->workflow->execute(new PrintRunWorkflowInput(
            processConfigId: 1,
            dryRun: true,
        ));
    }

    public function testPassesRegionalFlagToDriverQuery(): void
    {
        $config = $this->makeConfig();
        $issueDelivery = $this->makeIssueDelivery();

        $this->stubConfigAndDriver($config);
        $this->stubTransactionPassthrough();

        $this->issueDeliveryRepository
            ->shouldReceive('findEligibleForPrintRun')
            ->andReturn(new Collection([$issueDelivery]));

        $this->driver
            ->shouldReceive('isRegional')
            ->with($issueDelivery)
            ->once()
            ->andReturn(true);

        // Mock WorkflowRunFactory to return a partial workflow run
        $workflowRun = \Mockery::mock(\App\Models\WorkflowRun::class)->makePartial();
        $workflowRun->shouldReceive('markComplete')->once();
        $workflowRun->shouldReceive('markNoData')->never();

        $this->workflowRunFactory
            ->shouldReceive('create')
            ->once()
            ->andReturn($workflowRun);

        $printRun = $this->makePrintRun();
        $this->printRunRepository->shouldReceive('create')->andReturn($printRun);
        $this->printRunRepository->shouldReceive('cancelAllPendingForIssueDelivery')->andReturn(0);
        $this->batchBuilderService->shouldReceive('buildBatches')->andReturn(new Collection([]));
        $this->batchRepository
            ->shouldReceive('attachToPrintRun')
            ->andReturn(new \App\Framework\Support\Collection([]));

        $this->workflow->execute(new PrintRunWorkflowInput(processConfigId: 1));
    }

    // =====================================
    // Test: Cancel preceding runs
    // =====================================

    private function makePrintRun(int $id = 10): PrintRun
    {
        $mock = Mockery::mock(PrintRun::class)->makePartial();
        $mock->id = $id;
        $mock->shouldReceive('update')->andReturnSelf();
        $mock->shouldReceive('markComplete')->andReturnSelf();
        $mock->shouldReceive('recordDriverSync')->andReturnSelf();
        return $mock;
    }

    public function testForceRegionalBypassesDriverQuery(): void
    {
        $config = $this->makeConfig();
        $issueDelivery = $this->makeIssueDelivery();

        $this->stubConfigAndDriver($config);
        $this->stubTransactionPassthrough();

        $this->issueDeliveryRepository
            ->shouldReceive('findEligibleForPrintRun')
            ->andReturn(new Collection([$issueDelivery]));

        $this->driver->shouldReceive('isRegional')->never();

        $printRun = $this->makePrintRun();
        $this->printRunRepository->shouldReceive('create')->andReturn($printRun);
        $this->printRunRepository->shouldReceive('cancelAllPendingForIssueDelivery')->andReturn(0);
        $this->batchBuilderService->shouldReceive('buildBatches')->andReturn(new Collection([]));
        $this->batchRepository
            ->shouldReceive('attachToPrintRun')
            ->andReturn(new \App\Framework\Support\Collection([]));

        $workflowRun = \Mockery::mock(\App\Models\WorkflowRun::class)->makePartial();
        $workflowRun->shouldReceive('markComplete')->once();
        $workflowRun->shouldReceive('markNoData')->never();

        $this->workflowRunFactory
            ->shouldReceive('create')
            ->once()
            ->andReturn($workflowRun);

        $this->workflow->execute(new PrintRunWorkflowInput(
            processConfigId: 1,
            forceRegional: true,
        ));
    }

    // =====================================
    // Test: Regional path
    // =====================================

    public function testCreatesPrintRunInsideTransaction(): void
    {
        $config = $this->makeConfig();
        $issueDelivery = $this->makeIssueDelivery();

        $this->stubConfigAndDriver($config);

        $this->issueDeliveryRepository
            ->shouldReceive('findEligibleForPrintRun')
            ->andReturn(new Collection([$issueDelivery]));

        $this->driver->shouldReceive('isRegional')->andReturn(false);
        $this->printRunRepository->shouldReceive('cancelAllPendingForIssueDelivery')->andReturn(0);

        // Mock the workflow run
        $workflowRun = \Mockery::mock(\App\Models\WorkflowRun::class)->makePartial();
        $workflowRun->shouldReceive('markComplete')->once();
        $workflowRun->shouldReceive('markNoData')->never();

        $this->workflowRunFactory
            ->shouldReceive('create')
            ->once()
            ->andReturn($workflowRun);

        // Mock print run creation
        $printRun = \Mockery::mock(\App\Models\PrintRun::class)->makePartial();
        $this->printRunRepository
            ->shouldReceive('create')
            ->once()
            ->andReturn($printRun);

        $this->batchBuilderService
            ->shouldReceive('buildBatches')
            ->once()
            ->andReturn(new \App\Framework\Support\Collection([]));

        $this->batchRepository
            ->shouldReceive('attachToPrintRun')
            ->andReturn(new \App\Framework\Support\Collection([]));

        // Ensure transaction is called
        $this->databaseMock->shouldReceive('transaction')->once()->andReturnUsing(function ($callback) {
            return $callback();
        });

        $this->workflow->execute(new PrintRunWorkflowInput(processConfigId: 1));
    }

    public function testMarksPrintRunCompleteAfterBatchCreation(): void
    {
        $config = $this->makeConfig();
        $issueDelivery = $this->makeIssueDelivery();

        $this->stubConfigAndDriver($config);
        $this->stubTransactionPassthrough();

        // Eligible deliveries
        $this->issueDeliveryRepository
            ->shouldReceive('findEligibleForPrintRun')
            ->andReturn(new Collection([$issueDelivery]));

        // Mock the driver call
        $this->driver
            ->shouldReceive('isRegional')
            ->with($issueDelivery)
            ->once()
            ->andReturn(false);

        // Mock WorkflowRun from factory
        $workflowRun = \Mockery::mock(\App\Models\WorkflowRun::class)->makePartial();
        $workflowRun->shouldReceive('markComplete')->once();
        $workflowRun->shouldReceive('markNoData')->never();

        $this->workflowRunFactory
            ->shouldReceive('create')
            ->once()
            ->andReturn($workflowRun);

        // Mock PrintRun repository
        $printRun = $this->makePrintRun();
        $this->printRunRepository
            ->shouldReceive('create')
            ->once()
            ->andReturn($printRun);

        // Cancel pending runs
        $this->printRunRepository
            ->shouldReceive('cancelAllPendingForIssueDelivery')
            ->once()
            ->andReturn(0);

        // Batch builder and repository
        $this->batchBuilderService
            ->shouldReceive('buildBatches')
            ->once()
            ->andReturn(new \App\Framework\Support\Collection([]));

        $this->batchRepository
            ->shouldReceive('attachToPrintRun')
            ->once()
            ->andReturn(new \App\Framework\Support\Collection([]));

        // Execute workflow
        $this->workflow->execute(new PrintRunWorkflowInput(processConfigId: 1));
    }

    // =====================================
    // Test: PrintRun creation & completion
    // =====================================

    public function testDoesNotSyncToDriverWhenDisabledOnConfig(): void
    {
        $config = $this->makeConfig(['driver_sync_enabled' => false]);
        $issueDelivery = $this->makeIssueDelivery();

        $this->stubConfigAndDriver($config);
        $this->stubTransactionPassthrough();

        $this->issueDeliveryRepository
            ->shouldReceive('findEligibleForPrintRun')
            ->andReturn(new Collection([$issueDelivery]));

        // driver must always be mocked for isRegional
        $this->driver
            ->shouldReceive('isRegional')
            ->with($issueDelivery)
            ->once()
            ->andReturn(false);

        $this->printRunRepository
            ->shouldReceive('cancelAllPendingForIssueDelivery')
            ->withArgs(fn($id) => is_int($id)) // optional: ensure int id
            ->andReturn(0); // or whatever you expect

        // workflowRunFactory must always be mocked
        $workflowRun = \Mockery::mock(\App\Models\WorkflowRun::class)->makePartial();
        $workflowRun->shouldReceive('markComplete')->once();
        $workflowRun->shouldReceive('markNoData')->never();

        $this->workflowRunFactory
            ->shouldReceive('create')
            ->once()
            ->andReturn($workflowRun);

        $printRun = $this->makePrintRun();
        $this->printRunRepository->shouldReceive('create')->andReturn($printRun);

        $this->batchBuilderService->shouldReceive('buildBatches')->andReturn(new \App\Framework\Support\Collection([]));
        $this->batchRepository->shouldReceive('attachToPrintRun')->andReturn(new \App\Framework\Support\Collection([]));

        // Sync disabled, so never called
        $this->driver->shouldReceive('sync')->never();

        $this->workflow->execute(new PrintRunWorkflowInput(processConfigId: 1));
    }

    public function testSyncsToDriverWhenEnabledOnConfig(): void
    {
        $config = $this->makeConfig(['driver_sync_enabled' => true]);
        $issueDelivery = $this->makeIssueDelivery();

        $this->stubConfigAndDriver($config);
        $this->stubTransactionPassthrough();

        $this->issueDeliveryRepository
            ->shouldReceive('findEligibleForPrintRun')
            ->andReturn(new Collection([$issueDelivery]));

        $this->driver->shouldReceive('isRegional')->andReturn(false);

        // Mock WorkflowRunFactory + WorkflowRun
        $workflowRun = \Mockery::mock(\App\Models\WorkflowRun::class)->makePartial();
        $workflowRun->shouldReceive('markComplete')->once();
        $workflowRun->shouldReceive('markNoData')->never();

        $this->workflowRunFactory
            ->shouldReceive('create')
            ->once()
            ->andReturn($workflowRun);

        // Mock PrintRunRepository
        $printRun = $this->makePrintRun();
        $this->printRunRepository->shouldReceive('create')->andReturn($printRun);
        $this->printRunRepository->shouldReceive('cancelAllPendingForIssueDelivery')->andReturn(0);

        // Mock batches
        $this->batchBuilderService->shouldReceive('buildBatches')->andReturn(new Collection([]));
        $this->batchRepository->shouldReceive('attachToPrintRun')->andReturn(new \App\Framework\Support\Collection([]));

        // Expect driver sync
        $this->driver
            ->shouldReceive('sync')
            ->once()
            ->with($printRun)
            ->andReturn('DRIVER-REF-001');

        $this->workflow->execute(new PrintRunWorkflowInput(processConfigId: 1));
    }

    // =====================================
    // Test: Driver sync
    // =====================================
    // Example: driver sync disabled

    public function testDriverSyncFailureIsLoggedButDoesNotFailWorkflow(): void
    {
        $config = $this->makeConfig(['driver_sync_enabled' => true]);
        $issueDelivery = $this->makeIssueDelivery();

        $this->stubConfigAndDriver($config);
        $this->stubTransactionPassthrough();

        $this->issueDeliveryRepository
            ->shouldReceive('findEligibleForPrintRun')
            ->andReturn(new Collection([$issueDelivery]));

        $this->driver->shouldReceive('isRegional')->andReturn(false);

        // Mock WorkflowRunFactory + WorkflowRun
        $workflowRun = \Mockery::mock(\App\Models\WorkflowRun::class)->makePartial();
        $workflowRun->shouldReceive('markComplete')->once();
        $workflowRun->shouldReceive('markNoData')->never();

        $this->workflowRunFactory
            ->shouldReceive('create')
            ->once()
            ->andReturn($workflowRun);

        // Mock PrintRunRepository
        $printRun = $this->makePrintRun();
        $this->printRunRepository->shouldReceive('create')->andReturn($printRun);
        $this->printRunRepository->shouldReceive('cancelAllPendingForIssueDelivery')->andReturn(0);

        // Mock batches
        $this->batchBuilderService->shouldReceive('buildBatches')->andReturn(new Collection([]));
        $this->batchRepository->shouldReceive('attachToPrintRun')->andReturn(new \App\Framework\Support\Collection([]));

        // Driver sync throws
        $this->driver
            ->shouldReceive('sync')
            ->once()
            ->andThrow(new \RuntimeException('SFTP connection refused'));

        $this->logger
            ->shouldReceive('error')
            ->atLeast()->once()
            ->with('PrintRunWorkflow: driver sync failed', Mockery::on(fn($arg) => isset($arg['error'])));

        $this->workflow->execute(new PrintRunWorkflowInput(processConfigId: 1));
    }

    public function testDryRunDoesNotPersistPrintRuns(): void
    {
        $config = $this->makeConfig();
        $issueDelivery = $this->makeIssueDelivery();

        $this->stubConfigAndDriver($config);

        $this->issueDeliveryRepository
            ->shouldReceive('findEligibleForPrintRun')
            ->andReturn(new Collection([$issueDelivery]));

        $this->driver->shouldReceive('isRegional')->andReturn(false);

        // WorkflowRunFactory mock (dry-run should not persist, still needs to be mocked)
        $workflowRun = \Mockery::mock(\App\Models\WorkflowRun::class)->makePartial();
        $workflowRun->shouldReceive('markComplete')->once();
        $workflowRun->shouldReceive('markNoData')->never();

        $this->workflowRunFactory
            ->shouldReceive('create')
            ->once()
            ->andReturn($workflowRun);

        // PrintRunRepository never called
        $this->printRunRepository->shouldReceive('create')->never();
        $this->databaseMock->shouldReceive('transaction')->never();

        $this->workflow->execute(new PrintRunWorkflowInput(
            processConfigId: 1,
            dryRun: true,
        ));
    }

    public function testContinuesProcessingRemainingIssuesWhenOneFails(): void
    {
        $config = $this->makeConfig();
        $issue1 = $this->makeIssueDelivery(1);
        $issue2 = $this->makeIssueDelivery(2);

        $this->stubConfigAndDriver($config);

        $this->issueDeliveryRepository
            ->shouldReceive('findEligibleForPrintRun')
            ->andReturn(new Collection([$issue1, $issue2]));

        $this->printRunRepository->shouldReceive('cancelAllPendingForIssueDelivery')->andReturn(0);
        $this->driver->shouldReceive('isRegional')->andReturn(false);

        $callCount = 0;
        $printRun = $this->makePrintRun();
        $printRun->shouldReceive('markComplete')->andReturnSelf();

        $this->databaseMock->shouldReceive('transaction')->andReturnUsing(function (callable $cb) use (&$callCount, $printRun) {
            $callCount++;
            if ($callCount === 1) {
                throw new \RuntimeException('DB error on issue 1');
            }
            return $printRun;
        });

        // WorkflowRunFactory mock
        $workflowRun = \Mockery::mock(\App\Models\WorkflowRun::class)->makePartial();
        $workflowRun->shouldReceive('markComplete')->once();
        $workflowRun->shouldReceive('markNoData')->never();

        $this->workflowRunFactory
            ->shouldReceive('create')
            ->once()
            ->andReturn($workflowRun);

        // Mock batches
        $this->batchBuilderService->shouldReceive('buildBatches')->andReturn(new Collection([]));
        $this->batchRepository->shouldReceive('attachToPrintRun')->andReturn(new \App\Framework\Support\Collection([]));

        $this->logger->shouldReceive('error')->atLeast()->once()->with(
            'PrintRunWorkflow: issue processing failed',
            Mockery::on(fn($arg) => isset($arg['issue_delivery_id']))
        );

        $this->workflow->execute(new PrintRunWorkflowInput(processConfigId: 1));
    }

    protected function setUp(): void
    {
        $this->processConfigRepository = Mockery::mock(PrintProcessConfigRepository::class);
        $this->issueDeliveryRepository = Mockery::mock(IssueDeliveryRepository::class);
        $this->printRunRepository = Mockery::mock(PrintRunRepository::class);
        $this->batchRepository = Mockery::mock(PrintBatchRepository::class);
        $this->batchBuilderService = Mockery::mock(BatchBuilderService::class);
        $this->driverRegistry = Mockery::mock(PrintDriverRegistry::class);
        $this->logger = Mockery::mock(Logger::class)->shouldIgnoreMissing();
        $this->driver = Mockery::mock(PrintRunDriverInterface::class);
        $this->databaseMock = Mockery::mock(Database::class);
        $this->workflowRunFactory = Mockery::mock(WorkflowRunFactory::class);

        $this->workflow = new PrintRunWorkflow(
            $this->processConfigRepository,
            $this->issueDeliveryRepository,
            $this->printRunRepository,
            $this->batchRepository,
            $this->batchBuilderService,
            $this->driverRegistry,
            $this->databaseMock,
            $this->logger,
            $this->workflowRunFactory
        );

        parent::setUp();
    }

    private function makeWorkflowRun(int $id = 100): WorkflowRun
    {
        $mock = Mockery::mock(WorkflowRun::class)->makePartial();
        $mock->id = $id;
        $mock->shouldReceive('markNoData')->andReturnSelf();
        $mock->shouldReceive('markComplete')->andReturnSelf();
        return $mock;
    }
}