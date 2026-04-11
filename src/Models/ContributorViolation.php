<?php

namespace App\Models;

use App\Enums\OpenCollab\ViolationAction;

class ContributorViolation extends Model
{
    protected $table = 'oc_contributor_violations';

    protected $fillable = [
        'user_id',
        'site_id',
        'type',
        'severity',
        'reason',
        'action_taken',
        'created_by',
        'resolved_at',
        'resolved_by',
        'resolution_notes',
        'page_id',
    ];

    protected $casts = [
        'resolved_at' => 'datetime',
    ];

    public function isResolved(): bool
    {
        return $this->resolved_at !== null;
    }

    public function isBan(): bool
    {
        return $this->action_taken === ViolationAction::Ban->value;
    }

    public function isSuspension(): bool
    {
        return $this->action_taken === ViolationAction::Suspension->value;
    }

    public function user($relation = false)
    {
        return $this->belongsTo(User::class, 'user_id', 'id', $relation);
    }

    public function page($relation = false)
    {
        return $this->belongsTo(Page::class, 'page_id', 'id', $relation);
    }
}