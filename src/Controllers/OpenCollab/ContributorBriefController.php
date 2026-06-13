<?php

namespace App\Controllers\OpenCollab;

use App\Controllers\Controller;
use App\Framework\Authorization\Auth;
use App\Framework\Http\JsonResponse;
use App\Framework\Support\SiteContext;
use App\Models\User;
use App\Repositories\OpenCollab\ContributorBriefRepository;
use App\Requests\OpenCollab\Briefs\BriefCommentRequest;
use App\Requests\OpenCollab\Briefs\NegotiateBriefAssignmentRequest;
use App\Requests\OpenCollab\Briefs\RejectBriefAssignmentRequest;
use App\Requests\OpenCollab\Briefs\RequestBriefClarificationRequest;
use App\Requests\OpenCollab\Briefs\RequestBriefDeadlineChangeRequest;
use App\Requests\OpenCollab\Briefs\SubmitBriefRequest;
use App\Requests\OpenCollab\Briefs\UpdateBriefTaskRequest;
use App\Requests\OpenCollab\Briefs\UploadBriefAttachmentRequest;
use App\Services\OpenCollab\CmsBriefGateway;
use App\Services\OpenCollab\OpenCollabBriefAccessService;
use App\Services\OpenCollab\OpenCollabBriefActionAvailabilityService;
use App\Services\OpenCollab\OpenCollabBriefPresenter;

class ContributorBriefController extends Controller
{
    public function __construct(
        private readonly OpenCollabBriefAccessService $access,
        private readonly OpenCollabBriefPresenter $presenter,
        private readonly OpenCollabBriefActionAvailabilityService $actions,
        private readonly CmsBriefGateway $gateway,
        private readonly ContributorBriefRepository $briefs,
    )
    {
        parent::__construct();
    }

    public function show(int $brief)
    {
        if (!$this->canAccess($brief)) {
            return $this->notFound();
        }

        return $this->view('open-collab.briefs.show', [
            'briefId' => $brief,
            'currentUser' => User::hydrateStatic(Auth::getUser()),
            'site' => SiteContext::slug(),
        ]);
    }

    public function apiShow(int $brief): JsonResponse
    {
        return $this->withBrief($brief, fn($model, $assignment) => $this->resourceResponse(
            $this->presenter->workspace($model, $assignment)
        ));
    }

    public function timeline(int $brief): JsonResponse
    {
        return $this->withBrief($brief, fn($model, $assignment) => $this->resourceResponse(
            $this->presenter->timeline($model, $assignment)
        ));
    }

    public function tasks(int $brief): JsonResponse
    {
        return $this->withBrief($brief, fn($model) => $this->resourceResponse([
            'data' => $model->tasks?->map(fn($task) => $this->presenter->task($task))->toArray() ?? [],
        ]));
    }

    public function updateTask(UpdateBriefTaskRequest $request, int $brief, int $task): JsonResponse
    {
        $validated = $request->validated();

        return $this->withBrief($brief, function ($model) use ($validated, $task) {
            $updated = $this->gateway->updateTask(
                $model,
                $task,
                Auth::id(),
                $validated['status'],
            );

            return $this->resourceResponse(['data' => $this->presenter->task($updated)]);
        });
    }

    public function attachments(int $brief): JsonResponse
    {
        return $this->withBrief($brief, fn($model) => $this->resourceResponse([
            'data' => $model->attachments?->map(fn($attachment) => $this->presenter->attachment($attachment))->toArray() ?? [],
        ]));
    }

    public function uploadAttachment(UploadBriefAttachmentRequest $request, int $brief): JsonResponse
    {
        return $this->withBrief($brief, function ($model) use ($request) {
            try {
                $attachment = $this->gateway->addAttachment(
                    $model,
                    $request,
                    Auth::id(),
                    Auth::user()?->name ?? 'Contributor',
                );
            } catch (\InvalidArgumentException $exception) {
                return $this->validation(['file' => [$exception->getMessage()]]);
            }

            return $this->resourceResponse(['data' => $this->presenter->attachment($attachment)], 201);
        });
    }

    public function deleteAttachment(int $brief, int $attachment): JsonResponse
    {
        return $this->withBrief($brief, function ($model) use ($attachment) {
            $this->gateway->deleteAttachment($model, $attachment, Auth::id());

            return $this->successResponse('Attachment deleted.');
        });
    }

    public function comments(int $brief): JsonResponse
    {
        return $this->withBrief($brief, fn($model) => $this->resourceResponse([
            'data' => $model->comments?->map(fn($comment) => $this->presenter->comment($comment))->toArray() ?? [],
        ]));
    }

    public function createComment(BriefCommentRequest $request, int $brief): JsonResponse
    {
        $validated = $request->validated();

        return $this->withBrief($brief, function ($model) use ($validated) {
            $comment = $this->gateway->addComment(
                $model,
                Auth::id(),
                $validated['content'],
            );

            return $this->resourceResponse(['data' => $this->presenter->comment($comment)], 201);
        });
    }

    public function updateComment(BriefCommentRequest $request, int $comment): JsonResponse
    {
        $validated = $request->validated();

        try {
            $model = $this->briefs->findComment($comment);
            if (!$model) {
                return $this->notFound('Comment not found', true);
            }

            $this->access->assertCanAccessBrief((int)$model->brief_id, Auth::id(), SiteContext::getId());
            $updated = $this->gateway->updateComment(
                $comment,
                Auth::id(),
                $validated['content'],
            );

            return $this->resourceResponse(['data' => $this->presenter->comment($updated)]);
        } catch (\RuntimeException $exception) {
            return $this->errorResponse($exception->getMessage() === 'Forbidden' ? 'Forbidden' : $exception->getMessage(), $exception->getMessage() === 'Forbidden' ? 403 : 404);
        }
    }

