<?php

namespace App\Repositories\Subscriptions;

use App\Enums\Subscriptions\SubscriptionStatus;
use App\Enums\Subscriptions\SubscriptionType;
use App\Events\Subscriptions\SubscriptionCreated;
use App\Framework\Support\Collection;
use App\Framework\Support\SiteContext;
use App\Models\Model;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Repositories\Repository;

class SubscriptionRepository extends Repository
{
    /**
     * Find all active print subscriptions eligible to receive a specific
     * issue delivery.
     *
     * Eligibility rules (all must pass):
     *   1. delivery_type = 'print'
     *   2. status = 'active'
     *   3. plan_id matches the IssueDelivery's subscription_plan_id
     *   4. start_date is on or before the reference date (or null)
     *   5. end_date is on or after the reference date (or null — open-ended)
     *
     * Address validation is intentionally deferred to CreatePrintFulfillmentAction.
     * A missing address is a per-subscription failure, not a reason to exclude
     * the subscriber from the result set — the action logs it and the pipeline
     * continues for the remaining subscriptions.
     *
     * Idempotency (fulfilled exclusion) is also deferred to
     * CreatePrintFulfillmentAction, which guards against duplicate
     * PrintFulfillment records. Excluding at query level would make the
     * query more complex and would prevent legitimate re-runs after a
     * failed export.
     *
     * Reference date: the issue's on_sale_date when set, otherwise
     * estimated_delivery_date. The caller resolves and passes this date
     * so the repository stays free of IssueDelivery model knowledge.
     *
     * @param int $issueDeliveryId Used only for logging context — not filtered on.
     * @param int $planId The subscription_plan_id from the IssueDelivery.
     * @param \DateTime $referenceDate on_sale_date ?? estimated_delivery_date.
     *
     * @return Collection<Subscription>
     */
    public function findPrintSubscriptionsForIssueDelivery(
        int       $issueDeliveryId,
        int       $planId,
        \DateTime $referenceDate,
    ): Collection
    {
        return Subscription::where('delivery_type', SubscriptionType::PRINTED->value)
            ->where('status', SubscriptionStatus::ACTIVE->value)
            ->where('plan_id', $planId)
            ->where(function ($query) use ($referenceDate) {
                $query->whereNull('start_date')
                    ->orWhere('start_date', '<=', $referenceDate->format('Y-m-d H:i:s'));
            })
            ->where(function ($query) use ($referenceDate) {
                $query->whereNull('end_date')
                    ->orWhere('end_date', '>=', $referenceDate->format('Y-m-d H:i:s'));
            })
            ->get();
    }

    /**
     * Returns true when at least one active print subscription exists for
     * the given plan_id, globally across all sites.
     *
     * Used by IssueDeliveryDispatchedListener as a fast existence check
     * before dispatching TriggerPrintRunWorkflowJob. If no print subscriptions
     * exist for the plan, the print pipeline is not triggered at all —
     * no PrintRun is created, no jobs are wasted.
     *
     * Deliberately a global check (no site_id filter) because:
     *   - IssueDelivery already scopes to a plan
     *   - Plans are already site-scoped upstream
     *   - Adding site_id here would require passing it through the event
     *     chain for no extra safety benefit
     *
     * @param int $planId The subscription_plan_id from the IssueDelivery.
     */
    public function hasPrintSubscriptionsForPlan(int $planId): bool
    {
        return Subscription::where('delivery_type', SubscriptionType::PRINTED->value)
            ->where('status', SubscriptionStatus::ACTIVE->value)
            ->where('plan_id', $planId)
            ->exists();
    }

    /**
     * Chunk through active subscriptions for a specific plan.
     *
     * @param mixed $planId
     * @param int $chunkSize
     * @param callable $callback
     * @return bool
     */
    public function chunkActiveByPlan($planId, int $chunkSize, callable $callback): bool
    {
        return Subscription::where('plan_id', $planId)
            ->where('status', 'active') // Adjust this column/value based on your DB schema
            ->chunk($chunkSize, $callback);
    }

    protected function getModelClass(): string
    {
        return Subscription::class;
    }

    public function getActiveSubscriptionForMember(
        int  $memberId,
        ?int $siteId = null,
        bool $includeRecentlyExpired = false
    ): ?Subscription
    {
        $siteId = $siteId ?? SiteContext::getId();

        return Subscription::where('member_id', $memberId)
            ->where('site_id', $siteId)
            ->where('status', 'active')
            ->where(function ($query) use ($includeRecentlyExpired) {
                $query->whereNull('end_date');

                // Only include recently expired if flag is true
                if ($includeRecentlyExpired) {
                    $query->orWhereRaw('end_date >= DATE_SUB(NOW(), INTERVAL 30 DAY)');
                } else {
                    $query->orWhereRaw('end_date >= NOW()');
                }
            })
            ->where(function ($query) {
                $query->whereNull('start_date')
                    ->orWhereRaw('start_date <= NOW()');
            })
            ->first();
    }

