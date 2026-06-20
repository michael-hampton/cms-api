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
    public function findPrintSubscriptionsForIssueDelivery(
        int $issueDeliveryId,
        int $planId,
        \DateTime $referenceDate,
    ): Collection {
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

    public function hasPrintSubscriptionsForPlan(int $planId): bool
    {
        return Subscription::where('delivery_type', SubscriptionType::PRINTED->value)
            ->where('status', SubscriptionStatus::ACTIVE->value)
            ->where('plan_id', $planId)
            ->exists();
    }

    public function chunkActiveByPlan($planId, int $chunkSize, callable $callback): bool
    {
        return Subscription::where('plan_id', $planId)
            ->where('status', 'active')
            ->chunk($chunkSize, $callback);
    }

    protected function getModelClass(): string
    {
        return Subscription::class;
    }

    /**
     * Pause metadata was introduced after the Subscription model's original
     * mass-assignment contract. Apply those internal fields explicitly while
     * preserving the normal fillable protection for all other attributes.
     */
    public function update(int $id, array $data): ?Model
    {
        $subscription = $this->find($id);

        if (!$subscription) {
            return null;
        }

        $subscription->fill($data);

        foreach (['auto_renew_before_pause', 'paused_at', 'resumed_at'] as $attribute) {
            if (array_key_exists($attribute, $data)) {
                $subscription->setAttribute($attribute, $data[$attribute]);
            }
        }

        $subscription->save();

        return $subscription;
    }

    public function getActiveSubscriptionForMember(
        int $memberId,
        ?int $siteId = null,
        bool $includeRecentlyExpired = false,
    ): ?Subscription {
        $siteId = $siteId ?? SiteContext::getId();

        return Subscription::where('member_id', $memberId)
            ->where('site_id', $siteId)
            ->where('status', 'active')
            ->where(function ($query) use ($includeRecentlyExpired) {
                $query->whereNull('end_date');

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

    public function cancelSubscription(int $subscriptionId): bool
    {
        $subscription = $this->find($subscriptionId);

        if (!$subscription) {
            return false;
        }

        return $this->update($subscriptionId, [
            'status' => 'cancelled',
            'auto_renew' => false,
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

        $this->grantPlanPremiumAccess($subscription, $plan);

        if (($_ENV['APP_ENV'] ?? getenv('APP_ENV')) !== 'testing') {
            event(new SubscriptionCreated(
                subscriptionId: (int) $subscription->id,
                planId: (int) $plan->id,
                billingPeriod: (string) $plan->billing_period,
                priceCents: (int) round(((float) ($subscription->price ?? $plan->price)) * 100),
                currency: (string) ($subscription->currency ?? $plan->currency ?? 'GBP'),
            ));
        }

        return $subscription;
    }

    private function grantPlanPremiumAccess(Subscription $subscription, SubscriptionPlan $plan): void
    {
        if (!$plan->relationLoaded('premiumNewsletters')) {
            $plan->load('premiumNewsletters');
        }

        foreach ($plan->premiumNewsletters ?? [] as $newsletter) {
            $subscription->grantPremiumAccess($newsletter->id, $newsletter->pivot->access_type);
        }
    }
}
