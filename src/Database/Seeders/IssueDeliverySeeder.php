<?php

namespace App\Database\Seeders;

use App\Framework\Database\Seeder\Seeder;
use App\Models\IssueDelivery;
use App\Models\Site;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;

class IssueDeliverySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->populateForPlans();
        return;

        $now = now_datetime();
        $now->setDate(
            (int)$now->format('Y'),
            (int)$now->format('m'),
            1
        );
        $now->setTime(0, 0, 0);

        Site::all()->each(function ($site) use ($now) {

            // Adjust this if your relationship is named differently
            Subscription::all()->each(function ($subscription) use ($now, $site) {

                // Create issues from -6 months to +12 months
                for ($i = -6; $i <= 12; $i++) {

                    $onSaleDate = (clone $now)->modify(sprintf('%+d months', $i));
                    $estimatedDeliveryDate = (clone $onSaleDate)->modify('+7 days');

                    IssueDelivery::firstOrCreate(
                        [
                            'subscription_id' => $subscription->id,
                            'issue_number' => (int)$onSaleDate->format('Ym'),
                        ],
                        [
                            'issue_title' => 'Issue ' . $onSaleDate->format('F Y'),
                            'on_sale_date' => $onSaleDate->format('Y-m-d H:i:s'),
                            'estimated_delivery_date' => $estimatedDeliveryDate->format('Y-m-d H:i:s'),
                            'metadata' => [
                                'site_id' => $site->id,
                                'seeded' => true,
                            ],
                        ]
                    );
                }
            });
        });
    }

    /**
     * Run the database seeds.
     */
    public function populateForPlans(): void
    {
        $now = now_datetime();

        $now->setDate(
            (int)$now->format('Y'),
            (int)$now->format('m'),
            1
        );

        $now->setTime(0, 0, 0);

        Site::all()->each(function ($site) use ($now) {
            SubscriptionPlan::query()
                ->where('site_id', $site->id)
                ->get()
                ->each(function ($plan) use ($now, $site) {
                    // Create issues from -6 months to +12 months
                    for ($i = -6; $i <= 12; $i++) {
                        $onSaleDate = (clone $now)->modify(sprintf('%+d months', $i));
                        $estimatedDeliveryDate = (clone $onSaleDate)->modify('+7 days');

                        IssueDelivery::firstOrCreate(
                            [
                                'subscription_plan_id' => $plan->id,
                                'issue_number' => (int)$onSaleDate->format('Ym'),
                            ],
                            [
                                'issue_title' => 'Issue ' . $onSaleDate->format('F Y'),
                                'on_sale_date' => $onSaleDate->format('Y-m-d H:i:s'),
                                'estimated_delivery_date' => $estimatedDeliveryDate->format('Y-m-d H:i:s'),
                                'metadata' => [
                                    'site_id' => $site->id,
                                    'subscription_plan_id' => $plan->id,
                                    'seeded' => true,
                                ],
                            ]
                        );
                    }
                });
        });
    }
}
