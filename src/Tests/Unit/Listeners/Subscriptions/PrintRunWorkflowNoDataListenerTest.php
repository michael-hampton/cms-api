<?php

declare(strict_types=1);

namespace App\Tests\Unit\Listeners\Subscriptions;

use App\Events\Subscriptions\PrintRunWorkflowNoData;
use App\Framework\Support\Logger;
use App\Listeners\Subscriptions\PrintRunWorkflowNoDataListener;
use App\Models\WorkflowRun;
use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\TestCase;

class PrintRunWorkflowNoDataListenerTest extends TestCase
{
    private Logger&MockInterface $logger;
    private PrintRunWorkflowNoDataListener $listener;

    protected function setUp(): void
    {
        parent::setUp();

        $this->logger = Mockery::mock(Logger::class);
        $this->listener = new PrintRunWorkflowNoDataListener($this->logger);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_it_logs_a_warning_with_the_workflow_run_context(): void
    {
        $workflowRun = Mockery::mock(WorkflowRun::class)->makePartial();
        $workflowRun->id = 7;
        $workflowRun->workflow_type = 'print_run';
        $workflowRun->input = ['process_config_id' => 3];

        $this->logger
            ->shouldReceive('warning')
            ->once()
            ->with(
                'PrintRunWorkflowNoDataListener: workflow completed with no eligible issue deliveries',
                Mockery::on(fn (array $context) => $context['workflow_run_id'] === 7
                    && $context['workflow_type'] === 'print_run'
                    && $context['input'] === ['process_config_id' => 3]),
            );

        $this->listener->handle(new PrintRunWorkflowNoData($workflowRun));

        // Mockery expectation verification (shouldReceive()->once()) isn't
        // counted as a PHPUnit assertion unless registered explicitly — this
        // class doesn't extend the codebase's UnitTestCase (whose
        // assertPostConditions() does that registration), so add an explicit
        // assertion to avoid a false "risky: no assertions" flag, matching
        // AssignInitialSubscriptionSegmentTest's existing pattern.
        $this->assertTrue(true);
    }
}
