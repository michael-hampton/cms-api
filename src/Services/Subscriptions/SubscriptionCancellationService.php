<?php

namespace App\Services\Subscriptions;

use App\DTO\Subscriptions\CancellationPolicyContext;
use App\Enums\Subscriptions\SubscriptionStatus;
use App\Events\Subscriptions\SubscriptionCancelled;
use App\Events\Subscriptions\SubscriptionReactivated;
use App\Framework\Database\Database;
use App\Framework\Support\Logger;
use App\Models\CancellationReason;
use App\Models\Subscription;
use App\Repositories\Billing\PaymentRepository;
use App\Repositories\Subscriptions\BusinessDecisions\CancellationReasonRepository;
use App\Repositories\Subscriptions\SubscriptionRepository;
use App\Services\Billing\Stripe\StripeSubscriptionLifecycleService;
use App\Services\Members\Consents\ConsentService;
use App\Services\Subscriptions\BusinessDecisions\CancellationOptionsResolver;
use App\Services\Subscriptions\BusinessDecisions\CancellationRefundCapCalculator;
use App\Services\Subscriptions\Calculators\SubscriptionTermCalculator;
use App\Services\Subscriptions\Refunds\FullRefundStrategy;
use App\Services\Subscriptions\Refunds\ManualRefundStrategy;
use App\Services\Subscriptions\Refunds\ProRatedRefundStrategy;
use App\Services\Subscriptions\Refunds\RefundStrategy;
use DateTimeImmutable;
use Exception;

class SubscriptionCancellationService
{
    private Database $database;
    private ReplacementPolicyResolver $policyResolver;
    private SubscriptionTermCalculator $termCalculator;
    private PolicySettingOverrideResolver $settingOverrideResolver;
    private CancellationRefundCapCalculator $refundCapCalculator;

    /**
     * The consent type code written by writeMarketingConsentDecision().
     * See ConsentSeeder — this is an existing seeded code, reused here
     * rather than introducing a cancellation-specific one.
     */
    private const MARKETING_CONSENT_CODE = 'marketing_email';

    public function __construct(
        private readonly SubscriptionRepository $subscriptionRepository,
        private readonly PaymentRepository      $paymentRepository,
        private readonly StripeSubscriptionLifecycleService $stripeLifecycleService,
        private readonly SubscriptionRefundService $refundService,
        private readonly CancellationReasonRepository $cancellationReasonRepository,
        private readonly CancellationOptionsResolver $cancellationOptionsResolver,
        private readonly ConsentService $consentService,
        ?Database                               $database = null,
        ?ReplacementPolicyResolver               $policyResolver = null,
        ?SubscriptionTermCalculator              $termCalculator = null,
        ?PolicySettingOverrideResolver           $settingOverrideResolver = null,
        ?CancellationRefundCapCalculator         $refundCapCalculator = null,
    )
    {
        $this->database = $database ?? Database::getInstance();
        // Constructed with a default here (rather than only relying on
        // container auto-resolution) so existing call sites/tests that
        // predate this ticket keep working without every one of them
        // having to pass a policy resolver mock explicitly.
        $this->policyResolver = $policyResolver ?? new ReplacementPolicyResolver(
            $this->subscriptionRepository,
            new \App\Repositories\Subscriptions\ReplacementPolicyRepository()
        );
        $this->termCalculator = $termCalculator ?? new SubscriptionTermCalculator();
        $this->settingOverrideResolver = $settingOverrideResolver ?? new PolicySettingOverrideResolver(
            new \App\Repositories\Subscriptions\SubscriptionPolicySettingOverrideRepository()
        );
        $this->refundCapCalculator = $refundCapCalculator ?? new CancellationRefundCapCalculator();
    }

