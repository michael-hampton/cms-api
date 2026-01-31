<?php

namespace App\Database\Seeders;

use App\Framework\Container;
use App\Framework\Database\Seeder\Seeder;
use App\Models\Subscription;
use App\Services\Billing\PaymentProviders\StripePaymentProcessor;
use Exception;

class BackfillSubscriptionPeriods extends Seeder
{

    public function run(): void
    {
        $stripeProcessor = Container::getInstance()->resolve(StripePaymentProcessor::class);

        $subscriptions = Subscription::where('status', 'active')
            ->whereNotNull('payment_subscription_id')
            ->get();

        echo "Processing " . count($subscriptions) . " subscriptions...\n";

        foreach ($subscriptions as $subscription) {
            if (!$subscription->hasStripeSubscription()) {
                continue;
            }

            try {
                $result = $stripeProcessor->getSubscription($subscription->getStripeSubscriptionId());

                if ($result['success']) {
                    $stripeData = $result['subscription'];

                    $subscription->update([
                        'current_period_start' => date('Y-m-d H:i:s', $stripeData['current_period_start']),
                        'current_period_end' => date('Y-m-d H:i:s', $stripeData['current_period_end'])
                    ]);

                    echo "✓ Updated subscription {$subscription->id}\n";
                }
            } catch (Exception $e) {
                echo "✗ Failed to update subscription {$subscription->id}: {$e->getMessage()}\n";
            }
        }

        echo "Done!\n";
    }
}