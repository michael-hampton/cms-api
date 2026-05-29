<?php

namespace App\Models;

use App\Enums\Member\SubscriptionSegmentStatus;

class SubscriptionSegment extends Model
{
    protected $table = 'subscription_segments';

    protected $fillable = [
        'subscription_id',
        'segment_id',
        'assigned_at',
        'evaluated_at',
        'status',
        'metadata',
    ];

    protected $casts = [
        'assigned_at'  => 'datetime',
        'evaluated_at' => 'datetime',
        'status'       => SubscriptionSegmentStatus::class,
        'metadata'     => 'array',
    ];

    public function subscription()
    {
        return $this->belongsTo(Subscription::class);
    }

    public function segment()
    {
        return $this->belongsTo(Segment::class);
    }
}