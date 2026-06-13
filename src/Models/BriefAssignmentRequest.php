<?php

namespace App\Models;

use App\Enums\OpenCollab\BriefAssignmentRequestStatus;
use App\Enums\OpenCollab\BriefAssignmentRequestType;

class BriefAssignmentRequest extends Model
{
    protected $table = 'brief_assignment_requests';

    protected $fillable = [
        'brief_id',
        'assignment_id',
        'contributor_id',
        'type',
        'status',
        'message',
        'reason',
        'requested_deadline_at',
        'scope_details',
        'editor_response',
        'resolved_by',
        'resolved_at',
        'metadata',
    ];

    protected $casts = [
        'requested_deadline_at' => 'datetime',
        'resolved_at'           => 'datetime',
        'metadata'              => 'array',
        'created_at'            => 'datetime',
        'updated_at'            => 'datetime',
    ];

    public function brief(bool $relation = false)
    {
        return $this->belongsTo(Brief::class, 'brief_id', 'id', $relation);
    }

    public function contributor(bool $relation = false)
    {
        return $this->belongsTo(User::class, 'contributor_id', 'id', $relation);
    }

    public function resolvedByUser(bool $relation = false)
    {
        return $this->belongsTo(User::class, 'resolved_by', 'id', $relation);
    }

    public function typeEnum(): BriefAssignmentRequestType
    {
        return BriefAssignmentRequestType::from($this->type);
    }

    public function statusEnum(): BriefAssignmentRequestStatus
    {
        return BriefAssignmentRequestStatus::from($this->status);
    }

    public function isPending(): bool
    {
        return $this->status === BriefAssignmentRequestStatus::Pending->value;
    }

    public function isTerminal(): bool
    {
        return BriefAssignmentRequestStatus::from($this->status)->isTerminal();
    }

    /**
     * Return only fields safe for contributor-facing presentation.
     * Internal CMS notes (editor_response as an internal field) are included
     * here only when the response has been marked contributor-visible.
     * resolved_by and raw metadata are excluded.
     */
    public function toContributorArray(): array
    {
        return [
            'id'                    => $this->id,
            'type'                  => $this->type,
            'status'                => $this->status,
            'message'               => $this->message,
            'reason'                => $this->reason,
            'requested_deadline_at' => $this->requested_deadline_at?->format('Y-m-d H:i:s'),
            'scope_details'         => $this->scope_details,
            'editor_response'       => $this->editor_response,
            'created_at'            => $this->created_at?->format('Y-m-d H:i:s'),
        ];
    }
}