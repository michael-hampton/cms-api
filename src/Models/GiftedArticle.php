<?php

namespace App\Models;

class GiftedArticle extends Model
{
    protected $table = 'gifted_articles';

    protected $fillable = [
        'page_id',
        'gifted_by_member_id',
        'site_id',
        'recipient_email',
        'recipient_member_id',
        'gift_token',
        'gifted_at',
        'claimed_at',
        'personal_message',
        'status',
        'expires_at'
    ];

    protected $casts = [
        'gifted_at' => 'datetime',
        'claimed_at' => 'datetime',
        'expires_at' => 'datetime'
    ];

    public function page($relation = false)
    {
        return $this->belongsTo(Page::class, 'page_id', 'id', $relation);
    }

    public function giftedBy($relation = false)
    {
        return $this->belongsTo(Member::class, 'gifted_by_member_id', 'id', $relation);
    }

    public function recipient($relation = false)
    {
        return $this->belongsTo(Member::class, 'recipient_member_id', 'id', $relation);
    }

    public function isClaimed(): bool
    {
        return $this->status === 'claimed';
    }

    public function claim(int $memberId): bool
    {
        if (!$this->isPending()) {
            return false;
        }

        return $this->update([
            'recipient_member_id' => $memberId,
            'claimed_at' => now_datetime()->toDateTimeString(),
            'status' => 'claimed'
        ]);
    }

    public function isPending(): bool
    {
        return $this->status === 'pending' && !$this->isExpired();
    }

    public function isExpired(): bool
    {
        return $this->status === 'expired' ||
            ($this->expires_at && $this->expires_at < now_datetime());
    }
}