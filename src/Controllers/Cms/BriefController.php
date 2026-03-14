<?php

namespace App\Controllers\Cms;

use App\Controllers\Controller;
use App\Framework\Exceptions\ValidationException;
use App\Framework\Http\JsonResponse;
use App\Framework\Http\Request;
use App\Framework\Resource\PaginatedResourceCollection;
use App\Framework\Support\SiteContext;
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
use Exception;

class BriefController extends Controller
{
    public function __construct(
        private readonly BriefService $briefService,
    )
    {
        parent::__construct();
    }

    public function index(Request $request, string $siteName): JsonResponse
    {
        try {
            $criteria = SearchCriteriaParser::fromRequest($request, $siteName);
            $result = $this->briefService->search($criteria);

            $collection = new PaginatedResourceCollection($result, BriefResource::class);
            return $this->resourceResponse($collection->toArray(), 200);
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    public function show(int $id, string $siteName): JsonResponse
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

    public function store(StoreBriefRequest $request, string $siteName): JsonResponse
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

    public function update(int $id, UpdateBriefRequest $request, string $siteName): JsonResponse
    {
        try {
            $data = $request->all();
            $userId = $request->get('user_id', $data['owner_id'] ?? null);

            $brief = $this->briefService->updateBrief($id, $data, $userId);

            return $this->resourceResponse([
                'data' => BriefResource::make($brief)->toArray(),
            ]);
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    public function destroy(int $id, string $siteName): JsonResponse
    {
        try {
            $result = $this->briefService->deleteBrief($id);

            if (!$result) {
                return $this->errorResponse('Brief not found', 404);
            }

            return $this->successResponse('Brief deleted successfully');
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    public function addAttachment(int $id, AddBriefAttachmentRequest $request, string $siteName): JsonResponse
    {
        try {
            $attachment = $this->briefService->addAttachment($id, $request->all());

            return $this->resourceResponse(['data' => $attachment->toArray()], 201);
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    public function updateAttachment(int $id, int $attachmentId, Request $request, string $siteName): JsonResponse
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

    public function deleteAttachment(int $id, int $attachmentId, string $siteName): JsonResponse
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

    public function addComment(int $id, AddBriefCommentRequest $request, string $siteName): JsonResponse
    {
        try {
            $data = $request->all();
            $data['user_id'] = $request->get('user_id');

            $comment = $this->briefService->addComment($id, $data);

            return $this->resourceResponse(['data' => $comment->toArray()], 201);
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    public function updateComment(int $id, int $commentId, UpdateBriefCommentRequest $request, string $siteName): JsonResponse
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

    public function deleteComment(int $id, int $commentId, string $siteName): JsonResponse
    {
        try {
            $result = $this->briefService->deleteComment($id, $commentId);

            if (!$result) {
                return $this->errorResponse('Comment not found', 404);
            }

            return $this->successResponse('Comment deleted successfully');
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    public function resolveComment(int $id, int $commentId, Request $request, string $siteName): JsonResponse
    {
        try {
            $comment = $this->briefService->resolveComment($id, $commentId, $request->get('user_id'));

            return $this->resourceResponse(['data' => $comment->toArray()]);
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    public function unresolveComment(int $id, int $commentId, Request $request, string $siteName): JsonResponse
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

    public function convertToPage(int $id, Request $request, string $siteName): JsonResponse
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

    public function archive(int $id, string $siteName): JsonResponse
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

    public function duplicate(int $id, Request $request, string $siteName): JsonResponse
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

    public function updateStatus(int $id, Request $request, string $siteName): JsonResponse
    {
        try {
            $brief = $this->briefService->updateStatus($id, $request->get('status'), $request->get('user_id'));

            return $this->resourceResponse([
                'data' => BriefResource::make($brief)->toArray(),
            ]);
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

            $count = $this->briefService->bulkUpdateStatus($briefIds, $status);

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

    public function bulkDelete(Request $request, string $siteName): JsonResponse
    {
        try {
            $briefIds = $request->get('brief_ids', []);

            if (empty($briefIds)) {
                return $this->errorResponse('No briefs selected', 400);
            }

            $this->briefService->bulkDelete($briefIds);

            return $this->successResponse('Deleted ' . count($briefIds) . ' briefs');
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    public function getTemplates(Request $request, string $siteName): JsonResponse
    {
        try {
            $templates = $this->briefService->getTemplatesForSite(SiteContext::getId());

            return $this->resourceResponse(['items' => $templates]);
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    public function createFromTemplate(int $templateId, Request $request, string $siteName): JsonResponse
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

    public function saveAsTemplate(int $id, Request $request, string $siteName): JsonResponse
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

    public function getCollaborators(int $id, string $siteName): JsonResponse
    {
        try {
            $collaborators = $this->briefService->getCollaborators($id);
            return $this->resourceResponse(['items' => $collaborators]);
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    public function addCollaborator(int $id, AddBriefCollaboratorRequest $request, string $siteName): JsonResponse
    {
        try {
            $collaborator = $this->briefService->addCollaborator($id, $request->all(), $request->get('user_id'));

            return $this->resourceResponse(['data' => $collaborator->toArray()], 201);
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    public function updateCollaborator(int $id, int $collaboratorId, AddBriefCollaboratorRequest $request, string $siteName): JsonResponse
    {
        try {
            $updated = $this->briefService->updateCollaborator($id, $collaboratorId, $request->all());

            return $this->resourceResponse(['data' => $updated->toArray()]);
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    public function removeCollaborator(int $id, int $collaboratorId, string $siteName): JsonResponse
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

    public function getTasks(int $id, string $siteName): JsonResponse
    {
        try {
            $tasks = $this->briefService->getTasks($id);
            return $this->resourceResponse(['items' => $tasks]);
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    public function createTask(int $id, CreateBriefTaskRequest $request, string $siteName): JsonResponse
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

    public function updateTask(int $id, int $taskId, UpdateBriefTaskRequest $request, string $siteName): JsonResponse
    {
        try {
            $task = $this->briefService->updateTask($taskId, $request->all());

            return $this->resourceResponse(['data' => $task->toArray()]);
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    public function deleteTask(int $id, int $taskId, string $siteName): JsonResponse
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

    public function getVersions(int $id, string $siteName): JsonResponse
    {
        try {
            $versions = $this->briefService->getVersions($id);
            return $this->resourceResponse(['items' => $versions]);
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    public function restoreVersion(int $id, int $versionId, Request $request, string $siteName): JsonResponse
    {
        try {
            $this->briefService->restoreVersion($id, $versionId, $request->get('user_id'));
            return $this->successResponse('Version restored');
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    public function getActivityLog(int $id, string $siteName): JsonResponse
    {
        try {
            $activities = $this->briefService->getActivityLog($id);
            return $this->resourceResponse(['items' => $activities]);
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    public function getRelationships(int $id, string $siteName): JsonResponse
    {
        try {
            $relationships = $this->briefService->getRelationships($id);
            return $this->resourceResponse(['items' => $relationships]);
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    public function addRelationship(int $id, AddBriefRelationshipRequest $request, string $siteName): JsonResponse
    {
        try {
            $relationship = $this->briefService->addRelationship($id, $request->all());

            return $this->resourceResponse(['data' => $relationship->toArray()], 201);
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    public function removeRelationship(int $id, int $relationshipId, string $siteName): JsonResponse
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

    public function addWorkflowChange(int $id, AddBriefWorkflowChangeRequest $request, string $siteName): JsonResponse
    {
        try {
            $workflow = $this->briefService->addWorkflowChange($id, $request->all());

            return $this->resourceResponse(['data' => $workflow->toArray()], 201);
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    public function getWorkflowHistory(int $id, string $siteName): JsonResponse
    {
        try {
            $history = $this->briefService->getWorkflowHistory($id);
            return $this->resourceResponse(['items' => $history]);
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    public function setDeadline(int $id, SetBriefDeadlineRequest $request, string $siteName): JsonResponse
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

    public function getDeadline(int $id, string $siteName): JsonResponse
    {
        try {
            $deadline = $this->briefService->getDeadline($id);
            return $this->resourceResponse(['data' => $deadline]);
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    public function deleteDeadline(int $id, string $siteName): JsonResponse
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

    public function uploadAttachment(int $id, Request $request, string $siteName): JsonResponse
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

    public function convertBriefToArticle(
        int                          $id,
        ConvertBriefToArticleRequest $request,
        string                       $siteName
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
}