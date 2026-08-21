<?php

namespace App\Services\Billing\PaymentProviders;

use Exception;
use Stripe\StripeClient;

/**
 * Thin wrapper around a small set of ad-hoc Stripe customer/subscription
 * lookups that don't yet have a dedicated gateway.
 *
 * NOTE: Subscription creation, one-time payments, refunds, webhook handling,
 * and payment-method management have all moved to dedicated collaborators
 * (StripeSubscriptionOrchestrator, SubscriptionBillingService,
 * StripeCustomerPaymentMethodService, RefundService, StripeWebhookService).
 * This class only retains the handful of methods still called from
 * production code that haven't been migrated yet.
 */
class StripePaymentProcessor
{
    private StripeClient $stripe;

    public function __construct(
        ?StripeClient $stripeClient = null
    )
    {
        if ($stripeClient) {
            $this->stripe = $stripeClient;
        } else {
            $secretKey = $_ENV['STRIPE_SECRET_KEY'] ?? config('payment.stripe.secret_key');
            $this->stripe = new StripeClient($secretKey);
        }
    }

    public function getSubscription(string $stripeSubscriptionId): array
    {
        try {
            $subscription = $this->stripe->subscriptions->retrieve($stripeSubscriptionId, [
                'expand' => ['latest_invoice.payment_intent']
            ]);

            return [
                'success' => true,
                'subscription' => [
                    'id' => $subscription->id,
                    'status' => $subscription->status,
                    'current_period_start' => $subscription->current_period_start,
                    'current_period_end' => $subscription->current_period_end,
                    'cancel_at_period_end' => $subscription->cancel_at_period_end,
                    'canceled_at' => $subscription->canceled_at,
                    'ended_at' => $subscription->ended_at
                ]
            ];
        } catch (\Stripe\Exception\ApiErrorException $e) {
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }

    /**
     * Update arbitrary customer fields in Stripe (email, name, address, ...).
     *
     * The $fields array is passed directly to the Stripe customers->update
     * call so keys must match the Stripe API schema.
     */
    public function updateCustomerDetails(string $customerId, array $fields): array
    {
        try {
            $customer = $this->stripe->customers->update($customerId, $fields);

            return [
                'success' => true,
                'customer_id' => $customer->id,
            ];
        } catch (Exception $e) {
            error_log('Error updating Stripe customer details: ' . $e->getMessage());

            return [
                'success' => false,
                'message' => 'Failed to update customer details in Stripe: ' . $e->getMessage(),
            ];
        }
    }
}
