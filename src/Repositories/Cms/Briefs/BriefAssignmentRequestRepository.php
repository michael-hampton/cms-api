<?php

namespace App\Repositories\Cms\Briefs;

use App\Enums\OpenCollab\BriefAssignmentRequestStatus;
use App\Enums\OpenCollab\BriefAssignmentRequestType;
use App\Framework\Support\Collection;
use App\Models\BriefAssignmentRequest;
use App\Models\Model;
use App\Repositories\Repository;

class BriefAssignmentRequestRepository extends Repository
{
    public function findForBrief(int $briefId): Collection
    {
        return BriefAssignmentRequest::where('brief_id', $briefId)
            ->with(['contributor', 'resolvedByUser'])
            ->orderBy('created_at', 'desc')
            ->get();
    }

    public function findPendingForAssignment(
        int $assignmentId,
        BriefAssignmentRequestType $type,
    ): ?BriefAssignmentRequest {
        return BriefAssignmentRequest::where('assignment_id', $assignmentId)
            ->where('type', $type->value)
            ->where('status', BriefAssignmentRequestStatus::Pending->value)
            ->first();
    }

    public function findPendingForBrief(int $briefId): Collection
    {
        return BriefAssignmentRequest::where('brief_id', $briefId)
            ->where('status', BriefAssignmentRequestStatus::Pending->value)
            ->orderBy('created_at', 'desc')
            ->get();
    }

    public function findForContributor(int $contributorId, int $briefId): Collection
    {
        return BriefAssignmentRequest::where('contributor_id', $contributorId)
            ->where('brief_id', $briefId)
            ->orderBy('created_at', 'desc')
            ->get();
    }

    public function resolve(
        BriefAssignmentRequest $request,
        BriefAssignmentRequestStatus $status,
        int $resolvedBy,
        ?string $editorResponse,
    ): BriefAssignmentRequest {
        $request->update([
            'status'          => $status->value,
            'editor_response' => $editorResponse,
            'resolved_by'     => $resolvedBy,
            'resolved_at'     => now(),
        ]);

        return $request->fresh();
    }

    protected function getModelClass(): string
    {
        return BriefAssignmentRequest::class;
    }
}