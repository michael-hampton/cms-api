<?php

declare(strict_types=1);

namespace App\Database\Seeders;

use App\Enums\Subscriptions\ReplacementLimitScope;
use App\Framework\Database\Seeder\Seeder;
use App\Models\ReplacementPolicy;
use App\Models\Site;

class ReplacementPolicySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Fetch all sites using the platform context to anchor policy distributions
        $sites = Site::all();

        if ($sites->isEmpty()) {
            return;
        }

        foreach ($sites as $site) {
            $policies = $this->getPolicyDefinitions();

            foreach ($policies as $policyData) {
                // Ensure idempotency to allow clean updates without duplicating base rows
                ReplacementPolicy::updateOrCreate(
                    [
                        'site_id'      => $site->id,
                        'policy_class' => $policyData['policy_class'],
                    ],
                    [
                        'name'                      => $policyData['name'],
                        'description'               => $policyData['description'],
                        'is_default'                => $policyData['is_default'],
                        'active'                    => $policyData['active'],
                    ]
                );
            }
        }
    }

    /**
     * Compile exact blueprint parameters matched directly from core source engines.
     */
    private function getPolicyDefinitions(): array
    {
        return [
            // 1. No Replacement Policy (System Fallback & Promotional Plans)
            [
                'name'                      => 'No Replacements',
                'policy_class'              => \App\Services\Subscriptions\Policies\NoReplacementPolicy::class,
                'description'               => 'No replacements or extensions permitted under any circumstance. Default safe fallback.',
                'allows_replacements'       => false,
                'allows_extensions'         => false,
                'max_replacements'          => 0,
                'max_extensions'            => 0,
                'replacement_limit_scope'   => ReplacementLimitScope::PER_ISSUE->value,
                'extension_limit_scope'     => ReplacementLimitScope::PER_ISSUE->value,
                'require_stock'             => false,
                'requires_manager_approval' => false,
                'is_default'                => true, // System-wide failure safe default choice
                'active'                    => true,
            ],

            // 2. Standard Consumer Policy (Bronze / Standard-tier Plans)
            [
                'name'                      => 'Standard Consumer Policy',
                'policy_class'              => \App\Services\Subscriptions\Policies\StandardConsumerPolicy::class,
                'description'               => 'Bounded physical replacements and subscription extensions for standard members.',
                'allows_replacements'       => true,
                'allows_extensions'         => true,
                'max_replacements'          => 2, // MAX_REPLACEMENTS = 2
                'max_extensions'            => 2, // MAX_EXTENSIONS = 2
                'replacement_limit_scope'   => ReplacementLimitScope::PER_SUBSCRIPTION->value,
                'extension_limit_scope'     => ReplacementLimitScope::PER_SUBSCRIPTION->value,
                'require_stock'             => true,
                'requires_manager_approval' => false,
                'is_default'                => false,
                'active'                    => true,
            ],

            // 3. Premium Policy (Silver / Gold-tier Plans)
            [
                'name'                      => 'Premium Policy',
                'policy_class'              => \App\Services\Subscriptions\Policies\PremiumPolicy::class,
                'description'               => 'Unlimited replacements and extensions with zero caps and no manager approval.',
                'allows_replacements'       => true,
                'allows_extensions'         => true,
                'max_replacements'          => null, // Unlimited by default configuration
                'max_extensions'            => null,
                'replacement_limit_scope'   => ReplacementLimitScope::PER_YEAR->value,
                'extension_limit_scope'     => ReplacementLimitScope::PER_YEAR->value,
                'require_stock'             => true,
                'requires_manager_approval' => false,
                'is_default'                => false,
                'active'                    => true,
            ],

            // 4. Corporate Entitlement (Institutional Accounts, Schools, Libraries)
            [
                'name'                      => 'Corporate Entitlement Policy',
                'policy_class'              => \App\Services\Subscriptions\Policies\CorporatePolicy::class,
                'description'               => 'Corporate adjustments subject to contract agreements. Forces manager approval check flows.',
                'allows_replacements'       => true,
                'allows_extensions'         => true,
                'max_replacements'          => null,
                'max_extensions'            => null,
                'replacement_limit_scope'   => ReplacementLimitScope::LIFETIME->value,
                'extension_limit_scope'     => ReplacementLimitScope::LIFETIME->value,
                'require_stock'             => true,
                'requires_manager_approval' => true, // Every fulfillment event mandates an escalated audit check
                'is_default'                => false,
                'active'                    => true,
            ],

            // 5. Digital Only Policy (Purely electronic entitlements)
            [
                'name'                      => 'Digital Only Policy',
                'policy_class'              => \App\Services\Subscriptions\Policies\DigitalOnlyPolicy::class,
                'description'               => 'Denies physical inventory distribution. Permits extensions up to threshold limit flags.',
                'allows_replacements'       => false, // Physical item replacement is fundamentally unsupported
                'allows_extensions'         => true,
                'max_replacements'          => 0,
                'max_extensions'            => 3, // MAX_EXTENSIONS = 3
                'replacement_limit_scope'   => ReplacementLimitScope::PER_ISSUE->value,
                'extension_limit_scope'     => ReplacementLimitScope::PER_SUBSCRIPTION->value,
                'require_stock'             => false,
                'requires_manager_approval' => false,
                'is_default'                => false,
                'active'                    => true,
            ],
        ];
    }
}