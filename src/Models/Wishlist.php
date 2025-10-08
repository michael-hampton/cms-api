<?php

namespace App\Models;

class Wishlist extends Model
{
    protected $table = 'wishlists';

    protected $fillable = [
        'session_id',
        'user_id',
        'product_id',
        'site_id'
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
}