<?php

namespace App\Controllers\Rewards;

use App\Controllers\Controller;
use App\Framework\Authorization\Auth;
use App\Framework\Http\JsonResponse;
use App\Framework\Http\Request;
use App\Repositories\Rewards\RewardAuditLogRepository;

class RewardAuditLogController extends Controller
{
    public function __construct(
        private readonly RewardAuditLogRepository $auditLogRepository
    )
    {
        parent::__construct();
    }

    public function index(Request $request): JsonResponse
    {
        if (!Auth::check()) {
            return $this->jsonResponse(['success' => false, 'message' => 'Unauthorized'], 401);
        }

        $limit = $request->input('limit', 50);
        $logs = $this->auditLogRepository->getRecentLogs($limit);

        return $this->resourceResponse([
            'success' => true,
            'logs' => $logs->toArray()
        ]);
    }

    public function getForReward(int $rewardId): JsonResponse
    {
        if (!Auth::check()) {
            return $this->jsonResponse(['success' => false, 'message' => 'Unauthorized'], 401);
        }

        $logs = $this->auditLogRepository->getLogsForReward($rewardId);

        return $this->resourceResponse([
            'success' => true,
            'logs' => $logs->toArray()
        ]);
    }

    public function getByAction(Request $request, string $action): JsonResponse
    {
        if (!Auth::check()) {
            return $this->jsonResponse(['success' => false, 'message' => 'Unauthorized'], 401);
        }

        $limit = $request->input('limit', 100);
        $logs = $this->auditLogRepository->getLogsByAction($action, $limit);

        return $this->resourceResponse([
            'success' => true,
            'logs' => $logs->toArray()
        ]);
    }

    public function getByDateRange(Request $request): JsonResponse
    {
        if (!Auth::check()) {
            return $this->jsonResponse(['success' => false, 'message' => 'Unauthorized'], 401);
        }

        $dateFrom = $request->input('date_from');
        $dateTo = $request->input('date_to');

        if (!$dateFrom || !$dateTo) {
            return $this->errorResponse('Date range required', 422);
        }

        $logs = $this->auditLogRepository->getLogsByDateRange($dateFrom, $dateTo);

        return $this->resourceResponse([
            'success' => true,
            'logs' => $logs->toArray()
        ]);
    }
}