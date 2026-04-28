<?php

namespace App\Controllers\OpenCollab\Admin;

use App\Controllers\Controller;
use App\Events\OpenCollab\ContractPublishedEvent;
use App\Framework\Http\JsonResponse;
use App\Framework\Http\Request;
use App\Framework\Notifications\NotificationDispatcher;
use App\Framework\Support\SiteContext;
use App\Jobs\OpenCollab\ContractFanoutJob;
use App\Jobs\OpenCollab\GuidelineUpdatedFanoutJob;
use App\Repositories\OpenCollab\ContractRepository;

/**
 * Admin CRUD for contributor contracts.
 *
 * Routes:
 *   GET    /api/{site}/open-collab/admin/contracts           — list all versions
 *   GET    /api/{site}/open-collab/admin/contracts/latest    — latest version
 *   POST   /api/{site}/open-collab/admin/contracts           — create new version
 *   GET    /api/{site}/open-collab/admin/contracts/{id}      — show one
 *   PUT    /api/{site}/open-collab/admin/contracts/{id}      — update content
 *   DELETE /api/{site}/open-collab/admin/contracts/{id}      — delete (non-latest only)
 */
class AdminContractController extends Controller
{
    public function __construct(
        private readonly ContractRepository $contractRepository
    )
    {
        parent::__construct();
    }

    public function index(): JsonResponse
    {
        $contracts = $this->contractRepository->getContractsForSite(SiteContext::getId());
        return $this->jsonResponse(
            $contracts->map(fn($c) => $this->formatContract($c))->toArray()
        );
    }

    private function formatContract(\App\Models\Contract $contract): array
    {
        return [
            'id' => $contract->id,
            'site_id' => $contract->site_id,
            'version' => $contract->version,
            'content' => $contract->content,
            'created_at' => $contract->created_at,
        ];
    }

    public function latest(): JsonResponse
    {
        $contract = $this->contractRepository->latestForSite(SiteContext::getId());
        if (!$contract) {
            return $this->errorResponse('No contract found for this site.', 404);
        }
        return $this->jsonResponse(['contract' => $this->formatContract($contract)]);
    }

    public function show(int $id): JsonResponse
    {
        $contract = \App\Models\Contract::find($id);
        if (!$contract || (int)$contract->site_id !== SiteContext::getId()) {
            return $this->errorResponse('Contract not found.', 404);
        }
        return $this->jsonResponse(['contract' => $this->formatContract($contract)]);
    }

    public function store(Request $request): JsonResponse
    {
        $body = json_decode(file_get_contents('php://input'), true) ?? [];
        $content = trim($body['content'] ?? $request->input('content', ''));

        if (empty($content)) {
            return $this->errorResponse('Contract content is required.', 422);
        }
        if (mb_strlen($content) < 50) {
            return $this->errorResponse('Contract content must be at least 50 characters.', 422);
        }

        $siteId = SiteContext::getId();
        $latest = $this->contractRepository->latestForSite($siteId);
        $nextVersion = $latest ? $latest->version + 1 : 1;

        $contract = \App\Models\Contract::create([
            'site_id' => $siteId,
            'version' => $nextVersion,
            'content' => $content,
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        event(new ContractPublishedEvent($contract, $siteId, 1));

        dispatch(ContractFanoutJob::for($contract->id, SiteContext::getId()));


        return $this->jsonResponse([
            'contract' => $this->formatContract($contract),
            'message' => "Contract version {$nextVersion} created.",
        ], 201);
    }

    /**
     * PUT /api/{site}/open-collab/admin/contracts/{id}
     * Updates the content of an existing contract version.
     * Only allowed if no contributor has signed this version.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $contract = \App\Models\Contract::find($id);
        if (!$contract || (int)$contract->site_id !== SiteContext::getId()) {
            return $this->errorResponse('Contract not found.', 404);
        }

        // Guard: do not allow editing a signed contract
        if ($this->contractRepository->hasAnySigned($id)) {
            return $this->errorResponse(
                'This contract version has been signed and cannot be edited. Create a new version instead.',
                409
            );
        }

        $content = trim($request->input('content', ''));

        if (empty($content)) {
            return $this->errorResponse('Contract content is required.', 422);
        }
        if (mb_strlen($content) < 50) {
            return $this->errorResponse('Contract content must be at least 50 characters.', 422);
        }

        $contract->update(['content' => $content]);

        return $this->jsonResponse([
            'contract' => $this->formatContract($contract->fresh()),
            'message' => "Contract version {$contract->version} updated.",
        ]);
    }

    /**
     * DELETE /api/{site}/open-collab/admin/contracts/{id}
     * Only the latest version can be deleted, and only if unsigned.
     */
    public function destroy(int $id): JsonResponse
    {
        $contract = \App\Models\Contract::find($id);
        if (!$contract || (int)$contract->site_id !== SiteContext::getId()) {
            return $this->errorResponse('Contract not found.', 404);
        }

        $latest = $this->contractRepository->latestForSite(SiteContext::getId());
        if (!$latest || $latest->id !== $contract->id) {
            return $this->errorResponse(
                'Only the latest contract version can be deleted.',
                409
            );
        }

        if ($this->contractRepository->hasAnySigned($id)) {
            return $this->errorResponse(
                'This contract version has been signed and cannot be deleted.',
                409
            );
        }

        $contract->delete();

        return $this->successResponse("Contract version {$contract->version} deleted.");
    }
}