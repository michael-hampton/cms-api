<?php

namespace App\Console\Commands\Subscriptions;

use App\Framework\Support\Logger;
use App\Jobs\Subscriptions\DeliverIssueDeliveryJob;
use App\Repositories\Subscriptions\IssuesDeliveredRepository;

class RetryFailedDeliveriesCommand
{
    private const MAX_ATTEMPTS = 3;

    public function __construct(
        private readonly IssuesDeliveredRepository $repository
    )
    {
    }

    public function handle(): int
    {
        $failed = $this->repository->getFailedRetriable(self::MAX_ATTEMPTS);

        $retried = 0;

        foreach ($failed as $delivery) {
            try {
                dispatch(new DeliverIssueDeliveryJob($delivery->id));
                $retried++;
            } catch (\Exception $e) {
                Logger::error('Failed to dispatch retry job', [
                    'issues_delivered_id' => $delivery->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        Logger::info('Failed deliveries retried', [
            'total_failed' => $failed->count(),
            'retried' => $retried,
        ]);

        return 0;
    }
}