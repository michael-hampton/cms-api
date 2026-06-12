<?php

namespace App\Controllers\OpenCollab;

use App\Controllers\Controller;
use App\Framework\Authorization\Auth;
use App\Framework\Http\JsonResponse;
use App\Framework\Http\Request;
use App\Framework\Support\SiteContext;
use App\Models\User;
use App\Repositories\OpenCollab\ContributorBriefRepository;
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

    public function updateTask(Request $request, int $brief, int $task): JsonResponse
    {
        return $this->withBrief($brief, function ($model) use ($request, $task) {
            $status = (string)$request->get('status', '');
            if (!in_array($status, ['pending', 'in_progress', 'completed'], true)) {
                return $this->validation(['status' => ['Invalid task status.']]);
            }

            $updated = $this->gateway->updateTask($model, $task, Auth::id(), $status);

            return $this->resourceResponse(['data' => $this->presenter->task($updated)]);
        });
    }

    public function attachments(int $brief): JsonResponse
    {
        return $this->withBrief($brief, fn($model) => $this->resourceResponse([
            'data' => $model->attachments?->map(fn($attachment) => $this->presenter->attachment($attachment))->toArray() ?? [],
        ]));
    }

    public function uploadAttachment(Request $request, int $brief): JsonResponse
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

    public function createComment(Request $request, int $brief): JsonResponse
    {
        return $this->withBrief($brief, function ($model) use ($request) {
            $content = trim((string)$request->get('content', ''));
            if ($content === '' || mb_strlen($content) > 3000) {
                return $this->validation(['content' => ['Comment is required and must be 3000 characters or fewer.']]);
            }

            $comment = $this->gateway->addComment($model, Auth::id(), $content);

            return $this->resourceResponse(['data' => $this->presenter->comment($comment)], 201);
        });
    }

    public function updateComment(Request $request, int $comment): JsonResponse
    {
        try {
            $model = $this->briefs->findComment($comment);
            if (!$model) {
                return $this->notFound('Comment not found', true);
            }

            $this->access->assertCanAccessBrief((int)$model->brief_id, Auth::id(), SiteContext::getId());
            $content = trim((string)$request->get('content', ''));
            if ($content === '' || mb_strlen($content) > 3000) {
                return $this->validation(['content' => ['Comment is required and must be 3000 characters or fewer.']]);
            }

            $updated = $this->gateway->updateComment($comment, Auth::id(), $content);

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

    public function reject(Request $request, int $brief): JsonResponse
    {
        $reason = trim((string)$request->get('reason', ''));
        if ($reason === '' || mb_strlen($reason) > 1000) {
            return $this->validation(['reason' => ['Reason is required and must be 1000 characters or fewer.']]);
        }

        return $this->assignmentAction($brief, 'reject', fn($model, $assignment) => $this->gateway->rejectAssignment($model, $assignment, Auth::id(), $reason), 'Assignment rejected.');
    }

    public function requestClarification(Request $request, int $brief): JsonResponse
    {
        $message = trim((string)$request->get('message', ''));
        if ($message === '' || mb_strlen($message) > 3000) {
            return $this->validation(['message' => ['Message is required and must be 3000 characters or fewer.']]);
        }

        return $this->assignmentAction($brief, 'request_clarification', fn($model) => $this->gateway->requestClarification($model, Auth::id(), $message), 'Clarification requested.');
    }

    public function requestDeadlineChange(Request $request, int $brief): JsonResponse
    {
        return $this->withBrief($brief, function ($model, $assignment) use ($request) {
            $requestedDeadline = trim((string)$request->get('requested_deadline', ''));
            $reason = trim((string)$request->get('reason', ''));
            $currentDeadline = $this->presenter->deadline($model);
            $errors = [];

            if ($requestedDeadline === '') {
                $errors['requested_deadline'][] = 'Requested deadline is required.';
            } elseif (strtotime($requestedDeadline) <= time()) {
                $errors['requested_deadline'][] = 'Requested deadline cannot be in the past.';
            } elseif ($currentDeadline && strtotime($requestedDeadline) <= strtotime($currentDeadline)) {
                $errors['requested_deadline'][] = 'Requested deadline must be later than the current deadline.';
            }

            if ($reason === '' || mb_strlen($reason) > 1000) {
                $errors['reason'][] = 'Reason is required and must be 1000 characters or fewer.';
            }

            if ($errors) {
                return $this->validation($errors);
            }

            $this->actions->assertActionAvailable('request_deadline_change', $model, $assignment);
            $this->gateway->requestDeadlineChange($model, Auth::id(), $requestedDeadline, $reason);

            return $this->actionResponse($model->id, 'Deadline change requested.');
        });
    }

    public function negotiate(Request $request, int $brief): JsonResponse
    {
        $message = trim((string)$request->get('message', ''));
        $requestedDeadline = trim((string)$request->get('requested_deadline', ''));

        if ($message === '' || mb_strlen($message) > 3000) {
            return $this->validation(['message' => ['Message is required and must be 3000 characters or fewer.']]);
        }

        if ($requestedDeadline !== '' && strtotime($requestedDeadline) <= time()) {
            return $this->validation(['requested_deadline' => ['Requested deadline cannot be in the past.']]);
        }

        return $this->assignmentAction($brief, 'negotiate', fn($model, $assignment) => $this->gateway->negotiateAssignment($model, $assignment, Auth::id(), [
            'message' => $message,
            'requested_deadline' => $requestedDeadline ?: null,
            'scope_details' => trim((string)$request->get('scope_details', '')) ?: null,
        ]), 'Negotiation requested.');
    }

    public function submit(Request $request, int $brief): JsonResponse
    {
        $notes = trim((string)$request->get('notes', ''));
        if (mb_strlen($notes) > 2000) {
            return $this->validation(['notes' => ['Submission notes must be 2000 characters or fewer.']]);
        }

        return $this->assignmentAction($brief, 'submit', fn($model) => $this->gateway->submit($model, Auth::id(), $notes), 'Brief submitted for review.');
    }

    public function resubmit(Request $request, int $brief): JsonResponse
    {
        $notes = trim((string)$request->get('notes', ''));
        if (mb_strlen($notes) > 2000) {
            return $this->validation(['notes' => ['Submission notes must be 2000 characters or fewer.']]);
        }

        return $this->assignmentAction($brief, 'resubmit', fn($model) => $this->gateway->resubmit($model, Auth::id(), $notes), 'Brief resubmitted for review.');
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
