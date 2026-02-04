<?php

namespace App\Observers;

use App\Framework\Support\Logger;
use App\Models\SubscriptionPlan;

class SubscriptionPlanObserver
{
    /**
     * Handle the SubscriptionPlan "created" event.
     */
    public function created(SubscriptionPlan $plan): void
    {
        // Only create default pricing if this is a one-time plan and has no pricing tiers
        if ($plan->plan_type === 'onetime' && $plan->pricingTiers()->count() === 0) {
            $this->createDefaultPricingTiers($plan);

            Logger::info('Created default pricing tiers for new subscription plan', [
                'plan_id' => $plan->id,
                'plan_name' => $plan->name
            ]);
        }
    }

    /**
     * Create default pricing tiers for a newly created plan
     */
    private function createDefaultPricingTiers(SubscriptionPlan $plan): void
    {
        $basePrice = $plan->price;

        $defaultTiers = [
            [
                'duration_months' => 6,
                'issue_count' => 6,
                'price' => $basePrice,
                'original_price' => null,
                'discount_percentage' => null,
                'label' => '6 month subscription',
                'period_description' => 'for 6 issues',
                'is_default' => false,
                'sort_order' => 1,
                'is_active' => true,
            ],
            [
                'duration_months' => 12,
                'issue_count' => 12,
                'price' => round($basePrice * 2 * 0.63, 2), // 37% off
                'original_price' => round($basePrice * 2, 2),
                'discount_percentage' => 37,
                'label' => '1 year subscription',
                'period_description' => 'for one year / 12 issues',
                'is_default' => true,
                'sort_order' => 2,
                'is_active' => true,
            ],
            [
                'duration_months' => 24,
                'issue_count' => 24,
                'price' => round($basePrice * 4 * 0.56, 2), // 44% off
                'original_price' => round($basePrice * 4, 2),
                'discount_percentage' => 44,
                'label' => '2 year subscription',
                'period_description' => 'for two years / 24 issues',
                'is_default' => false,
                'sort_order' => 3,
                'is_active' => true,
            ],
        ];

        foreach ($defaultTiers as $tierData) {
            $plan->pricingTiers()->create($tierData);
        }
    }
}