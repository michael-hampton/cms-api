<?php

namespace App\Services\Cms;

use App\Actions\Brief\BulkAssignCollaborator;
use App\Actions\Brief\ConvertBriefToPage;
use App\Actions\Brief\CreateBriefVersion;
use App\Actions\Brief\DuplicateBrief;
use App\Actions\Brief\LogBriefActivity;
use App\Framework\Database\Database;
use App\Framework\Support\Collection;
use App\Models\Brief;
use App\Models\BriefDeadline;
use App\Models\BriefRelationship;
use App\Models\BriefWorkflowHistory;
use App\Models\Model;
use App\Repositories\Cms\Briefs\BriefCollaboratorRepository;
use App\Repositories\Cms\Briefs\BriefRepository;
use App\Repositories\Cms\Briefs\BriefTaskRepository;
use App\Repositories\Cms\Briefs\BriefTemplateRepository;
use App\Repositories\Cms\Briefs\BriefVersionRepository;
use App\Search\PaginatedResult;
use App\Search\SearchCriteria;
use Exception;

class BriefService
{
    public function __construct(
        private readonly BriefRepository             $briefRepository,
        private readonly BriefTemplateRepository     $templateRepository,
        private readonly BriefCollaboratorRepository $collaboratorRepository,
        private readonly BriefTaskRepository         $taskRepository,
        private readonly BriefVersionRepository      $versionRepository,
        private readonly CreateBriefVersion          $createBriefVersion,
        private readonly LogBriefActivity            $logBriefActivity,
        private readonly DuplicateBrief              $duplicateBrief,
        private readonly BulkAssignCollaborator      $bulkAssignCollaborator,
        private readonly ConvertBriefToPage          $convertBriefToPage,
        private readonly Database                    $database
    )
    {
    }

    public function search(SearchCriteria $criteria): PaginatedResult
    {
        return $this->briefRepository->search($criteria);
    }

    public function getCompleteBrief(int $id): ?Model
    {
        return $this->briefRepository->getCompleteBriefData($id);
    }

    public function createBrief(array $data): Model
    {
        $brief = $this->briefRepository->create($data);

        // Create initial version
        $this->createBriefVersion->handle($brief->id, $data['owner_id'], 'Initial version');

        // Log activity
        $this->logBriefActivity->handle($brief->id, $data['owner_id'], 'created', 'Brief created');

        return $brief;
    }

    public function updateBrief(int $id, array $data, ?int $userId = null): Model
    {
        $oldBrief = $this->briefRepository->find($id);

        if (!$oldBrief) {
            throw new Exception("Brief not found: {$id}");
        }

        $brief = $this->briefRepository->update($id, $data);

        // Create version if significant changes
        if ($this->shouldCreateVersion($oldBrief, $data) && $userId) {
            $changeSummary = $this->generateChangeSummary($oldBrief, $data);
            $this->createBriefVersion->handle($id, $userId, implode(', ', $changeSummary));
        }

        // Log activity
        if ($userId) {
            $this->logBriefActivity->handle($id, $userId, 'updated', 'Brief updated');
        }

        return $brief;
    }

    private function shouldCreateVersion(Model $oldBrief, array $data): bool
    {
        $fieldsToCheck = [
            'title', 'description', 'status', 'target_word_count',
            'target_publish_date', 'seo_keywords', 'target_audience', 'category_id'
        ];

        foreach ($fieldsToCheck as $field) {
            if (isset($data[$field]) && $data[$field] != $oldBrief->$field) {
                return true;
            }
        }

        return false;
    }

    private function generateChangeSummary(Model $oldBrief, array $data): array
    {
        $changes = [];
        $fieldsToCheck = [
            'title' => 'Title',
            'description' => 'Description',
            'status' => 'Status',
            'target_word_count' => 'Target word count',
            'target_publish_date' => 'Target publish date',
            'seo_keywords' => 'SEO keywords',
            'target_audience' => 'Target audience',
            'category_id' => 'Category'
        ];

        foreach ($fieldsToCheck as $field => $label) {
            if (isset($data[$field]) && $data[$field] != $oldBrief->$field) {
                $changes[] = "{$label} updated";
            }
        }

        return $changes;
    }

    public function deleteBrief(int $id): bool
    {
        return $this->briefRepository->delete($id);
    }

    public function addAttachment(int $briefId, array $data): Model
    {
        return $this->briefRepository->addAttachment($briefId, $data);
    }

    public function updateAttachment(int $briefId, int $attachmentId, array $data): ?Model
    {
        return $this->briefRepository->updateAttachment($briefId, $attachmentId, $data);
    }

    public function deleteAttachment(int $briefId, int $attachmentId): bool
    {
        return $this->briefRepository->deleteAttachment($briefId, $attachmentId);
    }

    public function addComment(int $briefId, array $data): Model
    {
        return $this->briefRepository->addComment($briefId, $data);
    }

    public function deleteComment(int $briefId, int $commentId): bool
    {
        return $this->briefRepository->deleteComment($briefId, $commentId);
    }

