<?php

namespace App\Database\Seeders;

use App\Models\ReplacementPolicy;
use App\Models\Site;

/**
 * Seeds the baseline policy set from the ticket for every existing site.
 * Idempotent by (site_id, name) so it's safe to re-run.
 *
 * IMPORTANT: this only seeds the policy *rows* — it does not assign them
 * to subscription_plans.replacement_policy_id. That mapping
 * (Promotional -> No Replacement, Bronze -> Standard Consumer, etc.) is
 * real business data tied to your actual plan records, which I don't
 * have — see IMPLEMENTATION_NOTES.md for the follow-up needed there.
 *
 * "Goodwill" is intentionally not marked is_default and is not meant to
 * be assigned to any plan — it exists only to be selected manually
 * during a business override, per the ticket.
 */
class SeedBaselineReplacementPolicies extends \App\Framework\Database\Seeder\Seeder
{
    public function run(): void
    {
        foreach (Site::all() as $site) {
            foreach ($this->policyDefinitions() as $definition) {
                $exists = ReplacementPolicy::where('site_id', $site->id)
                    ->where('name', $definition['name'])
                    ->exists();

                if ($exists) {
                    continue;
                }

                ReplacementPolicy::create(array_merge(
                    ['site_id' => $site->id],
                    $definition
                ));
            }
        }
    }

    public function down(): void
    {
        foreach ($this->policyDefinitions() as $definition) {
            ReplacementPolicy::where('name', $definition['name'])->delete();
        }
    }

    private function policyDefinitions(): array
    {
        return [
            [
                'name' => 'Default (Fallback)',
                'description' => 'Conservative safety net used when a plan has no assigned policy. New plans never accidentally grant entitlements.',
                'allows_replacements' => false,
                'allows_extensions' => false,
                'max_replacements' => 0,
                'max_extensions' => 0,
                'replacement_limit_scope' => 'per_subscription',
                'extension_limit_scope' => 'per_subscription',
                'require_stock' => true,
                'requires_manager_approval' => false,
                'is_default' => true,
                'active' => true,
            ],
            [
                'name' => 'No Replacement',
                'description' => 'Promotional, trial, complimentary, and employee subscriptions.',
                'allows_replacements' => false,
                'allows_extensions' => false,
                'max_replacements' => 0,
                'max_extensions' => 0,
                'replacement_limit_scope' => 'per_subscription',
                'extension_limit_scope' => 'per_subscription',
                'require_stock' => true,
                'requires_manager_approval' => false,
                'is_default' => false,
                'active' => true,
            ],
            [
                'name' => 'Standard Consumer',
                'description' => 'Bronze / standard monthly subscriptions.',
                'allows_replacements' => true,
                'allows_extensions' => true,
                'max_replacements' => 1,
                'max_extensions' => 1,
                'replacement_limit_scope' => 'per_issue',
                'extension_limit_scope' => 'per_subscription',
                'require_stock' => true,
                'requires_manager_approval' => false,
                'is_default' => false,
                'active' => true,
            ],
            [
                'name' => 'Premium',
                'description' => 'Silver / Gold subscriptions — unlimited entitlement, no overrides needed.',
                'allows_replacements' => true,
                'allows_extensions' => true,
                'max_replacements' => null,
                'max_extensions' => null,
                'replacement_limit_scope' => 'per_issue',
                'extension_limit_scope' => 'per_subscription',
                'require_stock' => true,
                'requires_manager_approval' => false,
                'is_default' => false,
                'active' => true,
            ],
            [
                'name' => 'Corporate',
                'description' => 'Corporate accounts, schools, libraries, businesses. Unlimited entitlement, gated behind manager approval (enum only — no workflow implemented yet).',
                'allows_replacements' => true,
                'allows_extensions' => true,
                'max_replacements' => null,
                'max_extensions' => null,
                'replacement_limit_scope' => 'per_issue',
                'extension_limit_scope' => 'per_subscription',
                'require_stock' => true,
                'requires_manager_approval' => true,
                'is_default' => false,
                'active' => true,
            ],
            [
                'name' => 'Digital Only',
                'description' => 'Digital subscriptions — no physical replacement possible, extensions unlimited, no stock constraint.',
                'allows_replacements' => false,
                'allows_extensions' => true,
                'max_replacements' => 0,
                'max_extensions' => null,
                'replacement_limit_scope' => 'per_subscription',
                'extension_limit_scope' => 'per_subscription',
                'require_stock' => false,
                'requires_manager_approval' => false,
                'is_default' => false,
                'active' => true,
            ],
            [
                'name' => 'Goodwill',
                'description' => 'Not assigned to any plan. Operational policy selected only during a manual business override.',
                'allows_replacements' => true,
                'allows_extensions' => true,
                'max_replacements' => null,
                'max_extensions' => null,
                'replacement_limit_scope' => 'per_issue',
                'extension_limit_scope' => 'per_subscription',
                'require_stock' => true,
                'requires_manager_approval' => false,
                'is_default' => false,
                'active' => true,
            ],
        ];
    }
}