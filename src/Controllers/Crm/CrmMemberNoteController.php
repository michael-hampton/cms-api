<?php

namespace App\Controllers\Crm;

use App\Controllers\Controller;
use App\Controllers\Concerns\RequiresSitePermission;
use App\Framework\Authorization\Auth;
use App\Framework\Exceptions\ValidationException;
use App\Framework\Http\Request;
use App\Framework\Support\SiteContext;
use App\Requests\Members\CrmCreateNoteRequest;
use App\Requests\Members\CrmUpdateNoteRequest;
use App\Services\Members\MemberNoteService;
use Exception;

/**
 * CRM — Member Notes
 *
 * All routes are scoped under:
 *   /crm/members/{memberId}/notes
 *
 * Route registration (add to your routes file):
 *   $router->get   ('crm/members/{memberId}/notes',          [CrmMemberNoteController::class, 'index']);
 *   $router->post  ('crm/members/{memberId}/notes',          [CrmMemberNoteController::class, 'store']);
 *   $router->post  ('crm/members/{memberId}/notes/{id}',     [CrmMemberNoteController::class, 'update']);
 *   $router->delete('crm/members/{memberId}/notes/{id}',     [CrmMemberNoteController::class, 'destroy']);
 */
class CrmMemberNoteController extends Controller
{
    use RequiresSitePermission;

    public function __construct(
        private readonly MemberNoteService $noteService,
    )
    {
        parent::__construct();
    }

    // ── GET /crm/members/{memberId}/notes ──────────────────────────────────

    public function index(int $memberId, Request $request): mixed
    {
        if (!Auth::check()) {
            return $this->errorResponse('Unauthorized', 401);
        }
        if ($response = $this->requireSitePermission('crm.notes.view')) {
            return $response;
        }

        $page = max(1, (int)$request->get('page', 1));
        $perPage = max(1, min(100, (int)$request->get('per_page', 20)));

        try {
            $result = $this->noteService->listForMember(
                $memberId,
                SiteContext::getId(),
                $page,
                $perPage,
                $request->get('date_from'),
                $request->get('date_to'),
                $request->get('updated_from'),
                $request->get('updated_to'),
            );

            return $this->resourceResponse($result);
        } catch (\InvalidArgumentException $e) {
            return $this->jsonResponse(['success' => false, 'message' => $e->getMessage()], 404);
        } catch (Exception $e) {
            return $this->jsonResponse(['success' => false, 'message' => 'Failed to load notes.'], 500);
        }
    }

    // ── POST /crm/members/{memberId}/notes ─────────────────────────────────

    public function store(int $memberId, CrmCreateNoteRequest $request): mixed
    {
        if (!Auth::check()) {
            return $this->errorResponse('Unauthorized', 401);
        }
        if ($response = $this->requireSitePermission('crm.notes.create')) {
            return $response;
        }

        try {
            $data = $request->validated();
            $siteId = SiteContext::getId();
            $parentId = isset($data['parent_id']) ? (int)$data['parent_id'] : null;

            $note = $parentId
                ? $this->noteService->createReply($memberId, $siteId, $parentId, $data['body'])
                : $this->noteService->createNote($memberId, $siteId, $data['body']);

            // Re-load with replies so the response shape is consistent.
            $note->load('replies');

            return $this->resourceResponse([
                'success' => true,
                'message' => 'Note saved.',
                'note' => $this->formatNoteForResponse($note),
            ], 201);
        } catch (ValidationException $e) {
            return $this->errorResponse('Validation failed', 422, $e->getErrors());
        } catch (\InvalidArgumentException $e) {
            return $this->jsonResponse(['success' => false, 'message' => $e->getMessage()], 422);
        } catch (Exception $e) {
            return $this->jsonResponse(['success' => false, 'message' => 'Failed to save note.'], 500);
        }
    }

    // ── POST /crm/members/{memberId}/notes/{id} ────────────────────────────

    private function formatNoteForResponse(\App\Models\MemberNote $note): array
    {
        $data = $note->toArray();

        $data['created_at'] = $note->created_at?->format('Y-m-d H:i:s');
        $data['updated_at'] = $note->updated_at?->format('Y-m-d H:i:s');

        $data['replies'] = ($note->replies ?? collect())
            ->map(function ($reply) {
                $replyData = $reply->toArray();

                $replyData['created_at'] = $reply->created_at?->format('Y-m-d H:i:s');
                $replyData['updated_at'] = $reply->updated_at?->format('Y-m-d H:i:s');

                return $replyData;
            })
            ->values()
            ->all();

        return $data;
    }

    // ── DELETE /crm/members/{memberId}/notes/{id} ──────────────────────────

    public function update(int $memberId, int $id, CrmUpdateNoteRequest $request): mixed
    {
        if (!Auth::check()) {
            return $this->errorResponse('Unauthorized', 401);
        }
        if ($response = $this->requireSitePermission('crm.notes.edit')) {
            return $response;
        }

        try {
            $data = $request->validated();
            $note = $this->noteService->updateNote($memberId, SiteContext::getId(), $id, $data['body']);
            $note->load('replies');

            return $this->jsonResponse([
                'success' => true,
                'message' => 'Note updated.',
                'note' => $this->formatNoteForResponse($note),
            ]);
        } catch (ValidationException $e) {
            return $this->errorResponse('Validation failed', 422, $e->getErrors());
        } catch (\InvalidArgumentException $e) {
            return $this->jsonResponse(['success' => false, 'message' => $e->getMessage()], 422);
        } catch (Exception $e) {
            return $this->jsonResponse(['success' => false, 'message' => 'Failed to update note.'], 500);
        }
    }

    // ── Private helpers ────────────────────────────────────────────────────

    public function destroy(int $memberId, int $id): mixed
    {
        if (!Auth::check()) {
            return $this->errorResponse('Unauthorized', 401);
        }
        if ($response = $this->requireSitePermission('crm.notes.delete')) {
            return $response;
        }

        try {
            $this->noteService->deleteNote($memberId, SiteContext::getId(), $id);

            return $this->jsonResponse(['success' => true, 'message' => 'Note deleted.']);
        } catch (\InvalidArgumentException $e) {
            return $this->jsonResponse(['success' => false, 'message' => $e->getMessage()], 422);
        } catch (Exception $e) {
            return $this->jsonResponse(['success' => false, 'message' => 'Failed to delete note.'], 500);
        }
    }
}
