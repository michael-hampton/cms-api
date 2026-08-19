<?php

namespace App\Services\OpenCollab;

use App\Actions\Brief\LogBriefActivity;
use App\Enums\OpenCollab\BriefActivityAction;
use App\Enums\OpenCollab\BriefCollaboratorRole;
use App\Enums\OpenCollab\BriefNotificationType;
use App\Enums\OpenCollab\BriefSubmissionStatus;
use App\Framework\Database\Database;
use App\Framework\FileUpload\FileUpload;
use App\Framework\Http\UploadedFile;
use App\Models\Brief;
use App\Models\BriefComment;
use App\Models\BriefTask;
use App\Models\Collaborator;
use App\Repositories\Cms\Briefs\BriefCollaboratorRepository;
use App\Repositories\OpenCollab\ContributorBriefRepository;
use App\Services\Cms\BriefAssignmentRequestService;
use App\Services\Cms\BriefService;
use RuntimeException;

class CmsBriefGateway
{
    public function __construct(
        private readonly BriefService                      $briefService,
        private readonly BriefCollaboratorRepository       $collaborators,
        private readonly ContributorBriefRepository        $briefs,
        private readonly LogBriefActivity                  $activity,
        private readonly OpenCollabBriefNotificationService $notifications,
        private readonly BriefAssignmentRequestService     $assignmentRequestService,
        private readonly Database                          $database,
    ) {}

    public function acceptAssignment(Brief $brief, Collaborator $assignment, int $userId): void
    {
        $this->database->transaction(function () use ($brief, $assignment, $userId): void {
            $this->collaborators->update($assignment->id, [
                'role' => BriefCollaboratorRole::Writer->value,
                'assigned_at' => date('Y-m-d H:i:s'),
            ]);
            $this->activity->handle($brief->id, $userId, BriefActivityAction::AssignmentAccepted->value, 'Assignment accepted');
            $this->notify($brief, $userId, BriefNotificationType::AssignmentAccepted->value, 'Assignment accepted', "You accepted {$brief->title}.");
        });
    }

    public function rejectAssignment(Brief $brief, Collaborator $assignment, int $userId, string $reason): void
    {
        $this->database->transaction(function () use ($brief, $assignment, $userId, $reason): void {
            $this->collaborators->update($assignment->id, ['role' => BriefCollaboratorRole::Rejected->value]);

            // Store the reason as structured CMS-owned data; activity log is secondary.
            $this->assignmentRequestService->recordRejectionReason($brief, $userId, $reason);
        });
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
        $this->database->transaction(function () use ($assignment, $brief, $userId, $data): void {
            $this->collaborators->update($assignment->id, ['role' => BriefCollaboratorRole::Negotiating->value]);

            $this->assignmentRequestService->createNegotiationRequest(
                brief: $brief,
                contributorId: $userId,
                message: $data['message'],
                requestedDeadline: $data['requested_deadline'] ?? null,
                scopeDetails: $data['scope_details'] ?? null,
            );
        });
    }

    public function submit(Brief $brief, int $userId, string $notes = ''): void
    {
        $this->database->transaction(function () use ($brief, $userId, $notes): void {
            $this->briefService->updateStatus($brief->id, BriefSubmissionStatus::InReview->value, $userId);

            if ($notes !== '') {
                $this->activity->handle($brief->id, $userId, BriefActivityAction::SubmissionNotesAdded->value, 'Submission notes: ' . $notes);
            }

            $this->notify($brief, $userId, BriefNotificationType::SubmittedForApproval->value, 'Brief submitted', "{$brief->title} was submitted for review.");
        });
    }

    public function resubmit(Brief $brief, int $userId, string $notes = ''): void
    {
        $this->database->transaction(function () use ($brief, $userId, $notes): void {
            $this->briefService->updateStatus($brief->id, BriefSubmissionStatus::InReview->value, $userId);
            $this->activity->handle($brief->id, $userId, BriefActivityAction::BriefResubmitted->value, $notes !== '' ? 'Resubmitted: ' . $notes : 'Resubmitted for review');
            $this->notify($brief, $userId, BriefNotificationType::ResubmittedForApproval->value, 'Brief resubmitted', "{$brief->title} was resubmitted for review.");
        });
    }

