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
        'site_id',
        'trial_days',
        'intro_price',
        'intro_cycles',
        'stripe_intro_price_id'
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
        'trial_days'   => 'integer',
        'intro_cycles' => 'integer',
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

    private function resolveEffectivePrice(float $price, mixed $sale): float
    {
        $sale = is_numeric($sale) ? (float)$sale : null;

        return ($sale !== null && $sale > 0 && $sale < $price)
            ? $sale
            : $price;
    }

    public function getEffectivePrintPrice(): float
    {
        return $this->resolveEffectivePrice(
            (float)($this->price ?? 0),
            $this->sale_price
        );
    }

    public function getEffectiveDigitalPrice(): float
    {
        $price = is_numeric($this->digital_price)
            ? (float)$this->digital_price
            : (float)($this->price ?? 0);

        $sale = is_numeric($this->digital_sale_price)
            ? $this->digital_sale_price
            : $this->sale_price;

        return $this->resolveEffectivePrice($price, $sale);
    }

    public function getEffectivePrice(string $type = 'digital'): float
    {
        return $type === 'print'
            ? $this->getEffectivePrintPrice()
            : $this->getEffectiveDigitalPrice();
    }

    public function getStripeBillingPriceForPlan(SubscriptionPlan $plan): float
    {
        if ($plan->hasDigitalOption()) {
            return $this->resolveRequiredStripePrice(
                $this->digital_sale_price,
                $this->digital_price,
                'digital_price'
            );
        }

        if ($plan->hasPrintOption()) {
            return $this->resolveRequiredStripePrice(
                $this->sale_price,
                $this->price,
                'price'
            );
        }

        return $this->resolveRequiredStripePrice(
            $this->sale_price,
            $this->price,
            'price'
        );
    }

    private function resolveRequiredStripePrice(mixed $salePrice, mixed $price, string $priceField): float
    {
        if (is_numeric($salePrice) && (float)$salePrice > 0) {
            return (float)$salePrice;
        }

        if (is_numeric($price) && (float)$price > 0) {
            return (float)$price;
        }

        throw new \InvalidArgumentException("{$priceField} is required to create a Stripe price");
    }

    public function hasTrial(): bool
    {
        return $this->trial_days !== null && $this->trial_days > 0;
    }

    public function hasIntroPricing(): bool
    {
        return $this->intro_price !== null
            && $this->intro_cycles !== null
            && $this->intro_cycles > 0;
    }
}
