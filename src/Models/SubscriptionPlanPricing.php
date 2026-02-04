<?php

namespace App\Models;

class SubscriptionPlanPricing extends Model
{
    protected $table = 'subscription_plan_pricing';

    protected $fillable = [
        'plan_id',
        'duration_months',
        'issue_count',
        'price',
        'original_price',
        'discount_percentage',
        'label',
        'period_description',
        'is_default',
        'sort_order',
        'is_active',
        'digital_price',
    ];

    protected $casts = [
        'price' => 'decimal',
        'original_price' => 'decimal',
        'is_default' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function plan()
    {
        return $this->belongsTo(SubscriptionPlan::class, 'plan_id');
    }

    public function getSavingsText(): ?string
    {
        if ($this->discount_percentage) {
            return "SAVE {$this->discount_percentage}%";
        }
        return null;
    }

    public function hasDiscount(): bool
    {
        return $this->original_price && $this->original_price > $this->price;
    }
}