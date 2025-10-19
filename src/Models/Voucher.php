<?php

namespace App\Models;

class Voucher extends Model
{
    protected $table = 'vouchers';

    protected $fillable = [
        'site_id',
        'code',
        'name',
        'description',
        'type',
        'value',
        'minimum_order_value',
        'maximum_discount',
        'usage_limit',
        'usage_count',
        'per_user_limit',
        'starts_at',
        'expires_at',
        'status'
    ];

    protected $casts = [
        'site_id' => 'integer',
        'value' => 'float',
        'minimum_order_value' => 'float',
        'maximum_discount' => 'float',
        'usage_limit' => 'integer',
        'usage_count' => 'integer',
        'per_user_limit' => 'integer',
        'starts_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    public function isValid(): bool
    {
        if ($this->status !== 'active') {
            return false;
        }

        $now = new \DateTime();

        if ($this->starts_at && $this->starts_at > $now) {
            return false;
        }

        if ($this->expires_at && $this->expires_at < $now) {
            return false;
        }

        if ($this->usage_limit && $this->usage_count >= $this->usage_limit) {
            return false;
        }

        return true;
    }

    public function isExpired(): bool
    {
        if (!$this->expires_at) {
            return false;
        }

        return $this->expires_at < new \DateTime();
    }

    public function calculateDiscount(float $orderValue): float
    {
        if ($this->minimum_order_value && $orderValue < $this->minimum_order_value) {
            return 0;
        }

        $discount = 0;

        if ($this->type === 'percentage') {
            $discount = ($orderValue * $this->value) / 100;
        } else {
            $discount = $this->value;
        }

        if ($this->maximum_discount && $discount > $this->maximum_discount) {
            $discount = $this->maximum_discount;
        }

        return round($discount, 2);
    }
}