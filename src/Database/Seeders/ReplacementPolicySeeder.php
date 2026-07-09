<?php

declare(strict_types=1);

namespace App\Database\Seeders;

use App\Models\ReplacementPolicy;
use App\Models\Site;
use App\Services\Subscriptions\Policies\GoodwillPolicy;
use App\Services\Subscriptions\Policies\NoReplacementPolicy;

/**
 * Migration Strategy Phase 2 (per ticket): seed one default policy for
 * every site before the strategy-pattern application code deploys.
 *
 * Also seeds a GoodwillPolicy row per site — required by
 * ReplacementPolicyResolver::resolveGoodwill() for business overrides.
 * The ticket doesn't explicitly call this out as part of Phase 2, but
 * business overrides can't function without it, so treating it as
 * required at the same point in the rollout.
 *
 * ASSUMPTION: Site model exists with a queryable `all()`/`get()`-style
 * accessor and an `id` column — not confirmed against a real Site model
 * file, mirrored from its use elsewhere in this codebase
 * (SubscriptionPlan::site() belongsTo relation).
 */
class ReplacementPolicySeeder
{
    public function run(): void
    {
        foreach (Site::all() as $site) {
            $this->seedIfMissing($site->id, NoReplacementPolicy::class, 'Default (No Replacements)', [
                'description' => 'System default: no replacements or extensions permitted.',
                'is_default' => true,
            ]);

            $this->seedIfMissing($site->id, GoodwillPolicy::class, 'Goodwill Override', [
                'description' => 'Internal-only policy used for authorised business overrides. Not assignable to plans.',
                'is_default' => false,
            ]);
        }
    }

    private function seedIfMissing(int $siteId, string $policyClass, string $name, array $attributes): void
    {
        $exists = ReplacementPolicy::where('site_id', $siteId)
            ->where('policy_class', $policyClass)
            ->exists();

        if ($exists) {
            return;
        }

        ReplacementPolicy::create(array_merge([
            'site_id' => $siteId,
            'name' => $name,
            'policy_class' => $policyClass,
            'active' => true,
            'is_default' => false,
        ], $attributes));
    }
}
