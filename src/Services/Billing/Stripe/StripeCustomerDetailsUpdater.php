<?php

namespace App\Services\Billing\Stripe;

use Exception;
use Stripe\StripeClient;

class StripeCustomerDetailsUpdater
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

    public function update(string $customerId, array $fields): array
    {
        try {
            $customer = $this->stripe->customers->update($customerId, $fields);

            return [
                'success' => true,
                'customer_id' => $customer->id,
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Failed to update customer details in Stripe: ' . $e->getMessage(),
            ];
        }
    }
}
