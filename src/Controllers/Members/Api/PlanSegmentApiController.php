<?php

namespace App\Controllers\Members\Api;

use App\Controllers\Controller;
use App\Framework\Http\JsonResponse;
use App\Requests\Members\AssignSegmentToPlanRequest;
use App\Services\MemberInsights\Segmentation\PlanSegmentService;

class PlanSegmentApiController extends Controller
{
    public function __construct(
        private readonly PlanSegmentService $service,
    ) {
        parent::__construct();
    }

    /**
     * POST /api/plans/{id}/segments/assign
     */
    public function assign(int $planId, AssignSegmentToPlanRequest $request): JsonResponse
    {
        try {
            $assignment = $this->service->assign(
                $planId,
                (int) $request->validated()['segment_id'],
                $request->only(['priority', 'is_active', 'starts_at', 'ends_at']),
            );

            return $this->resourceResponse($this->format($assignment), 201);
        } catch (\InvalidArgumentException $e) {
            $status = str_contains(strtolower($e->getMessage()), 'not found') ? 404 : 422;
            return $this->errorResponse($e->getMessage(), $status);
        }
    }

    /**
     * DELETE /api/plans/{id}/segments/{segmentId}
     */
    public function remove(int $planId, int $segmentId): JsonResponse
    {
        try {
            $this->service->remove($planId, $segmentId);
            return $this->jsonResponse(['message' => 'Segment assignment removed.']);
        } catch (\InvalidArgumentException $e) {
            return $this->errorResponse($e->getMessage(), 404);
        }
    }

    // -------------------------------------------------------------------------

    private function format(\App\Models\PlanSegment $assignment): array
    {
        return [
            'id'         => $assignment->id,
            'plan_id'    => $assignment->plan_id,
            'segment_id' => $assignment->segment_id,
            'priority'   => $assignment->priority,
            'is_active'  => $assignment->is_active,
            'starts_at'  => $assignment->starts_at?->toIso8601String(),
            'ends_at'    => $assignment->ends_at?->toIso8601String(),
        ];
    }
}