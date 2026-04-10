<?php

namespace App\Models;

class ContributorToken extends Model
{
    protected $table = 'oc_contributor_tokens';

    protected $fillable = [
        'contributor_id',
        'token',
        'last_used_at',
        'expires_at',
        'site_id',
    ];

    protected $casts = [
        'last_used_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }
}