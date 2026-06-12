<?php

namespace App\Services\OpenCollab;

use App\Models\Brief;
use App\Models\Collaborator;

class OpenCollabBriefStatusMapper
{
    public function assignmentStatus(?Collaborator $assignment): string
    {
        $role = strtolower((string)($assignment?->role ?? ''));

        return match ($role) {
            'awaiting_response', 'pending' => 'awaiting_response',
            'rejected' => 'rejected',
            'negotiating' => 'negotiating',
            default => 'accepted',
        };
    }

    public function workflowStatus(Brief $brief): string
    {
        return match ((string)$brief->status) {
            'draft', 'active' => 'assigned',
            'in_progress' => 'in_progress',
            'in_review' => 'submitted',
            'on_hold' => 'returned_for_changes',
            'ready' => 'approved',
            'converted' => 'published',
            default => (string)$brief->status,
        };
    }

    public function label(string $status): string
    {
        return implode(' ', array_map('ucfirst', explode('_', $status)));
    }

    public function deadlineStatus(?string $deadlineAt, string $workflowStatus): string
    {
        if ($deadlineAt === null) {
            return 'none';
        }

        if (in_array($workflowStatus, ['approved', 'published', 'completed'], true)) {
            return 'complete';
        }

        if (strtotime($deadlineAt) < time()) {
            return 'overdue';
        }

        return 'upcoming';
    }
}
