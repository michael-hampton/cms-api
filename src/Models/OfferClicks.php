<?php

namespace App\Models;

class OfferClicks extends Model
{
    protected $table = 'offer_clicks';

    protected $fillable = [
        'offer_id',
        'member_id',
        'ip_address',
        'user_agent',
        'action',
        'channel',
        'surface_type',
        'surface_id',
        'deal_id',
        'clicked_at',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'clicked_at' => 'datetime',
    ];

    public function offer()
    {
        return $this->belongsTo(ProductOffer::class, 'offer_id');
    }

    public function member()
    {
        return $this->belongsTo(Member::class);
    }
}