<?php

namespace App\Models;

class DealClick extends Model
{
    protected $fillable = [
        'product_id',
        'member_id',
        'site_id',
        'action',
        'channel',
        'surface_type',
        'surface_id',
        'deal_id',
        'ip_address',
        'user_agent',
        'metadata',
        'created_at',
    ];

    protected $casts = [
        'metadata' => 'array',
        'created_at' => 'datetime',
    ];

    protected $table = 'deal_clicks';

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function member()
    {
        return $this->belongsTo(Member::class);
    }
}