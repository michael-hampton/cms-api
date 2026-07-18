<?php

namespace App\Models;

class SubscriptionCommunicationSuppressionLog extends Model
{
    protected $table = 'subscription_communication_suppression_logs';

    protected $fillable = [
        'subscription_id',
        'member_id',
        'subscription_communication_id',
        'channel',
        'reason',
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
    ];
}
