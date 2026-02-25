<?php

namespace App\Models;

class GiftPromotion extends Model
{
    protected $table = 'gift_promotions';

    protected $fillable = [
        'merchant_id',
        'gift_type',
        'gift_product_id',
        'gift_subscription_plan_id',
        'quantity_rule',
        'max_per_order',
        'exclusive',
        'priority',
        'starts_at',
        'ends_at',
        'active'
    ];

    protected $casts = [
        'exclusive' => 'boolean',
        'active' => 'boolean',
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'priority' => 'integer',
        'max_per_order' => 'integer',
    ];

    /*
    | ------------------------------------------------------------------
    | Relationships
    | ------------------------------------------------------------------
    */

    public function triggers()
    {
        return $this->hasMany(GiftPromotionTrigger::class, 'promotion_id');
    }

    /*
    | ------------------------------------------------------------------
    | Scopes
    | ------------------------------------------------------------------
    */

    public function scopeActive($query)
    {
        return $query
            ->where('active', true)
            ->where(function ($q) {
                $q->whereNull('starts_at')
                    ->orWhere('starts_at', '<=', now());
            })
            ->where(function ($q) {
                $q->whereNull('ends_at')
                    ->orWhere('ends_at', '>=', now());
            });
    }

    public function scopeForMerchant($query, array $merchantId)
    {
        return $query->where(function ($q) use ($merchantId) {
            $q->whereNull('merchant_id')
                ->orWhereIn('merchant_id', $merchantId);
        });
    }
}