<?php

namespace App\Models;

class CampaignEvent extends Model
{
    protected $table = 'campaign_events';

    protected $fillable = [
        'member_id',
        'campaign_id',
        'event_type',
        'metadata',
        'variant_id',
    ];

    protected $casts = [
        'metadata' => 'array',
        'created_at' => 'datetime',
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