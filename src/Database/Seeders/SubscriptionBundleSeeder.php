<?php

namespace App\Database\Seeders;

use App\Framework\Database\Seeder\Seeder;
use App\Models\SubscriptionBundle;
use App\Models\SubscriptionBundleItem;
use App\Models\SubscriptionPlan;

/**
 * SubscriptionBundleSeeder
 *
 * Creates realistic example subscription bundles.
 * Requires at least two active subscription plans to exist for the target site.
 *
 * Usage:
 *   php artisan db:seed --class=SubscriptionBundleSeeder
 */
class SubscriptionBundleSeeder extends Seeder
{
    public function run(): void
    {
        // Pull first two active one-time plans for site 1 as bundle components.
        $plans = SubscriptionPlan::where('is_active', true)
            ->get();

        if ($plans->count() < 2) {
            echo "SubscriptionBundleSeeder: need at least 2 active plans for site 1. Skipping.\n";
            return;
        }

        [$planA, $planB] = [$plans->first(), $plans->last()];

        $totalPrice = $planA->price + $planB->price;

        $this->database->transaction(function () use ($plans) {

            $chunks = $plans->chunk(2);
            $bundleIndex = 1;

            foreach ($chunks as $pair) {

                if ($pair->count() < 2) {
                    continue; // ignore leftover if odd
                }

                [$planA, $planB] = [$pair->first(), $pair->last()];

                $totalPrice = $planA->price + $planB->price;
                $bundlePrice = round($totalPrice * 0.80, 2);

                $bundle = SubscriptionBundle::create([
                    'name' => "Bundle {$bundleIndex}",
                    'slug' => "bundle-{$bundleIndex}",
                    'description' => "Bundle of {$planA->name} and {$planB->name}.",
                    'bundle_price' => $bundlePrice,
                    'total_price' => $totalPrice,
                    'site_id' => 1,
                    'is_active' => true,
                    'start_date' => null,
                    'end_date' => null,
                ]);

                SubscriptionBundleItem::create([
                    'bundle_id' => $bundle->id,
                    'subscription_plan_id' => $planA->id,
                    'quantity' => 1,
                    'delivery_type' => $planA->hasDigitalOption() ? 'digital' : 'print',
                ]);

                SubscriptionBundleItem::create([
                    'bundle_id' => $bundle->id,
                    'subscription_plan_id' => $planB->id,
                    'quantity' => 1,
                    'delivery_type' => $planB->hasDigitalOption() ? 'digital' : 'print',
                ]);

                echo "Created bundle {$bundle->name} (£{$bundlePrice} instead of £{$totalPrice})\n";

                $bundleIndex++;
            }
        });
    }
}