<?php

declare(strict_types=1);

namespace App\Repositories\Subscriptions;

use App\Models\ReplacementPolicy;
use App\Models\SubscriptionPlan;
use App\Repositories\Repository;

class ReplacementPolicyRepository extends Repository
{
    protected function getModelClass(): string
    {
        return ReplacementPolicy::class;
    }

    /**
     * The active default policy for a site. There should only ever be one,
     * but if configuration drifts and more than one is marked default,
     * the first match wins — resolveDefault() in ReplacementPolicyResolver
     * is where that ambiguity should eventually be surfaced/alerted on,
     * not here.
     */
    public function findDefault(int $siteId): ?ReplacementPolicy
    {
        return ReplacementPolicy::where('site_id', $siteId)
            ->where('is_default', true)
            ->where('active', true)
            ->first();
    }

    /**
     * All policies for a site, including inactive ones (an admin listing
     * screen needs to see deactivated policies to potentially reactivate
     * them — active-only filtering belongs to callers that need it, like
     * findDefault()/findForPlan()).
     */
    public function listForSite(int $siteId): \App\Framework\Support\Collection
    {
        return ReplacementPolicy::where('site_id', $siteId)
            ->orderBy('is_default', 'desc')
            ->orderBy('name', 'asc')
            ->get();
    }

    /**
     * Any other active default policy for a site, excluding the given
     * policy ID. Used when swapping which policy is the site default.
     */
    public function findOtherActiveDefault(int $siteId, int $excludingPolicyId): ?ReplacementPolicy
    {
        return ReplacementPolicy::where('site_id', $siteId)
            ->where('is_default', true)
            ->where('active', true)
            ->where('id', '!=', $excludingPolicyId)
            ->first();
    }

    /**
     * Unsets is_default on every other policy for a site. Persistence
     * only — the decision about *when* to call this belongs to
     * ReplacementPolicyService.
     */
    public function clearDefaultForSite(int $siteId, ?int $exceptPolicyId = null): void
    {
        $query = ReplacementPolicy::where('site_id', $siteId)
            ->where('is_default', true);

        if ($exceptPolicyId !== null) {
            $query = $query->where('id', '!=', $exceptPolicyId);
        }

        foreach ($query->get() as $policy) {
            $policy->fill(['is_default' => false]);
            $policy->save();
        }
    }

    /**
     * The active policy assigned to a plan, or null if the plan has no
     * policy assigned (or the assigned policy has been deactivated).
     * A null return is the signal for the resolver to fall back to default.
     */
    public function findForPlan(int $planId): ?ReplacementPolicy
    {
        $plan = SubscriptionPlan::find($planId);

        if (!$plan || !$plan->replacement_policy_id) {
            return null;
        }

        return ReplacementPolicy::where('id', (int) $plan->replacement_policy_id)
            ->where('active', true)
            ->first();
    }

    public function findByClass(string $policyClass, int $siteId): ?ReplacementPolicy
    {
        return ReplacementPolicy::where('site_id', $siteId)
            ->where('policy_class', $policyClass)
            ->where('active', true)
            ->first();
    }
}