    public function resolveComment(int $briefId, int $commentId, int $userId): Model
    {
        $comment = $this->briefRepository->updateComment($briefId, $commentId, [
            'is_resolved' => true,
            'resolved_by' => $userId,
            'resolved_at' => date('Y-m-d H:i:s')
        ]);

        $this->logBriefActivity->handle($briefId, $userId, 'comment_resolved', 'Resolved a comment');

        return $comment;
    }

    public function updateComment(int $briefId, int $commentId, array $data): ?Model
    {
        return $this->briefRepository->updateComment($briefId, $commentId, $data);
    }

    public function unresolveComment(int $briefId, int $commentId): ?Model
    {
        return $this->briefRepository->updateComment($briefId, $commentId, [
            'is_resolved' => false,
            'resolved_by' => null,
            'resolved_at' => null
        ]);
    }

    public function convertToPage(int $briefId, array $conversionData): array
    {
        return $this->convertBriefToPage->handle($briefId, $conversionData);
    }

    public function archiveBrief(int $id): bool
    {
        return $this->briefRepository->archive($id);
    }

    public function duplicateBrief(int $briefId, int $userId): Brief
    {
        return $this->duplicateBrief->handle($briefId, $userId);
    }

    public function updateStatus(int $briefId, string $newStatus, int $userId): Model
    {
        // Create version before status change
        $this->createBriefVersion->handle($briefId, $userId, "Status changed to {$newStatus}");

        $brief = $this->briefRepository->update($briefId, [
            'status' => $newStatus,
            'last_activity_at' => date('Y-m-d H:i:s'),
            'last_activity_user_id' => $userId
        ]);

        $this->logBriefActivity->handle($briefId, $userId, 'status_changed', "Status changed to {$newStatus}");

        return $brief;
    }

    public function bulkUpdateStatus(array $briefIds, string $status): int
    {
        return $this->briefRepository->bulkUpdateStatus($briefIds, $status);
    }

    // Template methods

    public function bulkAssignCollaborator(array $briefIds, int $userId, string $role): int
    {
        return $this->bulkAssignCollaborator->handle($briefIds, $userId, $role);
    }

    public function bulkDelete(array $briefIds): void
    {
        $this->briefRepository->bulkDelete($briefIds);
    }

    public function getTemplatesForSite(int $siteId): array
    {
        return $this->templateRepository->getForSite($siteId);
    }

    // Collaborator methods

    public function createFromTemplate(int $templateId, array $data): Model
    {
        $template = $this->templateRepository->find($templateId);

        if (!$template) {
            throw new Exception("Template not found: {$templateId}");
        }

        // Apply template defaults
        if ($template->default_fields) {
            $data = array_merge($template->default_fields, $data);
        }

        $data['template_id'] = $templateId;

        $brief = $this->briefRepository->create($data);

        $this->logBriefActivity->handle(
            $brief->id,
            $data['owner_id'],
            'created_from_template',
            "Created from template: {$template->name}"
        );

        return $brief;
    }

    public function saveAsTemplate(int $briefId, array $templateData): Model
    {
        $brief = $this->briefRepository->getCompleteBriefData($briefId);

        if (!$brief) {
            throw new Exception("Brief not found: {$briefId}");
        }

        $templateData['default_fields'] = [
            'title' => $brief->title,
            'description' => $brief->description,
            'target_word_count' => $brief->target_word_count,
            'seo_keywords' => $brief->seo_keywords,
        ];
        $templateData['site_id'] = $brief->site_id;
        $templateData['is_system'] = false;

        return $this->templateRepository->create($templateData);
    }

    public function getCollaborators(int $briefId): array
    {
        return $this->collaboratorRepository->getForBrief($briefId);
    }

    public function addCollaborator(int $briefId, array $data, int $userId): Model
    {
        $data['brief_id'] = $briefId;
        $data['assigned_at'] = date('Y-m-d H:i:s');

        $collaborator = $this->collaboratorRepository->createForBrief($briefId, $data);

        $this->logBriefActivity->handle(
            $briefId,
            $userId,
            'collaborator_added',
            "Added collaborator: {$collaborator->user->name} as {$collaborator->role}"
        );

        return $collaborator;
    }

    // Task methods

    public function updateCollaborator(int $briefId, int $collaboratorId, array $data): Model
    {
        $collaborator = $this->collaboratorRepository->find($collaboratorId);

        if (!$collaborator || $collaborator->brief_id !== $briefId) {
            throw new Exception("Collaborator not found");
        }

        return $this->collaboratorRepository->update($collaboratorId, $data);
    }

    public function removeCollaborator(int $collaboratorId): bool
    {
        return $this->collaboratorRepository->delete($collaboratorId);
    }

    public function getTasks(int $briefId): array
    {
        return $this->taskRepository->getForBrief($briefId)->map(function ($task) {
            $data = $task->toArray();
            $data['due_date'] = $task->due_date?->format('Y-m-d H:i:s') ?? '';
            return $data;
        })->toArray();
    }

