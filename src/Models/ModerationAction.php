<?php

namespace App\Models;

use App\Enums\OpenCollab\ModerationActionType;

class ModerationAction extends Model
{
    protected $table = 'oc_moderation_actions';

    public const UPDATED_AT = null; // append-only

    protected $fillable = [
        'site_id', 'queue_entry_id', 'page_id', 'page_version_id',
        'actor_user_id', 'action', 'reason_code', 'notes', 'metadata', 'created_at',
    ];

    protected $casts = [
        'action' => ModerationActionType::class,
        'metadata' => 'array',
        'created_at' => 'datetime',
    ];
}