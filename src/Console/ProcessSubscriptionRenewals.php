<?php

namespace App\Console;

use App\Framework\Support\Logger;
use App\Services\SubscriptionPaymentService;

class ProcessSubscriptionRenewals
{
    public function __construct(
        private readonly SubscriptionPaymentService $subscriptionPaymentService
    )
    {
    }

    public function handle(): void
    {
        Logger::info("Starting subscription renewal processing");

        $results = $this->subscriptionPaymentService->processRenewals();

        Logger::info("Subscription renewal processing completed", [
            'processed' => $results['processed'],
            'successful' => $results['successful'],
            'failed' => $results['failed']
        ]);

        if (!empty($results['errors'])) {
            Logger::error("Subscription renewal errors", [
                'errors' => $results['errors']
            ]);
        }

        echo "Processed: {$results['processed']}\n";
        echo "Successful: {$results['successful']}\n";
        echo "Failed: {$results['failed']}\n";
    }
}