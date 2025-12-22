<?php

namespace App\Models;

class SubscriptionWindow extends Model
{
    protected $table = 'subscription_windows';

    protected $fillable = [
        'member_id',
        'subscription_id',
        'site_id',
        'window_start',
        'window_end',
        'type'
    ];

    protected $casts = [
        'window_start' => 'datetime',
        'window_end' => 'datetime'
    ];

    public function member($relation = false)
    {
        return $this->belongsTo(Member::class, 'member_id', 'id', $relation);
    }

    public function subscription($relation = false)
    {
        return $this->belongsTo(Subscription::class, 'subscription_id', 'id', $relation);
    }
}