    /**
     * Cancel a subscription and handle Stripe integration.
     *
     * Supported $options keys:
     *   cancel_at_period_end  bool   (default true)
     *   create_refund         bool   (default false)
     *   refund_type           string 'full' | 'pro_rated'  (default 'pro_rated')
     *   refund_amount         float  optional override — triggers ManualRefundStrategy
     */
    public function cancelSubscription(int $subscriptionId, array $options = []): array
    {
        return $this->database->transaction(function () use ($subscriptionId, $options) {

            $subscription = $this->subscriptionRepository->find($subscriptionId);

            if (!$subscription) {
                throw new Exception('Subscription not found');
            }

            if ($subscription->status === 'cancelled') {
                throw new Exception('Subscription is already cancelled');
            }

            $cancelAtPeriodEnd = $options['cancel_at_period_end'] ?? true;
            $resolvedReason = $this->resolveCancellationReason($options);

            $this->assertCancellationAllowedByPolicy($subscription, $options, $cancelAtPeriodEnd, $resolvedReason);
            $this->assertCancellationAllowedByBusinessDecision($subscription, $resolvedReason);
            $this->assertCancellationNoteProvided($resolvedReason, $options);

            // Stripe cancellation
            $stripeResult = null;
            if ($subscription->hasStripeSubscription()) {
                $stripeResult = $this->stripeLifecycleService->cancel(
                    $subscription->getStripeSubscriptionId(),
                    $cancelAtPeriodEnd
                );

                if (!$stripeResult['success']) {
                    throw new Exception(
                        'Failed to cancel Stripe subscription: ' . $stripeResult['message']
                    );
                }
            }

            if ($subscription->type === 'paid') {
                $subscription->closeWindow();
            }

            $updateData = [
                'auto_renew' => false,
                'cancelled_at' => now_datetime()->format('Y-m-d H:i:s'),
                'cancel_at_period_end' => $cancelAtPeriodEnd,
                'cancellation_reason' => $resolvedReason?->code ?? $options['cancellation_reason'] ?? null,
                'cancellation_reason_id' => $resolvedReason?->id,
                'cancellation_notes' => $options['cancellation_notes'] ?? null,
            ];

            if (!$cancelAtPeriodEnd) {
                $updateData['status'] = 'cancelled';
                $updateData['end_date'] = date('Y-m-d H:i:s');
            }

            $updated = $this->subscriptionRepository->update($subscriptionId, $updateData);

            if (!$updated) {
                throw new Exception('Failed to update subscription status');
            }

            /**
             * 🔥 FIX: refund branch MUST include override-only cases
             */
            $shouldRefund =
                !$cancelAtPeriodEnd &&
                (
                    ($options['create_refund'] ?? false)
                    || isset($options['refund_amount'])
                );

            if ($shouldRefund) {
                $strategy = $this->resolveRefundStrategy($subscription, $options);
                $strategy = $this->applyRefundPolicyCap($subscription, $strategy, $resolvedReason);
                $this->refundService->executeWithStrategy($subscription, $strategy);
            }

            if (!$cancelAtPeriodEnd) {
                $this->subscriptionRepository->revokeAllPremiumAccess($subscriptionId);
            }

            if ($resolvedReason !== null) {
                $this->writeMarketingConsentDecision($subscription, $resolvedReason);
            }

            Logger::info('Subscription cancelled', [
                'subscription_id' => $subscriptionId,
                'stripe_subscription_id' => $subscription->getStripeSubscriptionId(),
                'cancel_at_period_end' => $cancelAtPeriodEnd,
            ]);

            $refreshedSubscription = $this->subscriptionRepository->find($subscriptionId);

            if ($this->shouldDispatchLifecycleEvent((int)$subscriptionId)) {
                event(new SubscriptionCancelled(
                    subscriptionId: (int)$subscriptionId,
                    cancelAtPeriodEnd: (bool)$cancelAtPeriodEnd,
                    endDate: $this->formatEventDate($refreshedSubscription?->end_date),
                ));
            }

            return [
                'success' => true,
                'subscription' => $refreshedSubscription,
                'stripe_result' => $stripeResult,
            ];
        });
    }

