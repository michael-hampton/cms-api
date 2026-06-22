<?php

namespace App\Console;

use App\Framework\Console\Command;
use App\Framework\Console\ReportsCommandResult;
use App\Jobs\Subscriptions\DeliverIssueDeliveryJob;
use App\Repositories\Subscriptions\SubscriptionIssueFulfilmentRepository;

class RetryFailedDeliveriesCommand extends Command
{
    use ReportsCommandResult;

    const SUCCESS = 1;
    const FAILURE = 0;
    private const MAX_ATTEMPTS = 3;

    protected $signature = 'subscriptions:retry-failed-deliveries';
    public $description = 'Retries failed issue deliveries that haven\'t exceeded max attempts.';

    public function __construct(
        private readonly SubscriptionIssueFulfilmentRepository $repository
    )
    {
    }

    public function handle(): int
    {
        $result = $this->createResult('subscriptions:retry-failed-deliveries');
        $failed = $this->repository->getFailedRetriable(self::MAX_ATTEMPTS);

        if ($failed->isEmpty()) {
            $this->info('No failed deliveries to retry.');
            return self::SUCCESS;
        }

        foreach ($failed as $delivery) {
            try {
                dispatch(DeliverIssueDeliveryJob::for((int)$delivery->id))->dispatchNow();

                $result->incrementSucceeded();
                $result->addMessage("Dispatched retry job for delivery #{$delivery->id}");
            } catch (\Throwable $e) {
                $this->reportFailure(
                    result: $result,
                    message: "Failed to dispatch retry for delivery #{$delivery->id}: {$e->getMessage()}",
                    context: ['delivery_id' => $delivery->id],
                    throwable: $e
                );
            }
        }

        $this->reportResult($result);
        return $result->hasFailures() ? self::FAILURE : self::SUCCESS;
    }
}