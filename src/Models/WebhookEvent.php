<?php

namespace App\Models;

class WebhookEvent extends Model
{
    protected $table = 'webhook_events';

    protected $fillable = [
        'stripe_event_id',
        'type',
        'status',
        'payload',
        'processed_at',
        'error_message',
    ];

    protected $casts = [
        'payload' => 'array',
        'processed_at' => 'datetime',
    ];

    public function markFailed(string $errorMessage): void
    {
        $this->status = 'failed';
        $this->error_message = $errorMessage;
        $this->save();
    }
}