    /**
     * Reactivate a cancelled subscription (only if cancel_at_period_end is set).
     */
    public function reactivateSubscription(int $subscriptionId): array
    {
        return $this->database->transaction(function () use ($subscriptionId) {
            $subscription = $this->subscriptionRepository->find($subscriptionId);

            if (!$subscription) {
                throw new Exception('Subscription not found');
            }

            if ($subscription->status !== SubscriptionStatus::CANCELLED->value && !$subscription->isCancellationScheduled()) {
                throw new Exception('Can only reactivate cancelled subscriptions');
            }

            $now = new \DateTime();
            if ($subscription->end_date && $subscription->end_date < $now) {
                throw new Exception('Subscription entitlement period has ended. Please purchase a new subscription.');
            }

            $daysRemaining = null;
            if ($subscription->end_date) {
                $interval = $now->diff($subscription->end_date);
                $daysRemaining = $interval->days;

                if ($daysRemaining <= 0) {
                    throw new Exception('Subscription entitlement period has ended. Please purchase a new subscription.');
                }
            }

            if ($subscription->hasStripeSubscription()) {
                $stripeResult = $this->stripeLifecycleService->reactivate(
                    $subscription->getStripeSubscriptionId()
                );

                if (!$stripeResult['success']) {
                    if (isset($stripeResult['error_code']) && $stripeResult['error_code'] === 'subscription_already_canceled') {
                        throw new Exception('This subscription cannot be reactivated. Please subscribe to a new plan.');
                    }

                    throw new Exception('Failed to reactivate Stripe subscription: ' . $stripeResult['message']);
                }
            }

            $newEndDate = $subscription->plan?->billing_period === 'lifetime' ? null : $subscription->end_date;

            $updated = $this->subscriptionRepository->update($subscriptionId, [
                'status' => 'active',
                'auto_renew' => true,
                'cancelled_at' => null,
                'cancel_at_period_end' => false,
                'cancellation_reason' => null,
                'cancellation_notes' => null,
                'end_date' => $newEndDate?->format('Y-m-d H:i:s'),
                'next_billing_date' => $newEndDate?->format('Y-m-d H:i:s'),
            ]);

            if (!$updated) {
                throw new Exception('Failed to update subscription status');
            }

            $this->refreshPremiumAccess($subscription);

            Logger::info('Subscription reactivated within entitlement period', [
                'subscription_id' => $subscriptionId,
                'days_remaining' => $daysRemaining,
                'stripe_subscription_id' => $subscription->getStripeSubscriptionId(),
            ]);

            $refreshedSubscription = $this->subscriptionRepository->find($subscriptionId);

            if ($this->shouldDispatchLifecycleEvent((int)$subscriptionId)) {
                event(new SubscriptionReactivated(
                    subscriptionId: (int)$subscriptionId,
                    daysRemaining: $daysRemaining,
                ));
            }

            return [
                'success' => true,
                'subscription' => $refreshedSubscription,
                'days_remaining' => $daysRemaining,
                'message' => $daysRemaining
                    ? "Reactivated with {$daysRemaining} days remaining"
                    : 'Reactivated successfully',
            ];
        });
    }

    // -------------------------------------------------------------------------
    // Subscription policy integration
    // -------------------------------------------------------------------------

    /**
     * Resolves the subscription's assigned policy, builds the
     * CancellationPolicyContext, and evaluates it. Throws when the policy
     * does not allow the cancellation. Contains no entitlement logic
     * itself — that decision belongs entirely to the resolved policy
     * (StandardConsumerPolicy/PremiumPolicy/CorporatePolicy/
     * NoReplacementPolicy etc.), per ticket acceptance criteria.
     *
     * Uses ReplacementPolicyResolver::resolveForPlan() (not
     * resolveForSubscription()) so it can reuse the $subscription already
     * loaded by cancelSubscription() rather than issuing a second
     * repository lookup.
     */
    private function assertCancellationAllowedByPolicy(
        Subscription $subscription,
        array $options,
        bool $cancelAtPeriodEnd,
        ?CancellationReason $resolvedReason,
    ): void {
        $policy = $this->policyResolver->resolveForPlan(
            (int) $subscription->plan_id,
            (int) $subscription->site_id,
            (int) $subscription->id
        );

        $context = $this->buildCancellationContext($subscription, $options, $cancelAtPeriodEnd, $policy::class, $resolvedReason);

        $evaluation = $policy->evaluateCancellation($context);

        if (!$evaluation->isAllowed()) {
            throw new Exception(
                $evaluation->blockedReason ?? 'This subscription cannot be cancelled under its current policy.'
            );
        }
    }

    /**
     * Resolves the CancellationReason model from either
     * `cancellation_reason_id` or the legacy `cancellation_reason` code
     * string. Returns null when neither option key is present (some
     * callers, e.g. reactivation-adjacent flows, cancel without a
     * reason). Throws on an unknown/inactive id or code — that is a
     * genuine input validation failure, not the non-critical case.
     */
    private function resolveCancellationReason(array $options): ?CancellationReason
    {
        if (isset($options['cancellation_reason_id'])) {
            $reason = $this->cancellationReasonRepository->findActive((int) $options['cancellation_reason_id']);

            if (!$reason) {
                throw new Exception('Unknown or inactive cancellation reason.');
            }

            return $reason;
        }

        if (isset($options['cancellation_reason'])) {
            $reason = $this->cancellationReasonRepository->findActiveByCode((string) $options['cancellation_reason']);

            if (!$reason) {
                throw new Exception('Unknown or inactive cancellation reason.');
            }

            return $reason;
        }

        return null;
    }

