<?php

namespace App\Models;

class DealAlert extends Model
{
    protected $table = 'deal_alerts';

    protected $fillable = [
        'user_id',
        'email',
        'frequency',
        'preferences',
        'is_active',
        'verified_at',
        'verification_token',
        'created_at',
    ];

    protected $casts = [
        'preferences' => 'array',
        'is_active' => 'boolean',
        'verified_at' => 'datetime'
    ];

    public function member()
    {
        return $this->belongsTo(Member::class);
    }

    public function isVerified(): bool
    {
        return !is_null($this->verified_at);
    }
}