<?php

namespace App\Controllers\Crm;

use App\Controllers\Controller;
use App\Framework\Authorization\Auth;
use App\Framework\Http\JsonResponse;
use App\Framework\Http\Request;
use App\Requests\Crm\DisableChargingRequest;
use App\Services\Billing\ChargingPolicyService;
use App\Services\Billing\PaymentService;
use Exception;

class CrmChargingController extends Controller
{
    public function __construct(
        private readonly ChargingPolicyService $chargingPolicyService,
        private readonly PaymentService        $paymentService,
    ) {
        parent::__construct();
    }

    /**
     * POST crm/members/{memberId}/charging/disable
     */
    public function disable(int $memberId, DisableChargingRequest $request): JsonResponse
    {
        try {
            $data       = $request->validated();
            $disabledBy = Auth::id();

            $member = $this->chargingPolicyService->disableCharging(
                $memberId,
                $disabledBy,
                $data['reason'] ?? null,
            );

            return $this->resourceResponse(['member' => $member->toArray()]);
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 422);
        }
    }

    /**
     * POST crm/members/{memberId}/charging/enable
     */
    public function enable(int $memberId, Request $request): JsonResponse
    {
        try {
            $enabledBy = Auth::id();

            $member = $this->chargingPolicyService->enableCharging($memberId, $enabledBy);

            return $this->resourceResponse(['member' => $member->toArray()]);
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 422);
        }
    }

    /**
     * POST crm/members/{memberId}/payments/{paymentId}/retry
     *
     * Retries a failed Stripe payment intent.
     * Enforces charging_disabled flag before attempting.
     */
    public function retryPayment(int $memberId, int $paymentId, Request $request): JsonResponse
    {
        try {
            $this->chargingPolicyService->assertChargingAllowed($memberId);

            $payment = $this->paymentService->retryPayment($paymentId);

            return $this->resourceResponse(['payment' => $payment->toArray()]);
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 422);
        }
    }
}