    public function getSubscriptionHistory(int $memberId, ?int $siteId = null): Collection
    {
        $siteId = $siteId ?? $this->siteId;

        return Subscription::where('member_id', $memberId)
            ->where('site_id', $siteId)
            ->orderBy('created_at', 'desc')
            ->get();
    }

    public function countActiveSubscriptions(int $memberId, ?int $siteId = null): int
    {
        $siteId = $siteId ?? $this->siteId;

        return Subscription::where('member_id', $memberId)
            ->where('site_id', $siteId)
            ->where('status', 'active')
            ->count();
    }

    public function findActiveWithDifferentCurrency(int $memberId, string $currency, ?int $excludeSubscriptionId = null): ?Subscription
    {
        $query = Subscription::where('member_id', $memberId)
            ->whereIn('status', [
                SubscriptionStatus::ACTIVE->value,
                SubscriptionStatus::TRIALING->value,
                SubscriptionStatus::GRACE_PERIOD->value,
                SubscriptionStatus::RETRYING->value,
                SubscriptionStatus::PAST_DUE->value,
            ])
            ->whereRaw('UPPER(currency) != :currency', ['currency' => strtoupper($currency)]);

        if ($excludeSubscriptionId !== null) {
            $query->where('id', '!=', $excludeSubscriptionId);
        }

        return $query->first();
    }

    public function cancelSubscription(int $subscriptionId): bool
    {
        $subscription = $this->find($subscriptionId);

        if (!$subscription) {
            return false;
        }

        return $this->update($subscriptionId, [
                'status' => 'cancelled',
                'auto_renew' => false
            ]) !== null;
    }

    public function createSubscription(int $memberId, int $planId, int $siteId, array $additionalData = []): Model
    {
        $plan = SubscriptionPlan::findOrFail($planId);

        $startDate = new \DateTimeImmutable();
        $endDate = null;
        $nextBillingDate = null;

        if ($plan->billing_period !== 'lifetime') {
            $interval = match ($plan->billing_period) {
                'weekly' => '+1 week',
                'monthly' => '+1 month',
                'quarterly' => '+3 months',
                'yearly' => '+1 year',
                default => throw new \InvalidArgumentException('Unrecognized billing period'),
            };

            $endDate = $startDate->modify($interval);
            $nextBillingDate = $startDate->modify($interval);
        }

        $subscription = Subscription::create(array_merge([
            'member_id' => $memberId,
            'site_id' => $siteId,
            'plan_id' => $planId,
            'plan_name' => $plan->name,
            'status' => 'active',
            'start_date' => $startDate->format('Y-m-d H:i:s'),
            'end_date' => $endDate?->format('Y-m-d H:i:s'),
            'next_billing_date' => $nextBillingDate?->format('Y-m-d H:i:s'),
            'price' => $plan->price,
            'currency' => $plan->currency,
            'auto_renew' => $plan->billing_period !== 'lifetime',
        ], $additionalData));

        // **GRANT PREMIUM ACCESS FROM PLAN**
        $this->grantPlanPremiumAccess($subscription, $plan);

        if (($_ENV['APP_ENV'] ?? getenv('APP_ENV')) !== 'testing') {
            event(new SubscriptionCreated(
                subscriptionId: (int)$subscription->id,
                planId: (int)$plan->id,
                billingPeriod: (string)$plan->billing_period,
                priceCents: (int)round(((float)($subscription->price ?? $plan->price)) * 100),
                currency: (string)($subscription->currency ?? $plan->currency ?? 'GBP'),
            ));
        }

        return $subscription;
    }

    /**
     * Grant all premium access defined in the plan
     */
    private function grantPlanPremiumAccess(Subscription $subscription, SubscriptionPlan $plan): void
    {
        $premiumGrants = $plan->getPremiumAccessGrants();

        foreach ($premiumGrants as $grant) {
            $subscription->grantPremiumAccess(
                $grant['type'],
                $grant['identifier'],
                $grant['expires_at'] ?? null
            );

            \App\Framework\Support\Logger::info('Premium access granted on subscription creation', [
                'subscription_id' => $subscription->id,
                'premium_type' => $grant['type'],
                'premium_identifier' => $grant['identifier']
            ]);
        }

        // Backward compatibility - set includes_digital_access if insider granted
        if ($plan->grantsPremiumAccess('newsletter', 'insider')) {
            $subscription->update(['includes_digital_access' => true]);
        }
    }

