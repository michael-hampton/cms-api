<?php

namespace App\Models;

class ActivityEvent extends Model
{
    protected $table = 'oc_activity_events';

    protected $fillable = [
        'site_id',
        'user_id',
        'type',
        'payload',
        'created_at',
    ];

    protected $casts = [
        'created_at' => 'datetime',
    ];

    public function getPayloadDecodedAttribute(): array
    {
        return json_decode($this->payload, true) ?? [];
    }
}