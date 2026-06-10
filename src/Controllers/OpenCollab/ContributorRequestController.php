<?php

namespace App\Controllers\OpenCollab;

use App\Controllers\Controller;
use App\Framework\Authorization\Auth;
use App\Framework\Exceptions\ValidationException;
use App\Framework\Http\JsonResponse;
use App\Framework\Support\SiteContext;
use App\Models\Site;
use App\Requests\OpenCollab\SubmitContributorRequestRequest;
use App\Services\OpenCollab\ContributorProfileFieldConfigService;
use App\Services\OpenCollab\ContributorRequestService;
use App\Services\OpenCollab\DynamicFieldValidator;

/**
 * Self-service contributor registration and admin review queue.
 *
 * Routes:
 *   POST /api/{site}/open-collab/contributor-requests              — public: submit request
 *   GET  /api/{site}/open-collab/admin/contributor-requests        — admin: list pending
 *   POST /api/{site}/open-collab/admin/contributor-requests/{id}/approve — admin: approve
 *   POST /api/{site}/open-collab/admin/contributor-requests/{id}/reject  — admin: reject
 *
 * Ticket 5: store() validates and passes dynamic field values to ContributorRequestService;
 * formatRequest() now includes custom_fields for admin review.
 */
class ContributorRequestController extends Controller
{
    public function __construct(
        private readonly ContributorRequestService            $requestService,
        private readonly ContributorProfileFieldConfigService $profileFieldConfigService,
        private readonly DynamicFieldValidator                $dynamicFieldValidator,
    ) {
        parent::__construct();
    }

    /**
     * POST /api/{site}/open-collab/contributor-requests
     * Public — no auth required.
     */
    public function store(SubmitContributorRequestRequest $request): JsonResponse
    {
        try {

            $data   = $request->validated();

            $siteId = SiteContext::getId();
            $site   = Site::find($siteId);

            if (!$site) {
                return $this->errorResponse('Site not found.', 404);
            }

            // Ticket 5: validate dynamic request fields before writing anything.
            $fieldDefinitions = $this->profileFieldConfigService->activeRequestFieldsForSite($site);
            $fieldValues = $this->extractDefinedFieldValues($data, $fieldDefinitions);
            $validationErrors = $this->dynamicFieldValidator->validate($fieldDefinitions, $fieldValues);
            $customFieldValues = $this->extractCustomFieldValues($fieldValues);

            if (!empty($validationErrors)) {
                return $this->errorResponse('Validation failed.', 422, $validationErrors);
            }

            $requiresApproval = (bool) ($site->require_invite_approval ?? true);

            $result = $this->requestService->submit(
                email: $fieldValues['email'] ?? '',
                name: $fieldValues['name'] ?? '',
                bio: $fieldValues['bio'] ?? '',
                siteId: $siteId,
                requiresApproval: $requiresApproval,
                customFields: $customFieldValues,
            );

            return $this->jsonResponse([
                'requires_approval' => $result['requires_approval'],
                'message' => $result['requires_approval']
                    ? 'Your request has been received and is pending review.'
                    : 'Invitation sent — check your inbox.',
            ], 201);
        } catch (ValidationException $exception) {
            return $this->errorResponse($exception->getMessage(), 422, $exception->getErrors());
        } catch (\InvalidArgumentException $e) {
            return $this->errorResponse($e->getMessage(), 422);
        }
    }

    /**
     * GET /api/{site}/open-collab/admin/contributor-requests
     */
    public function index(): JsonResponse
    {
        $requests = $this->requestService->pendingForSite(SiteContext::getId());

        return $this->jsonResponse(
            $requests->map(fn($r) => $this->formatRequest($r))->toArray()
        );
    }

    /**
     * POST /api/{site}/open-collab/admin/contributor-requests/{id}/approve
     */
    public function approve(int $id): JsonResponse
    {
        try {
            $invitation = $this->requestService->approve(
                requestId: $id,
                adminId:   Auth::id(),
                siteId:    SiteContext::getId(),
            );

            return $this->jsonResponse([
                'message'    => 'Request approved — invitation dispatched.',
                'invitation' => [
                    'id'         => $invitation->id,
                    'email'      => $invitation->email,
                    'expires_at' => $invitation->expires_at,
                ],
            ]);
        } catch (\InvalidArgumentException $e) {
            return $this->errorResponse($e->getMessage(), 422);
        }
    }

    /**
     * POST /api/{site}/open-collab/admin/contributor-requests/{id}/reject
     */
    public function reject(int $id): JsonResponse
    {
        $body   = json_decode(file_get_contents('php://input'), true) ?? [];
        $reason = trim($body['reason'] ?? '');

        try {
            $this->requestService->reject(
                requestId: $id,
                adminId:   Auth::id(),
                siteId:    SiteContext::getId(),
                reason:    $reason ?: null,
            );

            return $this->successResponse('Request rejected.');
        } catch (\InvalidArgumentException $e) {
            return $this->errorResponse($e->getMessage(), 422);
        }
    }

    // ── Private helpers ───────────────────────────────────────────────────────

    /**
     * Ticket 5: custom_fields included in admin review payload so admins can see
     * submitted dynamic values when deciding whether to approve a request.
     */
    private function formatRequest(\App\Models\ContributorRequest $r): array
    {
        return [
            'id'            => $r->id,
            'email'         => $r->email,
            'name'          => $r->name,
            'bio'           => $r->bio,
            'status'        => $r->status,
            'custom_fields' => $r->custom_fields ?? [],
            'created_at'    => $r->created_at,
        ];
    }

    private function extractDefinedFieldValues(array $data, $fieldDefinitions): array
    {
        $result = [];

        foreach ($fieldDefinitions->all() as $definition) {
            $key = (string) $definition->key;

            if (array_key_exists($key, $data)) {
                $result[$key] = $data[$key];
            }
        }

        return $result;
    }

    private function extractCustomFieldValues(array $fieldValues): array
    {
        foreach (['name', 'email', 'bio'] as $columnBackedField) {
            unset($fieldValues[$columnBackedField]);
        }

        return $fieldValues;
    }
}