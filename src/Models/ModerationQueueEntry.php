<?php

namespace App\Models;

use App\Enums\OpenCollab\ModerationQueueStatus;

class ModerationQueueEntry extends Model
{
    protected $table = 'oc_moderation_queue_entries';

    protected $fillable = [
        'site_id', 'page_id', 'page_version_id', 'status', 'submitted_at',
        'risk_score', 'priority_score', 'scheduled_publish_at', 'sla_due_at',
        'assigned_to_user_id', 'claimed_at',
    ];

    protected $casts = [
        'status' => ModerationQueueStatus::class,
        'submitted_at' => 'datetime',
        'scheduled_publish_at' => 'datetime',
        'sla_due_at' => 'datetime',
        'claimed_at' => 'datetime',
        'risk_score' => 'integer',
        'priority_score' => 'integer',
    ];

    public function page()
    {
        return $this->belongsTo(\App\Models\Page::class, 'page_id');
    }

    public function assignedUser()
    {
        return $this->belongsTo(\App\Models\User::class, 'assigned_to_user_id');
    }
}