    /**
     * Enforces the resolved Business Decision's allow_cancel for the
     * chosen reason (ticket acceptance criteria: "The cancel endpoint
     * validates the selected save action against resolved options").
     * Skipped entirely when no reason was given — legacy callers that
     * cancel without a reason are unaffected.
     */
    private function assertCancellationAllowedByBusinessDecision(
        Subscription $subscription,
        ?CancellationReason $resolvedReason,
    ): void {
        if ($resolvedReason === null) {
            return;
        }

        $options = $this->cancellationOptionsResolver->resolveOptionsForReasonId(
            (int) $subscription->plan_id,
            (int) $subscription->site_id,
            (int) $resolvedReason->id,
        );

        if (!$options->allowCancel) {
            throw new Exception('This cancellation reason does not permit cancelling this subscription.');
        }
    }

    /**
     * Reasons flagged requires_note (e.g. "Other") must carry free-text
     * notes — the catalogue row is the source of truth, not the UI.
     */
    private function assertCancellationNoteProvided(
        ?CancellationReason $resolvedReason,
        array $options,
    ): void {
        if ($resolvedReason === null || !$resolvedReason->requires_note) {
            return;
        }

        $notes = trim((string) ($options['cancellation_notes'] ?? ''));

        if ($notes === '') {
            throw new Exception('A note is required for this cancellation reason.');
        }
    }

    /**
     * Caps (or rejects) a refund against the reason's refund_max_percent.
     * Legacy callers that refund without a reason keep their existing
     * uncapped behaviour.
     */
    private function applyRefundPolicyCap(
        Subscription $subscription,
        RefundStrategy $strategy,
        ?CancellationReason $resolvedReason,
    ): RefundStrategy {
        if ($resolvedReason === null) {
            return $strategy;
        }

        $policyOptions = $this->cancellationOptionsResolver->resolveOptionsForReasonId(
            (int) $subscription->plan_id,
            (int) $subscription->site_id,
            (int) $resolvedReason->id,
        );

        if ($policyOptions->refundMaxPercent <= 0) {
            throw new Exception('This cancellation reason does not permit a refund.');
        }

        $calculated = $strategy->calculate($subscription);

        if ($calculated->noRefundDue || $calculated->amount <= 0) {
            return $strategy;
        }

        $paymentAmount = (float) ($calculated->meta['paid_amount']
            ?? $calculated->meta['original_amount']
            ?? $calculated->amount);

        $cap = $this->refundCapCalculator->maxRefundableAmount(
            $paymentAmount,
            $policyOptions->refundMaxPercent,
        );

        if ($cap <= 0) {
            throw new Exception('This cancellation reason does not permit a refund.');
        }

        if ($calculated->amount <= $cap) {
            return $strategy;
        }

        return new ManualRefundStrategy(
            $this->paymentRepository,
            $cap,
            (string) ($calculated->meta['reason'] ?? 'immediate_cancellation'),
        );
    }

