<?php

namespace App\Models;

class SubscriptionPremiumAccess extends Model
{
    protected $table = 'subscription_premium_access';

    protected $fillable = [
        'subscription_id',
        'premium_type',
        'premium_identifier',
        'granted_at',
        'expires_at',
        'is_active'
    ];

    protected $casts = [
        'granted_at' => 'datetime',
        'expires_at' => 'datetime',
        'is_active' => 'boolean'
    ];

    public function subscription($relation = false)
    {
        return $this->belongsTo(Subscription::class, 'subscription_id', 'id', $relation);
    }

    public function isValid(): bool
    {
        return $this->is_active && !$this->isExpired();
    }

    public function isExpired(): bool
    {
        if (!$this->expires_at) {
            return false;
        }

        return $this->expires_at < new \DateTime();
    }

    /**
     * Scope to get active premium access
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true)
            ->where(function ($q) {
                $q->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            });
    }

    /**
     * Scope by premium type
     */
    public function scopeByType($query, string $type)
    {
        return $query->where('premium_type', $type);
    }

    /**
     * Scope by identifier
     */
    public function scopeByIdentifier($query, string $identifier)
    {
        return $query->where('premium_identifier', $identifier);
    }
}