    public function hasActiveSubscriptionToPlan(int $memberId, int $planId, ?int $siteId = null, bool $allowCancelled = true): bool
    {
        $siteId = $siteId ?? SiteContext::getId();

        $statuses = $allowCancelled ? ['active', 'cancelled'] : ['active'];

        return Subscription::where('member_id', $memberId)
            ->where('plan_id', $planId)
            ->where('site_id', $siteId)
            ->whereIn('status', $statuses)
            ->exists();
    }

    public function getMemberLastSubscriptionCheck(int $memberId, int $siteId): ?string
    {
        // Check session/cookie for last time we showed the modal
        $key = "subscription_modal_shown_{$memberId}_{$siteId}";
        return $_SESSION[$key] ?? null;
    }

    public function setMemberSubscriptionCheckTime(int $memberId, int $siteId): void
    {
        $key = "subscription_modal_shown_{$memberId}_{$siteId}";
        $_SESSION[$key] = date('Y-m-d H:i:s');
    }

    public function shouldShowSubscriptionModal(int $memberId, int $siteId): bool
    {
        // Don't show if they have active subscription
        if ($this->getActiveSubscriptionForMember($memberId, $siteId)) {
            return false;
        }

        // Don't show if we showed it in the last 24 hours
        $lastShown = $this->getMemberLastSubscriptionCheck($memberId, $siteId);

        if ($lastShown) {
            $lastShownTime = strtotime($lastShown);
            $hoursSince = (time() - $lastShownTime) / 3600;
            if ($hoursSince < 24) {
                return false;
            }
        }

        return true;
    }

    public function getSubscriptionsDueForRenewal(?int $siteId = null): Collection
    {
        $siteId = $siteId ?? SiteContext::getId();

        return Subscription::where('site_id', $siteId)
            ->where('status', 'active')
            ->where('auto_renew', true)
            ->whereNotNull('next_billing_date')
            ->where('next_billing_date', '<=', date('Y-m-d H:i:s'))
            ->get();
    }

    public function updateNextBillingDate(int $subscriptionId, \DateTime $nextBillingDate): bool
    {
        return $this->update($subscriptionId, [
                'next_billing_date' => $nextBillingDate->format('Y-m-d H:i:s')
            ]) !== null;
    }

    public function updateLastPaymentDate(int $subscriptionId, ?\DateTime $lastPaymentDate = null): bool
    {
        $lastPaymentDate ??= new \DateTime();

        return $this->update($subscriptionId, [
                'last_payment_date' => $lastPaymentDate->format('Y-m-d H:i:s'),
            ]) !== null;
    }

    public function markAsPastDue(int $subscriptionId): bool
    {
        return $this->update($subscriptionId, [
                'status' => 'past_due'
            ]) !== null;
    }

    public function getSubscriptionsWithFailedPayments(?int $siteId = null): Collection
    {
        $siteId = $siteId ?? SiteContext::getId();

        return Subscription::where('site_id', $siteId)
            ->where('status', 'past_due')
            ->get();
    }

    public function getCancelledSubscriptionForPlan(int $memberId, int $planId, int $siteId): ?Subscription
    {
        return Subscription::where('member_id', $memberId)
            ->where('plan_id', $planId)
            ->where('site_id', $siteId)
            ->where('status', 'cancelled')
            ->orderBy('updated_at', 'DESC')
            ->first();
    }

    public function getCancelledSubscriptionForMember(int $memberId, int $siteId): ?Subscription
    {
        return Subscription::where('member_id', $memberId)
            ->where('site_id', $siteId)
            ->where('status', 'cancelled')
            ->orderBy('updated_at', 'DESC')
            ->first();
    }

    /**
     * Revoke all premium access for a subscription
     */
    public function revokeAllPremiumAccess(int $subscriptionId): void
    {
        $subscription = $this->find($subscriptionId);

        if (!$subscription) {
            return;
        }

        // Get premium access - handle both Collection and Relation
        $relation = $subscription->premiumAccess();
        $premiumAccess = $relation instanceof Collection ? $relation : $relation->get();

        foreach ($premiumAccess as $access) {
            $subscription->revokePremiumAccess(
                $access->premium_type,
                $access->premium_identifier
            );

            \App\Framework\Support\Logger::info('Premium access revoked on cancellation', [
                'subscription_id' => $subscription->id,
                'premium_type' => $access->premium_type,
                'premium_identifier' => $access->premium_identifier
            ]);
        }

        $subscription->update(['includes_digital_access' => false]);
    }

