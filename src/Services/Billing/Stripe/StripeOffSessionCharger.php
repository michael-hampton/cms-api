<?php

namespace App\Services\Billing\Stripe;

use Exception;
use Stripe\Exception\ApiErrorException;
use Stripe\StripeClient;

class StripeOffSessionCharger
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

    public function charge(string $stripeCustomerId, int $amountCents, string $currency, array $metadata = []): array
    {
        try {
            $customer = $this->stripe->customers->retrieve($stripeCustomerId, [
                'expand' => ['invoice_settings.default_payment_method'],
            ]);

            $defaultPaymentMethod = $customer->invoice_settings->default_payment_method ?? null;

            if (!$defaultPaymentMethod) {
                return [
                    'success' => false,
                    'message' => 'No default payment method on file for this customer.',
                ];
            }

            $paymentMethodId = is_string($defaultPaymentMethod)
                ? $defaultPaymentMethod
                : $defaultPaymentMethod->id;

            $paymentIntent = $this->stripe->paymentIntents->create([
                'amount' => $amountCents,
                'currency' => strtolower($currency),
                'customer' => $stripeCustomerId,
                'payment_method' => $paymentMethodId,
                'confirm' => true,
                'off_session' => true,
                'metadata' => $metadata,
            ]);

            return [
                'success' => true,
                'payment_intent_id' => $paymentIntent->id,
                'client_secret' => $paymentIntent->client_secret,
                'status' => $paymentIntent->status,
            ];
        } catch (ApiErrorException $e) {
            return [
                'success' => false,
                'message' => $e->getMessage(),
                'error_code' => $e->getStripeCode(),
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'An unexpected error occurred during off-session charge.',
            ];
        }
    }
}
