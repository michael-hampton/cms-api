<?php

namespace App\Models;

class SubscriptionCommunicationEvent extends Model
{
    protected $fillable = [
        'subscription_communication_delivery_id',
        'event_type', 'url', 'user_agent', 'ip_address',
    ];

    public function delivery()
    {
        return $this->belongsTo(SubscriptionCommunicationDelivery::class);
    }
}