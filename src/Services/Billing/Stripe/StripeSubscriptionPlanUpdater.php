<?php

namespace App\Services\Billing\Stripe;

use Exception;
use Stripe\StripeClient;

class StripeSubscriptionPlanUpdater
{
    private StripeClient $stripe;

    public function __construct(?StripeClient $stripeClient = null)
    {
        if ($stripeClient) {
            $this->stripe = $stripeClient;
            return;
        }

        $secretKey = $_ENV['STRIPE_SECRET_KEY'] ?? config('payment.stripe.secret_key');
        $this->stripe = new StripeClient($secretKey);
    }

    public function update(string $stripeSubscriptionId, string $newPriceId, array $metadata = []): array
    {
        try {
            $subscription = $this->stripe->subscriptions->retrieve($stripeSubscriptionId);

            $this->stripe->subscriptions->update($stripeSubscriptionId, [
                'items' => [[
                    'id' => $subscription->items->data[0]->id,
                    'price' => $newPriceId,
                ]],
                'proration_behavior' => 'always_invoice',
                'metadata' => $metadata,
            ]);

            return [
                'success' => true,
                'stripe_subscription_id' => $stripeSubscriptionId,
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }
}
