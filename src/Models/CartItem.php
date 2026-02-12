<?php

namespace App\Models;

class CartItem extends Model
{
    protected $table = 'cart_items';

    protected $fillable = [
        'session_id',
        'user_id',
        'product_id',
        'quantity',
        'price',
        'options',
        'site_id',
        'subtotal',
        'subscription_plan_id',
        'merchant_id'
    ];

    protected $casts = [
        'quantity' => 'integer',
        'price' => 'float',
        'options' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'subscription_plan_id' => 'integer',
    ];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function getSubtotal(): float
    {
        return $this->price * $this->quantity;
    }

    public function subscriptionPlan()
    {
        return $this->belongsTo(SubscriptionPlan::class, 'subscription_plan_id');
    }

    public function isSubscription(): bool
    {
        return $this->subscription_plan_id !== null;
    }

    public function getItemType(): string
    {
        $options = $this->options ?? [];
        return $options['type'] ?? 'product';
    }

    public function isOffer(): bool
    {
        return $this->getItemType() === 'offer';
    }

    public function isBundle(): bool
    {
        return $this->getItemType() === 'bundle';
    }

    public function getBundleId(): ?int
    {
        $options = $this->options ?? [];
        return $options['bundle_id'] ?? null;
    }

    public function getOfferId(): ?int
    {
        $options = $this->options ?? [];
        return $options['offer_id'] ?? null;
    }

    public function getMerchantId(): ?int
    {
        $options = $this->options ?? [];
        return $this->merchant_id ?? $options['merchant_id'] ?? null;
    }
}