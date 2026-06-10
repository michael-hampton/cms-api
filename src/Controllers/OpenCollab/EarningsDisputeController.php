<?php

namespace App\Controllers\OpenCollab;

use App\Controllers\Controller;
use App\Framework\Authorization\Auth;
use App\Framework\Http\JsonResponse;
use App\Framework\Http\Request;
use App\Framework\Support\SiteContext;
use App\Models\EarningsDispute;
use App\Repositories\OpenCollab\EarningsDisputeRepository;
use App\Services\OpenCollab\EarningsDisputeService;
use InvalidArgumentException;
use RuntimeException;

/**
 * Routes:
 *   POST /api/{site}/open-collab/disputes                        — contributor: raise dispute
 *   GET  /api/{site}/open-collab/disputes                        — contributor: own disputes
 *   GET  /api/{site}/open-collab/admin/disputes                  — admin: all open disputes
 *   POST /api/{site}/open-collab/admin/disputes/{id}/resolve     — admin: resolve
 *   POST /api/{site}/open-collab/admin/disputes/{id}/reject      — admin: reject
 */
class EarningsDisputeController extends Controller
{
    public function __construct(
        private readonly EarningsDisputeService    $disputeService,
        private readonly EarningsDisputeRepository $disputeRepository,
    )
    {
        parent::__construct();
    }

    // ── Contributor endpoints ─────────────────────────────────────────────────

    /**
     * POST /api/{site}/open-collab/disputes
     * Body: { earnings_ledger_id: int, reason: string }
     */
    public function store(Request $request): JsonResponse
    {
        if (!Auth::check()) {
            return $this->errorResponse('Not logged in', 401);
        }

        $ledgerId = $request->get('earnings_ledger_id') ?: 0;
        $reason = trim($request->get('reason') ?? '');

        if ($ledgerId <= 0) {
            return $this->errorResponse('A valid earnings_ledger_id is required.', 422);
        }

        if (mb_strlen($reason) < 10) {
            return $this->errorResponse('Reason must be at least 10 characters.', 422);
        }

        try {
            $dispute = $this->disputeService->raise(
                userId: Auth::id(),
                ledgerId: $ledgerId,
                reason: $reason,
            );

            return $this->jsonResponse([
                'dispute' => $this->formatDispute($dispute),
                'message' => 'Dispute raised. Our team will review it shortly.',
            ], 201);
        } catch (RuntimeException $e) {
            return $this->errorResponse($e->getMessage(), 409);
        } catch (InvalidArgumentException $e) {
            return $this->errorResponse($e->getMessage(), 422);
        }
    }

    private function formatDispute(EarningsDispute $dispute): array
    {
        return [
            'id' => $dispute->id,
            'user_id' => $dispute->user_id,
            'earnings_ledger_id' => $dispute->earnings_ledger_id,
            'reason' => $dispute->reason,
            'status' => $dispute->status,
            'admin_notes' => $dispute->admin_notes,
            'created_at' => $dispute->created_at,
            'updated_at' => $dispute->updated_at,
        ];
    }

    // ── Admin endpoints ───────────────────────────────────────────────────────

    /**
     * GET /api/{site}/open-collab/disputes
     */
    public function index(): JsonResponse
    {
        $disputes = $this->disputeRepository->forContributor(Auth::id());

        return $this->jsonResponse(
            $disputes->map(fn($d) => $this->formatDispute($d))->toArray()
        );
    }

    /**
     * GET /api/{site}/open-collab/admin/disputes
     */
    public function adminIndex(): JsonResponse
    {
        $disputes = $this->disputeRepository->openForSite(SiteContext::getId());

        return $this->jsonResponse(
            $disputes->map(fn($d) => $this->formatDispute($d))->toArray()
        );
    }

    /**
     * POST /api/{site}/open-collab/admin/disputes/{id}/resolve
     * Body: { admin_notes: string, adjustment_amount?: int, adjustment_reason?: string }
     */
    public function resolve(int $id, Request $request): JsonResponse
    {
        $adminNotes = trim($request->get('admin_notes') ?? '');
        $adjustmentAmount = $request->get('adjustment_amount') ?: null;
        $adjustmentReason = trim($request->get('adjustment_reason', '')) ?: null;

        if (empty($adminNotes)) {
            return $this->errorResponse('Admin notes are required when resolving a dispute.', 422);
        }

        try {
            $dispute = $this->disputeService->resolve(
                disputeId: $id,
                adminId: Auth::id(),
                adminNotes: $adminNotes,
                adjustmentAmount: $adjustmentAmount,
                adjustmentReason: $adjustmentReason,
            );

            return $this->jsonResponse([
                'dispute' => $this->formatDispute($dispute),
                'message' => 'Dispute resolved.',
            ]);
        } catch (InvalidArgumentException $e) {
            return $this->errorResponse($e->getMessage(), 422);
        }
    }

    // ── Formatter ─────────────────────────────────────────────────────────────

    /**
     * POST /api/{site}/open-collab/admin/disputes/{id}/reject
     * Body: { admin_notes: string }
     */
    public function reject(int $id, Request $request): JsonResponse
    {
        $adminNotes = trim($request->get('admin_notes') ?? '');

        if (empty($adminNotes)) {
            return $this->errorResponse('Admin notes are required when rejecting a dispute.', 422);
        }

        try {
            $dispute = $this->disputeService->reject(
                disputeId: $id,
                adminId: Auth::id(),
                adminNotes: $adminNotes,
            );

            return $this->jsonResponse([
                'dispute' => $this->formatDispute($dispute),
                'message' => 'Dispute rejected.',
            ]);
        } catch (InvalidArgumentException $e) {
            return $this->errorResponse($e->getMessage(), 422);
        }
    }
}