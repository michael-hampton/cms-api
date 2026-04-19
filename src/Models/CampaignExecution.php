<?php

namespace App\Models;

class CampaignExecution extends Model
{
    public $timestamps = false;
    protected $table = 'campaign_executions';
    protected $fillable = [
        'member_id',
        'campaign_id',
        'segment_key',
        'sent_at',
        'is_marketing'
    ];
    protected $casts = [
        'sent_at' => 'datetime',
        'is_marketing' => 'boolean',
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