    /**
     * Check if a pending payment already exists for the current billing cycle
     */
    public function hasPendingPaymentForCycle(int $subscriptionId, ?\DateTime $billingDate = null): bool
    {
        $billingDate ??= new \DateTime();

        $startOfDay = (clone $billingDate)->setTime(0, 0, 0);
        $endOfDay   = (clone $billingDate)->setTime(23, 59, 59);

        $query = "
        SELECT COUNT(*) as count 
        FROM payments 
        WHERE subscription_id = ? 
        AND status = 'pending' 
        AND created_at BETWEEN ? AND ?
    ";

        $result = $this->database->query($query, [
            $subscriptionId,
            $startOfDay->format('Y-m-d H:i:s'),
            $endOfDay->format('Y-m-d H:i:s'),
        ]);

        return ($result[0]['count'] ?? 0) > 0;
    }

    /**
     * Returns all active subscriptions for a given plan whose active window
     * includes $scheduledDate.
     *
     * @return Collection<Subscription>
     */
    public function findActiveByPlanAndDate(int $planId, \DateTime $scheduledDate): Collection
    {
        return Subscription::where('plan_id', $planId)
            ->where('status', SubscriptionStatus::ACTIVE->value)
            ->where(function ($query) use ($scheduledDate) {
                $query->where('start_date', '<=', $scheduledDate->format('Y-m-d H:i:s'))
                    ->orWhereNull('start_date');
            })
            ->where(function ($query) use ($scheduledDate) {
                $query->where('end_date', '>=', $scheduledDate->format('Y-m-d H:i:s'))
                    ->orWhereNull('end_date');
            })
            ->get();
    }

    public function getActivePlanIds(int $userId, array $planIds, ?int $siteId = null): array
    {
        $siteId = $siteId ?? SiteContext::getId();

        return Subscription::where('member_id', $userId)
            ->where('site_id', $siteId)
            ->whereIn('plan_id', $planIds)
            ->whereIn('status', Subscription::ACTIVE_STATUSES)
            ->where(function ($query) {
                $query->whereNull('end_date')
                    ->orWhere('end_date', '>=', now());
            })
            ->get()
            ->pluck('plan_id')
            ->all();
    }

    /**
     * Returns all subscriptions whose status is Scheduled and whose
     * start_date has already passed (i.e. they are due to go active).
     *
     * The limit is a safety guard against unbounded result sets when the
     * job falls behind. Tune it to match your expected batch size.
     */
    public function getScheduledDue(\DateTimeImmutable $asOf, int $limit = 500): Collection
    {
        return Subscription::query()
            ->where('status', SubscriptionStatus::SCHEDULED->value)
            ->where('start_date', '<=', $asOf->format('Y-m-d H:i:s'))
            ->limit($limit)
            ->get();
    }

    /**
     * Persist the status transition to Active.
     *
     * Only `status` is updated here. If an `activated_at` timestamp column
     * is added to the subscriptions table in the future, add it to
     * Subscription::$fillable and include it in the array below.
     */
    public function markAsActive(Subscription $subscription, \DateTimeImmutable $asOf): void
    {
        $subscription->update([
            'status' => SubscriptionStatus::ACTIVE->value,
        ]);
    }

    public function getSubscribersForPlan(
        int     $planId,
        int     $page = 1,
        int     $perPage = 25,
        ?string $status = null,
    ): array
    {
        $offset = ($page - 1) * $perPage;

        $base = Subscription::where('plan_id', $planId);

        if ($status !== null) {
            $base->where('status', $status);
        }

        $total = (clone $base)->count();

        $items = (clone $base)
            ->with(['member'])
            ->orderBy('created_at', 'desc')
            ->limit($perPage)
            ->offset($offset)
            ->get();

        return [
            'items' => $items,
            'total' => $total,
            'per_page' => $perPage,
            'current_page' => $page,
            'last_page' => max(1, (int)ceil($total / $perPage)),
        ];
    }

    public function findSubscriptionByStripeId(string $stripeSubscriptionId): ?Subscription
    {
        return Subscription::where('payment_subscription_id', $stripeSubscriptionId)->first();
    }

    public function memberHadTrialOnPlan(
        int $memberId,
        int $planId
    ): bool {
        return Subscription::where('member_id', $memberId)
            ->where('plan_id', $planId)
            ->whereNotNull('trial_used_at')
            ->exists();
    }

    /**
     * Returns all active auto-renew subscriptions whose next_billing_date
     * has passed, across all sites.
     *
     * Intentionally cross-site: the renewal command processes every site in
     * one run. SiteContext is not available in a console context.
     *
     * The limit guards against unbounded result sets when the job falls behind.
     */
    public function findAllDueForRenewal(\DateTimeImmutable $asOf, int $limit = 500): Collection
    {
        return Subscription::where('status', SubscriptionStatus::ACTIVE->value)
            ->where('auto_renew', true)
            ->whereNotNull('next_billing_date')
            ->where('next_billing_date', '<=', $asOf->format('Y-m-d H:i:s'))
            ->limit($limit)
            ->get();
    }
}
