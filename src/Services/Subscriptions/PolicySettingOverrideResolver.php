<?php

declare(strict_types=1);

namespace App\Services\Subscriptions;

use App\DTO\Subscriptions\SubscriptionPolicySettingOverrides;
use App\Repositories\Subscriptions\SubscriptionPolicySettingOverrideRepository;

/**
 * Read-side of the policy setting override feature: turns the active DB
 * rows for a (site, policy class) pair into the SubscriptionPolicySettingOverrides
 * value object that gets attached to PausePolicyContext/CancellationPolicyContext.
 *
 * Deliberately has no write methods — see SubscriptionPolicySettingOverrideService
 * for the admin-authored write path, kept separate per the "services
 * orchestrate, don't do double duty" principle.
 */
class PolicySettingOverrideResolver
{
    public function __construct(
        private readonly SubscriptionPolicySettingOverrideRepository $repository,
    ) {
    }

    public function resolveForSitePolicy(int $siteId, string $policyClass): SubscriptionPolicySettingOverrides
    {
        $overrides = $this->repository->activeForSitePolicy($siteId, $policyClass);

        $values = [];
        foreach ($overrides as $override) {
            $values[$override->setting_key] = $override->value;
        }

        return new SubscriptionPolicySettingOverrides($values);
    }
}