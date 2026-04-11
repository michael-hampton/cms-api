<?php

namespace App\Controllers\OpenCollab;

use App\Controllers\Controller;
use App\Framework\Authorization\Auth;
use App\Framework\Exceptions\ValidationException;
use App\Framework\Http\JsonResponse;
use App\Framework\Support\SiteContext;
use App\Repositories\OpenCollab\PayoutRepository;
use App\Requests\OpenCollab\MarkPayoutPaidRequest;
use App\Requests\OpenCollab\RequestPayoutRequest;
use App\Services\OpenCollab\PayoutService;

/**
 * Routes:
 *   GET  /api/{site}/open-collab/payouts                     — contributor's own payouts
 *   GET  /api/{site}/open-collab/payouts/balance             — contributor's available balance
 *   POST /api/{site}/open-collab/payouts                     — contributor requests payout
 *   GET  /api/{site}/open-collab/admin/payouts               — admin: all payouts for site
 *   POST /api/{site}/open-collab/admin/payouts/{id}/approve  — admin: approve
 *   POST /api/{site}/open-collab/admin/payouts/{id}/paid     — admin: mark paid
 *   POST /api/{site}/open-collab/admin/payouts/{id}/reject   — admin: reject
 */
class PayoutController extends Controller
{
    public function __construct(
        private readonly PayoutService    $payoutService,
        private readonly PayoutRepository $payoutRepository,
    )
    {
        parent::__construct();
    }

    // ── Contributor endpoints ─────────────────────────────────────────────────

    /**
     * GET /api/{site}/open-collab/payouts
     * Returns the authenticated contributor's payout history, newest first.
     */
    public function index(): JsonResponse
    {
        $userId = Auth::id();
        $payouts = $this->payoutRepository->forContributor($userId);

        return $this->resourceResponse(
            $payouts->map(fn($p) => $this->formatPayout($p))->toArray()
        );
    }

    /**
     * GET /api/{site}/open-collab/payouts/balance
     */
    public function balance(): JsonResponse
    {
        $userId = Auth::id();
        $balance = $this->payoutService->availableBalance($userId);

        return $this->jsonResponse([
            'balance_pence' => $balance,
            'balance_pounds' => number_format($balance / 100, 2, '.', ''),
        ]);
    }

    /**
     * POST /api/{site}/open-collab/payouts
     * Contributor requests a payout for their full available balance.
     */
    public function request(RequestPayoutRequest $request): JsonResponse
    {
        try {
            $data = $request->validated();
            $payout = $this->payoutService->requestPayout(
                userId: Auth::id(),
                siteId: SiteContext::getId(),
                method: $data['method'],
            );

            return $this->jsonResponse([
                'payout' => $this->formatPayout($payout),
                'message' => 'Payout request submitted. Our team will process it shortly.',
            ], 201);
        } catch (ValidationException $e) {
            return $this->errorResponse('Validation failed', 422, $e->getErrors());
        } catch (\InvalidArgumentException $e) {
            return $this->errorResponse($e->getMessage(), 422);
        } catch (\RuntimeException $e) {
            return $this->errorResponse($e->getMessage(), 409);
        }
    }

    // ── Admin endpoints ───────────────────────────────────────────────────────

    /**
     * GET /api/{site}/open-collab/admin/payouts
     * All payouts for the site, paginated, newest first.
     */
    public function adminIndex(): JsonResponse
    {
        $perPage = (int)($_GET['per_page'] ?? 25);
        $payouts = $this->payoutRepository->forSite(SiteContext::getId(), $perPage);

        // forSite returns a paginated array from the framework
        $items = is_array($payouts) ? ($payouts['data'] ?? $payouts) : $payouts;

        if (is_object($items) && method_exists($items, 'toArray')) {
            $items = $items->toArray();
        }

        return $this->resourceResponse(
            array_map(fn($p) => $this->formatPayout(
                is_array($p) ? (object)$p : $p
            ), (array)$items)
        );
    }

    private function formatPayout(\App\Models\Payout $payout): array
    {
        return [
            'id' => $payout->id,
            'user_id' => $payout->user_id,
            'amount_pence' => $payout->amount,
            'amount_pounds' => number_format($payout->amount / 100, 2, '.', ''),
            'currency' => $payout->currency,
            'status' => $payout->status,
            'method' => $payout->method,
            'reference' => $payout->reference,
            'notes' => $payout->notes,
            'approved_at' => $payout->approved_at,
            'processed_at' => $payout->processed_at,
            'rejected_at' => $payout->rejected_at,
            'rejection_reason' => $payout->rejection_reason,
            'created_at' => $payout->created_at,
        ];
    }

    /**
     * POST /api/{site}/open-collab/admin/payouts/{id}/approve
     */
    public function approve(int $id): JsonResponse
    {
        try {
            $payout = $this->payoutService->approve($id, Auth::id());

            return $this->jsonResponse([
                'payout' => $this->formatPayout($payout),
                'message' => 'Payout approved.',
            ]);
        } catch (\InvalidArgumentException $e) {
            return $this->errorResponse($e->getMessage(), 422);
        }
    }

    /**
     * POST /api/{site}/open-collab/admin/payouts/{id}/paid
     */
    public function markPaid(MarkPayoutPaidRequest $request, int $id): JsonResponse
    {
        try {
            $data = $request->validated();
            $payout = $this->payoutService->markPaid(
                payoutId: $id,
                adminId: Auth::id(),
                reference: $data['reference'] ?? null,
                notes: $data['notes'] ?? null,
            );

            return $this->jsonResponse([
                'payout' => $this->formatPayout($payout),
                'message' => 'Payout marked as paid.',
            ]);
        } catch (ValidationException $e) {
            return $this->errorResponse('Validation failed', 422, $e->getErrors());
        } catch (\InvalidArgumentException $e) {
            return $this->errorResponse($e->getMessage(), 422);
        }
    }

    // ─────────────────────────────────────────────────────────────────────────

    /**
     * POST /api/{site}/open-collab/admin/payouts/{id}/reject
     */
    public function reject(int $id): JsonResponse
    {
        $reason = $_POST['reason'] ?? (json_decode(file_get_contents('php://input'), true)['reason'] ?? '');

        if (empty($reason)) {
            return $this->errorResponse('A rejection reason is required.', 422);
        }

        try {
            $payout = $this->payoutService->reject($id, Auth::id(), $reason);

            return $this->jsonResponse([
                'payout' => $this->formatPayout($payout),
                'message' => 'Payout rejected.',
            ]);
        } catch (\InvalidArgumentException $e) {
            return $this->errorResponse($e->getMessage(), 422);
        }
    }
}