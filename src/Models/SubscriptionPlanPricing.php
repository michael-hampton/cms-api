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
        'sale_price',
        'discount_percentage',
        'label',
        'period_description',
        'is_default',
        'sort_order',
        'is_active',
        'digital_price',
        'digital_sale_price',
        'currency',
        'stripe_price_id',
        'replaced_by_price_id',
        'site_id'
    ];

    protected $casts = [
        'price' => 'decimal',
        'sale_price' => 'decimal',
        'digital_price' => 'decimal',
        'digital_sale_price' => 'decimal',
        'discount_percentage' => 'integer',
        'duration_months' => 'integer',
        'issue_count' => 'integer',
        'sort_order' => 'integer',
        'is_default' => 'boolean',
        'is_active' => 'boolean',
        'replaced_by_price_id' => 'integer',
    ];

    public function plan()
    {
        return $this->belongsTo(SubscriptionPlan::class, 'plan_id');
    }

    /**
     * The newer PlanPricing row that replaced this one (if any).
     */
    public function replacedBy()
    {
        return $this->belongsTo(self::class, 'replaced_by_price_id');
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
        return $this->sale_price && $this->sale_price < $this->price;
    }

    public function getActualDiscount(): float
    {
        if (!$this->hasDiscount()) {
            return 0;
        }

        return $this->price - $this->sale_price;
    }

    public function getPricePerIssue(): float
    {
        if ($this->issue_count <= 0) {
            return 0;
        }

        return round($this->price / $this->issue_count, 2);
    }

    public function scopeForSite($query, int $siteId)
    {
        return $query->where('subscription_plan_pricing.site_id', $siteId);
    }
}