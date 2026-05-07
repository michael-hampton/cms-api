<?php

namespace App\Models;

class SubscriptionEvent extends Model
{
    public $timestamps = false;
    protected $table = 'subscription_events';
    protected $fillable = [
        'subscription_id',
        'event_type',
        'occurred_at',
        'metadata',
    ];

    protected $casts = [
        'occurred_at' => 'datetime',
        'metadata' => 'array',
    ];

    public function subscription()
    {
        return $this->belongsTo(Subscription::class);
    }
}