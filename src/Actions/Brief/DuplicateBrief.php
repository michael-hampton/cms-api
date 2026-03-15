<?php

namespace App\Actions\Brief;

use App\DTO\Briefs\DuplicateBriefOptions;
use App\Enums\BriefStatus;
use App\Framework\Database\Database;
use App\Models\Brief;
use App\Repositories\Cms\Briefs\BriefCollaboratorRepository;
use App\Repositories\Cms\Briefs\BriefRepository;
use App\Repositories\Cms\Briefs\BriefTaskRepository;

class DuplicateBrief
{
    public function __construct(
        private readonly BriefRepository             $briefRepository,
        private readonly BriefTaskRepository         $subtaskRepository,
        private readonly BriefCollaboratorRepository $collaboratorRepository,
        private readonly LogBriefActivity            $logBriefActivity,
        private readonly Database                    $database,
    )
    {
    }

    /**
     * @param int $briefId Source brief ID
     * @param int $userId Actor performing the clone
     * @param string|null $titleOverride Optional title; falls back to "{original} (Copy)"
     * @param DuplicateBriefOptions|null $options Defaults to all relations included
     */
    public function handle(
        int                    $briefId,
        int                    $userId,
        ?string                $titleOverride = null,
        ?DuplicateBriefOptions $options = null,
    ): Brief
    {
        $options ??= DuplicateBriefOptions::all();

        return $this->database->transaction(function () use (
            $briefId, $userId, $titleOverride, $options
        ) {
            $original = $this->briefRepository->getWithRelations($briefId);

            if (!$original) {
                throw new \RuntimeException("Brief not found: {$briefId}");
            }

            $newBrief = $this->briefRepository->create([
                'site_id' => $original->site_id,
                'title' => $titleOverride ?? $original->title . ' (Copy)',
                'description' => $original->description,
                'owner_id' => $userId,
                'category_id' => $original->category_id,
                'status' => BriefStatus::DRAFT->value,
                // converted_page_id intentionally omitted — reset to null
                'target_word_count' => $original->target_word_count,
                'seo_keywords' => $original->seo_keywords,
                'target_audience' => $original->target_audience,
                'template_id' => $original->template_id,
            ]);

            $this->cloneAttachments($original, $newBrief->id);

            if ($options->includeSubtasks) {
                $this->cloneSubtasks($briefId, $newBrief->id);
            }

            if ($options->includeCollaborators) {
                $this->cloneCollaborators($original, $newBrief->id, $userId);
            }

            if ($options->includeComments) {
                $this->cloneComments($original, $newBrief->id);
            }

            if ($options->includeRelationships) {
                $this->cloneRelationships($original, $newBrief->id);
            }

            if ($options->includeDeadlines) {
                $this->cloneDeadlines($original, $newBrief->id, $userId);
            }

            $this->logBriefActivity->handle(
                $newBrief->id,
                $userId,
                'cloned',
                "Cloned from brief #{$briefId}",
            );

            return $this->briefRepository->getWithRelations($newBrief->id);
        });
    }

    // -------------------------------------------------------------------------
    // Private cloners
    // -------------------------------------------------------------------------

    private function cloneAttachments(Brief $original, int $newBriefId): void
    {
        foreach ($original->attachments as $attachment) {
            $this->briefRepository->addAttachment($newBriefId, [
                'type' => $attachment->type,
                'file_url' => $attachment->file_url,
                'file_name' => $attachment->file_name,
                'image_id' => $attachment->image_id,
                'product_id' => $attachment->product_id,
                'url' => $attachment->url,
                'metadata' => $attachment->metadata,
                'sort_order' => $attachment->sort_order,
            ]);
        }
    }

    private function cloneSubtasks(int $sourceBriefId, int $newBriefId): void
    {
        $subtasks = $this->subtaskRepository->getForBrief($sourceBriefId);

        foreach ($subtasks as $subtask) {
            $this->subtaskRepository->create([
                'brief_id' => $newBriefId,
                'title' => $subtask->title,
                'description' => $subtask->description,
                'status' => $subtask->status,
                'assigned_to' => $subtask->assigned_to,
                'due_date' => $subtask->due_date,
                'sort_order' => $subtask->sort_order,
            ]);
        }
    }

    private function cloneCollaborators(Brief $original, int $newBriefId, int $userId): void
    {
        foreach ($original->collaborators as $collaborator) {
            $this->collaboratorRepository->createForBrief($newBriefId, [
                'user_id' => $collaborator->user_id,
                'role' => $collaborator->role,
                'assigned_at' => now_datetime()->toDateTimeString(),
                'assigned_by' => $userId,
            ]);
        }
    }

    private function cloneComments(Brief $original, int $newBriefId): void
    {
        // Top-level comments first so we can map old IDs → new IDs for reply parenting.
        $idMap = [];

        $topLevel = $original->comments->filter(
            fn($c) => $c->parent_comment_id === null
        );

        foreach ($topLevel as $comment) {
            $newComment = $this->briefRepository->addComment($newBriefId, [
                'user_id' => $comment->user_id,
                'content' => $comment->content,
                'highlighted_text' => $comment->highlighted_text,
                'highlighted_range' => $comment->highlighted_range,
                'mentions' => $comment->mentions,
                'is_resolved' => false,
                'resolved_by' => null,
                'resolved_at' => null,
                'parent_comment_id' => null,
            ]);

            $idMap[$comment->id] = $newComment->id;
        }

        // Replies — re-parent to the newly created top-level comment.
        $replies = $original->comments->filter(
            fn($c) => $c->parent_comment_id !== null
        );

        foreach ($replies as $reply) {
            $newParentId = $idMap[$reply->parent_comment_id] ?? null;

            // Guard: skip orphaned replies whose parent was not in the loaded set.
            if ($newParentId === null) {
                continue;
            }

            $this->briefRepository->addComment($newBriefId, [
                'user_id' => $reply->user_id,
                'content' => $reply->content,
                'highlighted_text' => $reply->highlighted_text,
                'highlighted_range' => $reply->highlighted_range,
                'mentions' => $reply->mentions,
                'is_resolved' => false,
                'resolved_by' => null,
                'resolved_at' => null,
                'parent_comment_id' => $newParentId,
            ]);
        }
    }

    private function cloneRelationships(Brief $original, int $newBriefId): void
    {
        foreach ($original->relationships as $relationship) {
            $this->briefRepository->addRelationship($newBriefId, [
                'related_brief_id' => $relationship->related_brief_id,
                'related_page_id' => $relationship->related_page_id,
                'relationship_type' => $relationship->relationship_type,
                'sort_order' => $relationship->sort_order,
            ]);
        }
    }

    private function cloneDeadlines(Brief $original, int $newBriefId, int $userId): void
    {
        if (!$original->deadlines) {
            return;
        }

        foreach ($original->deadlines as $deadline) {
            $this->briefRepository->addDeadline($newBriefId, [
                'due_date' => $deadline->due_date->format('Y-m-d H:i:s'),
                'reminder_days' => $deadline->reminder_days,
                'notify_collaborators' => $deadline->notify_collaborators,
                'created_by' => $userId,
            ]);
        }
    }
}