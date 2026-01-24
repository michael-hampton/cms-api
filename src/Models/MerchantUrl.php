<?php

namespace App\Models;

class MerchantUrl extends Model
{
    protected $fillable = [
        'merchant_id',
        'url',
        'is_primary',
        'label',
        'created_at',
        'updated_at'
    ];

    protected $casts = [
        'is_primary' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    protected $table = 'merchant_urls';

    public function merchant()
    {
        return $this->belongsTo(Merchant::class);
    }
}