<?php

namespace App\Services\OpenCollab;

use App\Models\Brief;
use App\Models\Collaborator;

class OpenCollabBriefActionAvailabilityService
{
    public function __construct(
        private readonly OpenCollabBriefStatusMapper $statusMapper,
    )
    {
    }

    public function availableActions(Brief $brief, ?Collaborator $assignment): array
    {
        $assignmentStatus = $this->statusMapper->assignmentStatus($assignment);
        $workflowStatus = $this->statusMapper->workflowStatus($brief);

        if (in_array($assignmentStatus, ['rejected'], true)
            || in_array($workflowStatus, ['approved', 'published'], true)) {
            return [];
        }

        if ($assignmentStatus === 'awaiting_response') {
            return ['accept', 'reject', 'negotiate', 'request_clarification'];
        }

        $actions = ['request_clarification', 'request_deadline_change', 'negotiate'];

        if (in_array($workflowStatus, ['assigned', 'in_progress'], true)) {
            $actions[] = 'submit';
        }

        if ($workflowStatus === 'returned_for_changes') {
            $actions[] = 'resubmit';
        }

        return $actions;
    }

    public function assertActionAvailable(string $action, Brief $brief, ?Collaborator $assignment): void
    {
        if (!in_array($action, $this->availableActions($brief, $assignment), true)) {
            throw new \InvalidArgumentException('This action is not available for the current assignment state.');
        }
    }
}
