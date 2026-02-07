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
    ];

    protected $casts = [
        'newsletter_slugs' => 'array',
        'is_active' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
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
}