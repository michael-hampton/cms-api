<?php

namespace App\Models;

class MerchantContact extends Model
{
    protected $table = 'merchant_contacts';

    protected $fillable = [
        'merchant_id',
        'name',
        'email',
        'phone',
        'role',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'is_active' => 'boolean',
    ];

    public function merchant()
    {
        return $this->belongsTo(Merchant::class);
    }
}