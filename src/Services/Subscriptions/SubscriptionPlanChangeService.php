<?php

declare(strict_types=1);

namespace App\Services\Subscriptions;

use App\Enums\Subscriptions\SubscriptionStatus;
use App\Events\Subscriptions\SubscriptionPlanChanged;
use App\Framework\Container;
use App\Framework\Database\Database;
use App\Framework\Support\Logger;
use App\Models\SubscriptionPlanPricing;
use App\Repositories\Subscriptions\SubscriptionChangeRepository;
use App\Repositories\Subscriptions\SubscriptionPlanRepository;
use App\Repositories\Subscriptions\SubscriptionRepository;

/**
 * Handles CRM-agent-initiated subscription plan/publication changes.
 *
 * Domain language:
 *   - SubscriptionPlan = publication
 *   - IssueDelivery = edition / issue schedule row
 *   - Subscription = customer/member subscription to a publication
 */
class SubscriptionPlanChangeService
{
    public function __construct(
        private readonly SubscriptionRepository $subscriptionRepository,
        private readonly SubscriptionPlanRepository $planRepository,
        private readonly SubscriptionChangeRepository $changeRepository,
        private readonly SubscriptionIssueDeliveryRebuildService $rebuildService,
        private readonly Database $database,
        private readonly ?SubscriptionStripePlanSyncService $stripePlanSyncService = null,
    ) {}

