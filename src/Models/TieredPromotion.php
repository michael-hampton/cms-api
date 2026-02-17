<?php

namespace App\Models;

class TieredPromotion extends Model
{
    protected $table = 'tiered_promotions';

    protected $fillable = [
        'name',
        'min_subtotal_cents',
        'discount_type',
        'value',
        'stackable',
        'applies_to',
        'is_active',
        'start_date',
        'end_date',
    ];

    protected $casts = [
        'min_subtotal_cents' => 'integer',
        'value' => 'decimal:2',
        'stackable' => 'boolean',
        'is_active' => 'boolean',
        'start_date' => 'datetime',
        'end_date' => 'datetime',
    ];

    public function isActive(): bool
    {
        if (!$this->is_active) {
            return false;
        }

        $now = now();

        if ($this->start_date && $now->lt($this->start_date)) {
            return false;
        }

        if ($this->end_date && $now->gt($this->end_date)) {
            return false;
        }

        return true;
    }

    public function appliesTo(bool $isSubscription): bool
    {
        if ($this->applies_to === 'both') {
            return true;
        }

        if ($this->applies_to === 'subscription' && $isSubscription) {
            return true;
        }

        if ($this->applies_to === 'one_time' && !$isSubscription) {
            return true;
        }

        return false;
    }
}