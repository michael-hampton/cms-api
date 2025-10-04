<?php

namespace App\Models;

class EventSignup extends Model
{
    protected $table = 'event_signups';

    protected $fillable = [
        'event_title', 'event_date', 'name', 'email', 'phone', 'company',
        'dietary_requirements', 'accessibility_requirements', 'newsletter',
        'notifications', 'status', 'confirmation_token', 'created_at', 'updated_at'
    ];

    protected $casts = [
        'event_date' => 'date',
        'newsletter' => 'boolean',
        'notifications' => 'json'
    ];

    public function getNotificationsAttribute()
    {
        $rawData = $this->attributes['notifications'] ?? null;
        return $rawData ? json_decode($rawData, true) : [];
    }

    public function setNotificationsAttribute($value): void
    {
        $this->attributes['notifications'] = is_array($value) ? json_encode($value) : $value;
    }

    public function isConfirmed(): bool
    {
        return $this->status === 'confirmed';
    }

    public function confirm(): void
    {
        $this->status = 'confirmed';
        $this->save();
    }
}