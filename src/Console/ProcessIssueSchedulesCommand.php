<?php

namespace App\Console;

use App\Framework\Console\Command;
use App\Framework\Console\ReportsCommandResult;
use App\Jobs\Subscriptions\GenerateIssueDeliveriesJob;
use App\Repositories\Subscriptions\IssueDeliveryRepository;

class ProcessIssueSchedulesCommand extends Command
{
    use ReportsCommandResult;

    const SUCCESS = 1;
    const FAILURE = 0;

    protected $signature = 'subscriptions:process-issue-schedules';
    public $description = 'Dispatches jobs for due issue deliveries.';

    public function __construct(
        private readonly IssueDeliveryRepository $issueDeliveryRepository,
    )
    {
    }

    public function handle(): int
    {
        $result = $this->createResult('subscriptions:process-issue-schedules');
        $schedules = $this->issueDeliveryRepository->findDueForDispatch(new \DateTime());

        foreach ($schedules as $schedule) {
            try {
                dispatch(GenerateIssueDeliveriesJob::for((int)$schedule->id))->onQueue('print');

                $result->incrementSucceeded();
                $result->addMessage("Dispatched delivery job for schedule #{$schedule->id}");
            } catch (\Throwable $e) {
                $this->reportFailure(
                    result: $result,
                    message: "Failed to dispatch GenerateIssueDeliveriesJob for #{$schedule->id}: {$e->getMessage()}",
                    context: ['issue_delivery_id' => $schedule->id],
                    throwable: $e,
                );
            }
        }

        $this->reportResult($result);
        return $result->hasFailures() ? self::FAILURE : self::SUCCESS;
    }
}