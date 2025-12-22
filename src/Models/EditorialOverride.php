<?php

namespace App\Models;

use DateTimeImmutable;
use DateTimeZone;

class EditorialOverride extends Model
{
    protected $table = 'editorial_overrides';

    protected $fillable = [
        'page_id',
        'member_id',
        'override_access_level',
        'starts_at',
        'ends_at',
        'created_at'
    ];

    protected $casts = [
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'created_at' => 'datetime'
    ];

    public function page($relation = false)
    {
        return $this->belongsTo(Page::class, 'page_id', 'id', $relation);
    }

    public function member($relation = false)
    {
        return $this->belongsTo(Member::class, 'member_id', 'id', $relation);
    }

    /**
     * Check if override is currently active
     */
    public function isActive(): bool
    {
        $now = new DateTimeImmutable('now', new DateTimeZone('UTC'));
        $startsAt = new DateTimeImmutable($this->starts_at, new DateTimeZone('UTC'));

        if ($now < $startsAt) {
            return false;
        }

        if ($this->ends_at) {
            $endsAt = new DateTimeImmutable($this->ends_at, new DateTimeZone('UTC'));
            return $now <= $endsAt;
        }

        return true;
    }

    /**
     * Check if override is global (applies to all pages or all users)
     */
    public function isGlobal(): bool
    {
        return $this->page_id === null || $this->member_id === null;
    }

    /**
     * Check if override is user-specific
     */
    public function isUserSpecific(): bool
    {
        return $this->member_id !== null;
    }

    /**
     * Check if override is page-specific
     */
    public function isPageSpecific(): bool
    {
        return $this->page_id !== null;
    }

    /**
     * Scope: Active overrides only
     */
    public function scopeActive($query)
    {
        $now = date('Y-m-d H:i:s');

        return $query->where('starts_at', '<=', $now)
            ->where(function ($q) use ($now) {
                $q->whereNull('ends_at')
                    ->orWhere('ends_at', '>=', $now);
            });
    }

    /**
     * Scope: For specific page
     */
    public function scopeForPage($query, int $pageId)
    {
        return $query->where('page_id', $pageId);
    }

    /**
     * Scope: For specific member
     */
    public function scopeForMember($query, int $memberId)
    {
        return $query->where('member_id', $memberId);
    }

    /**
     * Scope: Global overrides (page_id or member_id is null)
     */
    public function scopeGlobal($query)
    {
        return $query->where(function ($q) {
            $q->whereNull('page_id')
                ->orWhereNull('member_id');
        });
    }
}