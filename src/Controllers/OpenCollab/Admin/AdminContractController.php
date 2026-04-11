<?php

namespace App\Controllers\OpenCollab\Admin;

use App\Controllers\Controller;
use App\Framework\Http\JsonResponse;
use App\Framework\Http\Request;
use App\Framework\Support\SiteContext;
use App\Repositories\OpenCollab\ContractRepository;

/**
 * Admin CRUD for contributor contracts.
 *
 * Routes:
 *   GET  /api/{site}/open-collab/admin/contracts           — list all versions
 *   GET  /api/{site}/open-collab/admin/contracts/latest    — latest version
 *   POST /api/{site}/open-collab/admin/contracts           — create new version
 *   GET  /api/{site}/open-collab/admin/contracts/{id}      — show one
 */
class AdminContractController extends Controller
{
    public function __construct(
        private readonly ContractRepository $contractRepository,
    )
    {
        parent::__construct();
    }

    /**
     * GET /api/{site}/open-collab/admin/contracts
     */
    public function index(): JsonResponse
    {
        $contracts = \App\Models\Contract::where('site_id', SiteContext::getId())
            ->orderByDesc('version')
            ->get();

        return $this->resourceResponse(
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

    /**
     * GET /api/{site}/open-collab/admin/contracts/latest
     */
    public function latest(): JsonResponse
    {
        $contract = $this->contractRepository->latestForSite(SiteContext::getId());

        if (!$contract) {
            return $this->errorResponse('No contract found for this site.', 404);
        }

        return $this->jsonResponse(['contract' => $this->formatContract($contract)]);
    }

    /**
     * GET /api/{site}/open-collab/admin/contracts/{id}
     */
    public function show(int $id): JsonResponse
    {
        $contract = \App\Models\Contract::find($id);

        if (!$contract || (int)$contract->site_id !== SiteContext::getId()) {
            return $this->errorResponse('Contract not found.', 404);
        }

        return $this->jsonResponse(['contract' => $this->formatContract($contract)]);
    }

    // ─────────────────────────────────────────────────────────────────────────

    /**
     * POST /api/{site}/open-collab/admin/contracts
     * Creates a new version. Version is auto-incremented from the current max.
     * Body: { content: string }
     */
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

        return $this->jsonResponse([
            'contract' => $this->formatContract($contract),
            'message' => "Contract version {$nextVersion} created.",
        ], 201);
    }
}