    public function resolveComment(int $comment): JsonResponse
    {
        try {
            $model = $this->briefs->findComment($comment);
            if (!$model) {
                return $this->notFound('Comment not found', true);
            }

            $this->access->assertCanAccessBrief((int)$model->brief_id, Auth::id(), SiteContext::getId());
            $updated = $this->gateway->resolveComment($comment, Auth::id());

            return $this->resourceResponse(['data' => $this->presenter->comment($updated)]);
        } catch (\RuntimeException $exception) {
            return $this->errorResponse($exception->getMessage() === 'Forbidden' ? 'Forbidden' : $exception->getMessage(), $exception->getMessage() === 'Forbidden' ? 403 : 404);
        }
    }

    public function accept(int $brief): JsonResponse
    {
        return $this->assignmentAction($brief, 'accept', fn($model, $assignment) => $this->gateway->acceptAssignment($model, $assignment, Auth::id()), 'Assignment accepted.');
    }

    public function reject(RejectBriefAssignmentRequest $request, int $brief): JsonResponse
    {
        $validated = $request->validated();

        return $this->assignmentAction(
            $brief,
            'reject',
            fn($model, $assignment) => $this->gateway->rejectAssignment(
                $model,
                $assignment,
                Auth::id(),
                $validated['reason'],
            ),
            'Assignment rejected.',
        );
    }

    public function requestClarification(RequestBriefClarificationRequest $request, int $brief): JsonResponse
    {
        $validated = $request->validated();

        return $this->assignmentAction(
            $brief,
            'request_clarification',
            fn($model) => $this->gateway->requestClarification(
                $model,
                Auth::id(),
                $validated['message'],
            ),
            'Clarification requested.',
        );
    }

    public function requestDeadlineChange(RequestBriefDeadlineChangeRequest $request, int $brief): JsonResponse
    {
        $validated = $request->validated();

        return $this->assignmentAction(
            $brief,
            'request_deadline_change',
            fn($model) => $this->gateway->requestDeadlineChange(
                $model,
                Auth::id(),
                $validated['requested_deadline'],
                $validated['reason'],
            ),
            'Deadline change requested.',
        );
    }

    public function negotiate(NegotiateBriefAssignmentRequest $request, int $brief): JsonResponse
    {
        $validated = $request->validated();

        return $this->assignmentAction(
            $brief,
            'negotiate',
            fn($model, $assignment) => $this->gateway->negotiateAssignment(
                $model,
                $assignment,
                Auth::id(),
                [
                    'message' => $validated['message'],
                    'requested_deadline' => $validated['requested_deadline'] ?? null,
                    'scope_details' => $validated['scope_details'] ?? null,
                ],
            ),
            'Negotiation requested.',
        );
    }

    public function submit(SubmitBriefRequest $request, int $brief): JsonResponse
    {
        $validated = $request->validated();

        return $this->assignmentAction(
            $brief,
            'submit',
            fn($model) => $this->gateway->submit(
                $model,
                Auth::id(),
                $validated['notes'] ?? '',
            ),
            'Brief submitted for review.',
        );
    }

    public function resubmit(SubmitBriefRequest $request, int $brief): JsonResponse
    {
        $validated = $request->validated();

        return $this->assignmentAction(
            $brief,
            'resubmit',
            fn($model) => $this->gateway->resubmit(
                $model,
                Auth::id(),
                $validated['notes'] ?? '',
            ),
            'Brief resubmitted for review.',
        );
    }

    private function assignmentAction(int $brief, string $action, callable $handler, string $message): JsonResponse
    {
        return $this->withBrief($brief, function ($model, $assignment) use ($action, $handler, $message) {
            $this->actions->assertActionAvailable($action, $model, $assignment);
            $handler($model, $assignment);

            return $this->actionResponse($model->id, $message);
        });
    }

    private function actionResponse(int $briefId, string $message): JsonResponse
    {
        $brief = $this->access->assertCanAccessBrief($briefId, Auth::id(), SiteContext::getId());
        $assignment = $this->access->assignmentForBrief($briefId, Auth::id(), SiteContext::getId());
        $payload = $this->presenter->workspace($brief, $assignment);

        return $this->resourceResponse([
            'success' => true,
            'message' => $message,
            'assignment' => $payload['assignment'],
            'workflow' => $payload['brief'],
            'available_actions' => $payload['available_actions'],
            'timeline' => $payload['timeline'],
            'workspace' => $payload,
        ]);
    }

    private function withBrief(int $brief, callable $callback): JsonResponse
    {
        try {
            $model = $this->access->assertCanAccessBrief($brief, Auth::id(), SiteContext::getId());
            $assignment = $this->access->assignmentForBrief($brief, Auth::id(), SiteContext::getId());

            return $callback($model, $assignment);
        } catch (\InvalidArgumentException $exception) {
            return $this->errorResponse($exception->getMessage(), 422);
        } catch (\RuntimeException $exception) {
            return $this->errorResponse($exception->getMessage() === 'Forbidden' ? 'Forbidden' : $exception->getMessage(), $exception->getMessage() === 'Forbidden' ? 403 : 404);
        }
    }

    private function canAccess(int $brief): bool
    {
        try {
            $this->access->assertCanAccessBrief($brief, Auth::id(), SiteContext::getId());
            return true;
        } catch (\RuntimeException) {
            return false;
        }
    }

    private function validation(array $errors): JsonResponse
    {
        return $this->errorResponse('Validation failed', 422, $errors);
    }
}
