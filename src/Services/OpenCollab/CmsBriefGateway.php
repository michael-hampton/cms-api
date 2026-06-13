<?php

namespace App\Services\OpenCollab;

use App\Actions\Brief\LogBriefActivity;
use App\Framework\Http\Request;
use App\Models\Brief;
use App\Models\BriefComment;
use App\Models\BriefTask;
use App\Models\Collaborator;
use App\Repositories\Cms\Briefs\BriefCollaboratorRepository;
use App\Repositories\OpenCollab\ContributorBriefRepository;
use App\Services\Cms\BriefAssignmentRequestService;
use App\Services\Cms\BriefService;

class CmsBriefGateway
{
    public function __construct(
        private readonly BriefService                      $briefService,
        private readonly BriefCollaboratorRepository       $collaborators,
        private readonly ContributorBriefRepository        $briefs,
        private readonly LogBriefActivity                  $activity,
        private readonly OpenCollabBriefNotificationService $notifications,
        private readonly BriefAssignmentRequestService     $assignmentRequestService,
    ) {}

    public function acceptAssignment(Brief $brief, Collaborator $assignment, int $userId): void
    {
        $this->collaborators->update($assignment->id, ['role' => 'writer', 'assigned_at' => date('Y-m-d H:i:s')]);
        $this->activity->handle($brief->id, $userId, 'assignment_accepted', 'Assignment accepted');
        $this->notify($brief, $userId, 'brief_assignment_accepted', 'Assignment accepted', "You accepted {$brief->title}.");
    }

    public function rejectAssignment(Brief $brief, Collaborator $assignment, int $userId, string $reason): void
    {
        $this->collaborators->update($assignment->id, ['role' => 'rejected']);

        // Store the reason as structured CMS-owned data; activity log is secondary.
        $this->assignmentRequestService->recordRejectionReason($brief, $userId, $reason);
    }

    public function requestClarification(Brief $brief, int $userId, string $message): void
    {
        $this->assignmentRequestService->createClarificationRequest($brief, $userId, $message);
    }

    public function requestDeadlineChange(Brief $brief, int $userId, string $requestedDeadline, string $reason): void
    {
        $this->assignmentRequestService->createDeadlineChangeRequest(
            brief: $brief,
            contributorId: $userId,
            requestedDeadline: $requestedDeadline,
            reason: $reason,
        );
    }

    public function negotiateAssignment(Brief $brief, Collaborator $assignment, int $userId, array $data): void
    {
        $this->collaborators->update($assignment->id, ['role' => 'negotiating']);

        $this->assignmentRequestService->createNegotiationRequest(
            brief: $brief,
            contributorId: $userId,
            message: $data['message'],
            requestedDeadline: $data['requested_deadline'] ?? null,
            scopeDetails: $data['scope_details'] ?? null,
        );
    }

    public function submit(Brief $brief, int $userId, string $notes = ''): void
    {
        $this->briefService->updateStatus($brief->id, 'in_review', $userId);

        if ($notes !== '') {
            $this->activity->handle($brief->id, $userId, 'submission_notes_added', 'Submission notes: ' . $notes);
        }

        $this->notify($brief, $userId, 'brief_submitted_for_approval', 'Brief submitted', "{$brief->title} was submitted for review.");
    }

    public function resubmit(Brief $brief, int $userId, string $notes = ''): void
    {
        $this->briefService->updateStatus($brief->id, 'in_review', $userId);
        $this->activity->handle($brief->id, $userId, 'brief_resubmitted', $notes !== '' ? 'Resubmitted: ' . $notes : 'Resubmitted for review');
        $this->notify($brief, $userId, 'brief_resubmitted_for_approval', 'Brief resubmitted', "{$brief->title} was resubmitted for review.");
    }

    public function updateTask(Brief $brief, int $taskId, int $userId, string $status): BriefTask
    {
        $task = $this->briefs->findTaskForContributor((int) $brief->id, $taskId, $userId);

        if (!$task) {
            throw new \RuntimeException('Task not found');
        }

        $updated = $this->briefService->updateTask($taskId, ['status' => $status]);
        $this->activity->handle($brief->id, $userId, 'task_updated', "Task updated: {$task->title}");

        return $updated;
    }

    public function addAttachment(Brief $brief, Request $request, int $userId, string $userName)
    {
        $file = $request->file('file');

        if (!$file) {
            throw new \InvalidArgumentException('No file provided.');
        }

        $fileUpload = new \App\Framework\FileUpload\FileUpload($file, 'uploads/briefs/' . $brief->id);
        $fileUpload->setAllowedExtensions(['pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'txt', 'csv', 'png', 'jpg', 'jpeg']);
        $fileUpload->setMaxSize(10 * 1024 * 1024);
        $filePath = $fileUpload->store();

        $attachment = $this->briefService->addAttachment($brief->id, [
            'type' => 'document',
            'file_url' => $filePath,
            'file_name' => $file->getClientOriginalName(),
            'filesize' => $file->getSize(),
            'metadata' => [
                'description' => trim((string) $request->get('description', '')),
                'mime_type' => $file->getMimeType(),
                'uploaded_by' => $userId,
                'uploaded_by_name' => $userName,
            ],
            'sort_order' => 0,
        ]);

        $this->activity->handle($brief->id, $userId, 'attachment_uploaded', 'Attachment uploaded: ' . $file->getClientOriginalName());

        return $attachment;
    }

    public function deleteAttachment(Brief $brief, int $attachmentId, int $userId): void
    {
        $attachment = $this->briefs->findAttachmentOwnedByContributor((int) $brief->id, $attachmentId, $userId);

        if (!$attachment) {
            throw new \RuntimeException('Attachment not found');
        }

        $this->briefService->deleteAttachment($brief->id, $attachmentId);
        $this->activity->handle($brief->id, $userId, 'attachment_deleted', 'Attachment deleted');
    }

    public function addComment(Brief $brief, int $userId, string $content)
    {
        $comment = $this->briefService->addComment($brief->id, [
            'user_id' => $userId,
            'content' => $content,
        ]);

        $this->activity->handle($brief->id, $userId, 'comment_added', 'Comment added');

        return $comment;
    }

    public function updateComment(int $commentId, int $userId, string $content): BriefComment
    {
        $comment = $this->briefs->findCommentOwnedByContributor($commentId, $userId);

        if (!$comment) {
            throw new \RuntimeException('Comment not found');
        }

        return $this->briefService->updateComment((int) $comment->brief_id, $commentId, ['content' => $content]);
    }

    public function resolveComment(int $commentId, int $userId): BriefComment
    {
        $comment = $this->briefs->findComment($commentId);

        if (!$comment) {
            throw new \RuntimeException('Comment not found');
        }

        return $this->briefService->resolveComment((int) $comment->brief_id, $commentId, $userId);
    }

    private function notify(Brief $brief, int $userId, string $type, string $title, string $message): void
    {
        $this->notifications->notifyContributor($userId, $brief, $type, $title, $message);
    }
}