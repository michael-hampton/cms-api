<?php

namespace App\Controllers\Crm;

use App\Controllers\Controller;
use App\Framework\Authorization\Auth;
use App\Framework\Http\JsonResponse;
use App\Framework\Http\Request;
use App\Framework\Support\SiteContext;
use App\Services\Billing\OrderService;

class CrmOrderController extends Controller
{
    public function __construct(
        private readonly OrderService $orderService,
    )
    {
        parent::__construct();
    }

    public function index(Request $request): JsonResponse
    {
        if (!Auth::check()) {
            return $this->errorResponse('Unauthorized', 401);
        }

        $result = $this->orderService->searchForCrm(SiteContext::getId(), [
            'search' => $request->get('search'),
            'status' => $request->get('status'),
            'payment_status' => $request->get('payment_status'),
            'date_from' => $request->get('date_from'),
            'date_to' => $request->get('date_to'),
            'min_total' => $request->get('min_total'),
            'max_total' => $request->get('max_total'),
            'member_id' => $request->get('member_id'),
            'sort_by' => $request->get('sort_by', 'created_at'),
            'sort_order' => $request->get('sort_order', 'desc'),
            'page' => (int)$request->get('page', 1),
            'per_page' => (int)$request->get('per_page', 20),
        ]);

        return $this->resourceResponse([
            'success' => true,
            'items' => $result['items'],
            'pagination' => $result['pagination'],
        ]);
    }
}
