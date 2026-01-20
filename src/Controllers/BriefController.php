<?php

namespace App\Controllers;

use App\Actions\Brief\BulkAssignCollaborator;
use App\Actions\Brief\ConvertBriefToPage;
use App\Actions\Brief\CreateBriefVersion;
use App\Actions\Brief\DuplicateBrief;
use App\Actions\Brief\LogBriefActivity;
use App\Framework\Container;
use App\Framework\Exceptions\ValidationException;
use App\Framework\Http\JsonResponse;
use App\Framework\Http\Request;
use App\Framework\Support\SiteContext;
use App\Models\BriefDeadline;
use App\Models\BriefRelationship;
use App\Models\BriefWorkflowHistory;
use App\Repositories\Cms\Briefs\BriefCollaboratorRepository;
use App\Repositories\Cms\Briefs\BriefRepository;
use App\Repositories\Cms\Briefs\BriefTaskRepository;
use App\Repositories\Cms\Briefs\BriefTemplateRepository;
use App\Repositories\Cms\Briefs\BriefVersionRepository;
use App\Search\SearchCriteriaParser;
use Exception;

class BriefController extends Controller
{
    public function __construct(
        private readonly BriefRepository             $briefRepository,
        private readonly BriefTemplateRepository     $templateRepository,
        private readonly BriefCollaboratorRepository $collaboratorRepository,
        private readonly BriefTaskRepository         $taskRepository,
        private readonly BriefVersionRepository      $versionRepository
    )
    {
        parent::__construct();
    }

