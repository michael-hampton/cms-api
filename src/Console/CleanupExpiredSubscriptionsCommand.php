<?php

namespace App\Console;

use App\Framework\Console\Command;
use App\Framework\Console\ReportsCommandResult;
use App\Framework\Database\Database;
use App\Repositories\Subscriptions\SubscriptionRepository;

class CleanupExpiredSubscriptionsCommand extends Command
{
    use ReportsCommandResult;

    const SUCCESS = 1;
    const FAILURE = 0;

    protected $signature = 'subscriptions:cleanup-expired';
    public $description = 'Updates status of active subscriptions that have reached their end date.';

    public function __construct(
        private readonly SubscriptionRepository $subscriptionRepository,
        private readonly Database               $database
    )
    {
    }

    public function handle(): int
    {
        $result = $this->createResult('subscriptions:cleanup-expired');

        $expiredSubscriptions = $this->subscriptionRepository
            ->query()
            ->where('status', 'active')
            ->whereNotNull('end_date')
            ->where('end_date', '<', (new \DateTime())->format('Y-m-d H:i:s'))
            ->get();

        foreach ($expiredSubscriptions as $subscription) {
            try {
                $this->database->transaction(function () use ($subscription) {
                    $this->subscriptionRepository->update($subscription->id, [
                        'status' => 'expired',
                        'auto_renew' => false
                    ]);
                    $subscription->closeWindow();
                });

                $result->incrementSucceeded();
                $result->addMessage("Expired subscription #{$subscription->id}");
            } catch (\Throwable $e) {
                $this->reportFailure(
                    result: $result,
                    message: "Failed to expire subscription #{$subscription->id}: {$e->getMessage()}",
                    context: ['subscription_id' => $subscription->id],
                    throwable: $e
                );
            }
        }

        $this->reportResult($result);
        return $result->hasFailures() ? self::FAILURE : self::SUCCESS;
    }
}