<?php

namespace App\Services\Billing\Stripe;

use App\Models\Member;
use App\Repositories\Subscriptions\SubscriptionRepository;
use Stripe\StripeClient;
use Throwable;

/**
 * Dedicated collaborator for the subscription <-> payment method
 * relationship. Kept separate from StripeCustomerPaymentMethodService
 * (CRUD on payment methods) and StripePaymentMethodWarningService (expiry
 * status) because this calculation - "which subscriptions does this card
 * pay for" - has its own independent reason to change: it correlates two
 * different data sources (our local `subscriptions` table and Stripe's
 * live subscription objects) rather than just reading Stripe payment
 * methods.
 *
 * Local `subscriptions` rows are the source of truth for which
 * subscriptions exist and their site scoping; Stripe is the source of
 * truth for which payment method actually pays for each one
 * (subscription-level default_payment_method, falling back to the
 * customer's default the same way Stripe itself does at invoice time).
 */
class PaymentMethodSubscriptionUsageResolver
{
    public function __construct(
        private readonly StripeClient $stripe,
        private readonly SubscriptionRepository $subscriptions,
    ) {
    }

    /**
     * @return array<string, array{count: int, subscriptions: array<array{id: int, stripe_subscription_id: string, plan_name: string|null, site_id: int|null}>}>
     */
    public function usageByPaymentMethod(Member $member): array
    {
        if (!$member->stripe_customer_id || !$member->id) {
            return [];
        }

        $localSubscriptions = $this->subscriptions->getActiveStripeLinkedSubscriptionsForMember($member->id);

        if ($localSubscriptions->isEmpty()) {
            return [];
        }

        $customerId = (string) $member->stripe_customer_id;

        try {
            $paymentMethodByStripeSubscriptionId = $this->livePaymentMethodsByStripeSubscription($customerId);
        } catch (Throwable) {
            // Stripe is unreachable - fail closed to "unknown usage" rather
            // than guessing, so we never under-report a card as unused.
            return [];
        }

        $usage = [];

        foreach ($localSubscriptions as $subscription) {
            $paymentMethodId = $paymentMethodByStripeSubscriptionId[$subscription->stripe_subscription_id] ?? null;

            if (!$paymentMethodId) {
                continue;
            }

            $usage[$paymentMethodId]['count'] = ($usage[$paymentMethodId]['count'] ?? 0) + 1;
            $usage[$paymentMethodId]['subscriptions'][] = [
                'id' => (int) $subscription->id,
                'stripe_subscription_id' => (string) $subscription->stripe_subscription_id,
                'plan_name' => $subscription->plan_name,
                'site_id' => $subscription->site_id !== null ? (int) $subscription->site_id : null,
            ];
        }

        return $usage;
    }

    /**
     * Points every given Stripe subscription's default_payment_method at
     * the new card. Used by the "replace card" flow to move subscriptions
     * off an expiring/expired card before it's removed, and by the
     * "change payment method for this subscription" action.
     *
     * @param string[] $stripeSubscriptionIds
     */
    public function reassignSubscriptions(array $stripeSubscriptionIds, string $newPaymentMethodId): void
    {
        foreach ($stripeSubscriptionIds as $stripeSubscriptionId) {
            $this->stripe->subscriptions->update($stripeSubscriptionId, [
                'default_payment_method' => $newPaymentMethodId,
            ]);
        }
    }

    /**
     * @return array<string, string> stripe_subscription_id => payment_method_id
     */
    private function livePaymentMethodsByStripeSubscription(string $customerId): array
    {
        $customer = $this->stripe->customers->retrieve($customerId);
        $customerDefault = (string) ($customer->invoice_settings->default_payment_method ?? '');

        $subscriptions = $this->stripe->subscriptions->all([
            'customer' => $customerId,
            'status' => 'all',
            'limit' => 100,
        ])->data;

        $map = [];
        foreach ($subscriptions as $subscription) {
            $paymentMethodId = (string) ($subscription->default_payment_method ?? '') ?: $customerDefault;

            if ($paymentMethodId !== '') {
                $map[$subscription->id] = $paymentMethodId;
            }
        }

        return $map;
    }
}
