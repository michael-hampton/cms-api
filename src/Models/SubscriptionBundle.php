<?php

namespace App\Models;

class SubscriptionBundle extends Model
{
    protected $table = 'subscription_bundles';

    protected $fillable = [
        'site_id',
        'name',
        'slug',
        'description',
        'newsletter_slugs',
        'is_active',
        'bundle_price',
        'total_price',
        'start_date',
        'end_date',
    ];

    protected $casts = [
        'newsletter_slugs' => 'array',
        'is_active' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'bundle_price' => 'float',
        'total_price' => 'float',
        'start_date' => 'datetime',
        'end_date' => 'datetime',
    ];

    /**
     * Check if bundle includes a specific newsletter
     */
    public function includesNewsletter(string $newsletterSlug): bool
    {
        if (!$this->is_active) {
            return false;
        }

        return in_array($newsletterSlug, $this->newsletter_slugs ?? []);
    }

    /**
     * Get all newsletters in this bundle
     */
    public function newsletters()
    {
        return Newsletter::whereIn('slug', $this->newsletter_slugs ?? [])
            ->where('site_id', $this->site_id)
            ->get();
    }

    /**
     * Subscriptions using this bundle
     */
    public function subscriptions($relation = false)
    {
        return $this->hasMany(Subscription::class, 'bundle_id', 'id', $relation);
    }

    /**
     * Constituent plans with their per-bundle quantities.
     */
    public function items()
    {
        return $this->hasMany(SubscriptionBundleItem::class, 'bundle_id');
    }

    /**
     * A bundle is active when the is_active flag is set and we are within
     * any configured date window.
     */
    public function isCurrentlyActive(): bool
    {
        if (!$this->is_active) {
            return false;
        }

        $now = now_datetime();

        if ($this->start_date && $now < $this->start_date) {
            return false;
        }

        if ($this->end_date && $now > $this->end_date) {
            return false;
        }

        return true;
    }

    /**
     * Discount amount in absolute terms.
     */
    public function getSavingsAmount(): float
    {
        return max(0.0, $this->total_price - $this->bundle_price);
    }

    /**
     * Discount percentage rounded to the nearest integer.
     */
    public function getDiscountPercentage(): int
    {
        if ($this->total_price <= 0) {
            return 0;
        }

        return (int)round(($this->getSavingsAmount() / $this->total_price) * 100);
    }
}