    public function changePlan(
        int $subscriptionId,
        int $newPlanId,
        int $siteId,
        int $agentId,
        ?string $reason = null,
    ): object {
        $subscription = $this->subscriptionRepository->find($subscriptionId);

        if (!$subscription) {
            throw new \InvalidArgumentException("Subscription #{$subscriptionId} not found.");
        }

        if ((int) $subscription->site_id !== $siteId) {
            throw new \InvalidArgumentException('Subscription does not belong to this site.');
        }

        if ($subscription->status !== SubscriptionStatus::ACTIVE->value) {
            throw new \InvalidArgumentException(
                'Only active subscriptions can have their plan changed.'
            );
        }

        $oldPlanId = (int) $subscription->plan_id;

        if ($oldPlanId === $newPlanId) {
            throw new \InvalidArgumentException(
                'The selected plan is the same as the current plan. No change required.'
            );
        }

        $oldPlan = $this->planRepository->find($oldPlanId);

        if (!$oldPlan) {
            throw new \InvalidArgumentException(
                'Current subscription plan record not found.'
            );
        }

        $newPlan = $this->planRepository->find($newPlanId);

        if (!$newPlan) {
            throw new \InvalidArgumentException("Subscription plan #{$newPlanId} not found.");
        }

        if (!$newPlan->is_active) {
            throw new \InvalidArgumentException('The selected plan is not active.');
        }

        if ((int) $newPlan->site_id !== $siteId) {
            throw new \InvalidArgumentException(
                'The selected plan does not belong to the current site.'
            );
        }

        $oldDeliveryType = $oldPlan->getDeliveryType();
        $newDeliveryType = $newPlan->getDeliveryType();

        if ($oldDeliveryType !== $newDeliveryType) {
            throw new \InvalidArgumentException(
                'Plan changes must stay within the same delivery type. '
                . "Current: {$oldDeliveryType?->value}, requested: {$newDeliveryType?->value}."
            );
        }

        $currentPricingTier = $this->resolveCurrentPricingTier($subscription, $oldPlan);
        $targetPricingTier = null;
        $currentStripePriceId = $subscription->stripe_price_id
            ?? $currentPricingTier?->stripe_price_id
            ?? null;
        $targetStripePriceId = null;
        $requiresStripeSync = false;

        if ($currentPricingTier !== null) {
            $targetPricingTier = $this->resolveCompatibleTargetPricingTier($currentPricingTier, $newPlan);

            if ($targetPricingTier === null) {
                throw new \InvalidArgumentException(
                    'Cannot change publication because no compatible pricing tier exists for the target plan.'
                );
            }

            $targetStripePriceId = $targetPricingTier->stripe_price_id ?? null;
            $requiresStripeSync = $currentStripePriceId !== null
                && $targetStripePriceId !== null
                && $currentStripePriceId !== $targetStripePriceId;

        }

        /**
         * This is intentionally before the transaction writes.
         *
         * We calculate the customer's remaining entitlement before the old
         * future deliveries are superseded.
         */
        $remainingIssueCount = $this->rebuildService->countRemainingIssues($subscriptionId);

        $result = $this->database->transaction(function () use (
            $subscriptionId,
            $oldPlanId,
            $newPlanId,
            $agentId,
            $reason,
            $remainingIssueCount,
            $targetPricingTier,
            $targetStripePriceId,
            $requiresStripeSync,
        ): array {
            $updates = [
                'plan_id' => $newPlanId,
            ];

            if ($targetPricingTier !== null) {
                $updates['subscription_plan_pricing_id'] = (int) $targetPricingTier->id;
                $updates['stripe_price_id'] = $targetStripePriceId;
                $updates['stripe_sync_status'] = $requiresStripeSync ? 'pending' : 'synced';
                $updates['stripe_sync_error'] = null;
            }

            $this->subscriptionRepository->update($subscriptionId, $updates);

            $rebuildResult = $this->rebuildService->rebuildForPublicationChange(
                subscriptionId: $subscriptionId,
                newPublicationId: $newPlanId,
                remainingIssueCount: $remainingIssueCount,
            );

            $this->changeRepository->recordPublicationChange(
                subscriptionId: $subscriptionId,
                oldPublicationId: $oldPlanId,
                newPublicationId: $newPlanId,
                oldEditionId: $rebuildResult->oldEditionId ?? 0,
                newEditionId: $rebuildResult->newEditionId ?? 0,
                remainingIssuesTransferred: $rebuildResult->remainingIssuesTransferred,
                createdBy: $agentId,
                reason: $reason,
            );

            return [
                'subscription_id' => $subscriptionId,
                'old_plan_id' => $oldPlanId,
                'new_plan_id' => $newPlanId,
                'old_publication_id' => $oldPlanId,
                'new_publication_id' => $newPlanId,
                'old_edition_id' => $rebuildResult->oldEditionId,
                'new_edition_id' => $rebuildResult->newEditionId,
                'remaining_issues_transferred' => $rebuildResult->remainingIssuesTransferred,
                'stripe_sync_status' => $requiresStripeSync ? 'pending' : ($targetPricingTier ? 'synced' : null),
                'stripe_sync_required' => $requiresStripeSync,
            ];
        });

        if (($result['stripe_sync_required'] ?? false)) {
            $syncService = $this->stripePlanSyncService
                ?? Container::getInstance()->resolve(SubscriptionStripePlanSyncService::class);

            $syncService->syncPlanChange($subscriptionId);
            $syncedSubscription = $this->subscriptionRepository->find($subscriptionId);
            $result['stripe_sync_status'] = $syncedSubscription->stripe_sync_status ?? $result['stripe_sync_status'];
            $result['stripe_sync_error'] = $syncedSubscription->stripe_sync_error ?? null;
        }

        event(new SubscriptionPlanChanged(
            subscriptionId: $subscriptionId,
            oldPlanId: $oldPlanId,
            newPlanId: $newPlanId,
            agentId: $agentId,
            timestamp: now_datetime()->format('Y-m-d H:i:s'),
        ));

        Logger::info('Subscription plan changed', [
            'subscription_id' => $subscriptionId,
            'old_plan_id' => $oldPlanId,
            'new_plan_id' => $newPlanId,
            'agent_id' => $agentId,
        ]);

        return (object) array_merge($result, [
            'message' => 'Subscription plan changed successfully.',
        ]);
    }

    private function resolveCurrentPricingTier(object $subscription, object $oldPlan): ?SubscriptionPlanPricing
    {
        if (!empty($subscription->subscription_plan_pricing_id)) {
            return SubscriptionPlanPricing::where('id', (int) $subscription->subscription_plan_pricing_id)
                ->where('plan_id', (int) $oldPlan->id)
                ->first();
        }

        if (!empty($subscription->stripe_price_id)) {
            return SubscriptionPlanPricing::where('stripe_price_id', (string) $subscription->stripe_price_id)
                ->where('plan_id', (int) $oldPlan->id)
                ->first();
        }

        return null;
    }

    private function resolveCompatibleTargetPricingTier(
        SubscriptionPlanPricing $currentTier,
        object $newPlan,
    ): ?SubscriptionPlanPricing {

        return SubscriptionPlanPricing::where('plan_id', (int) $newPlan->id)
            ->where('is_active', true)
            ->where('duration_months', $currentTier->duration_months)
            //->where('issue_count', $currentTier->issue_count)
            ->where('currency', $currentTier->currency)
            ->orderBy('is_default', 'desc')
            ->orderBy('sort_order', 'asc')
            ->first();
    }
}
