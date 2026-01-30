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
        'action'
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
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