<?php

namespace App\Services\Billing\Stripe;

use Exception;
use Stripe\StripeClient;

class StripeCustomerEmailUpdater
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

    public function update(string $customerId, string $newEmail): array
    {
        try {
            $customer = $this->stripe->customers->update($customerId, [
                'email' => $newEmail,
            ]);

            return [
                'success' => true,
                'customer_id' => $customer->id,
                'email' => $customer->email,
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Failed to update customer email in Stripe',
            ];
        }
    }
}
