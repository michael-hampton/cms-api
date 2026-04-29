<?php

namespace App\Models;

/**
 * Append-only audit log for admin actions on contributors.
 *
 * @property int $id
 * @property int $admin_id
 * @property int $target_user_id
 * @property string $action
 * @property array|null $payload
 * @property string|null $reason
 * @property \DateTime $created_at
 */
class AdminActivityLog extends Model
{
    /**
     * Logs are append-only — disable update timestamps and prevent mass updates.
     */
    public const UPDATED_AT = null;
    protected $table = 'admin_activity_logs';
    protected $timestamps = false;
    protected $fillable = [
        'admin_id',
        'target_user_id',
        'action',
        'payload',
        'reason',
        'created_at'
    ];

    protected $casts = [
        'payload' => 'array',
        'created_at' => 'datetime',
    ];

    // ── Relationships ─────────────────────────────────────────────────────────

    public function admin($relation = false)
    {
        return $this->belongsTo(User::class, 'admin_id', 'id', $relation);
    }

    public function targetUser($relation = false)
    {
        return $this->belongsTo(User::class, 'target_user_id', 'id', $relation);
    }
}