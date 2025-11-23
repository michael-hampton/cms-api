<?php

namespace App\Models;

class MemberConsent extends Model
{
    protected $table = 'member_consents';

    protected $fillable = [
        'member_id',
        'consent_type_id',
        'is_granted',
        'channel',
        'ip_address',
        'user_agent',
        'granted_at',
        'revoked_at',
        'expires_at',
        'metadata',
        'created_at'
    ];

    protected $casts = [
        'is_granted' => 'boolean',
        'granted_at' => 'datetime',
        'revoked_at' => 'datetime',
        'expires_at' => 'datetime',
        'metadata' => 'array'
    ];

    public function member()
    {
        return $this->belongsTo(Member::class);
    }

    public function consentType()
    {
        return $this->belongsTo(ConsentType::class, 'consent_type_id', 'id');
    }

    public function isActive(): bool
    {
        if (!$this->is_granted) {
            return false;
        }

        if ($this->revoked_at !== null) {
            return false;
        }

        if ($this->expires_at !== null && $this->expires_at < now_datetime()) {
            return false;
        }

        return true;
    }

    public function isExpired(): bool
    {
        if ($this->expires_at === null) {
            return false;
        }

        return $this->expires_at < now_datetime();
    }

    public function scopeGranted($query)
    {
        return $query->where('is_granted', true);
    }

    public function scopeActive($query)
    {
        return $query->where('is_granted', true)
            ->whereNull('revoked_at')
            ->where(function ($q) {
                $q->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now_datetime()->format('Y-m-d H:i:s'));
            });
    }

    public function scopeExpired($query)
    {
        return $query->where('is_granted', true)
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now_datetime()->format('Y-m-d H:i:s'));
    }
}