<?php

declare(strict_types=1);

namespace App\Services\Subscriptions;

use App\Enums\Subscriptions\SubscriptionEndReason;
use App\Enums\Subscriptions\SubscriptionStatus;
use App\Events\Subscriptions\SubscriptionRenewedAndReplaced;
use App\Framework\Database\Database;
use App\Framework\Support\Logger;
use App\Repositories\Subscriptions\SubscriptionPlanRepository;
use App\Repositories\Subscriptions\SubscriptionRepository;
use App\Services\Billing\PaymentProviders\StripePaymentProcessor;
use App\Services\Subscriptions\Calculators\SubscriptionDateCalculator;

/**
 * Handles subscription renewal via the hard-replace model.
 *
 * Workflow (all inside a single transaction, after payment succeeds):
 *   1. Lock + validate the old subscription (status must be active or paused).
 *   2. Mark the old subscription: status=replaced, ended_at=now, end_reason=renewal.
 *   3. Create a new subscription starting now, linked back to the old one.
 *   4. Link old subscription's replaced_by_subscription_id → new subscription.
 *   5. Emit SubscriptionRenewedAndReplaced event.
 *
 * Payment is collected BEFORE this service is called, following the same
 * pattern as CrmSubscriptionCreationService. The caller (controller) passes
 * the resolved Stripe payment_method_id and amount_paid.
 *
 * This service does NOT:
 *   - Touch Stripe (payment already completed before entry)
 *   - Format data for presentation
 *   - Access sessions or request globals
 */
class SubscriptionRenewalService
{
    private const NON_RENEWABLE_STATUSES = [
        SubscriptionStatus::REPLACED->value,
        SubscriptionStatus::EXPIRED->value,
    ];

    public function __construct(
        private readonly SubscriptionRepository     $subscriptionRepository,
        private readonly SubscriptionPlanRepository $planRepository,
        private readonly StripePaymentProcessor     $stripeProcessor,
        private readonly SubscriptionDateCalculator $dateCalculator,
        private readonly Database                   $database,
    )
    {
    }

