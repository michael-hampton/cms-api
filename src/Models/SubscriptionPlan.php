<?php
// App/Models/SubscriptionPlan.php

namespace App\Models;

use App\Framework\Database\QueryBuilder;

class SubscriptionPlan extends Model
{
    protected $table = 'subscription_plans';

    protected $fillable = [
        'site_id',
        'name',
        'slug',
        'description',
        'price',
        'currency',
        'billing_period',
        'trial_days',
        'features',
        'is_active',
        'is_featured',
        'sort_order',
        'stripe_price_id',
        'plan_type',
        'digital_download_url',
        'print_shipping_required',
        'includes_insider',
        'is_upgrade_option',
        'upgrade_from_plan_id',
    ];

    protected $casts = [
        'price' => 'float',
        'trial_days' => 'integer',
        'features' => 'array',
        'is_active' => 'boolean',
        'is_featured' => 'boolean',
        'sort_order' => 'integer',
        'print_shipping_required' => 'boolean',
        'includes_insider' => 'boolean',
        'is_upgrade_option' => 'boolean',
    ];

    public function site($relation = false)
    {
        return $this->belongsTo(Site::class, 'site_id', 'id', $relation);
    }

    public function subscriptions($relation = false)
    {
        return $this->hasMany(Subscription::class, 'plan_id', 'id', $relation);
    }

    public function activeSubscriptions($relation = false)
    {
        return $this->hasMany(Subscription::class, 'plan_id', 'id', $relation)
            ->where('status', 'active');
    }

    public function isActive(): bool
    {
        return $this->is_active;
    }

    public function isFeatured(): bool
    {
        return $this->is_featured;
    }

    public function hasTrial(): bool
    {
        return $this->trial_days > 0;
    }

    public function getFormattedPrice(): string
    {
        return $this->currency . ' ' . number_format($this->price, 2);
    }

    public function getBillingPeriodLabel(): string
    {
        return match ($this->billing_period) {
            'monthly' => 'per month',
            'quarterly' => 'per quarter',
            'yearly' => 'per year',
            'lifetime' => 'one-time',
            default => ''
        };
    }

    public function scopeActive(QueryBuilder $query): QueryBuilder
    {
        return $query->where('is_active', true);
    }

    public function scopeFeatured(QueryBuilder $query): QueryBuilder
    {
        return $query->where('is_featured', true);
    }

    public function scopeBySite(QueryBuilder $query, int $siteId): QueryBuilder
    {
        return $query->where('site_id', $siteId);
    }

    public function scopeOrdered(QueryBuilder $query): QueryBuilder
    {
        return $query->orderBy('sort_order', 'asc')->orderBy('price', 'asc');
    }

    public function isRecurring(): bool
    {
        return $this->plan_type === 'recurring';
    }

    public function isOneTime(): bool
    {
        return $this->plan_type === 'onetime';
    }

    public function hasDigitalOption(): bool
    {
        return $this->digital_download_url && strlen($this->digital_download_url) > 0;
    }

    public function hasPrintOption(): bool
    {
        return $this->print_shipping_required;
    }

    public function getDeliveryOptions(): array
    {
        $options = [];

        if ($this->hasDigitalOption()) {
            $options[] = 'digital';
        }

        if ($this->hasPrintOption()) {
            $options[] = 'print';
        }

        return $options;
    }

    public function scopeOneTime(QueryBuilder $query): QueryBuilder
    {
        return $query->where('plan_type', 'onetime');
    }

    public function scopeRecurring(QueryBuilder $query): QueryBuilder
    {
        return $query->where('plan_type', 'recurring');
    }

    /**
     * Check if plan includes Insider access
     */
    public function includesInsider(): bool
    {
        return $this->includes_insider;
    }

    /**
     * Check if this is an upgrade plan
     */
    public function isUpgradePlan(): bool
    {
        return $this->is_upgrade_option;
    }

    /**
     * Get the plan this upgrades from
     */
    public function upgradesFromPlan()
    {
        if (!$this->upgrade_from_plan_id) {
            return null;
        }

        return $this->belongsTo(SubscriptionPlan::class, 'upgrade_from_plan_id', 'id');
    }
}