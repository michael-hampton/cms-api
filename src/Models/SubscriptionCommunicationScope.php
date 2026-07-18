<?php

namespace App\Models;

class SubscriptionCommunicationScope extends Model
{
    protected $table = 'subscription_communication_scopes';

    protected $fillable = [
        'subscription_communication_id',
        'site_id',
        'subscription_plan_id',
        'is_enabled',
    ];

    protected $casts = [
        'is_enabled' => 'boolean',
    ];
}
