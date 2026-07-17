<?php

declare(strict_types=1);

namespace App\Repositories\Subscriptions;

use App\Framework\Support\Collection;
use App\Models\SubscriptionPolicySettingOverride;
use App\Repositories\Repository;

class SubscriptionPolicySettingOverrideRepository extends Repository
{
    protected function getModelClass(): string
    {
        return SubscriptionPolicySettingOverride::class;
    }

    /**
     * All active overrides for a site + policy class, keyed for
     * PolicySettingOverrideResolver to fold into SubscriptionPolicySettingOverrides.
     */
    public function activeForSitePolicy(int $siteId, string $policyClass): Collection
    {
        return SubscriptionPolicySettingOverride::where('site_id', $siteId)
            ->where('policy_class', $policyClass)
            ->where('active', true)
            ->get();
    }

    /**
     * The full override history (active + cleared) for a site + policy
     * class, newest first — for the admin audit view.
     */
    public function historyForSitePolicy(int $siteId, string $policyClass): Collection
    {
        return SubscriptionPolicySettingOverride::where('site_id', $siteId)
            ->where('policy_class', $policyClass)
            ->orderBy('created_at', 'desc')
            ->get();
    }

    /**
     * Deactivates any existing active row for this (site, policy class,
     * setting) triple. Persistence only — callers decide when this is
     * appropriate (SubscriptionPolicySettingOverrideService always calls
     * this immediately before creating a replacement row, inside the
     * same transaction, so at most one row is ever active per key).
     */
    public function deactivateActive(int $siteId, string $policyClass, string $settingKey): void
    {
        $existing = SubscriptionPolicySettingOverride::where('site_id', $siteId)
            ->where('policy_class', $policyClass)
            ->where('setting_key', $settingKey)
            ->where('active', true)
            ->get();

        foreach ($existing as $override) {
            $override->fill(['active' => false]);
            $override->save();
        }
    }
}