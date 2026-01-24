<?php

namespace App\Models;

class MerchantProductFeed extends Model
{
    protected $fillable = [
        'merchant_id',
        'feed_url',
        'feed_type',
        'last_fetched_at',
        'next_fetch_at',
        'is_active',
        'fetch_frequency',
        'status',
        'last_error',
        'created_at',
        'updated_at'
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'last_fetched_at' => 'datetime',
        'next_fetch_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    protected $table = 'merchant_product_feeds';

    public function merchant()
    {
        return $this->belongsTo(Merchant::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeDueForFetch($query)
    {
        return $query->where('is_active', true)
            ->where(function ($q) {
                $q->whereNull('next_fetch_at')
                    ->orWhere('next_fetch_at', '<=', now());
            });
    }
}