<?php

namespace App\Models;

use DateTimeImmutable;

class CartSnapshot extends Model
{
    protected $table = 'cart_snapshots';

    protected $fillable = [
        'email',
        'session_id',
        'checkout_token',
        'site_id',
        'cart_data',
        'expires_at',
        'created_at',
    ];

    protected $casts = [
        'site_id' => 'integer',
    ];

    /**
     * Check if snapshot has expired
     */
    public function isExpired(): bool
    {
        $expiresAt = new DateTimeImmutable($this->expires_at);
        $now = now_datetime();

        return $now > $expiresAt;
    }

    /**
     * Get cart item count
     */
    public function getItemCount(): int
    {
        return count($this->getCartData());
    }

    /**
     * Get cart data as array
     */
    public function getCartData(): array
    {
        return json_decode($this->cart_data, true) ?? [];
    }
}