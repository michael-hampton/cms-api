<?php

namespace App\Models;

class CampaignExecution extends Model
{
    public $timestamps = false;
    protected $fillable = [
        'member_id',
        'campaign_id',
        'segment_key',
        'sent_at',
    ];
    protected $casts = [
        'sent_at' => 'datetime',
    ];

    public function member()
    {
        return $this->belongsTo(Member::class);
    }

    public function campaign()
    {
        return $this->belongsTo(Campaign::class);
    }
}