<?php

namespace App\Console;

use App\Framework\Support\Logger;
use App\Jobs\Subscriptions\GenerateIssueDeliveriesJob;
use App\Repositories\Subscriptions\IssueDeliveryRepository;

class ProcessIssueSchedulesCommand
{
    public function __construct(
        private readonly IssueDeliveryRepository $issueDeliveryRepository,
        private readonly Logger                  $logger,
    )
    {
    }

    public function handle(): int
    {
        $schedules = $this->issueDeliveryRepository->findDueForDispatch(new \DateTime());

        $dispatched = 0;
        $failed = 0;

        foreach ($schedules as $schedule) {
            try {
                dispatch(GenerateIssueDeliveriesJob::for(), $schedule);
                $dispatched++;
            } catch (\Throwable $e) {
                $failed++;

                $this->logger->error('Failed to dispatch GenerateIssueDeliveriesJob', [
                    'issue_delivery_id' => $schedule->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $this->logger->info('Issue schedules processed', [
            'total' => count($schedules),
            'dispatched' => $dispatched,
            'failed' => $failed,
        ]);

        return $failed > 0 ? 1 : 0;
    }
}