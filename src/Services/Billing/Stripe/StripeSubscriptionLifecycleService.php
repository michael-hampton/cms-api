<?php

namespace App\Services\Billing\Stripe;

use Exception;
use Stripe\Exception\ApiErrorException;
use Stripe\StripeClient;

class StripeSubscriptionLifecycleService
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

    public function cancel(string $stripeSubscriptionId, bool $cancelAtPeriodEnd = true): array
    {
        try {
            $subscription = $cancelAtPeriodEnd
                ? $this->stripe->subscriptions->update($stripeSubscriptionId, ['cancel_at_period_end' => true])
                : $this->stripe->subscriptions->cancel($stripeSubscriptionId);

            return [
                'success' => true,
                'status' => $subscription->status,
                'cancel_at_period_end' => $subscription->cancel_at_period_end ?? false,
                'canceled_at' => $subscription->canceled_at ?? null,
                'current_period_end' => $subscription->current_period_end ?? null,
            ];
        } catch (ApiErrorException $e) {
            return $this->failure($e);
        }
    }

    public function pause(string $stripeSubscriptionId): array
    {
        try {
            $subscription = $this->stripe->subscriptions->update($stripeSubscriptionId, [
                'pause_collection' => [
                    'behavior' => 'void',
                ],
            ]);

            return [
                'success' => true,
                'status' => $subscription->status,
            ];
        } catch (ApiErrorException $e) {
            return $this->failure($e);
        }
    }

    public function resume(string $stripeSubscriptionId): array
    {
        try {
            $subscription = $this->stripe->subscriptions->update($stripeSubscriptionId, [
                'pause_collection' => '',
            ]);

            return [
                'success' => true,
                'status' => $subscription->status,
            ];
        } catch (ApiErrorException $e) {
            return $this->failure($e);
        }
    }

    public function reactivate(string $stripeSubscriptionId): array
    {
        try {
            $subscription = $this->stripe->subscriptions->retrieve($stripeSubscriptionId);

            if ($subscription->status === 'canceled') {
                return [
                    'success' => false,
                    'message' => 'Subscription has already been canceled and cannot be reactivated. Please create a new subscription.',
                    'error_code' => 'subscription_already_canceled',
                ];
            }

            if (!$subscription->cancel_at_period_end) {
                return [
                    'success' => false,
                    'message' => 'Subscription is not scheduled for cancellation',
                    'error_code' => 'subscription_not_scheduled_for_cancellation',
                ];
            }

            $subscription = $this->stripe->subscriptions->update($stripeSubscriptionId, [
                'cancel_at_period_end' => false,
            ]);

            return [
                'success' => true,
                'status' => $subscription->status,
                'cancel_at_period_end' => false,
            ];
        } catch (ApiErrorException $e) {
            return $this->failure($e);
        } catch (Exception) {
            return [
                'success' => false,
                'message' => 'Failed to reactivate Stripe subscription.',
            ];
        }
    }

    private function failure(ApiErrorException $e): array
    {
        return [
            'success' => false,
            'message' => $e->getMessage(),
            'error_code' => $e->getStripeCode(),
        ];
    }
}
