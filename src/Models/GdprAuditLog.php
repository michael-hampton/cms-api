<?php

namespace App\Models;

/**
 * Immutable audit log for all GDPR-sensitive actions.
 *
 * Actions logged:
 *   sar_export_requested
 *   sar_export_downloaded
 *   rtbf_requested
 *   rtbf_executed
 *   admin_override
 *   failed_access_attempt
 *
 * Logs are append-only — no update timestamps, no soft-delete.
 * Retention: 7 years (enforced by a scheduled purge command, not here).
 *
 * @property int         $id
 * @property int         $member_id
 * @property string      $action
 * @property string      $performed_by_type   'admin' | 'member' | 'system'
 * @property int|null    $performed_by_id
 * @property string|null $ip_address
 * @property array|null  $metadata
 * @property \DateTime   $created_at
 */
class GdprAuditLog extends Model
{
    public const UPDATED_AT = null;

    protected $table      = 'gdpr_audit_logs';
    protected $timestamps = false;

    protected $fillable = [
        'member_id',
        'action',
        'performed_by_type',
        'performed_by_id',
        'ip_address',
        'metadata',
        'created_at',
    ];

    protected $casts = [
        'metadata'   => 'array',
        'created_at' => 'datetime',
    ];

    // ── Relationships ──────────────────────────────────────────────────────

    public function member($relation = false)
    {
        return $this->belongsTo(Member::class, 'member_id', 'id', $relation);
    }

    // ── Scopes ─────────────────────────────────────────────────────────────

    public function scopeForMember($query, int $memberId)
    {
        return $query->where('member_id', $memberId);
    }

    public function scopeByAction($query, string $action)
    {
        return $query->where('action', $action);
    }
}