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

    public function updateSubscriptionItemPrice(
        string $stripeSubscriptionItemId,
        string $stripePriceId,
        array $options = [],
    ): array {
        try {
            $payload = array_merge([
                // CRM publication switches should not invoice automatically in
                // this first implementation; future billing strategy can pass a
                // different proration_behavior explicitly.
                'price' => $stripePriceId,
                'proration_behavior' => 'none',
            ], $options);

            $item = $this->stripe->subscriptionItems->update(
                $stripeSubscriptionItemId,
                $payload,
            );

            return [
                'success' => true,
                'stripe_subscription_item_id' => $stripeSubscriptionItemId,
                'stripe_price_id' => $stripePriceId,
                'stripe_subscription_id' => $item->subscription ?? null,
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }
}
