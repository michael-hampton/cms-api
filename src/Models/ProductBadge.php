<?php

namespace App\Models;

class ProductBadge extends Model
{
    protected $table = 'product_badges';

    protected $fillable = [
        'product_id', 'badge_type', 'label', 'color', 'icon',
        'valid_from', 'valid_until', 'sort_order', 'is_active'
    ];

    protected $casts = [
        'valid_from' => 'date',
        'valid_until' => 'date',
        'is_active' => 'boolean'
    ];

    public function product($relation = false)
    {
        return $this->belongsTo(Product::class, 'product_id', 'id', $relation);
    }

    public function isValid(): bool
    {
        if (!$this->is_active) {
            return false;
        }

        $now = now_datetime();

        if ($this->valid_from && $now->lt($this->valid_from)) {
            return false;
        }

        if ($this->valid_until && $now->gt($this->valid_until)) {
            return false;
        }

        return true;
    }
}