<?php

namespace App\Controllers\OpenCollab\Admin;

use App\Controllers\Controller;
use App\Framework\Http\JsonResponse;
use App\Framework\Support\SiteContext;
use App\Services\OpenCollab\PaymentTermsService;

/**
 * Admin CRUD for per-site payment terms.
 *
 * Routes:
 *   GET  /api/{site}/open-collab/admin/payment-terms  — get current terms
 *   POST /api/{site}/open-collab/admin/payment-terms  — save / update terms
 */
class AdminPaymentTermsController extends Controller
{
    public function __construct(
        private readonly PaymentTermsService $paymentTermsService,
    )
    {
        parent::__construct();
    }

    /**
     * GET /api/{site}/open-collab/admin/payment-terms
     */
    public function show(): JsonResponse
    {
        $terms = $this->paymentTermsService->forSite(SiteContext::getId());

        return $this->jsonResponse([
            'payment_terms' => $this->formatTerms($terms),
        ]);
    }

    private function formatTerms(\App\Models\PaymentTerms $terms): array
    {
        return [
            'id' => $terms->id,
            'site_id' => $terms->site_id,
            'payout_delay_days' => $terms->payout_delay_days,
            'minimum_payout_amount' => $terms->minimum_payout_amount,
            'minimum_payout_pounds' => number_format($terms->minimum_payout_amount / 100, 2),
            'created_at' => $terms->created_at,
            'updated_at' => $terms->updated_at,
        ];
    }

    /**
     * POST /api/{site}/open-collab/admin/payment-terms
     * Body: { payout_delay_days: int, minimum_payout_amount: int }
     */
    public function save(): JsonResponse
    {
        $body = json_decode(file_get_contents('php://input'), true) ?? [];
        $payoutDelayDays = isset($body['payout_delay_days']) ? (int)$body['payout_delay_days'] : null;
        $minimumPayoutAmount = isset($body['minimum_payout_amount']) ? (int)$body['minimum_payout_amount'] : null;

        if ($payoutDelayDays === null) {
            return $this->errorResponse('payout_delay_days is required.', 422);
        }

        if ($minimumPayoutAmount === null) {
            return $this->errorResponse('minimum_payout_amount is required.', 422);
        }

        try {
            $terms = $this->paymentTermsService->save(
                siteId: SiteContext::getId(),
                payoutDelayDays: $payoutDelayDays,
                minimumPayoutAmount: $minimumPayoutAmount,
            );

            return $this->jsonResponse([
                'payment_terms' => $this->formatTerms($terms),
                'message' => 'Payment terms saved.',
            ]);
        } catch (\InvalidArgumentException $e) {
            return $this->errorResponse($e->getMessage(), 422);
        }
    }
}