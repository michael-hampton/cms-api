<?php

namespace App\Models;

use App\Enums\OpenCollab\EscalationCategory;
use App\Enums\OpenCollab\EscalationStatus;
use App\Enums\OpenCollab\RiskSeverity;

class ModerationEscalation extends Model
{
    protected $table = 'oc_moderation_escalations';

    public const UPDATED_AT = null;

    protected $fillable = [
        'site_id', 'queue_entry_id', 'page_id', 'page_version_id', 'cms_image_id',
        'risk_marker_id', 'category', 'severity', 'assigned_team', 'assigned_user_id',
        'status', 'due_at', 'created_by_user_id', 'created_at', 'acknowledged_at',
        'resolved_at', 'resolution', 'resolution_notes',
    ];

    protected $casts = [
        'category' => EscalationCategory::class,
        'severity' => RiskSeverity::class,
        'status' => EscalationStatus::class,
        'due_at' => 'datetime',
        'created_at' => 'datetime',
        'acknowledged_at' => 'datetime',
        'resolved_at' => 'datetime',
    ];
}