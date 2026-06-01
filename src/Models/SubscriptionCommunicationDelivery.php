<?php

namespace App\Models;

use App\Enums\Subscriptions\CommunicationDeliveryStatus;

class SubscriptionCommunicationDelivery extends Model
{
    protected $fillable = [
        'subscription_communication_id',
        'subscription_communication_schedule_id',
        'subscription_id', 'member_id',
        'segment_id', 'subscription_segment_id',
        'channel', 'status', 'token',
        'recipient_email', 'subject',
        'sent_at', 'failed_at', 'opened_at', 'clicked_at',
        'failure_reason', 'metadata',
    ];

    protected $casts = [
        'sent_at'    => 'datetime',
        'failed_at'  => 'datetime',
        'opened_at'  => 'datetime',
        'clicked_at' => 'datetime',
        'metadata'   => 'array',
        'status'     => CommunicationDeliveryStatus::class,
    ];

    protected $table = 'subscription_communication_deliveries';

    public function communication()
    {
        return $this->belongsTo(SubscriptionCommunication::class, 'subscription_communication_id');
    }

    public function schedule()
    {
        return $this->belongsTo(SubscriptionCommunicationSchedule::class, 'subscription_communication_schedule_id');
    }

    public function events()
    {
        return $this->hasMany(SubscriptionCommunicationEvent::class);
    }
}