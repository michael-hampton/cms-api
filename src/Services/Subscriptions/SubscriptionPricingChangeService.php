<?php

declare(strict_types=1);

namespace App\Services\Subscriptions;

use App\Enums\Subscriptions\SubscriptionPricingChangeStatus;
use App\Events\Subscriptions\SubscriptionPricingChangeScheduled;
use App\Framework\Database\Database;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\SubscriptionPricingChange;
use App\Repositories\Subscriptions\SubscriptionPricingChangeRepository;
use App\Repositories\Subscriptions\SubscriptionPricingChangeTransitionRepository;
use App\Repositories\Subscriptions\SubscriptionRepository;
use App\Services\Subscriptions\Communications\SubscriptionItdCommunicationService;

class SubscriptionPricingChangeService
{
    public const MIN_NOTICE_DAYS = 30;

    public function __construct(
        private readonly SubscriptionPricingChangeRepository           $repository,
        private readonly Database                                      $database,
        private readonly SubscriptionRepository                       $subscriptionRepository,
        private readonly SubscriptionCancellationService              $cancellationService,
        private readonly SubscriptionPaymentService                   $paymentService,
        private readonly SubscriptionPricingChangeTransitionRepository $transitionRepository,
        private readonly SubscriptionItdCommunicationService          $itdCommunicationService,
    ) {
    }

    public function schedule(
        SubscriptionPlan   $plan,
        float              $newPrice,
        \DateTimeInterface $effectiveDate,
        int                $createdBy,
        ?string            $reason = null,
        bool               $requiresSubscriptionReplacement = false,
        bool               $itdRequired = false,
        ?string            $itdLetterCode = null,
    ): SubscriptionPricingChange {
        $this->assertValidEffectiveDate($effectiveDate);
        $this->assertNoActiveChange((int) $plan->id);

        if ($requiresSubscriptionReplacement && $newPrice <= (float) $plan->price) {
            throw new \InvalidArgumentException(
                'Subscription replacement workflow is only valid for price increases.'
            );
        }

        if ($itdRequired && !$requiresSubscriptionReplacement) {
            throw new \InvalidArgumentException(
                'ITD notification cannot be required unless subscription replacement is enabled.'
            );
        }

        if ($itdRequired && !$itdLetterCode) {
            throw new \InvalidArgumentException(
                'ITD letter code is required when ITD notification is enabled.'
            );
        }

        return $this->database->transaction(function () use (
            $plan,
            $newPrice,
            $effectiveDate,
            $createdBy,
            $reason,
            $requiresSubscriptionReplacement,
            $itdRequired,
            $itdLetterCode,
        ): SubscriptionPricingChange {
            $change = $this->repository->create([
                'plan_id' => $plan->id,
                'old_price' => $plan->price,
                'new_price' => $newPrice,
                'currency' => $plan->currency ?? 'GBP',
                'effective_date' => $effectiveDate->format('Y-m-d H:i:s'),
                'status' => SubscriptionPricingChangeStatus::Scheduled->value,
                'reason' => $reason,
                'created_by' => $createdBy,
                'requires_subscription_replacement' => $requiresSubscriptionReplacement,
                'itd_required' => $itdRequired,
                'itd_letter_code' => $itdLetterCode,
            ]);

            event(new SubscriptionPricingChangeScheduled($change));

            return $change;
        });
    }

    public function apply(SubscriptionPricingChange $change): void
    {
        if (!$change->isDueToApply()) {
            throw new \RuntimeException(
                "Pricing change #{$change->id} is not due to apply (status: {$change->status}, effective: {$change?->effective_date->format('Y-m-d')})"
            );
        }

        if ($this->requiresSubscriptionReplacement($change)) {
            $this->applyMidTermDirectDebitRise($change);
            return;
        }

        $this->applyPlanOnlyPriceChange($change);
    }

    public function cancel(SubscriptionPricingChange $change): void
    {
        if ($change->isApplied()) {
            throw new \RuntimeException(
                "Cannot cancel pricing change #{$change->id}: it has already been applied."
            );
        }

        if ($change->isCancelled()) {
            return;
        }

        $this->repository->markCancelled($change);
    }

    private function applyPlanOnlyPriceChange(SubscriptionPricingChange $change): void
    {
        $plan = $change->plan(true)->first();

        if (!$plan) {
            throw new \RuntimeException("Plan not found for pricing change #{$change->id}");
        }

        $this->database->transaction(function () use ($change, $plan): void {
            $this->repository->applyPlanPrice($plan, $change->new_price);
            $this->repository->markApplied($change);
        });
    }

    private function applyMidTermDirectDebitRise(SubscriptionPricingChange $change): void
    {
        if ($change->new_price <= $change->old_price) {
            throw new \RuntimeException(
                "Pricing change #{$change->id} is not a price rise and cannot use the mid-term replacement workflow."
            );
        }

        $plan = $change->plan(true)->first();

        if (!$plan) {
            throw new \RuntimeException("Plan not found for pricing change #{$change->id}");
        }

        $subscriptions = $this->repository->findActiveSubscribersForPlan((int) $change->plan_id);

        foreach ($subscriptions as $oldSubscription) {
            $this->transitionSingleSubscriptionForPriceRise(
                pricingChange: $change,
                oldSubscription: $oldSubscription,
                newPlanId: (int) $plan->id,
                letterCode: $this->resolveItdLetterCode($change, $oldSubscription),
            );
        }

        $this->database->transaction(function () use ($change, $plan): void {
            $this->repository->applyPlanPrice($plan, $change->new_price);
            $this->repository->markApplied($change);
        });
    }