    public function index(Request $request, string $siteName): JsonResponse
    {
        try {
            $criteria = SearchCriteriaParser::fromRequest($request, $siteName);
            $result = $this->briefRepository->search($criteria);

            return $this->searchResponse($result);
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    public function show(int $id, string $siteName): JsonResponse
    {
        try {
            $brief = $this->briefRepository->getCompleteBriefData($id);

            if (!$brief) {
                return $this->errorResponse('Brief not found', 404);
            }

            return $this->resourceResponse(['data' => $brief->toArray()]);
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    public function store(Request $request, string $siteName): JsonResponse
    {
        try {
            $data = $request->all();
            $data['site_id'] = $request->get('site_id');

            $brief = $this->briefRepository->create($data);

            // Create initial version
            CreateBriefVersion::execute($brief->id, $data['owner_id'], 'Initial version');

            // Log activity
            LogBriefActivity::execute($brief->id, $data['owner_id'], 'created', 'Brief created');

            return $this->resourceResponse(['data' => $brief->toArray()], 201);
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    public function update(int $id, Request $request, string $siteName): JsonResponse
    {
        try {
            $data = $request->all();
            $userId = $request->get('user_id', $data['owner_id'] ?? null);

            // Get old brief for comparison
            $oldBrief = $this->briefRepository->find($id);
            if (!$oldBrief) {
                return $this->errorResponse('Brief not found', 404);
            }

            // Update brief
            $brief = $this->briefRepository->update($id, $data);

            // Create version if significant changes
            $shouldVersion = false;
            $changeSummary = [];

            // Check all editable fields
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
                    $shouldVersion = true;
                    $changeSummary[] = "{$label} updated";
                }
            }

            if ($shouldVersion && $userId) {
                CreateBriefVersion::execute(
                    $id,
                    $userId,
                    implode(', ', $changeSummary)
                );
            }

            // Log activity
            if ($userId) {
                LogBriefActivity::execute($id, $userId, 'updated', 'Brief updated');
            }

            return $this->resourceResponse(['data' => $brief->toArray()]);
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    public function destroy(int $id, string $siteName): JsonResponse
    {
        try {
            $result = $this->briefRepository->delete($id);

            if (!$result) {
                return $this->errorResponse('Brief not found', 404);
            }

            return $this->successResponse('Brief deleted successfully');
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    public function addAttachment(int $id, Request $request, string $siteName): JsonResponse
    {
        try {
            $data = $request->all();
            $attachment = $this->briefRepository->addAttachment($id, $data);

            return $this->resourceResponse(['data' => $attachment->toArray()], 201);
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    public function deleteAttachment(int $id, int $attachmentId, string $siteName): JsonResponse
    {
        try {
            $result = $this->briefRepository->deleteAttachment($id, $attachmentId);

            if (!$result) {
                return $this->errorResponse('Attachment not found', 404);
            }

            return $this->successResponse('Attachment deleted successfully');
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    public function addComment(int $id, Request $request, string $siteName): JsonResponse
    {
        try {
            $data = $request->all();
            $data['user_id'] = $request->get('user_id'); // Should come from auth

            $comment = $this->briefRepository->addComment($id, $data);

            return $this->resourceResponse(['data' => $comment->toArray()], 201);
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    public function deleteComment(int $id, int $commentId, string $siteName): JsonResponse
    {
        try {
            $result = $this->briefRepository->deleteComment($id, $commentId);

            if (!$result) {
                return $this->errorResponse('Comment not found', 404);
            }

            return $this->successResponse('Comment deleted successfully');
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    public function convertToPage(int $id, Request $request, string $siteName): JsonResponse
    {
        /** @var ConvertBriefToPage $handler */
        $handler = Container::getInstance()->make(ConvertBriefToPage::class);

        try {
            $data = $request->all();

            $result = $handler->handle($id, $data);

            return $this->resourceResponse(['data' => $result]);
        } catch (ValidationException $e) {
            return $this->errorResponse(
                'Validation failed',
                422,
                $e->getErrors()
            );
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    public function archive(int $id, string $siteName): JsonResponse
    {
        try {
            $result = $this->briefRepository->archive($id);

            if (!$result) {
                return $this->errorResponse('Brief not found', 404);
            }

            return $this->successResponse('Brief archived successfully');
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    public function updateComment(int $id, int $commentId, Request $request, string $siteName): JsonResponse
    {
        try {
            $data = $request->all();
            $comment = $this->briefRepository->updateComment($id, $commentId, ['content' => $data['content'] ?? '']);

            if (!$comment) {
                return $this->errorResponse('Comment not found', 404);
            }

            return $this->resourceResponse(['data' => $comment->toArray()]);
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    public function updateAttachment(int $id, int $attachmentId, Request $request, string $siteName): JsonResponse
    {
        try {
            $data = $request->all();
            $attachment = $this->briefRepository->updateAttachment($id, $attachmentId, $data);

            if (!$attachment) {
                return $this->errorResponse('Attachment not found', 404);
            }

            return $this->resourceResponse(['data' => $attachment->toArray()]);
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    // Templates
    public function getTemplates(Request $request, string $siteName): JsonResponse
    {
        try {
            $siteId = SiteContext::getId();
            $templates = $this->templateRepository->getForSite($siteId);

            return $this->resourceResponse(['items' => $templates]);
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    public function createFromTemplate(int $templateId, Request $request, string $siteName): JsonResponse
    {
        try {
            $template = $this->templateRepository->find($templateId);
            if (!$template) {
                return $this->errorResponse('Template not found', 404);
            }

            $data = $request->all();
            $data['template_id'] = $templateId;
            $data['site_id'] = SiteContext::getId();

            // Apply template defaults
            if ($template->default_fields) {
                $data = array_merge($template->default_fields, $data);
            }

            $brief = $this->briefRepository->create($data);

            // Log activity
            LogBriefActivity::execute($brief->id, $data['owner_id'], 'created_from_template',
                "Created from template: {$template->name}");

            return $this->resourceResponse(['data' => $brief->toArray()], 201);
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    public function saveAsTemplate(int $id, Request $request, string $siteName): JsonResponse
    {
        try {
            $brief = $this->briefRepository->getCompleteBriefData($id);
            if (!$brief) {
                return $this->errorResponse('Brief not found', 404);
            }

            $templateData = [
                'site_id' => $brief->site_id,
                'name' => $request->get('name'),
                'description' => $request->get('description'),
                'type' => $request->get('type', 'custom'),
                'is_system' => false,
                'default_fields' => [
                    'title' => $brief->title,
                    'description' => $brief->description,
                    'target_word_count' => $brief->target_word_count,
                    'seo_keywords' => $brief->seo_keywords,
                ],
                'created_by' => $request->get('user_id')
            ];

            $template = $this->templateRepository->create($templateData);

            return $this->resourceResponse(['data' => $template->toArray()], 201);
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    // Collaboration
    public function getCollaborators(int $id, string $siteName): JsonResponse
    {
        try {
            $collaborators = $this->collaboratorRepository->getForBrief($id);
            return $this->resourceResponse(['items' => $collaborators]);
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    public function addCollaborator(int $id, Request $request, string $siteName): JsonResponse
    {
        try {
            $data = $request->all();
            $data['brief_id'] = $id;
            $data['assigned_at'] = date('Y-m-d H:i:s');

            $collaborator = $this->collaboratorRepository->create($data);

            LogBriefActivity::execute($id, $request->get('user_id'), 'collaborator_added',
                "Added collaborator: {$collaborator->user->name} as {$collaborator->role}");

            return $this->resourceResponse(['data' => $collaborator->toArray()], 201);
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    public function removeCollaborator(int $id, int $collaboratorId, string $siteName): JsonResponse
    {
        try {
            $result = $this->collaboratorRepository->delete($collaboratorId);
            if (!$result) {
                return $this->errorResponse('Collaborator not found', 404);
            }

            return $this->successResponse('Collaborator removed');
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    // Tasks
    public function getTasks(int $id, string $siteName): JsonResponse
    {
        try {
            $tasks = $this->taskRepository->getForBrief($id);

            $tasks = $tasks->map(function ($task) {
                $data = $task->toArray();
                $data['due_date'] = $task->due_date?->format('Y-m-d H:i:s') ?? '';

                return $data;
            });

            return $this->resourceResponse(['items' => $tasks]);
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    public function createTask(int $id, Request $request, string $siteName): JsonResponse
    {
        try {
            $data = $request->all();
            $data['brief_id'] = $id;

            $task = $this->taskRepository->create($data);

            LogBriefActivity::execute($id, $data['created_by'], 'task_created',
                "Created task: {$task->title}");

            $data = $task->toArray();
            $data['due_date'] = $task->due_date?->format('Y-m-d') ?? '';

            return $this->resourceResponse(['data' => $data], 201);
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    public function updateTask(int $id, int $taskId, Request $request, string $siteName): JsonResponse
    {
        try {
            $data = $request->all();

            if (isset($data['status']) && $data['status'] === 'completed') {
                $data['completed_at'] = date('Y-m-d H:i:s');
            }

            $task = $this->taskRepository->update($taskId, $data);

            return $this->resourceResponse(['data' => $task->toArray()]);
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    public function deleteTask(int $id, int $taskId, string $siteName): JsonResponse
    {
        try {
            $result = $this->taskRepository->delete($taskId);
            if (!$result) {
                return $this->errorResponse('Task not found', 404);
            }
            return $this->successResponse('Task deleted successfully');
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    // Versions
    public function getVersions(int $id, string $siteName): JsonResponse
    {
        try {

            $versions = $this->versionRepository->getForBrief($id)->map(function ($task) {
                $data = $task->toArray();
                $data['created_at'] = $task->created_at?->format('Y-m-d H:i:s') ?? '';

                return $data;
            });
            return $this->resourceResponse(['items' => $versions]);
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    public function restoreVersion(int $id, int $versionId, Request $request, string $siteName): JsonResponse
    {
        try {
            $version = $this->versionRepository->find($versionId);
            if (!$version || $version->brief_id !== $id) {
                return $this->errorResponse('Version not found', 404);
            }

            // Create new version before restoring
            CreateBriefVersion::execute($id, $request->get('user_id'), 'Before restore');

            // Restore version data
            $this->briefRepository->update($id, [
                'title' => $version->title,
                'description' => $version->description,
                ...$version->data?->toArray() ?? []
            ]);

            LogBriefActivity::execute($id, $request->get('user_id'), 'version_restored',
                "Restored version {$version->version_number}");

            return $this->successResponse('Version restored');
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    // Status Management
    public function updateStatus(int $id, Request $request, string $siteName): JsonResponse
    {
        try {
            $newStatus = $request->get('status');
            $userId = $request->get('user_id');

            // Create version before status change
            CreateBriefVersion::execute($id, $userId, "Status changed to {$newStatus}");

            $brief = $this->briefRepository->update($id, [
                'status' => $newStatus,
                'last_activity_at' => date('Y-m-d H:i:s'),
                'last_activity_user_id' => $userId
            ]);

            LogBriefActivity::execute($id, $userId, 'status_changed',
                "Status changed to {$newStatus}");

            return $this->resourceResponse(['data' => $brief->toArray()]);
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    // Duplicate
    public function duplicate(int $id, Request $request, string $siteName): JsonResponse
    {
        try {
            $newBrief = DuplicateBrief::execute($id, $request->get('user_id'));

            return $this->resourceResponse(['data' => $newBrief->toArray()], 201);
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    // Activity Log
    public function getActivityLog(int $id, string $siteName): JsonResponse
    {
        try {
            $brief = $this->briefRepository->getCompleteBriefData($id);
            $activities = $brief->activityLog;

            return $this->resourceResponse(['items' => $activities]);
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    // Resolve Comment
    public function resolveComment(int $id, int $commentId, Request $request, string $siteName): JsonResponse
    {
        try {
            $userId = $request->get('user_id');

            $comment = $this->briefRepository->updateComment($id, $commentId, [
                'is_resolved' => true,
                'resolved_by' => $userId,
                'resolved_at' => date('Y-m-d H:i:s')
            ]);

            LogBriefActivity::execute($id, $userId, 'comment_resolved',
                'Resolved a comment');

            return $this->resourceResponse(['data' => $comment->toArray()]);
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    // Relationships
    public function getRelationships(int $id, string $siteName): JsonResponse
    {
        try {
            $brief = $this->briefRepository->getCompleteBriefData($id);
            $relationships = $brief->relationships(true)->with(['relatedBrief', 'relatedPage', 'relationships'])->get();

            return $this->resourceResponse(['items' => $relationships]);
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    public function addRelationship(int $id, Request $request, string $siteName): JsonResponse
    {
        try {
            $data = $request->all();
            $data['brief_id'] = $id;

            $relationship = BriefRelationship::create($data);

            return $this->resourceResponse(['data' => $relationship->toArray()], 201);
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    public function removeRelationship(int $id, int $relationshipId, string $siteName): JsonResponse
    {
        try {
            $result = BriefRelationship::where('id', $relationshipId)->delete();
            if (!$result) {
                return $this->errorResponse('Relationship not found', 404);
            }
            return $this->successResponse('Relationship removed');
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }

    }

    public function unresolveComment(int $id, int $commentId, Request $request, string $siteName): JsonResponse
    {
        try {
            $comment = $this->briefRepository->updateComment($id, $commentId, [
                'is_resolved' => false,
                'resolved_by' => null,
                'resolved_at' => null
            ]);

            if (!$comment) {
                return $this->errorResponse('Comment not found', 404);
            }

            return $this->resourceResponse(['data' => $comment->toArray()]);
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    public function bulkUpdateStatus(Request $request, string $siteName): JsonResponse
    {
        try {
            $briefIds = $request->get('brief_ids', []);
            $status = $request->get('status');

            if (empty($briefIds)) {
                return $this->errorResponse('No briefs selected', 400);
            }

            if (!in_array($status, ['draft', 'in_review', 'ready', 'converted', 'archived'])) {
                return $this->errorResponse('Invalid status', 400);
            }

            $count = $this->briefRepository->bulkUpdateStatus($briefIds, $status);

            return $this->successResponse("Updated {$count} briefs to {$status}");
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    public function bulkAssign(Request $request, string $siteName): JsonResponse
    {
        try {
            $briefIds = $request->get('brief_ids', []);
            $userId = $request->get('user_id');
            $role = $request->get('role', 'writer');

            if (empty($briefIds)) {
                return $this->errorResponse('No briefs selected', 400);
            }

            if (!$userId) {
                return $this->errorResponse('User ID is required', 400);
            }

            if (!in_array($role, ['writer', 'editor', 'reviewer', 'fact_checker'])) {
                return $this->errorResponse('Invalid role', 400);
            }

            $count = BulkAssignCollaborator::execute($briefIds, $userId, $role);

            return $this->successResponse("Assigned to {$count} briefs");
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    public function bulkDelete(Request $request, string $siteName): JsonResponse
    {
        try {
            $briefIds = $request->get('brief_ids', []);

            if (empty($briefIds)) {
                return $this->errorResponse('No briefs selected', 400);
            }

            $this->briefRepository->bulkDelete($briefIds);

            $count = count($briefIds);
            return $this->successResponse("Deleted {$count} briefs");
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    public function updateCollaborator(int $id, int $collaboratorId, Request $request, string $siteName): JsonResponse
    {
        try {
            $collaborator = $this->collaboratorRepository->find($collaboratorId);
            if (!$collaborator || $collaborator->brief_id !== $id) {
                return $this->errorResponse('Collaborator not found', 404);
            }

            $data = $request->all();
            $updated = $this->collaboratorRepository->update($collaboratorId, $data);

            return $this->resourceResponse(['data' => $updated->toArray()]);
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    public function addWorkflowChange(int $id, Request $request, string $siteName): JsonResponse
    {
        try {
            $data = $request->all();

            $workflowData = [
                'brief_id' => $id,
                'status' => $data['status'],
                'changed_by' => $data['changed_by'],
                'changed_at' => date('Y-m-d H:i:s'),
                'notes' => $data['notes'] ?? null
            ];

            // Store in brief_workflow_history table
            $workflow = BriefWorkflowHistory::create($workflowData);

            // Also update the brief status
            $this->briefRepository->update($id, [
                'status' => $data['status'],
                'last_activity_at' => date('Y-m-d H:i:s'),
                'last_activity_user_id' => $data['changed_by']
            ]);

            LogBriefActivity::execute($id, $data['changed_by'], 'workflow_changed',
                "Status changed to {$data['status']}");

            return $this->resourceResponse(['data' => $workflow->toArray()], 201);
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    public function getWorkflowHistory(int $id, string $siteName): JsonResponse
    {
        try {
            $history = BriefWorkflowHistory::where('brief_id', $id)
                ->orderBy('changed_at', 'desc')
                ->get();

            $history = $history->map(function ($item) {
                $data = $item->toArray();
                $data['changed_at'] = $item->changed_at?->format('Y-m-d H:i:s') ?? '';
                return $data;
            });

            return $this->resourceResponse(['items' => $history->toArray()]);
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    public function setDeadline(int $id, Request $request, string $siteName): JsonResponse
    {
        try {
            $data = $request->all();

            $deadlineData = [
                'brief_id' => $id,
                'due_date' => $data['due_date'],
                'reminder_days' => json_encode($data['reminder_days'] ?? []),
                'notify_collaborators' => $data['notify_collaborators'] ?? false,
                'created_by' => $data['user_id']
            ];

            // Check if deadline exists
            $existing = BriefDeadline::where('brief_id', $id)->first();

            if ($existing) {
                $deadline = $this->briefRepository->updateDeadline($existing->id, $deadlineData);
            } else {
                $deadline = BriefDeadline::create($deadlineData);
            }

            LogBriefActivity::execute($id, $data['user_id'], 'deadline_set',
                "Deadline set to {$data['due_date']}");

            $deadline = $deadline->toArray();
            $deadline['due_date'] = $deadline['due_date']?->format('Y-m-d H:i:s') ?? '';

            return $this->resourceResponse(['data' => $deadline]);
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    public function getDeadline(int $id, string $siteName): JsonResponse
    {
        try {
            $deadline = BriefDeadline::where('brief_id', $id)->first();

            if (!$deadline) {
                return $this->resourceResponse(['data' => null]);
            }

            $deadline = $deadline->toArray();
            $deadline['due_date'] = $deadline['due_date']?->format('Y-m-d H:i:s') ?? '';

            return $this->resourceResponse(['data' => $deadline]);
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    public function deleteDeadline(int $id, string $siteName): JsonResponse
    {
        try {
            $result = BriefDeadline::where('brief_id', $id)->delete();

            if (!$result) {
                return $this->errorResponse('Deadline not found', 404);
            }

            return $this->successResponse('Deadline removed');
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    public function uploadAttachment(int $id, Request $request, string $siteName): JsonResponse
    {
        try {
            $file = $request->file('file');

            if (!$file) {
                return $this->errorResponse('No file provided', 400);
            }

            // Use FileUpload class
            $fileUpload = new \App\Framework\FileUpload\FileUpload(
                $file,
                'uploads/briefs/' . $id
            );

            // Set allowed extensions for documents
            $fileUpload->setAllowedExtensions([
                'pdf', 'doc', 'docx', 'xls', 'xlsx',
                'ppt', 'pptx', 'txt', 'csv'
            ]);

            // Set max size to 10MB
            $fileUpload->setMaxSize(10 * 1024 * 1024);

            // Store the file
            $filePath = $fileUpload->store();

            // Create attachment record
            $attachmentData = [
                'type' => 'document',
                'file_url' => $filePath,
                'file_name' => $file->getClientOriginalName(),
                'filesize' => $file->getSize(),
                'metadata' => [
                    'description' => $request->get('description', ''),
                    'mime_type' => $file->getMimeType()
                ],
                'sort_order' => 0
            ];

            $attachment = $this->briefRepository->addAttachment($id, $attachmentData);

            return $this->resourceResponse(['data' => $attachment->toArray()], 201);
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }
}