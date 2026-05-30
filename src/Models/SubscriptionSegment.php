<?php

namespace App\Models;

use App\Enums\Member\SubscriptionSegmentSource;
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
        'source',
        'reason',
        'expires_at',
        'assigned_by_user_id',
        'metadata',
    ];

    protected $casts = [
        'assigned_at'  => 'datetime',
        'evaluated_at' => 'datetime',
        'expires_at'   => 'datetime',
        'status'       => SubscriptionSegmentStatus::class,
        'source'       => SubscriptionSegmentSource::class,
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

    public function assignedByUser()
    {
        return $this->belongsTo(User::class, 'assigned_by_user_id');
    }

    /**
     * Returns true if this is a manual override that has not yet expired.
     */
    public function isActiveManualOverride(): bool
    {
        if ($this->source !== SubscriptionSegmentSource::Manual) {
            return false;
        }

        if ($this->status !== SubscriptionSegmentStatus::Active) {
            return false;
        }

        if ($this->expires_at !== null && $this->expires_at->isPast()) {
            return false;
        }

        return true;
    }
}