<?php

namespace App\Repositories\OpenCollab;

use App\Framework\Support\Collection;
use App\Models\Brief;
use App\Models\BriefAttachment;
use App\Models\BriefComment;
use App\Models\BriefTask;
use App\Models\Collaborator;
use App\Models\Model;
use App\Repositories\Repository;

class ContributorBriefRepository extends Repository
{
    private const WORKSPACE_RELATIONS = [
        'site',
        'owner',
        'attachments',
        'attachments.image',
        'attachments.product',
        'comments',
        'comments.user',
        'comments.replies',
        'collaborators',
        'tasks',
        'tasks.assignee',
        'deadlines',
        'activityLog',
        'activityLog.user',
        'versions',
    ];

    private const INBOX_RELATIONS = [
        'site',
        'collaborators',
        'deadlines',
    ];

    public function assignmentsForContributor(int $contributorId, int $siteId): Collection
    {
//        dd(Collaborator::where('collaboratable_type', Brief::class)
//            ->where('user_id', $contributorId)
//            ->where('site_id', $siteId)
//            ->toSql());

        return Collaborator::where('collaboratable_type', Brief::class)
            ->where('user_id', $contributorId)
            ->where('site_id', $siteId)
            ->get();
    }

    public function assignmentForBrief(int $briefId, int $contributorId, int $siteId): ?Collaborator
    {
        return Collaborator::where('collaboratable_type', Brief::class)
            ->where('collaboratable_id', $briefId)
            ->where('user_id', $contributorId)
            ->where('site_id', $siteId)
            ->first();
    }

    public function assignedBriefsForContributor(int $contributorId, int $siteId): Collection
    {
        $briefIds = $this->assignmentsForContributor($contributorId, $siteId)
            ->map(fn($assignment) => (int)$assignment->collaboratable_id)
            ->filter(fn(int $id) => $id > 0)
            ->values()
            ->toArray();

        if (empty($briefIds)) {
            return Collection::make([]);
        }

        return Brief::with(self::INBOX_RELATIONS)
            ->where('site_id', $siteId)
            ->whereIn('id', $briefIds)
            ->where('status', '!=', 'archived')
            ->orderByDesc('updated_at')
            ->get();
    }

    public function findAssignedBrief(int $briefId, int $contributorId, int $siteId): ?Brief
    {
        if (!$this->assignmentForBrief($briefId, $contributorId, $siteId)) {
            return null;
        }

        return Brief::with(self::WORKSPACE_RELATIONS)
            ->where('id', $briefId)
            ->where('site_id', $siteId)
            ->where('status', '!=', 'archived')
            ->first();
    }

    public function findTaskForContributor(int $briefId, int $taskId, int $userId): ?BriefTask
    {
        return BriefTask::where('brief_id', $briefId)
            ->where('id', $taskId)
            ->where(function ($query) use ($userId) {
                $query->where('assigned_to', 0)
                    ->orWhereNull('assigned_to')
                    ->orWhere('assigned_to', $userId);
            })
            ->first();
    }

    public function findComment(int $commentId): ?BriefComment
    {
        return BriefComment::find($commentId);
    }

    public function findCommentOwnedByContributor(int $commentId, int $userId): ?BriefComment
    {
        return BriefComment::where('id', $commentId)
            ->where('user_id', $userId)
            ->first();
    }

    public function findAttachmentForBrief(int $briefId, int $attachmentId): ?BriefAttachment
    {
        return BriefAttachment::where('brief_id', $briefId)
            ->where('id', $attachmentId)
            ->first();
    }

    public function findAttachmentOwnedByContributor(int $briefId, int $attachmentId, int $userId): ?BriefAttachment
    {
        $attachment = $this->findAttachmentForBrief($briefId, $attachmentId);
        $metadata = is_array($attachment?->metadata) ? $attachment->metadata : [];

        if (!$attachment || (int)($metadata['uploaded_by'] ?? 0) !== $userId) {
            return null;
        }

        return $attachment;
    }

    protected function getModelClass(): string
    {
        return Brief::class;
    }
}
