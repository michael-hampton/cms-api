<?php

namespace App\Models;

use App\Enums\Subscriptions\CommunicationRelativeTo;

class SubscriptionCommunicationSchedule extends Model
{
    protected $fillable = [
        'subscription_communication_id', 'name',
        'trigger_type', 'offset_days', 'fixed_date',
        'relative_to', 'send_time', 'is_active', 'sort_order',
    ];

    protected $casts = [
        'fixed_date'  => 'date',
        'is_active'   => 'boolean',
        'relative_to' => CommunicationRelativeTo::class,
    ];

    protected $table = 'subscription_communication_schedules';

    public function communication()
    {
        return $this->belongsTo(SubscriptionCommunication::class);
    }
}