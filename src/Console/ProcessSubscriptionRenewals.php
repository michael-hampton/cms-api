<?php

namespace App\Console;

use App\Framework\Console\Command;
use App\Framework\Console\ReportsCommandResult;
use App\Services\Subscriptions\SubscriptionPaymentService;

class ProcessSubscriptionRenewals extends Command
{
    use ReportsCommandResult;

    const SUCCESS = 1;
    const FAILURE = 0;

    protected $signature = 'subscriptions:process-renewals';
    public $description = 'Processes recurring subscription renewals and payments.';

    public function __construct(
        private readonly SubscriptionPaymentService $subscriptionPaymentService
    )
    {
    }

    public function handle(): int
    {
        $result = $this->createResult('subscriptions:process-renewals');

        try {
            $renewalResults = $this->subscriptionPaymentService->processRenewals();

            for ($i = 0; $i < $renewalResults['successful']; $i++) {
                $result->incrementSucceeded();
            }

            $result->addMessage("Processed: {$renewalResults['processed']}, Successful: {$renewalResults['successful']}, Failed: {$renewalResults['failed']}");

            if (!empty($renewalResults['errors'])) {
                foreach ($renewalResults['errors'] as $error) {
                    $result->addMessage("Renewal Error: {$error}");
                }
            }
        } catch (\Throwable $e) {
            $this->reportFailure(
                result: $result,
                message: "Critical failure during renewal process: {$e->getMessage()}",
                throwable: $e
            );
        }

        $this->reportResult($result);
        return $result->hasFailures() ? self::FAILURE : self::SUCCESS;
    }
}