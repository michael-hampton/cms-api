<?php

namespace App\Models;

class SubscriberConsent extends Model
{
    protected $table = 'subscriber_consents';

    protected $fillable = [
        'email',
        'consent_type_id',
        'is_granted',
        'channel',
        'granted_at',
        'revoked_at',
        'metadata'
    ];

    protected $casts = [
        'is_granted' => 'boolean',
        'granted_at' => 'datetime',
        'revoked_at' => 'datetime',
        'metadata' => 'array'
    ];

    public function consentType()
    {
        return $this->belongsTo(ConsentType::class);
    }

    public function isActive(): bool
    {
        return $this->is_granted && $this->revoked_at === null;
    }

    public function scopeByEmail($query, string $email)
    {
        return $query->where('email', $email);
    }

    public function scopeGranted($query)
    {
        return $query->where('is_granted', true);
    }

    public function scopeActive($query)
    {
        return $query->where('is_granted', true)
            ->whereNull('revoked_at');
    }
}