<?php

namespace App\Controllers\OpenCollab;

use App\Controllers\Controller;
use App\Exceptions\OpenCollab\OnboardingIncompleteException;
use App\Framework\Authorization\Auth;
use App\Framework\Exceptions\UnauthorizedException;
use App\Framework\Exceptions\ValidationException;
use App\Framework\Http\JsonResponse;
use App\Framework\Http\Request;
use App\Framework\Support\SiteContext;
use App\Models\Payout;
use App\Models\PayoutLedgerEntry;
use App\Models\PayoutLiabilityRecovery;
use App\Repositories\OpenCollab\PayoutRepository;
use App\Requests\OpenCollab\MarkPayoutPaidRequest;
use App\Requests\OpenCollab\RequestPayoutRequest;
use App\Services\OpenCollab\OpenCollabAuthorizationService;
use App\Services\OpenCollab\PayoutService;
use InvalidArgumentException;
use RuntimeException;

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
        private readonly PayoutService                  $payoutService,
        private readonly PayoutRepository               $payoutRepository,
        private readonly OpenCollabAuthorizationService $authorization,
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

        return $this->jsonResponse(
            $payouts->map(fn($p) => $this->formatPayout($p))->toArray()
        );
    }

    private function formatPayout(Payout $payout): array
    {
        $deductionsTotal = $this->deductionsTotalForPayout((int)$payout->id);
        $ledgerEntryCount = $this->ledgerEntryCountForPayout((int)$payout->id);

        return [
            'id' => $payout->id,
            'user_id' => $payout->user_id,

            'amount' => $payout->amount,
            'amount_pence' => $payout->amount,
            'amount_pounds' => number_format($payout->amount / 100, 2, '.', ''),

            'currency' => $payout->currency,
            'status' => $payout->status,
            'method' => $payout->method,

            'batch_id' => $payout->batch_id,
            'accrual_window_id' => $payout->accrual_window_id,
            'idempotency_key' => $payout->idempotency_key,

            'provider' => $payout->provider,
            'provider_status' => $payout->provider_status,
            'provider_transfer_id' => $payout->provider_transfer_id,
            'provider_payout_id' => $payout->provider_payout_id,
            'provider_response_json' => $payout->provider_response_json,

            'deductions_total_pence' => $deductionsTotal,
            'deductions_total_pounds' => number_format($deductionsTotal / 100, 2, '.', ''),
            'ledger_entry_count' => $ledgerEntryCount,

            'reference' => $payout->reference,
            'notes' => $payout->notes,

            'approved_at' => $payout->approved_at,
            'processed_at' => $payout->processed_at,
            'rejected_at' => $payout->rejected_at,
            'rejection_reason' => $payout->rejection_reason,
            'created_at' => $payout->created_at,
        ];
    }

    private function deductionsTotalForPayout(int $payoutId): int
    {
        if (!class_exists(PayoutLiabilityRecovery::class)) {
            return 0;
        }

        return (int)PayoutLiabilityRecovery::where('payout_id', $payoutId)
            ->sum('amount');
    }

    private function ledgerEntryCountForPayout(int $payoutId): int
    {
        if (!class_exists(PayoutLedgerEntry::class)) {
            return 0;
        }

        return (int)PayoutLedgerEntry::where('payout_id', $payoutId)
            ->count();
    }

    /**
     * GET /api/{site}/open-collab/payouts/balance
     */
    /**
     * GET /api/{site}/open-collab/payouts/balance
     */
    public function balance(): JsonResponse
    {
        $userId = Auth::id();
        $siteId = SiteContext::getId();

        $balance = $this->payoutService->availableBalance($userId, $siteId);

        return $this->jsonResponse([
            'balance_pence' => $balance,
            'balance_pounds' => number_format($balance / 100, 2, '.', ''),
        ]);
    }

    // ── Admin endpoints ───────────────────────────────────────────────────────

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
        } catch (OnboardingIncompleteException $e) {
            return $this->errorResponse($e->getMessage(), 409, [
                'pending_steps' => $e->getPendingSteps(),
                'redirect' => '/contributor/onboarding',
            ]);
        } catch (InvalidArgumentException $e) {
            return $this->errorResponse($e->getMessage(), 422);
        } catch (RuntimeException $e) {
            return $this->errorResponse($e->getMessage(), 409);
        }
    }

    /**
     * GET /api/{site}/open-collab/admin/payouts
     * All payouts for the site, paginated, newest first.
     */
    public function adminIndex(): JsonResponse
    {
        try {
            $this->authorization->assertAny(Auth::id(), SiteContext::getId(), [
                'payout.view',
                'payout.approve',
            ]);
        } catch (UnauthorizedException $e) {
            return $this->errorResponse($e->getMessage(), 403);
        }

        $perPage = (int)($_GET['per_page'] ?? 25);
        $payouts = $this->payoutRepository->forSite(SiteContext::getId(), $perPage);

        // forSite returns a paginated array from the framework
        $items = is_array($payouts) ? ($payouts['data'] ?? $payouts) : $payouts;

        return $this->jsonResponse(
            $items->map(fn($p) => $this->formatPayout($p))->all()
        );
    }

    /**
     * POST /api/{site}/open-collab/admin/payouts/{id}/approve
     */
    public function approve(int $id): JsonResponse
    {
        if (!Auth::check()) {
            return $this->errorResponse('Not logged in', 422);
        }

        try {
            $this->authorization->assertAny(Auth::id(), SiteContext::getId(), ['payout.approve']);
        } catch (UnauthorizedException $e) {
            return $this->errorResponse($e->getMessage(), 403);
        }

        try {
            $payout = $this->payoutService->approve($id, Auth::id());

            return $this->jsonResponse([
                'payout' => $this->formatPayout($payout),
                'message' => 'Payout approved.',
            ]);
        } catch (InvalidArgumentException $e) {
            return $this->errorResponse($e->getMessage(), 422);
        }
    }

    /**
     * POST /api/{site}/open-collab/admin/payouts/{id}/paid
     */
    public function markPaid(MarkPayoutPaidRequest $request, int $id): JsonResponse
    {
        if (!Auth::check()) {
            return $this->errorResponse('Not logged in', 422);
        }

        try {
            $this->authorization->assertAny(Auth::id(), SiteContext::getId(), ['payout.mark_paid']);
        } catch (UnauthorizedException $e) {
            return $this->errorResponse($e->getMessage(), 403);
        }

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
        } catch (InvalidArgumentException $e) {
            return $this->errorResponse($e->getMessage(), 422);
        }
    }

    // ─────────────────────────────────────────────────────────────────────────

    /**
     * POST /api/{site}/open-collab/admin/payouts/{id}/reject
     */
    public function reject(int $id, Request $request): JsonResponse
    {
        if (!Auth::check()) {
            return $this->errorResponse('Not logged in', 422);
        }

        try {
            $this->authorization->assertAny(Auth::id(), SiteContext::getId(), ['payout.reject']);
        } catch (UnauthorizedException $e) {
            return $this->errorResponse($e->getMessage(), 403);
        }

        $reason = $request->input('reason');

        if (empty($reason)) {
            return $this->errorResponse('A rejection reason is required.', 422);
        }

        try {
            $payout = $this->payoutService->reject($id, Auth::id(), $reason);

            return $this->jsonResponse([
                'payout' => $this->formatPayout($payout),
                'message' => 'Payout rejected.',
            ]);
        } catch (InvalidArgumentException $e) {
            return $this->errorResponse($e->getMessage(), 422);
        }
    }

    /**
     * POST /api/{site}/open-collab/admin/payouts/{id}/retry
     */
    public function retry(int $id): JsonResponse
    {
        if (!Auth::check()) {
            return $this->errorResponse('Not logged in', 422);
        }

        try {
            $this->authorization->assertAny(Auth::id(), SiteContext::getId(), ['payout.approve']);
        } catch (UnauthorizedException $e) {
            return $this->errorResponse($e->getMessage(), 403);
        }

        try {
            $payout = $this->payoutService->retryStripeFailedPayout($id, Auth::id());

            return $this->jsonResponse([
                'payout' => $this->formatPayout($payout),
                'message' => 'Stripe payout retry queued.',
            ]);
        } catch (InvalidArgumentException $e) {
            return $this->errorResponse($e->getMessage(), 422);
        }
    }
}
