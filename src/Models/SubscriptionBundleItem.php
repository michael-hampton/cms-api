<?php

namespace App\Models;

/**
 * SubscriptionBundleItem
 *
 * Joins a SubscriptionBundle to a SubscriptionPlan.
 *
 * @property int $id
 * @property int $bundle_id
 * @property int $subscription_plan_id
 * @property int $quantity              Almost always 1; reserved for future multi-copy bundles
 * @property string $delivery_type        Default delivery type for this plan inside the bundle
 */
class SubscriptionBundleItem extends Model
{
    protected $table = 'subscription_bundle_items';

    protected $fillable = [
        'bundle_id',
        'subscription_plan_id',
        'quantity',
        'delivery_type',
    ];

    public function bundle()
    {
        return $this->belongsTo(SubscriptionBundle::class, 'bundle_id');
    }

    /**
     * Resolve the effective plan, eager-loading pricingTiers if not already loaded.
     */
    public function getEffectivePlan(): SubscriptionPlan
    {
        return $this->subscriptionPlan()->with(['pricingTiers'])->first();
    }

    public function subscriptionPlan()
    {
        return $this->belongsTo(SubscriptionPlan::class, 'subscription_plan_id');
    }
}