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

    public function merchant()
    {
        return $this->belongsTo(Merchant::class);
    }
}