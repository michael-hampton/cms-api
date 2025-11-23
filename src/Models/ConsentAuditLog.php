<?php

namespace App\Models;

class ConsentAuditLog extends Model
{
    public $timestamps = false;
    protected $table = 'consent_audit_log';
    protected $fillable = [
        'member_id',
        'consent_type_id',
        'action',
        'previous_state',
        'new_state',
        'source',
        'ip_address',
        'user_agent',
        'admin_user_id',
        'reason',
        'metadata',
        'created_at'
    ];
    protected $casts = [
        'previous_state' => 'boolean',
        'new_state' => 'boolean',
        'metadata' => 'array',
        'created_at' => 'datetime'
    ];

    public function member()
    {
        return $this->belongsTo(Member::class);
    }

    public function consentType()
    {
        return $this->belongsTo(ConsentType::class, 'consent_type_id', 'id');
    }

    public function adminUser()
    {
        return $this->belongsTo(User::class, 'admin_user_id');
    }

    public function scopeByMember($query, int $memberId)
    {
        return $query->where('member_id', $memberId);
    }

    public function scopeByConsentType($query, int $consentTypeId)
    {
        return $query->where('consent_type_id', $consentTypeId);
    }

    public function scopeByAction($query, string $action)
    {
        return $query->where('action', $action);
    }

    public function scopeRecent($query, int $days = 30)
    {
        return $query->where('created_at', '>=', now_datetime()->modify("-{$days} days")->format('Y-m-d H:i:s'));
    }
}