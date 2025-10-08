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
        'subtotal'
    ];

    protected $casts = [
        'quantity' => 'integer',
        'price' => 'float',
        'options' => 'json',
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

    public function getSubtotal(): float
    {
        return $this->price * $this->quantity;
    }
}