<?php

namespace App\Models;

class Wishlist extends Model
{
    protected $table = 'wishlists';

    protected $fillable = [
        'session_id',
        'user_id',
        'product_id',
        'site_id',
        'item_type',
        'item_id',
        'wishlistable_type'
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function getItemType(): string
    {
        return $this->item_type ?? 'product';
    }

    public function isOffer(): bool
    {
        return $this->item_type === 'offer';
    }

    public function isBundle(): bool
    {
        return $this->item_type === 'bundle';
    }

    public function offer()
    {
        return $this->belongsTo(ProductOffer::class, 'item_id');
    }

    public function bundle()
    {
        return $this->belongsTo(ProductOfferBundle::class, 'item_id');
    }
}