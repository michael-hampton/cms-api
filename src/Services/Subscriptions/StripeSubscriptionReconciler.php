<?php

declare(strict_types=1);

namespace App\Services\Subscriptions;

use App\Enums\Subscriptions\SubscriptionStatus;
use App\Framework\Support\Logger;
use App\Models\Subscription;
use Stripe\Exception\ApiErrorException;
use Stripe\StripeClient;

/**
 * Reconciles a single local subscription against its Stripe counterpart.
 *
 * This service knows about Stripe. The Artisan command knows about iteration
 * and reporting. Keeping them separate means the reconciler is testable in
 * isolation and reusable (e.g. from a queue job if volume grows).
 *
 * Source of truth: Stripe for all billing fields.
 * Source of truth: local DB for access flags, grace periods, internal state.
 *
 * Fields reconciled (only billing-owned fields):
 *   status, current_period_end, current_period_start,
 *   cancelled_at, auto_renew (cancel_at_period_end), end_date
 *
 * Fields deliberately NOT reconciled:
 *   member_id, plan_id, site_id, access grants, delivery fields,
 *   bundle_id, voucher_id, internal flags
 */
class StripeSubscriptionReconciler
{
    /**
     * Maps Stripe subscription statuses to your SubscriptionStatus enum values.
     *
     * Stripe statuses: trialing, active, incomplete, incomplete_expired,
     *                  past_due, canceled, unpaid, paused
     *
     * We map conservatively — unknown Stripe statuses are left unchanged so
     * you can triage them manually rather than silently corrupting state.
     */
    private const STATUS_MAP = [
        'trialing' => SubscriptionStatus::TRIALING,
        'active' => SubscriptionStatus::ACTIVE,
        'past_due' => SubscriptionStatus::PAST_DUE,
        'canceled' => SubscriptionStatus::CANCELLED,
        'incomplete' => SubscriptionStatus::PAST_DUE, // treat as past_due until resolved
        'incomplete_expired' => SubscriptionStatus::CANCELLED,
        'unpaid' => SubscriptionStatus::PAST_DUE,
    ];
    private StripeClient $stripe;

    public function __construct(
        private readonly Logger       $logger,
        ?StripeClient $stripe = null
    )
    {
        $secretKey = $_ENV['STRIPE_SECRET_KEY'] ?? config('payment.stripe.secret_key');

        $this->stripe = empty($stripe) ? new StripeClient($secretKey) : $stripe;
    }

    /**
     * Reconciles one subscription. Returns a structured result describing
     * what happened.
     *
     * @return array{
     *   subscription_id: int,
     *   stripe_subscription_id: string,
     *   action: 'updated'|'skipped'|'failed',
     *   changes: array<string, array{from: mixed, to: mixed}>,
     *   error: string|null,
     * }
     */
    public function reconcile(Subscription $subscription): array
    {
        $stripeId = $subscription->payment_subscription_id;

        if (empty($stripeId)) {
            return [];
        }

        try {
            $stripeSubscription = $this->stripe->subscriptions->retrieve($stripeId, [
                'expand' => ['latest_invoice'],
            ]);

        } catch (ApiErrorException $e) {
            $this->logger->error('Reconciler: Stripe API error', [
                'subscription_id' => $subscription->id,
                'stripe_subscription_id' => $stripeId,
                'error' => $e->getMessage(),
            ]);

            return $this->result($subscription->id, $stripeId, 'failed', [], $e->getMessage());
        }

        $desired = $this->buildDesiredState($stripeSubscription);

        $changes = $this->diff($subscription, $desired);

        if (empty($changes)) {
            return $this->result($subscription->id, $stripeId, 'skipped', []);
        }

        $updatePayload = array_map(fn($change) => $change['to'], $changes);

        $subscription->update($updatePayload);

        $this->logger->info('Reconciler: subscription updated', [
            'subscription_id' => $subscription->id,
            'stripe_subscription_id' => $stripeId,
            'changes' => $changes,
        ]);

        return $this->result($subscription->id, $stripeId, 'updated', $changes);
    }

    // ── Private helpers ────────────────────────────────────────────────────

    private function result(
        int     $subscriptionId,
        string  $stripeId,
        string  $action,
        array   $changes,
        ?string $error = null,
    ): array
    {
        return [
            'subscription_id' => $subscriptionId,
            'stripe_subscription_id' => $stripeId,
            'action' => $action,
            'changes' => $changes,
            'error' => $error,
        ];
    }

    /**
     * Builds the desired local state from a live Stripe subscription object.
     * Returns only the fields this reconciler is allowed to touch.
     */
    private function buildDesiredState(\Stripe\Subscription $s): array
    {
        $mappedStatus = self::STATUS_MAP[$s->status] ?? null;

        $desired = [];

        if ($mappedStatus !== null) {
            $desired['status'] = $mappedStatus->value;
        }

        if ($s->current_period_start) {
            $desired['current_period_start'] = date('Y-m-d H:i:s', $s->current_period_start);
        }

        if ($s->current_period_end) {
            $desired['current_period_end'] = date('Y-m-d H:i:s', $s->current_period_end);
            // end_date mirrors current_period_end for active/trialing; for cancelled
            // it marks when paid access expires.
            $desired['end_date'] = date('Y-m-d H:i:s', $s->current_period_end);
        }

        // cancel_at_period_end=true means the user cancelled but still has access.
        $desired['auto_renew'] = !$s->cancel_at_period_end;

        if ($s->canceled_at) {
            $desired['cancelled_at'] = date('Y-m-d H:i:s', $s->canceled_at);
        }

        return $desired;
    }

    /**
     * Returns only the fields that differ between current DB state and desired state.
     *
     * @return array<string, array{from: mixed, to: mixed}>
     */
    private function diff(Subscription $subscription, array $desired): array
    {
        $changes = [];

        foreach ($desired as $field => $desiredValue) {
            $currentValue = $subscription->{$field};

            // Normalise datetime objects and strings for comparison
            $normCurrent = $this->normalise($currentValue);
            $normDesired = $this->normalise($desiredValue);

            if ($normCurrent !== $normDesired) {
                $changes[$field] = ['from' => $normCurrent, 'to' => $desiredValue];
            }
        }

        return $changes;
    }

    private function normalise(mixed $value): mixed
    {
        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d H:i:s');
        }

        return $value;
    }
}