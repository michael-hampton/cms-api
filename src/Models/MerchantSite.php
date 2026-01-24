<?php

namespace App\Models;

class MerchantSite extends Model
{
    protected $table = 'merchant_sites';

    protected $fillable = [
        'merchant_id',
        'site_id',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function merchant()
    {
        return $this->belongsTo(Merchant::class);
    }

    public function site()
    {
        return $this->belongsTo(Site::class);
    }
}