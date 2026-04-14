<?php

namespace App\Database\Seeders;

use App\Framework\Database\Seeder\Seeder;
use App\Framework\Support\Str;
use App\Models\Model;
use App\Models\Site;
use App\Models\SubscriptionPlan;
use App\Models\SubscriptionPlanPricing;

class SubscriptionPlansSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Get all active sites
        $sites = Site::where('is_active', true)->get();

        if ($sites->isEmpty()) {
            echo 'No active sites found. Please create sites first.';
            return;
        }

        echo "Creating subscription plans for {$sites->count()} sites...";

        $this->database->transaction(function () use ($sites) {
            foreach ($sites as $site) {
                $this->createPlansForSite($site);
            }
        });

        echo 'Subscription plans seeded successfully!';
    }

    /**
     * Create subscription plans for a specific site
     */
    private function createPlansForSite(Site $site): void
    {
        echo "Creating plans for: {$site->name}";

        // Plan templates - customize these as needed
        $planTemplates = [
            [
                'name' => 'Digital Only Subscription',
                'description' => 'Access all digital content instantly. Perfect for on-the-go reading.',
                'base_price' => 9.99,
                'digital_only' => true,
                'print_only' => false,
                'features' => [
                    'Instant digital access',
                    'Read on any device',
                    'Downloadable PDFs',
                    'Exclusive digital content',
                    'Ad-free reading experience',
                ],
            ],
            [
                'name' => 'Print Edition',
                'description' => 'Traditional print magazine delivered to your door.',
                'base_price' => 19.99,
                'digital_only' => false,
                'print_only' => true,
                'features' => [
                    'Print magazine delivered monthly',
                    'Premium paper quality',
                    'Collectible editions',
                    'Free UK shipping',
                    'Cancel anytime',
                ],
            ],
            [
                'name' => 'Premium Bundle',
                'description' => 'Get both print and digital access. Best value for money!',
                'base_price' => 24.99,
                'digital_only' => false,
                'print_only' => false,
                'is_featured' => true,
                'features' => [
                    'Print magazine delivered monthly',
                    'Instant digital access',
                    'Exclusive online content',
                    'Early access to new issues',
                    'Member-only newsletter',
                    'Best value - save 20%',
                ],
            ],
        ];

        foreach ($planTemplates as $index => $template) {
            $plan = $this->createPlan($site, $template, $index);

            if (!$plan) {
                continue;
            }

            $this->createPricingTiers($plan, $template, $site);
        }
    }

    /**
     * Create a subscription plan
     */
    private function createPlan(Site $site, array $template, int $index): ?Model
    {
        $slug = Str::slug($template['name']) . '-' . Str::slug($site->name);

        $exists = SubscriptionPlan::where('slug', $slug)->first();

        if ($exists) {
            return null;
        }

        return SubscriptionPlan::create([
            'site_id' => $site->id,
            'name' => $template['name'],
            'slug' => $slug,
            'description' => $template['description'],
            'price' => $template['base_price'], // Base price (will show "from £X")
            'currency' => 'GBP',
            'billing_period' => 'monthly',
            'trial_days' => 0,
            'features' => $template['features'],
            'is_active' => true,
            'is_featured' => $template['is_featured'] ?? false,
            'sort_order' => $index,
            'plan_type' => 'onetime',
            'digital_download_url' => $template['digital_only'] ? 'https://example.com/download' : null,
            'print_shipping_required' => $template['print_only'] || !$template['digital_only'],
            'includes_insider' => $template['is_featured'] ?? false,
            'is_upgrade_option' => false,
            'premium_access' => $template['is_featured']
                ? [['type' => 'newsletter', 'identifier' => 'insider']]
                : null,
            'pre_release_enabled' => false,
        ]);
    }

    /**
     * Create pricing tiers for a plan
     */
    private function createPricingTiers(SubscriptionPlan $plan, array $template, Site $site): void
    {
        $basePrice = $template['base_price'];
        $hasDigital = !$template['print_only'];
        $hasPrint = !$template['digital_only'];

        // Define pricing tiers (duration, issues, discount %)
        $tiers = [
            [
                'months' => 3,
                'issues' => 3,
                'discount' => 0,
                'label' => '3 Month Subscription',
                'period' => '3 issues',
            ],
            [
                'months' => 6,
                'issues' => 6,
                'discount' => 10,
                'label' => '6 Month Subscription',
                'period' => '6 issues',
            ],
            [
                'months' => 12,
                'issues' => 12,
                'discount' => 20,
                'label' => '12 Month Subscription',
                'period' => '12 issues',
                'is_default' => true,
            ],
        ];

        foreach ($tiers as $index => $tier) {
            // Calculate prices
            $fullPrintPrice = $basePrice * $tier['issues'];
            $discountedPrintPrice = $fullPrintPrice * (1 - ($tier['discount'] / 100));

            // Digital is typically 30% cheaper
            $fullDigitalPrice = $hasDigital ? ($fullPrintPrice * 0.70) : null;
            $discountedDigitalPrice = $fullDigitalPrice ? ($fullDigitalPrice * (1 - ($tier['discount'] / 100))) : null;

            SubscriptionPlanPricing::create([
                'plan_id' => $plan->id,
                'duration_months' => $tier['months'],
                'issue_count' => $tier['issues'],
                'price' => round($discountedPrintPrice, 2),
                'original_price' => $tier['discount'] > 0 ? round($fullPrintPrice, 2) : null,
                'digital_price' => $discountedDigitalPrice ? round($discountedDigitalPrice, 2) : null,
                'discount_percentage' => $tier['discount'],
                'label' => $tier['label'],
                'period_description' => $tier['period'],
                'is_default' => $tier['is_default'] ?? false,
                'sort_order' => $index,
                'is_active' => true,
                'site_id' => $site->id
            ]);
        }
    }
}