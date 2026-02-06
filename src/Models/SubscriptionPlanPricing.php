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
        'digital_price' => 'decimal',
        'discount_percentage' => 'integer',
        'duration_months' => 'integer',
        'issue_count' => 'integer',
        'sort_order' => 'integer',
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

    public function getActualDiscount(): float
    {
        if (!$this->hasDiscount()) {
            return 0;
        }

        return $this->original_price - $this->price;
    }

    public function getPricePerIssue(): float
    {
        if ($this->issue_count <= 0) {
            return 0;
        }

        return round($this->price / $this->issue_count, 2);
    }
}