    public function createTask(int $briefId, array $data): Model
    {
        $data['brief_id'] = $briefId;
        $task = $this->taskRepository->create($data);

        $this->logBriefActivity->handle($briefId, $data['created_by'], 'task_created', "Created task: {$task->title}");

        return $task;
    }

    // Version methods

    public function updateTask(int $taskId, array $data): Model
    {
        if (isset($data['status']) && $data['status'] === 'completed') {
            $data['completed_at'] = date('Y-m-d H:i:s');
        }

        return $this->taskRepository->update($taskId, $data);
    }

    public function deleteTask(int $taskId): bool
    {
        return $this->taskRepository->delete($taskId);
    }

    // Activity log

    public function getVersions(int $briefId): array
    {
        return $this->versionRepository->getForBrief($briefId)->map(function ($version) {
            $data = $version->toArray();
            $data['created_at'] = $version->created_at?->format('Y-m-d H:i:s') ?? '';
            return $data;
        })->toArray();
    }

    // Relationships

    public function restoreVersion(int $briefId, int $versionId, int $userId): bool
    {
        $version = $this->versionRepository->find($versionId);

        if (!$version || $version->brief_id !== $briefId) {
            throw new Exception("Version not found");
        }

        // Create new version before restoring
        $this->createBriefVersion->handle($briefId, $userId, 'Before restore');

        $versionData = is_array($version->data) ? $version?->data : $version?->data->toArray();

        // Restore version data
        $this->briefRepository->update($briefId, [
            'title' => $version->title,
            'description' => $version->description,
            ...$versionData ?? []
        ]);

        $this->logBriefActivity->handle(
            $briefId,
            $userId,
            'version_restored',
            "Restored version {$version->version_number}"
        );

        return true;
    }

    public function getActivityLog(int $briefId): Collection
    {
        $brief = $this->briefRepository->getCompleteBriefData($briefId);
        return $brief->activityLog;
    }

    public function getRelationships(int $briefId): array
    {
        $brief = $this->briefRepository->getCompleteBriefData($briefId);
        return $brief->relationships(true)
            ->with(['relatedBrief', 'relatedPage', 'relationships'])
            ->get()
            ->toArray();
    }

    // Workflow

    public function addRelationship(int $briefId, array $data): Model
    {
        $data['brief_id'] = $briefId;
        return BriefRelationship::create($data);
    }

    public function removeRelationship(int $relationshipId): bool
    {
        return BriefRelationship::where('id', $relationshipId)->delete();
    }

    // Deadline

    public function addWorkflowChange(int $briefId, array $data): Model
    {
        $workflowData = [
            'brief_id' => $briefId,
            'status' => $data['status'],
            'changed_by' => $data['changed_by'],
            'changed_at' => date('Y-m-d H:i:s'),
            'notes' => $data['notes'] ?? null
        ];

        $workflow = BriefWorkflowHistory::create($workflowData);

        // Update brief status
        $this->briefRepository->update($briefId, [
            'status' => $data['status'],
            'last_activity_at' => date('Y-m-d H:i:s'),
            'last_activity_user_id' => $data['changed_by']
        ]);

        $this->logBriefActivity->handle(
            $briefId,
            $data['changed_by'],
            'workflow_changed',
            "Status changed to {$data['status']}"
        );

        return $workflow;
    }

    public function getWorkflowHistory(int $briefId): array
    {
        return BriefWorkflowHistory::where('brief_id', $briefId)
            ->orderBy('changed_at', 'desc')
            ->get()
            ->map(function ($item) {
                $data = $item->toArray();
                $data['changed_at'] = $item->changed_at?->format('Y-m-d H:i:s') ?? '';
                return $data;
            })
            ->toArray();
    }

    public function setDeadline(int $briefId, array $data): Model
    {
        $deadlineData = [
            'brief_id' => $briefId,
            'due_date' => $data['due_date'],
            'reminder_days' => json_encode($data['reminder_days'] ?? []),
            'notify_collaborators' => $data['notify_collaborators'] ?? false,
            'created_by' => $data['user_id']
        ];

        $existing = BriefDeadline::where('brief_id', $briefId)->first();

        if ($existing) {
            $deadline = $this->briefRepository->updateDeadline($existing->id, $deadlineData);
        } else {
            $deadline = BriefDeadline::create($deadlineData);
        }

        $this->logBriefActivity->handle(
            $briefId,
            $data['user_id'],
            'deadline_set',
            "Deadline set to {$data['due_date']}"
        );

        return $deadline;
    }

    // Private helper methods

    public function getDeadline(int $briefId): ?array
    {
        $deadline = BriefDeadline::where('brief_id', $briefId)->first();

        if (!$deadline) {
            return null;
        }

        $data = $deadline->toArray();
        $data['due_date'] = $deadline->due_date?->format('Y-m-d H:i:s') ?? '';

        return $data;
    }

    public function deleteDeadline(int $briefId): bool
    {
        return BriefDeadline::where('brief_id', $briefId)->delete();
    }
}