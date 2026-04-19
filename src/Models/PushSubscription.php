<?php

namespace App\Models;

class PushSubscription extends Model
{
    protected $table = 'push_subscriptions';
    protected $fillable = [
        'member_id',
        'endpoint',
        'keys',
        'is_active',
    ];

    protected $casts = [
        'keys' => 'array',
        'is_active' => 'boolean',
    ];

    public function member()
    {
        return $this->belongsTo(Member::class);
    }
}