    /**
     * Renew a subscription by hard-replacing it with a new one.
     *
     * @param int $subscriptionId The subscription being renewed.
     * @param int $planId The plan for the new subscription (may differ from old if agent overrode).
     * @param string $paymentMethodId Stripe pm_xxx — payment is charged here before DB mutations.
     * @param float $amountPaid Resolved price for the new subscription.
     * @param int $agentId ID of the acting CRM agent.
     * @param int $siteId Current site.
     *
     * @return array{ old_subscription: object, new_subscription: object }
     *
     * @throws \InvalidArgumentException  For business-rule violations.
     * @throws \RuntimeException          For payment or persistence failures.
     */
    public function renew(
        int    $subscriptionId,
        int    $planId,
        string $paymentMethodId,
        float  $amountPaid,
        int    $agentId,
        int    $siteId,
    ): array
    {
        // ── Validate before touching payment ──────────────────────────────
        $oldSubscription = $this->subscriptionRepository->find($subscriptionId);

        if (!$oldSubscription) {
            throw new \InvalidArgumentException("Subscription #{$subscriptionId} not found.");
        }

        if ($oldSubscription->site_id !== $siteId) {
            throw new \InvalidArgumentException("Subscription does not belong to this site.");
        }

        if (in_array($oldSubscription->status, self::NON_RENEWABLE_STATUSES, true)) {
            throw new \InvalidArgumentException(
                "Subscription cannot be renewed from status: {$oldSubscription->status}."
            );
        }

        if (!in_array($oldSubscription->status, self::renewableStatuses(), true)) {
            throw new \InvalidArgumentException(
                "Subscription must be active or paused to renew. Current status: {$oldSubscription->status}."
            );
        }

        $plan = $this->planRepository->find($planId);

        if (!$plan || !$plan->is_active) {
            throw new \InvalidArgumentException("Plan #{$planId} not found or inactive.");
        }

        if ($plan->site_id !== $siteId) {
            throw new \InvalidArgumentException("Plan does not belong to this site.");
        }

        // ── Charge payment BEFORE any DB mutation ─────────────────────────
        $paymentResult = $this->stripeProcessor->processSubscriptionPayment(
            $oldSubscription,
            $plan,
            [
                'payment_method_id' => $paymentMethodId,
                'amount' => $amountPaid,
                'metadata' => [
                    'type' => 'subscription_renewal',
                    'old_sub_id' => $subscriptionId,
                    'agent_id' => $agentId,
                ],
            ],
        );

        if (!($paymentResult['success'] ?? false)) {
            throw new \RuntimeException(
                'Payment failed: ' . ($paymentResult['message'] ?? 'Unknown error')
            );
        }

        // ── Atomic DB transaction ─────────────────────────────────────────
        return $this->database->transaction(function () use (
            $oldSubscription,
            $plan,
            $paymentResult,
            $amountPaid,
            $agentId,
        ): array {
            $now = now_datetime();

            // Step 1 — End the old subscription
            $this->subscriptionRepository->update($oldSubscription->id, [
                'status' => SubscriptionStatus::REPLACED->value,
                'ended_at' => $now->format('Y-m-d H:i:s'),
                'end_reason' => SubscriptionEndReason::RENEWAL->value,
                'auto_renew' => false,
            ]);

            // Step 2 — Calculate new subscription dates (start = now)
            $startDate = new \DateTimeImmutable($now->format('Y-m-d H:i:s'));
            $endDate = $plan->billing_period !== 'lifetime'
                ? $this->dateCalculator->calculateEndDate(
                    $startDate,
                    \App\Enums\Subscriptions\BillingPeriod::from($plan->billing_period)
                )
                : null;

            // Step 3 — Create new subscription
            $newSubscription = $this->subscriptionRepository->createSubscription(
                memberId: (int)$oldSubscription->member_id,
                planId: $plan->id,
                siteId: (int)$oldSubscription->site_id,
                additionalData: [
                    'start_date' => $startDate->format('Y-m-d H:i:s'),
                    'end_date' => $endDate?->format('Y-m-d H:i:s'),
                    'next_billing_date' => $endDate?->format('Y-m-d H:i:s'),
                    'price' => $amountPaid,
                    'delivery_type' => $oldSubscription->delivery_type,
                    'delivery_address_id' => $oldSubscription->delivery_address_id ?? null,
                    'payment_subscription_id' => $paymentResult['subscription_id'] ?? null,
                    'renewed_from_subscription_id' => $oldSubscription->id,
                    'replacement_reason' => SubscriptionEndReason::RENEWAL->value,
                    'status' => SubscriptionStatus::ACTIVE->value,
                ],
            );

            // Step 4 — Link old subscription to new
            $this->subscriptionRepository->update($oldSubscription->id, [
                'replaced_by_subscription_id' => $newSubscription->id,
            ]);

            // Step 5 — Emit domain event
            event(new SubscriptionRenewedAndReplaced(
                memberId: (int)$oldSubscription->member_id,
                oldSubscriptionId: $oldSubscription->id,
                newSubscriptionId: $newSubscription->id,
                productId: $plan->id,
                planId: $plan->id,
                amountPaid: $amountPaid,
                agentId: $agentId,
                timestamp: $now->format('Y-m-d H:i:s'),
            ));

            Logger::info('Subscription renewed (hard-replace)', [
                'old_subscription_id' => $oldSubscription->id,
                'new_subscription_id' => $newSubscription->id,
                'agent_id' => $agentId,
            ]);

            return [
                'old_subscription' => $this->subscriptionRepository->find($oldSubscription->id),
                'new_subscription' => $newSubscription,
            ];
        });
    }

    private static function renewableStatuses(): array
    {
        return [
            SubscriptionStatus::ACTIVE->value,
            SubscriptionStatus::PAUSED->value,
        ];
    }
}