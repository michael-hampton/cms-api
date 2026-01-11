<?php

namespace App\Database\Seeders;

use App\Framework\Database\Seeder\Seeder;
use App\Models\Subscription;

class BackfillPremiumAccessCommand extends Seeder
{

    public function run(): void
    {
        echo "Starting premium access backfill...\n";

        $subscriptions = Subscription::where('status', 'active')->get();
        $count = 0;

        foreach ($subscriptions as $subscription) {
            if (!$subscription->plan) {
                continue;
            }

            // Grant premium access based on current plan
            $premiumGrants = $subscription->plan->getPremiumAccessGrants();

            if (empty($premiumGrants)) {
                continue;
            }

            foreach ($premiumGrants as $grant) {
                // Check if already exists
                if (!$subscription->hasPremiumAccess($grant['type'], $grant['identifier'])) {
                    $subscription->grantPremiumAccess(
                        $grant['type'],
                        $grant['identifier']
                    );
                    $count++;

                    echo "Granted {$grant['type']}:{$grant['identifier']} to subscription {$subscription->id}\n";
                }
            }

            // Handle legacy includes_digital_access flag
            if ($subscription->includes_digital_access &&
                !$subscription->hasPremiumAccess('newsletter', 'insider')) {
                $subscription->grantPremiumAccess('newsletter', 'insider');
                $count++;

                echo "Granted insider to subscription {$subscription->id} (legacy flag)\n";
            }
        }

        echo "Backfill complete! Granted {$count} premium access grants.\n";
    }
}