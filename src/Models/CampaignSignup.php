<?php

namespace App\Models;

class CampaignSignup extends Model
{
    protected $table = 'campaign_signups';

    protected $fillable = [
        'campaign_id',
        'site_id',
        'user_id',
        'email',
        'ip_address',
        'user_agent',
        'referrer',
        'created_at',
    ];

    protected $casts = [
        'campaign_id' => 'int',
        'site_id' => 'int',
        'user_id' => 'int',
    ];
}
