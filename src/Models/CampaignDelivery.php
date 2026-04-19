<?php

namespace App\Models;

class CampaignDelivery extends Model
{

    protected $table = 'campaign_deliveries';
    protected $fillable = [
        'member_id',
        'campaign_id',
        'channel',
        'audience_key',
        'variant_id',
        'token',
        'delivered_at',
    ];

    protected $casts = [
        'delivered_at' => 'datetime',
    ];

    public function member()
    {
        return $this->belongsTo(Member::class);
    }

    public function campaign()
    {
        return $this->belongsTo(Campaign::class);
    }

    public function variant()
    {
        return $this->belongsTo(CampaignVariant::class);
    }
}