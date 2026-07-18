<?php

namespace App\Models;

use App\Enums\Member\CampaignPurpose;
use App\Enums\Subscriptions\CommunicationChannelStrategy;
use App\Enums\Subscriptions\CommunicationTypeEnum;

class SubscriptionCommunication extends Model
{
    protected $fillable = [
        'key', 'name', 'description', 'type',
        'segment_id', 'template', 'channels', 'channel_strategy', 'purpose',
        'is_active', 'sort_order',
    ];

    protected $casts = [
        'channels'         => 'array',
        'channel_strategy' => CommunicationChannelStrategy::class,
        'purpose'          => CampaignPurpose::class,
        'is_active'        => 'boolean',
        'type'             => CommunicationTypeEnum::class,
    ];

    protected $table = 'subscription_communications';

    public function segment()
    {
        return $this->belongsTo(Segment::class);
    }

    public function schedules()
    {
        return $this->hasMany(SubscriptionCommunicationSchedule::class, 'subscription_communication_id');
    }

    public function deliveries()
    {
        return $this->hasMany(SubscriptionCommunicationDelivery::class);
    }
}