<?php

namespace App\Models;

/**
 * Represents a self-service contributor access request.
 *
 * Status flow:
 *   pending → approved  (admin dispatches invitation)
 *   pending → rejected  (admin declines)
 *
 * When Site::require_invite_approval is false, ContributorRequestService
 * skips creating this record and dispatches an invitation immediately.
 */
class ContributorRequest extends Model
{
    protected $table = 'oc_contributor_requests';

    protected $fillable = [
        'site_id',
        'email',
        'name',
        'bio',
        'status',
        'reviewed_by',
        'reviewed_at',
        'rejection_reason',
    ];

    protected $casts = [
        'site_id' => 'integer',
        'reviewed_by' => 'integer',
    ];

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isApproved(): bool
    {
        return $this->status === 'approved';
    }

    public function isRejected(): bool
    {
        return $this->status === 'rejected';
    }
}