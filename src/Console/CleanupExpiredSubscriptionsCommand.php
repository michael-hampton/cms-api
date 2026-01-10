<?php

namespace App\Console;

use App\Framework\Database\Database;
use App\Repositories\SubscriptionRepository;

class CleanupExpiredSubscriptionsCommand
{
    public function __construct(
        private readonly SubscriptionRepository $subscriptionRepository,
        private readonly Database               $database
    )
    {
    }

    public function handle(): void
    {
        $this->database->transaction(function () {
            // Find all subscriptions that should be expired
            $expiredSubscriptions = $this->subscriptionRepository
                ->query()
                ->where('status', 'active')
                ->whereNotNull('end_date')
                ->where('end_date', '<', (new \DateTime())->format('Y-m-d H:i:s'))
                ->get();

            $count = 0;
            foreach ($expiredSubscriptions as $subscription) {
                $this->subscriptionRepository->update($subscription->id, [
                    'status' => 'expired',
                    'auto_renew' => false
                ]);

                // Close the subscription window
                $subscription->closeWindow();
                $count++;
            }

            echo "Updated {$count} expired subscriptions\n";
        });
    }
}