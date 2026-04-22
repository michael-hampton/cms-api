<?php

namespace App\Models;

use App\Enums\Boost\BoostStatus;

class Boost extends Model
{
    protected $table = 'boosts';

    protected $fillable = [
        'boostable_type',
        'boostable_id',
        'merchant_id',
        'context',
        'starts_at',
        'ends_at',
        'multiplier',
        'status',
        'price_paid',
        'currency',
        'payment_reference',
        'created_at',
        'updated_at'
    ];

    protected $casts = [
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'created_at' => 'datetime',
        'multiplier' => 'float',
        'price_paid' => 'float',
    ];

    public function isPending(): bool
    {
        return $this->status === BoostStatus::Pending->value;
    }

    public function isActive(): bool
    {
        return $this->status === BoostStatus::Active->value;
    }

    public function isExpired(): bool
    {
        return $this->status === BoostStatus::Expired->value;
    }

    public function isCancelled(): bool
    {
        return $this->status === BoostStatus::Cancelled->value;
    }

    public function isPaused(): bool
    {
        return $this->status === BoostStatus::Paused->value;
    }

    public function limit()
    {
        return $this->hasOne(BoostLimit::class);
    }

    public function stat()
    {
        return $this->hasOne(BoostStat::class);
    }

    public function events()
    {
        return $this->hasMany(BoostEvent::class);
    }

    public function product()
    {
        if ($this->boostable_type !== 'product') {
            return null;
        }

        return Product::where('id', $this->boostable_id)->first();
    }
}