    private function transitionSingleSubscriptionForPriceRise(
        SubscriptionPricingChange $pricingChange,
        Subscription              $oldSubscription,
        int                       $newPlanId,
        string                    $letterCode,
    ): void {
        $existing = $this->transitionRepository->findForOldSubscription(
            (int) $pricingChange->id,
            (int) $oldSubscription->id,
        );

        if ($existing && $existing->isCompleted()) {
            return;
        }

        $transition = $existing ?? $this->transitionRepository->create([
            'subscription_pricing_change_id' => $pricingChange->id,
            'old_subscription_id' => $oldSubscription->id,
            'new_subscription_id' => null,
            'member_id' => $oldSubscription->member_id,
            'site_id' => $oldSubscription->site_id,
            'old_plan_id' => $oldSubscription->plan_id,
            'new_plan_id' => $newPlanId,
            'old_price' => $pricingChange->old_price,
            'new_price' => $pricingChange->new_price,
            'currency' => $pricingChange->currency,
            'old_stripe_subscription_id' => $oldSubscription->getStripeSubscriptionId(),
            'new_stripe_subscription_id' => null,
            'itd_required' => (bool) $pricingChange->itd_required,
            'itd_letter_code' => $pricingChange->itd_required ? $letterCode : null,
            'communication_dedupe_key' => sprintf(
                'pricing-change:%d:subscription:%d:itd',
                $pricingChange->id,
                $oldSubscription->id,
            ),
            'status' => 'pending',
            'metadata' => [
                'source' => 'mid_term_direct_debit_price_rise',
            ],
        ]);

        try {
            $this->cancellationService->cancelSubscription((int) $oldSubscription->id, [
                'cancel_at_period_end' => false,
                'create_refund' => false,
                'refund_reason' => 'price_rise_subscription_replacement',
            ]);

            $this->transitionRepository->markOldSubscriptionCancelled((int) $transition->id);

            $newSubscription = $this->subscriptionRepository->createSubscription(
                memberId: (int) $oldSubscription->member_id,
                planId: $newPlanId,
                siteId: (int) $oldSubscription->site_id,
                additionalData: [
                    'price' => $pricingChange->new_price,
                    'original_price' => $pricingChange->new_price,
                    'currency' => $pricingChange->currency,
                    'type' => $oldSubscription->type,
                    'delivery_type' => $oldSubscription->delivery_type,
                    'auto_renew' => true,
                    'renewed_from_subscription_id' => $oldSubscription->id,
                    'replacement_reason' => 'price_rise',
                    'account_number' => $oldSubscription->account_number,
                    'territory_id' => $oldSubscription->territory_id,
                    'territory_override_flag' => $oldSubscription->territory_override_flag,
                ],
            );

            $plan = $newSubscription->plan(true)->first();

            if (!$plan) {
                throw new \RuntimeException("Plan not found for replacement subscription #{$newSubscription->id}");
            }

            $stripeResult = $this->paymentService->processStripeSubscriptionPayment(
                subscription: $newSubscription,
                plan: $plan,
                data: [
                    'pricing_change_id' => $pricingChange->id,
                    'old_subscription_id' => $oldSubscription->id,
                    'transition_id' => $transition->id,
                ],
            );

            $this->transitionRepository->markNewSubscriptionCreated(
                transitionId: (int) $transition->id,
                newSubscriptionId: (int) $newSubscription->id,
                newStripeSubscriptionId: $stripeResult['subscription_id'] ?? null,
            );

            $oldSubscription->update([
                'replaced_by_subscription_id' => $newSubscription->id,
                'replacement_reason' => 'price_rise',
            ]);

            if ($pricingChange->itd_required) {
                $this->itdCommunicationService->generateForPriceRise(
                    pricingChange: $pricingChange,
                    oldSubscription: $oldSubscription,
                    newSubscription: $newSubscription,
                    transitionId: (int) $transition->id,
                    letterCode: $letterCode,
                );

                $this->transitionRepository->markItdGenerated((int) $transition->id);
            }

            $this->transitionRepository->markCompleted((int) $transition->id);
        } catch (\Throwable $e) {
            $this->transitionRepository->markFailed((int) $transition->id, $e->getMessage());

            throw $e;
        }
    }

    private function requiresSubscriptionReplacement(SubscriptionPricingChange $change): bool
    {
        return (bool) ($change->requires_subscription_replacement ?? false);
    }

    private function resolveItdLetterCode(
        SubscriptionPricingChange $change,
        Subscription              $subscription,
    ): string {
        if (!empty($change->itd_letter_code)) {
            return (string) $change->itd_letter_code;
        }

        if ($subscription->hasStripeSubscription()) {
            return 'ITD_DD_PRICE_RISE';
        }

        return 'ITD_PRICE_RISE';
    }

    private function assertValidEffectiveDate(\DateTimeInterface $effectiveDate): void
    {
        $minDate = (new \DateTime())->modify('+' . self::MIN_NOTICE_DAYS . ' days');

        if ($effectiveDate < $minDate) {
            throw new \InvalidArgumentException(
                sprintf(
                    'Effective date must be at least %d days from today. Minimum allowed: %s.',
                    self::MIN_NOTICE_DAYS,
                    $minDate->format('Y-m-d'),
                )
            );
        }
    }

    private function assertNoActiveChange(int $planId): void
    {
        $existing = $this->repository->findActivePlanChange($planId);

        if ($existing !== null) {
            throw new \InvalidArgumentException(
                "Plan #{$planId} already has an active pricing change (#{$existing->id}, status: {$existing->status}). Cancel it before scheduling a new one."
            );
        }
    }
}