    /**
     * Writes the resolved marketing_consent decision for this reason to
     * the member's existing consent record (ConsentService/MemberConsent
     * — not Stripe/ChargeBee metadata; confirmed with the requester,
     * this codebase already has a proper consent domain).
     *
     * Non-critical per the error-handling contract: this is a compliance
     * side effect of the cancellation, not the cancellation itself, and
     * the Stripe call above has already committed externally by this
     * point — failing the whole cancellation (and rolling back the DB
     * writes) over a consent-record write would leave Stripe and the DB
     * inconsistent, which is worse than logging and moving on.
     */
    private function writeMarketingConsentDecision(Subscription $subscription, CancellationReason $resolvedReason): void
    {
        try {
            $options = $this->cancellationOptionsResolver->resolveOptionsForReasonId(
                (int) $subscription->plan_id,
                (int) $subscription->site_id,
                (int) $resolvedReason->id,
            );

            $member = $subscription->member;

            if (!$member) {
                return;
            }

            if ($options->marketingConsent) {
                $this->consentService->grantConsent(
                    $member,
                    self::MARKETING_CONSENT_CODE,
                    'crm_cancellation',
                    metadata: ['cancellation_reason_code' => $resolvedReason->code],
                );
            } else {
                $this->consentService->revokeConsent(
                    $member,
                    self::MARKETING_CONSENT_CODE,
                    'crm_cancellation',
                    reason: 'Resolved from cancellation reason: ' . $resolvedReason->code,
                );
            }
        } catch (Exception $e) {
            Logger::error('Failed to write marketing consent decision on cancellation', [
                'subscription_id' => $subscription->id,
                'cancellation_reason_id' => $resolvedReason->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function buildCancellationContext(
        Subscription $subscription,
        array $options,
        bool $cancelAtPeriodEnd,
        string $policyClass,
        ?CancellationReason $resolvedReason,
    ): CancellationPolicyContext {
        // Pass-through only (no policy pattern-matches on the value) —
        // prefer the already-resolved reason's code so this stays
        // consistent whichever of cancellation_reason_id/
        // cancellation_reason the caller supplied.
        $reason = $resolvedReason?->code ?? (isset($options['cancellation_reason']) ? (string) $options['cancellation_reason'] : null);

        // ASSUMPTION: this service's public API takes a cancel_at_period_end
        // flag rather than an explicit requested date, so the "requested
        // cancellation date" the ticket asks for is derived: the current
        // period's end_date when cancelling at period end, or now for an
        // immediate cancellation.
        $requestedCancellationDate = $cancelAtPeriodEnd
            ? $this->toDateTimeImmutable($subscription->end_date)
            : new DateTimeImmutable();

        return new CancellationPolicyContext(
            subscription: $subscription,
            planId: (int) $subscription->plan_id,
            reason: $reason,
            cancellationNotes: $options['cancellation_notes'] ?? null,
            requestedCancellationDate: $requestedCancellationDate,
            currentStatus: SubscriptionStatus::tryFrom((string) $subscription->status) ?? SubscriptionStatus::ACTIVE,
            subscriptionAgeDays: $this->termCalculator->ageDays($subscription),
            remainingTermDays: $this->termCalculator->remainingTermDays($subscription),
            settingOverrides: $this->settingOverrideResolver->resolveForSitePolicy(
                (int) $subscription->site_id,
                $policyClass
            ),
        );
    }

    private function toDateTimeImmutable(mixed $value): ?DateTimeImmutable
    {
        if ($value instanceof DateTimeImmutable) {
            return $value;
        }

        if ($value instanceof \DateTimeInterface) {
            return DateTimeImmutable::createFromInterface($value);
        }

        if (is_string($value) && $value !== '') {
            return new DateTimeImmutable($value);
        }

        return null;
    }

    // -------------------------------------------------------------------------
    // Strategy resolution
    // -------------------------------------------------------------------------

    /**
     * Resolve the appropriate refund strategy from cancellation options.
     *
     * Precedence rule (from ticket):
     *   1. If refund_amount is provided → ManualRefundStrategy (always wins)
     *   2. If refund_type === 'full'    → FullRefundStrategy
     *   3. Default                      → ProRatedRefundStrategy
     */
    private function resolveRefundStrategy(Subscription $subscription, array $options): RefundStrategy
    {
        // Override takes absolute precedence
        if (isset($options['refund_amount'])) {
            return new ManualRefundStrategy(
                $this->paymentRepository,
                (float)$options['refund_amount'],
                $options['refund_reason'] ?? 'immediate_cancellation'
            );
        }

        $refundType = $options['refund_type'] ?? 'pro_rated';

        return match ($refundType) {
            'full' => new FullRefundStrategy(
                $this->paymentRepository,
                $options['refund_reason'] ?? 'immediate_cancellation'
            ),
            'pro_rated' => new ProRatedRefundStrategy(
                $this->paymentRepository,
                $options['refund_reason'] ?? 'immediate_cancellation'
            ),
            default => throw new Exception("Invalid refund type: {$refundType}"),
        };
    }

    // -------------------------------------------------------------------------
    // Premium access
    // -------------------------------------------------------------------------

    private function refreshPremiumAccess(Subscription $subscription): void
    {
        if (!$subscription->plan) {
            return;
        }

        $premiumGrants = $subscription->plan->getPremiumAccessGrants();

        foreach ($premiumGrants as $grant) {
            $subscription->grantPremiumAccess(
                $grant['type'],
                $grant['identifier'],
                $grant['expires_at'] ?? null
            );
        }
    }

    private function shouldDispatchLifecycleEvent(int $subscriptionId): bool
    {
        if (($_ENV['APP_ENV'] ?? getenv('APP_ENV')) === 'testing') {
            return false;
        }

        return Subscription::where('id', $subscriptionId)->exists();
    }

    private function formatEventDate(mixed $value): ?string
    {
        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d H:i:s');
        }

        return is_string($value) ? $value : null;
    }
}