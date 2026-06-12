<?php

namespace App\Services\OpenCollab;

use App\Framework\Authorization\Auth;
use App\Models\Brief;
use App\Models\BriefActivityLog;
use App\Models\BriefAttachment;
use App\Models\BriefComment;
use App\Models\BriefTask;
use App\Models\Collaborator;

class OpenCollabBriefPresenter
{
    public function __construct(
        private readonly OpenCollabBriefStatusMapper $statusMapper,
        private readonly OpenCollabBriefActionAvailabilityService $actions,
    )
    {
    }

    public function workspace(Brief $brief, ?Collaborator $assignment): array
    {
        return [
            'brief' => $this->brief($brief, $assignment),
            'tasks' => $brief->tasks?->map(fn($task) => $this->task($task))->toArray() ?? [],
            'attachments' => $brief->attachments?->map(fn($attachment) => $this->attachment($attachment))->toArray() ?? [],
            'comments' => $brief->comments?->map(fn($comment) => $this->comment($comment))->toArray() ?? [],
            'timeline' => $this->timeline($brief, $assignment)['events'],
            'available_actions' => $this->actions->availableActions($brief, $assignment),
            'assignment' => $this->assignment($assignment),
        ];
    }

    public function brief(Brief $brief, ?Collaborator $assignment): array
    {
        $deadline = $this->deadline($brief);
        $assignmentStatus = $this->statusMapper->assignmentStatus($assignment);
        $workflowStatus = $this->statusMapper->workflowStatus($brief);

        return [
            'id' => (int)$brief->id,
            'title' => (string)$brief->title,
            'description' => (string)($brief->description ?? ''),
            'requirements' => (string)($brief->description ?? ''),
            'target_audience' => $brief->target_audience,
            'seo_guidance' => $brief->seo_keywords,
            'target_word_count' => $brief->target_word_count,
            'reference_links' => [],
            'site' => (string)($brief->site?->name ?? $brief->site?->slug ?? ''),
            'assignment_status' => $assignmentStatus,
            'assignment_status_label' => $this->statusMapper->label($assignmentStatus),
            'workflow_status' => $workflowStatus,
            'workflow_status_label' => $this->statusMapper->label($workflowStatus),
            'deadline_at' => $deadline,
            'deadline_status' => $this->statusMapper->deadlineStatus($deadline, $workflowStatus),
            'is_overdue' => $this->statusMapper->deadlineStatus($deadline, $workflowStatus) === 'overdue',
            'last_updated_at' => $this->dateTime($brief->last_activity_at ?? $brief->updated_at),
        ];
    }

    public function assignment(?Collaborator $assignment): array
    {
        return [
            'id' => $assignment ? (int)$assignment->id : null,
            'status' => $this->statusMapper->assignmentStatus($assignment),
            'role' => $assignment?->role,
            'assigned_at' => $this->dateTime($assignment?->assigned_at),
        ];
    }

    public function timeline(Brief $brief, ?Collaborator $assignment): array
    {
        $briefData = $this->brief($brief, $assignment);
        $events = $brief->activityLog
            ?->map(fn($event) => $this->timelineEvent($event))
            ->toArray() ?? [];

        return [
            'current' => [
                'assignment_status' => $briefData['assignment_status'],
                'workflow_status' => $briefData['workflow_status'],
                'deadline_status' => $briefData['deadline_status'],
                'last_activity_at' => $briefData['last_updated_at'],
            ],
            'events' => $events,
        ];
    }

    public function task(BriefTask $task): array
    {
        return [
            'id' => (int)$task->id,
            'title' => (string)$task->title,
            'description' => (string)($task->description ?? ''),
            'status' => (string)$task->status,
            'assigned_user' => $this->relationName($task->assignee),
            'due_date' => $this->dateTime($task->due_date),
            'completed_at' => $this->dateTime($task->completed_at),
        ];
    }

    public function attachment(BriefAttachment $attachment): array
    {
        $metadata = is_array($attachment->metadata) ? $attachment->metadata : [];

        return [
            'id' => (int)$attachment->id,
            'filename' => (string)($attachment->file_name ?? basename((string)$attachment->file_url)),
            'type' => (string)$attachment->type,
            'size' => $attachment->filesize ? (int)$attachment->filesize : null,
            'uploaded_by' => $metadata['uploaded_by_name'] ?? null,
            'uploaded_by_id' => $metadata['uploaded_by'] ?? null,
            'uploaded_date' => $this->dateTime($attachment->created_at),
            'description' => $metadata['description'] ?? '',
            'url' => $attachment->file_url ?: $attachment->url,
            'can_delete' => isset($metadata['uploaded_by']) && (int)$metadata['uploaded_by'] === (int)Auth::id(),
        ];
    }

    public function comment(BriefComment $comment): array
    {
        return [
            'id' => (int)$comment->id,
            'author' => $this->relationName($comment->user) ?? 'Contributor',
            'author_id' => $comment->user_id,
            'date' => $this->dateTime($comment->created_at),
            'content' => (string)$comment->content,
            'is_resolved' => (bool)$comment->is_resolved,
            'replies' => $comment->replies?->map(fn($reply) => $this->comment($reply))->toArray() ?? [],
        ];
    }

    public function timelineEvent(BriefActivityLog $event): array
    {
        return [
            'type' => (string)$event->action,
            'label' => $this->statusMapper->label((string)$event->action),
            'message' => (string)$event->description,
            'created_at' => $this->dateTime($event->created_at),
            'actor' => $this->relationName($event->user) ? 'Contributor' : null,
        ];
    }

    public function deadline(Brief $brief): ?string
    {
        $deadline = $brief->deadlines?->first(fn($item) => !empty($item->due_date));

        return $this->dateTime($deadline?->due_date);
    }

    private function dateTime($value): ?string
    {
        if ($value instanceof \DateTimeInterface) {
            return $value->format(DATE_ATOM);
        }

        if (is_string($value) && $value !== '') {
            return (new \DateTimeImmutable($value))->format(DATE_ATOM);
        }

        return null;
    }

    private function relationName($relation): ?string
    {
        if (is_array($relation)) {
            return $relation['name'] ?? null;
        }

        return $relation?->name;
    }
}
