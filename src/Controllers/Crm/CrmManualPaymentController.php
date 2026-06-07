<?php

namespace App\Controllers\Crm;

use App\Controllers\Controller;
use App\Controllers\Concerns\RequiresSitePermission;
use App\Framework\Authorization\Auth;
use App\Framework\Http\JsonResponse;
use App\Framework\Http\Request;
use App\Framework\Support\SiteContext;
use App\Repositories\Billing\ManualPaymentRepository;
use App\Repositories\Billing\PaymentRepository;
use App\Requests\Crm\CreateManualPaymentRequest;
use App\Services\Billing\ManualPaymentService;
use Exception;

class CrmManualPaymentController extends Controller
{
    use RequiresSitePermission;

    public function __construct(
        private readonly ManualPaymentService    $manualPaymentService,
        private readonly PaymentRepository $manualPaymentRepository,
    ) {
        parent::__construct();
    }

    /**
     * GET crm/members/{memberId}/manual-payments
     */
    public function index(int $memberId, Request $request): JsonResponse
    {
        if ($response = $this->requireSitePermission('crm.payment_methods.view')) {
            return $response;
        }

        try {
            $siteId  = SiteContext::getId();
            $page    = (int) $request->query('page', 1);
            $perPage = (int) $request->query('per_page', 15);

            $result = $this->manualPaymentRepository->findByMemberPaginated(
                $memberId,
                $siteId,
                $page,
                $perPage,
            );

            return $this->resourceResponse([
                'manual_payments' => $result['items']->toArray(),
                'pagination'      => [
                    'total'        => $result['total'],
                    'per_page'     => $result['per_page'],
                    'current_page' => $result['page'],
                    'last_page'    => $result['last_page'],
                ],
            ]);
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    /**
     * POST crm/members/{memberId}/manual-payments
     */
    public function store(int $memberId, CreateManualPaymentRequest $request): JsonResponse
    {
        if ($response = $this->requireSitePermission('crm.payment_methods.manage')) {
            return $response;
        }

        try {
            $data      = $request->validated();
            $siteId    = SiteContext::getId();
            $createdBy = Auth::id();

            $payment = $this->manualPaymentService->create(
                $memberId,
                $siteId,
                $createdBy,
                $data,
            );

            return $this->resourceResponse(['manual_payment' => $payment->toArray()], 201);
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 422);
        }
    }

    /**
     * DELETE crm/members/{memberId}/manual-payments/{id}
     */
    public function destroy(int $memberId, int $id): JsonResponse
    {
        if ($response = $this->requireSitePermission('crm.payment_methods.manage')) {
            return $response;
        }

        try {
            $this->manualPaymentService->delete($id, $memberId);
            return $this->successResponse('Manual payment deleted.');
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 422);
        }
    }
}
