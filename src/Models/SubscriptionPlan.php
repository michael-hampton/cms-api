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
        'stripe_price_id'
    ];

    protected $casts = [
        'price' => 'float',
        'trial_days' => 'integer',
        'features' => 'array',
        'is_active' => 'boolean',
        'is_featured' => 'boolean',
        'sort_order' => 'integer'
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
}