    public function updateTask(Brief $brief, int $taskId, int $userId, string $status): BriefTask
    {
        $task = $this->briefs->findTaskForContributor((int) $brief->id, $taskId, $userId);

        if (!$task) {
            throw new RuntimeException('Task not found');
        }

        return $this->database->transaction(function () use ($brief, $taskId, $userId, $status, $task): BriefTask {
            $updated = $this->briefService->updateTask($taskId, ['status' => $status]);
            $this->activity->handle($brief->id, $userId, BriefActivityAction::TaskUpdated->value, "Task updated: {$task->title}");

            return $updated;
        });
    }

    /**
     * @param UploadedFile $file The uploaded file, already extracted from the
     *                           request at the controller boundary.
     */
    public function addAttachment(Brief $brief, UploadedFile $file, string $description, int $userId, string $userName)
    {
        $fileUpload = new FileUpload($file, 'uploads/briefs/' . $brief->id);
        $fileUpload->setAllowedExtensions(['pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'txt', 'csv', 'png', 'jpg', 'jpeg']);
        $fileUpload->setMaxSize(10 * 1024 * 1024);
        $filePath = $fileUpload->store();

        return $this->database->transaction(function () use ($brief, $file, $description, $userId, $userName, $filePath) {
            $attachment = $this->briefService->addAttachment($brief->id, [
                'type' => 'document',
                'file_url' => $filePath,
                'file_name' => $file->getClientOriginalName(),
                'filesize' => $file->getSize(),
                'metadata' => [
                    'description' => trim($description),
                    'mime_type' => $file->getMimeType(),
                    'uploaded_by' => $userId,
                    'uploaded_by_name' => $userName,
                ],
                'sort_order' => 0,
            ]);

            $this->activity->handle($brief->id, $userId, BriefActivityAction::AttachmentUploaded->value, 'Attachment uploaded: ' . $file->getClientOriginalName());

            return $attachment;
        });
    }

    public function deleteAttachment(Brief $brief, int $attachmentId, int $userId): void
    {
        $attachment = $this->briefs->findAttachmentOwnedByContributor((int) $brief->id, $attachmentId, $userId);

        if (!$attachment) {
            throw new RuntimeException('Attachment not found');
        }

        $this->database->transaction(function () use ($brief, $attachmentId, $userId): void {
            $this->briefService->deleteAttachment($brief->id, $attachmentId);
            $this->activity->handle($brief->id, $userId, BriefActivityAction::AttachmentDeleted->value, 'Attachment deleted');
        });
    }

    public function addComment(Brief $brief, int $userId, string $content)
    {
        return $this->database->transaction(function () use ($brief, $userId, $content) {
            $comment = $this->briefService->addComment($brief->id, [
                'user_id' => $userId,
                'content' => $content,
            ]);

            $this->activity->handle($brief->id, $userId, BriefActivityAction::CommentAdded->value, 'Comment added');

            return $comment;
        });
    }

    public function updateComment(int $commentId, int $userId, string $content): BriefComment
    {
        $comment = $this->briefs->findCommentOwnedByContributor($commentId, $userId);

        if (!$comment) {
            throw new RuntimeException('Comment not found');
        }

        return $this->briefService->updateComment((int) $comment->brief_id, $commentId, ['content' => $content]);
    }

    public function resolveComment(int $commentId, int $userId): BriefComment
    {
        $comment = $this->briefs->findComment($commentId);

        if (!$comment) {
            throw new RuntimeException('Comment not found');
        }

        return $this->briefService->resolveComment((int) $comment->brief_id, $commentId, $userId);
    }

    private function notify(Brief $brief, int $userId, string $type, string $title, string $message): void
    {
        $this->notifications->notifyContributor($userId, $brief, $type, $title, $message);
    }
}