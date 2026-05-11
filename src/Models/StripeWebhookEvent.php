<?php

namespace App\Models;

class StripeWebhookEvent extends Model
{
    protected $table = 'stripe_webhook_events';

    protected $fillable = [
        'stripe_event_id',
        'type',
        'payload_json',
        'processed_at',
        'failed_at',
        'error_message',
        'created_at',
        'updated_at',
    ];

    protected $casts = [
        'payload_json' => 'json',
        'processed_at' => 'datetime',
        'failed_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];
}

