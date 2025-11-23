<?php

namespace App\Models;

class MemberConsentNotice extends Model
{
    public $timestamps = false;
    protected $table = 'member_consent_notices';
    protected $fillable = [
        'member_id',
        'consent_notice_id',
        'shown_at',
        'responded_at',
        'response',
        'ip_address'
    ];
    protected $casts = [
        'shown_at' => 'datetime',
        'responded_at' => 'datetime'
    ];

    public function member()
    {
        return $this->belongsTo(Member::class);
    }

    public function consentNotice()
    {
        return $this->belongsTo(ConsentNotice::class);
    }

    public function hasResponded(): bool
    {
        return $this->responded_at !== null;
    }

    public function scopeByMember($query, int $memberId)
    {
        return $query->where('member_id', $memberId);
    }

    public function scopeResponded($query)
    {
        return $query->whereNotNull('responded_at');
    }

    public function scopeUnresponded($query)
    {
        return $query->whereNull('responded_at');
    }
}