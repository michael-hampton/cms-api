<?php

namespace App\Database\Seeders;

use App\Framework\Database\Seeder\Seeder;
use App\Models\Subscription;
use App\Models\SubscriptionWindow;

class BackfillSubscriptionWindows extends Seeder
{

    public function run(): void
    {
        $subscriptions = Subscription::where('type', 'paid')
            ->whereNotNull('end_date')
            ->get();

        $count = 0;

        foreach ($subscriptions as $subscription) {
            // Check if window already exists
            $existing = SubscriptionWindow::where('subscription_id', $subscription->id)->first();

            if (!$existing) {
                SubscriptionWindow::create([
                    'member_id' => $subscription->member_id,
                    'subscription_id' => $subscription->id,
                    'site_id' => $subscription->site_id,
                    'window_start' => $subscription->start_date->format('Y-m-d H:i:s'),
                    'window_end' => $subscription->end_date->format('Y-m-d H:i:s'),
                    'type' => 'paid'
                ]);
                $count++;
            }
        }

        echo "Backfilled {$count} subscription windows\n";
    }
}