<?php

namespace App\Models;

use App\Enums\Subscriptions\SubscriptionPricingChangeTransitionStatus;

class SubscriptionPricingChangeTransition extends Model
{
    protected $table = 'subscription_pricing_change_transitions';

    protected $fillable = [
        'subscription_pricing_change_id',
        'old_subscription_id',
        'new_subscription_id',
        'member_id',
        'site_id',
        'old_plan_id',
        'new_plan_id',
        'old_price',
        'new_price',
        'currency',
        'old_stripe_subscription_id',
        'new_stripe_subscription_id',
        'itd_required',
        'itd_letter_code',
        'communication_dedupe_key',
        'status',
        'failure_reason',
        'metadata',
    ];

    protected $casts = [
        'old_price' => 'float',
        'new_price' => 'float',
        'itd_required' => 'boolean',
        'metadata' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function pricingChange(bool $relation = false): mixed
    {
        return $this->belongsTo(
            SubscriptionPricingChange::class,
            'subscription_pricing_change_id',
            'id',
            $relation
        );
    }

    public function oldSubscription(bool $relation = false): mixed
    {
        return $this->belongsTo(
            Subscription::class,
            'old_subscription_id',
            'id',
            $relation
        );
    }

    public function newSubscription(bool $relation = false): mixed
    {
        return $this->belongsTo(
            Subscription::class,
            'new_subscription_id',
            'id',
            $relation
        );
    }

    public function isCompleted(): bool
    {
        return $this->status === SubscriptionPricingChangeTransitionStatus::Completed->value;
    }

    public function isFailed(): bool
    {
        return $this->status === SubscriptionPricingChangeTransitionStatus::Failed->value;
    }
}