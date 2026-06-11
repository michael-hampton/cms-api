<?php

namespace App\Controllers\Cms;

use App\Controllers\Controller;
use App\DTO\Briefs\DuplicateBriefOptions;
use App\Framework\Authorization\Auth;
use App\Framework\Exceptions\UnauthorizedException;
use App\Framework\Exceptions\ValidationException;
use App\Framework\Http\JsonResponse;
use App\Framework\Http\Request;
use App\Framework\Resource\PaginatedResourceCollection;
use App\Framework\Support\SiteContext;
use App\Models\BriefComment;
use App\Repositories\Cms\Briefs\BriefTaskRepository;
use App\Requests\Briefs\AddBriefAttachmentRequest;
use App\Requests\Briefs\AddBriefCollaboratorRequest;
use App\Requests\Briefs\AddBriefCommentRequest;
use App\Requests\Briefs\AddBriefRelationshipRequest;
use App\Requests\Briefs\AddBriefWorkflowChangeRequest;
use App\Requests\Briefs\ConvertBriefToArticleRequest;
use App\Requests\Briefs\CreateBriefTaskRequest;
use App\Requests\Briefs\SetBriefDeadlineRequest;
use App\Requests\Briefs\StoreBriefRequest;
use App\Requests\Briefs\UpdateBriefCommentRequest;
use App\Requests\Briefs\UpdateBriefRequest;
use App\Requests\Briefs\UpdateBriefTaskRequest;
use App\Resources\BriefResource;
use App\Search\SearchCriteriaParser;
use App\Services\Cms\BriefService;
use App\Services\Cms\ContentWorkflowAuthorizationService;
use Exception;

class BriefController extends Controller
{
    public function __construct(
        private readonly BriefService $briefService,
        private readonly BriefTaskRepository $taskRepository,
        private readonly ContentWorkflowAuthorizationService $workflowAuthorization,
    )
    {
        parent::__construct();
    }

