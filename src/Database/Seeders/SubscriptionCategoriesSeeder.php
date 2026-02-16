<?php

namespace App\Database\Seeders;

use App\Framework\Database\Seeder\Seeder;
use App\Models\SubscriptionPlan;

/**
 * Seeder to populate categories for subscription plans
 */
class SubscriptionCategoriesSeeder extends Seeder
{
    /**
     * Available categories with their metadata
     */
    private array $categories = [
        'Monthly' => [
            'description' => 'Monthly subscription plans',
            'icon' => '📅',
            'color' => '#3b82f6',
        ],
        'Digital Only' => [
            'description' => 'Instant digital access',
            'icon' => '📱',
            'color' => '#8b5cf6',
        ],
        'Print Edition' => [
            'description' => 'Physical magazines delivered',
            'icon' => '📰',
            'color' => '#ef4444',
        ],
        'Best Value' => [
            'description' => 'Maximum savings on longer subscriptions',
            'icon' => '💰',
            'color' => '#10b981',
        ],
        'Premium' => [
            'description' => 'Exclusive content and benefits',
            'icon' => '⭐',
            'color' => '#f59e0b',
        ],
        'Annual' => [
            'description' => 'Year-long subscriptions',
            'icon' => '📆',
            'color' => '#06b6d4',
        ],
        'Technology' => [
            'description' => 'Technology',
            'icon' => '📆',
            'color' => '#06b6d4',
            'site_ids' => [57, 2]
        ],
        'Fashion' => [
            'description' => 'Technology',
            'icon' => '📆',
            'color' => '#06b6d4',
            'site_ids' => [6]
        ],
        'Music' => [
            'description' => 'Technology',
            'icon' => '📆',
            'color' => '#06b6d4',
            'site_ids' => [52, 37, 7]
        ],
        'Home & Garden' => [
            'description' => 'Technology',
            'icon' => '📆',
            'color' => '#06b6d4',
            'site_ids' => [8]
        ],
        'Food & Wine' => [
            'description' => 'Technology',
            'icon' => '📆',
            'color' => '#06b6d4',
            'site_ids' => [10, 9]
        ],
        'Travel' => [
            'description' => 'Technology',
            'icon' => '📆',
            'color' => '#06b6d4',
            'site_ids' => [19]
        ],
        'Equestrian' => [
            'description' => 'Technology',
            'icon' => '📆',
            'color' => '#06b6d4',
            'site_ids' => [29]
        ],
        'Games' => [
            'description' => 'Technology',
            'icon' => '📆',
            'color' => '#06b6d4',
            'site_ids' => [38]
        ],
        'Sport' => [
            'description' => 'Technology',
            'icon' => '📆',
            'color' => '#06b6d4',
            'site_ids' => [46]
        ],
        'Space' => [
            'description' => 'Space',
            'icon' => '📆',
            'color' => '#06b6d4',
            'site_ids' => [50]
        ],
        'Current Affairs' => [
            'description' => 'Technology',
            'icon' => '📆',
            'color' => '#06b6d4',
            'site_ids' => [49, 51]
        ],
    ];

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        echo 'Assigning categories to subscription plans...';

        $this->database->transaction(function () {
            $this->assignCategoriesToExistingPlans();
            //$this->displayCategorySummary();
        });

        echo '✓ Categories assigned successfully!';
    }

    /**
     * Assign categories to existing plans based on their characteristics
     */
    private function assignCategoriesToExistingPlans(): void
    {
        // Get all subscription plans
        $plans = SubscriptionPlan::where('is_active', true)
            ->get();

        if ($plans->isEmpty()) {
            echo 'No subscription plans found. Run SubscriptionPlansSeeder first.';
            return;
        }

        foreach ($plans as $plan) {
            $categories = $this->determineCategoryForPlan($plan, $plan->site_id);
            $tags = $this->determineTagsForPlan($plan);

            SubscriptionPlan::where('id', $plan->id)
                ->update([
                    'categories' => json_encode($categories),
                    'tags' => json_encode($tags),
                    'updated_at' => now(),
                ]);

        }
    }

    /**
     * Determine the primary category for a plan
     */
    private function determineCategoryForPlan(object $plan, int $siteId): array
    {
        $categories = [];

        // Featured plans get "Premium" or "Best Value"
        if ($plan->is_featured) {
            $categories[] = 'Best Value';
        }

        // Digital only plans
        if ($plan->digital_download_url && !$plan->print_shipping_required) {
            $categories[] = 'Digital Only';
        }

        // Print only plans
        if ($plan->print_shipping_required && !$plan->digital_download_url) {
            $categories[] = 'Print Edition';
        }

        // Both digital and print
        if ($plan->digital_download_url && $plan->print_shipping_required) {
            $categories[] = 'Premium';
        }

        // Default to Monthly
        $categories[] = 'Monthly';

        foreach ($this->categories as $key => $category) {
            if (!empty($category['site_ids']) && in_array($siteId, $category['site_ids'])) {
                $categories[] = $key;
            }
        }

        return $categories;
    }

    /**
     * Determine tags for a plan
     */
    private function determineTagsForPlan(object $plan): array
    {
        $tags = [];

        // Add feature-based tags
        if ($plan->is_featured) {
            $tags[] = 'featured';
            $tags[] = 'popular';
        }

        // Add delivery type tags
        if ($plan->digital_download_url) {
            $tags[] = 'digital';
        }

        if ($plan->print_shipping_required) {
            $tags[] = 'print';
        }

        // Add price-based tags
        if ($plan->price < 15) {
            $tags[] = 'budget-friendly';
        } elseif ($plan->price > 20) {
            $tags[] = 'premium';
        }

        // Add access tags
        if ($plan->includes_insider) {
            $tags[] = 'insider-access';
        }

        return $tags;
    }

    /**
     * Get available categories (for reference)
     */
    public function getCategories(): array
    {
        return $this->categories;
    }

    /**
     * Display summary of categories
     */
    private function displayCategorySummary(): void
    {
        echo "\nCategory Summary:";
        echo str_repeat('=', 60);

        foreach ($this->categories as $category => $meta) {
            $count = SubscriptionPlan::where('category', $category)
                ->where('is_active', true)
                ->count();

            if ($count > 0) {
                echo
                sprintf(
                    "%s %s: %d plan(s)",
                    $meta['icon'],
                    $category,
                    $count
                );
            }
        }
    }
}