    public function index(Request $request, string $site): JsonResponse
    {
        try {
            $criteria = SearchCriteriaParser::fromRequest($request, $site);
            $result = $this->briefService->search($criteria);

            $collection = new PaginatedResourceCollection($result, BriefResource::class);
            return $this->resourceResponse($collection->toArray(), 200);
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    public function show(int $id, string $site): JsonResponse
    {
        try {
            $brief = $this->briefService->getCompleteBrief($id);

            if (!$brief) {
                return $this->errorResponse('Brief not found', 404);
            }

            return $this->resourceResponse([
                'data' => BriefResource::make($brief)->toArray(),
            ]);
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    public function store(StoreBriefRequest $request, string $site): JsonResponse
    {
        try {
            $data = $request->all();
            $data['site_id'] = $request->get('site_id');

            $brief = $this->briefService->createBrief($data);

            return $this->resourceResponse([
                'data' => BriefResource::make($brief)->toArray(),
            ], 201);
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    public function update(int $id, UpdateBriefRequest $request, string $site): JsonResponse
    {
        try {
            $data = $request->all();
            $userId = (int) ($request->get('user_id', $data['owner_id'] ?? null) ?: Auth::id());

            $brief = $this->briefService->getCompleteBrief($id);

            if (!$brief) {
                return $this->errorResponse('Brief not found', 404);
            }

            $this->workflowAuthorization->assertCanEdit(
                $userId,
                SiteContext::getId(),
                $brief->owner_id !== null ? (int) $brief->owner_id : null,
                'briefs',
            );

            $brief = $this->briefService->updateBrief($id, $data, $userId);

            return $this->resourceResponse([
                'data' => BriefResource::make($brief)->toArray(),
            ]);
        } catch (UnauthorizedException $e) {
            return $this->errorResponse($e->getMessage(), 403);
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    public function destroy(int $id, string $site): JsonResponse
    {
        try {
            $userId = Auth::id();

            if (!$userId) {
                return $this->errorResponse('Unauthenticated.', 401);
            }

            $brief = $this->briefService->getCompleteBrief($id);

            if (!$brief) {
                return $this->errorResponse('Brief not found', 404);
            }

            $this->workflowAuthorization->assertCanDelete(
                (int) $userId,
                SiteContext::getId(),
                $brief->owner_id !== null ? (int) $brief->owner_id : null,
                'briefs',
            );

            $result = $this->briefService->deleteBrief($id);

            if (!$result) {
                return $this->errorResponse('Brief not found', 404);
            }

            return $this->successResponse('Brief deleted successfully');
        } catch (UnauthorizedException $e) {
            return $this->errorResponse($e->getMessage(), 403);
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    public function addAttachment(int $id, AddBriefAttachmentRequest $request, string $site): JsonResponse
    {
        try {
            $attachment = $this->briefService->addAttachment($id, $request->all());

            return $this->resourceResponse(['data' => $attachment->toArray()], 201);
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    public function updateAttachment(int $id, int $attachmentId, Request $request, string $site): JsonResponse
    {
        try {
            $attachment = $this->briefService->updateAttachment($id, $attachmentId, $request->all());

            if (!$attachment) {
                return $this->errorResponse('Attachment not found', 404);
            }

            return $this->resourceResponse(['data' => $attachment->toArray()]);
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    public function deleteAttachment(int $id, int $attachmentId, string $site): JsonResponse
    {
        try {
            $result = $this->briefService->deleteAttachment($id, $attachmentId);

            if (!$result) {
                return $this->errorResponse('Attachment not found', 404);
            }

            return $this->successResponse('Attachment deleted successfully');
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    public function addComment(int $id, AddBriefCommentRequest $request, string $site): JsonResponse
    {
        try {
            $data = $request->all();
            $data['user_id'] = $request->get('user_id') ?? auth()->id();

            $comment = $this->briefService->addComment($id, $data);

            return $this->resourceResponse(['data' => $comment->toArray()], 201);
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    public function updateComment(int $id, int $commentId, UpdateBriefCommentRequest $request, string $site): JsonResponse
    {
        try {
            $comment = $this->briefService->updateComment($id, $commentId, [
                'content' => $request->all()['content'] ?? '',
            ]);

            if (!$comment) {
                return $this->errorResponse('Comment not found', 404);
            }

            return $this->resourceResponse(['data' => $comment->toArray()]);
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    public function deleteComment(int $id, int $commentId, string $site): JsonResponse
    {
        try {
            $userId = Auth::id();

            if (!$userId) {
                return $this->errorResponse('Unauthenticated.', 401);
            }

            // BriefService::findComment(briefId, commentId) must return the comment model or null.
            $comment = BriefComment::find($commentId);

            if (!$comment) {
                return $this->errorResponse('Comment not found', 404);
            }

            $this->workflowAuthorization->assertCanDeleteComment(
                (int) $userId,
                SiteContext::getId(),
                $comment->user_id !== null ? (int) $comment->user_id : null,
                'briefs',
            );

            $result = $this->briefService->deleteComment($id, $commentId);

            if (!$result) {
                return $this->errorResponse('Comment not found', 404);
            }

            return $this->successResponse('Comment deleted successfully');
        } catch (UnauthorizedException $e) {
            return $this->errorResponse($e->getMessage(), 403);
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    public function resolveComment(int $id, int $commentId, Request $request, string $site): JsonResponse
    {
        try {
            $comment = $this->briefService->resolveComment($id, $commentId, $request->get('user_id'));

            return $this->resourceResponse(['data' => $comment->toArray()]);
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    public function unresolveComment(int $id, int $commentId, Request $request, string $site): JsonResponse
    {
        try {
            $comment = $this->briefService->unresolveComment($id, $commentId);

            if (!$comment) {
                return $this->errorResponse('Comment not found', 404);
            }

            return $this->resourceResponse(['data' => $comment->toArray()]);
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    public function convertToPage(int $id, Request $request, string $site): JsonResponse
    {
        try {
            $result = $this->briefService->convertToPage($id, $request->all());

            return $this->resourceResponse(['data' => $result]);
        } catch (ValidationException $e) {
            return $this->errorResponse('Validation failed', 422, $e->getErrors());
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    public function archive(int $id, string $site): JsonResponse
    {
        try {
            $result = $this->briefService->archiveBrief($id);

            if (!$result) {
                return $this->errorResponse('Brief not found', 404);
            }

            return $this->successResponse('Brief archived successfully');
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    public function duplicate(int $id, Request $request, string $site): JsonResponse
    {
        try {
            $newBrief = $this->briefService->duplicateBrief($id, $request->get('user_id'));

            return $this->resourceResponse([
                'data' => BriefResource::make($newBrief)->toArray(),
            ], 201);
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    public function updateStatus(int $id, Request $request, string $site): JsonResponse
    {
        try {
            $userId = (int) $request->get('user_id');
            $status = (string) $request->get('status');

            if (!$userId) {
                return $this->errorResponse('User ID is required', 422);
            }

            $this->authorizeBriefStatusChange($id, $status, $userId);

            $brief = $this->briefService->getCompleteBrief($id);

            if (!$brief) {
                return $this->errorResponse('Brief not found', 404);
            }

            if (!$brief->canTransitionTo($status)) {
                return $this->errorResponse(
                    "Cannot change brief status from {$brief->status} to {$status}",
                    422,
                );
            }

            $brief = $this->briefService->updateStatus($id, $request->get('status'), $request->get('user_id'));

            return $this->resourceResponse([
                'data' => BriefResource::make($brief)->toArray(),
            ]);
        } catch (UnauthorizedException $e) {
            return $this->errorResponse($e->getMessage(), 403);
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    public function bulkUpdateStatus(Request $request, string $site): JsonResponse
    {
        try {
            $briefIds = $request->get('brief_ids', []);
            $status = $request->get('status');

            if (empty($briefIds)) {
                return $this->errorResponse('No briefs selected', 400);
            }

            if (!in_array($status, ['draft', 'in_review', 'ready', 'on_hold', 'converted', 'archived'])) {
                return $this->errorResponse('Invalid status', 400);
            }

            $userId = (int) $request->get('user_id');
            if (!$userId) {
                return $this->errorResponse('User ID is required', 422);
            }

            foreach ($briefIds as $briefId) {
                $this->authorizeBriefStatusChange((int) $briefId, $status, $userId);

                $brief = $this->briefService->getCompleteBrief((int) $briefId);

                if (!$brief) {
                    throw new Exception("Brief not found: {$briefId}");
                }

                if (!$brief->canTransitionTo($status)) {
                    return $this->errorResponse(
                        "Cannot change brief {$briefId} status from {$brief->status} to {$status}",
                        422,
                    );
                }
            }

            $count = $this->briefService->bulkUpdateStatus($briefIds, $status);

            return $this->successResponse("Updated {$count} briefs to {$status}");
        } catch (UnauthorizedException $e) {
            return $this->errorResponse($e->getMessage(), 403);
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    private function authorizeBriefStatusChange(int $briefId, string $newStatus, int $userId): void
    {
        $brief = $this->briefService->getCompleteBrief($briefId);

        if (!$brief) {
            throw new Exception("Brief not found: {$briefId}");
        }

        if ($newStatus === 'in_review') {
            $this->workflowAuthorization->assertCanRequestApproval($userId, SiteContext::getId(), 'briefs');
            return;
        }

        if ($newStatus === 'ready') {
            $this->workflowAuthorization->assertCanApprove($userId, SiteContext::getId(), 'briefs');
            return;
        }

        if ($newStatus === 'on_hold') {
            $this->workflowAuthorization->assertCanHold($userId, SiteContext::getId(), 'briefs');
            return;
        }

        if ($brief->status === 'in_review' && $newStatus === 'draft') {
            $this->workflowAuthorization->assertCanReject($userId, SiteContext::getId(), 'briefs');
        }
    }

    public function bulkAssign(Request $request, string $site): JsonResponse
    {
        try {
            $briefIds = $request->get('brief_ids', []);
            $userId = $request->get('user_id');
            $role = $request->get('role', 'writer');
            $siteId = SiteContext::getId();

            if (empty($briefIds)) {
                return $this->errorResponse('No briefs selected', 400);
            }

            if (!$userId) {
                return $this->errorResponse('User ID is required', 400);
            }

            if (!in_array($role, ['writer', 'editor', 'reviewer', 'fact_checker'])) {
                return $this->errorResponse('Invalid role', 400);
            }

            $count = $this->briefService->bulkAssignCollaborator($briefIds, $userId, $role, $siteId);

            return $this->successResponse("Assigned to {$count} briefs");
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    public function bulkDelete(Request $request, string $site): JsonResponse
    {
        try {
            $briefIds = $request->get('brief_ids', []);

            if (empty($briefIds)) {
                return $this->errorResponse('No briefs selected', 400);
            }

            $userId = (int) ($request->get('user_id') ?? Auth::id());

            if (!$userId) {
                return $this->errorResponse('User ID is required', 400);
            }

            // Each brief is checked individually: the acting user must be owner OR have delete permission.
            foreach ($briefIds as $briefId) {
                $brief = $this->briefService->getCompleteBrief((int) $briefId);

                if (!$brief) {
                    throw new Exception("Brief not found: {$briefId}");
                }

                $this->workflowAuthorization->assertCanDelete(
                    $userId,
                    SiteContext::getId(),
                    $brief->owner_id !== null ? (int) $brief->owner_id : null,
                    'briefs',
                );
            }

            $this->briefService->bulkDelete($briefIds);

            return $this->successResponse('Deleted ' . count($briefIds) . ' briefs');
        } catch (UnauthorizedException $e) {
            return $this->errorResponse($e->getMessage(), 403);
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    public function getTemplates(Request $request, string $site): JsonResponse
    {
        try {
            $templates = $this->briefService->getTemplatesForSite(SiteContext::getId());

            return $this->resourceResponse(['items' => $templates]);
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    public function createFromTemplate(int $templateId, Request $request, string $site): JsonResponse
    {
        try {
            $data = $request->all();
            $data['site_id'] = SiteContext::getId();

            $brief = $this->briefService->createFromTemplate($templateId, $data);

            return $this->resourceResponse([
                'data' => BriefResource::make($brief)->toArray(),
            ], 201);
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    public function saveAsTemplate(int $id, Request $request, string $site): JsonResponse
    {
        try {
            $template = $this->briefService->saveAsTemplate($id, [
                'name' => $request->get('name'),
                'description' => $request->get('description'),
                'type' => $request->get('type', 'custom'),
                'created_by' => $request->get('user_id'),
            ]);

            return $this->resourceResponse(['data' => $template->toArray()], 201);
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    public function getCollaborators(int $id, string $site): JsonResponse
    {
        try {
            $collaborators = $this->briefService->getCollaborators($id);
            return $this->resourceResponse(['items' => $collaborators]);
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    public function addCollaborator(int $id, AddBriefCollaboratorRequest $request, string $site): JsonResponse
    {
        try {
            $collaborator = $this->briefService->addCollaborator($id, $request->all(), $request->get('user_id'));

            return $this->resourceResponse(['data' => $collaborator->toArray()], 201);
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    public function updateCollaborator(int $id, int $collaboratorId, AddBriefCollaboratorRequest $request, string $site): JsonResponse
    {
        try {
            $updated = $this->briefService->updateCollaborator($id, $collaboratorId, $request->all());

            return $this->resourceResponse(['data' => $updated->toArray()]);
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    public function removeCollaborator(int $id, int $collaboratorId, string $site): JsonResponse
    {
        try {
            $result = $this->briefService->removeCollaborator($collaboratorId);

            if (!$result) {
                return $this->errorResponse('Collaborator not found', 404);
            }

            return $this->successResponse('Collaborator removed');
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    public function getTasks(int $id, string $site): JsonResponse
    {
        try {
            $tasks = $this->briefService->getTasks($id);
            return $this->resourceResponse(['items' => $tasks]);
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    public function createTask(int $id, CreateBriefTaskRequest $request, string $site): JsonResponse
    {
        try {
            $task = $this->briefService->createTask($id, $request->all());
            $taskData = $task->toArray();
            $taskData['due_date'] = $task->due_date?->format('Y-m-d') ?? '';

            return $this->resourceResponse(['data' => $taskData], 201);
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    public function updateTask(int $id, int $taskId, UpdateBriefTaskRequest $request, string $site): JsonResponse
    {
        try {
            $task = $this->briefService->updateTask($taskId, $request->all());

            return $this->resourceResponse(['data' => $task->toArray()]);
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    public function deleteTask(int $id, int $taskId, string $site): JsonResponse
    {
        try {
            $result = $this->briefService->deleteTask($taskId);

            if (!$result) {
                return $this->errorResponse('Task not found', 404);
            }

            return $this->successResponse('Task deleted successfully');
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    public function searchTasks(Request $request)
    {

        $ownerId = $request->get('ownerId') ?: null;
        $reviewerId = $request->get('reviewerId') ?: null;
        $startDate = $request->input('start_date');
        $endDate = $request->input('end_date');

        if (!$ownerId && !$reviewerId) {
            return $this->jsonResponse([
                'success' => false,
                'message' => 'Either ownerId or reviewerId is required.',
            ], 422);
        }

        $subtasks = $this->taskRepository
            ->getForUser(
                ownerId: $ownerId,
                reviewerId: $reviewerId,
                startDate: $startDate,
                endDate: $endDate,
            )
            ->map(fn($subtask) => $this->formatSubtask($subtask));

        return $this->jsonResponse([
            'success' => true,
            'data' => $subtasks,
        ]);
    }

    public function getVersions(int $id, string $site): JsonResponse
    {
        try {
            $versions = $this->briefService->getVersions($id);
            return $this->resourceResponse(['items' => $versions]);
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    public function restoreVersion(int $id, int $versionId, Request $request, string $site): JsonResponse
    {
        try {
            $this->briefService->restoreVersion($id, $versionId, $request->get('user_id'));
            return $this->successResponse('Version restored');
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    public function getActivityLog(int $id, string $site): JsonResponse
    {
        try {
            $activities = $this->briefService->getActivityLog($id);
            return $this->resourceResponse(['items' => $activities]);
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    public function getRelationships(int $id, string $site): JsonResponse
    {
        try {
            $relationships = $this->briefService->getRelationships($id);
            return $this->resourceResponse(['items' => $relationships]);
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    public function addRelationship(int $id, AddBriefRelationshipRequest $request, string $site): JsonResponse
    {
        try {
            $relationship = $this->briefService->addRelationship($id, $request->all());

            return $this->resourceResponse(['data' => $relationship->toArray()], 201);
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    public function removeRelationship(int $id, int $relationshipId, string $site): JsonResponse
    {
        try {
            $result = $this->briefService->removeRelationship($relationshipId);

            if (!$result) {
                return $this->errorResponse('Relationship not found', 404);
            }

            return $this->successResponse('Relationship removed');
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    public function addWorkflowChange(int $id, AddBriefWorkflowChangeRequest $request, string $site): JsonResponse
    {
        try {
            $workflow = $this->briefService->addWorkflowChange($id, $request->all());

            return $this->resourceResponse(['data' => $workflow->toArray()], 201);
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    public function getWorkflowHistory(int $id, string $site): JsonResponse
    {
        try {
            $history = $this->briefService->getWorkflowHistory($id);
            return $this->resourceResponse(['items' => $history]);
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    public function setDeadline(int $id, SetBriefDeadlineRequest $request, string $site): JsonResponse
    {
        try {
            $deadline = $this->briefService->setDeadline($id, $request->all());
            $deadlineData = $deadline->toArray();
            $deadlineData['due_date'] = $deadline->due_date?->format('Y-m-d H:i:s') ?? '';

            return $this->resourceResponse(['data' => $deadlineData]);
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    public function getDeadline(int $id, string $site): JsonResponse
    {
        try {
            $deadline = $this->briefService->getDeadline($id);
            return $this->resourceResponse(['data' => $deadline]);
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    public function deleteDeadline(int $id, string $site): JsonResponse
    {
        try {
            $result = $this->briefService->deleteDeadline($id);

            if (!$result) {
                return $this->errorResponse('Deadline not found', 404);
            }

            return $this->successResponse('Deadline removed');
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    public function uploadAttachment(int $id, Request $request, string $site): JsonResponse
    {
        try {
            $file = $request->file('file');

            if (!$file) {
                return $this->errorResponse('No file provided', 400);
            }

            $fileUpload = new \App\Framework\FileUpload\FileUpload($file, 'uploads/briefs/' . $id);
            $fileUpload->setAllowedExtensions(['pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'txt', 'csv']);
            $fileUpload->setMaxSize(10 * 1024 * 1024);
            $filePath = $fileUpload->store();

            $attachment = $this->briefService->addAttachment($id, [
                'type' => 'document',
                'file_url' => $filePath,
                'file_name' => $file->getClientOriginalName(),
                'filesize' => $file->getSize(),
                'metadata' => [
                    'description' => $request->get('description', ''),
                    'mime_type' => $file->getMimeType(),
                ],
                'sort_order' => 0,
            ]);

            return $this->resourceResponse(['data' => $attachment->toArray()], 201);
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    public function clone(int $id, Request $request, string $site): JsonResponse
    {
        try {
            $clone = $this->briefService->cloneBrief(
                $id,
                $request->get('user_id') ?? auth()->id(),
                $request->get('title'),
                new DuplicateBriefOptions(
                    includeSubtasks: (bool)$request->get('includeSubtasks', true),
                    includeCollaborators: (bool)$request->get('includeCollaborators', true),
                    includeComments: (bool)$request->get('includeComments', true),
                    includeRelationships: (bool)$request->get('includeRelationships', true),
                    includeDeadlines: (bool)$request->get('includeDeadlines', true),
                ),
            );

            return $this->resourceResponse(
                ['data' => BriefResource::make($clone)->toArray()],
                201,
            );
        } catch (\RuntimeException $e) {
            $notFound = str_contains($e->getMessage(), 'Brief not found');
            return $this->errorResponse($e->getMessage(), $notFound ? 404 : 500);
        }
    }

    public function createSchedule(int $id, Request $request, string $site): JsonResponse
    {
        try {

            $schedule = $this->briefService->createSchedule($id, $request->all());

            $data = $schedule->toArray();

            foreach (['created_at', 'updated_at', 'end_date', 'next_run_at'] as $f) {
                $data[$f] = isset($data[$f]) ? ($data[$f])->format('Y-m-d') : null;
            }

            return $this->resourceResponse(['data' => $data], 201);
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    public function getSchedule(int $id, string $site): JsonResponse
    {
        try {
            $schedule = $this->briefService->getSchedule($id);

            return $this->resourceResponse([
                'data' => $schedule?->toArray(),
            ]);
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    public function updateSchedule(int $id, Request $request, string $site): JsonResponse
    {
        try {
            $schedule = $this->briefService->updateSchedule($id, $request->all());

            if (!$schedule) {
                return $this->errorResponse('Schedule not found', 404);
            }

            return $this->resourceResponse(['data' => $schedule->toArray()]);
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    public function deleteSchedule(int $id, string $site): JsonResponse
    {
        try {
            $deactivated = $this->briefService->deactivateSchedule($id);

            if (!$deactivated) {
                return $this->errorResponse('Schedule not found', 404);
            }

            return $this->noContentResponse(); // 204
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    public function convertBriefToArticle(
        int                          $id,
        ConvertBriefToArticleRequest $request,
        string $site
    ): JsonResponse
    {
        try {
            $result = $this->briefService->convertBriefToArticle(
                $id,
                $request->input('title'),
                $request->input('images', []),
                $request->input('blockType'),
                $request->input('products', [])
            );

            return $this->resourceResponse(['data' => $result], 201);
        } catch (ValidationException $e) {
            return $this->errorResponse('Validation failed', 422, $e->getErrors());
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    public function runSchedule(int $id, string $site): JsonResponse
    {
        try {
            $schedule = $this->briefService->getSchedule($id);

            if (!$schedule || !$schedule->active) {
                return $this->errorResponse('No active schedule found', 404);
            }

            // Clone immediately without mutating the schedule's nextRunAt
            $this->briefService->cloneBrief(
                $schedule->source_brief_id,
                $schedule->owner_id,
                null,
                true
            );

            return $this->resourceResponse(['data' => $schedule->toArray()]);
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    private function formatSubtask(mixed $subtask): array
    {
        $data = $subtask->toArray();

        // Serialise Carbon dates to plain strings so Angular receives
        // "YYYY-MM-DD HH:mm:ss" rather than a Carbon object.
        $data['due_date'] = $subtask->due_date?->format('Y-m-d H:i:s') ?? null;
        $data['created_at'] = $subtask->created_at?->format('Y-m-d H:i:s') ?? null;
        $data['updated_at'] = $subtask->updated_at?->format('Y-m-d H:i:s') ?? null;

        // Eager-load brief summary so the calendar card can show "View Brief"
        if ($subtask->relationLoaded('brief') && $subtask->brief) {
            $data['brief'] = [
                'id' => $subtask->brief->id,
                'title' => $subtask->brief->title,
            ];
        }

        // Eager-load createdBy (owner) so the calendar card can show the avatar
        if ($subtask->relationLoaded('creator') && $subtask->creator) {
            $data['owner'] = [
                'id' => $subtask->creator['id'],
                'name' => $subtask->creator['name'],
                'email' => $subtask->creator['email'],
            ];
        }

        return $data;
    }
}