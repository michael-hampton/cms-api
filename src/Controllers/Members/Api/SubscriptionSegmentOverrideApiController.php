<?php

namespace App\Controllers\Members\Api;

use App\Controllers\Controller;
use App\Framework\Authorization\Auth;
use App\Framework\Exceptions\ValidationException;
use App\Framework\Http\JsonResponse;
use App\Requests\Members\ManualSegmentOverrideRequest;
use App\Services\MemberInsights\Segmentation\ManualSegmentOverrideService;

class SubscriptionSegmentOverrideApiController extends Controller
{
    public function __construct(
        private readonly ManualSegmentOverrideService $overrideService,
    ) {
        parent::__construct();
    }

    /**
     * POST /api/subscriptions/{subscription}/segment/assign
     */
    public function assign(int $subscriptionId, ManualSegmentOverrideRequest $request): JsonResponse
    {
        try {
            $data = $request->validated();
            $assignment = $this->overrideService->override(
                subscriptionId: $subscriptionId,
                segmentId: (int)$data['segment_id'],
                reason: $data['reason'],
                assignedByUserId: Auth::id(),
                expiresAt: $data['expires_at'] ?? null,
            );

            return $this->resourceResponse($this->format($assignment), 201);
        } catch (ValidationException $exception) {
          return $this->errorResponse($exception->getMessage(), 422, $exception->getErrors());
        } catch (\InvalidArgumentException $e) {
            $status = str_contains(strtolower($e->getMessage()), 'not found') ? 404 : 422;
            return $this->errorResponse($e->getMessage(), $status);
        }
    }

    // -------------------------------------------------------------------------

    private function format(\App\Models\SubscriptionSegment $assignment): array
    {
        return [
            'id'                   => $assignment->id,
            'subscription_id'      => $assignment->subscription_id,
            'segment_id'           => $assignment->segment_id,
            'source'               => $assignment->source,
            'reason'               => $assignment->reason,
            'expires_at'           => $assignment->expires_at?->format('c'),
            'assigned_by_user_id'  => $assignment->assigned_by_user_id,
            'assigned_at'          => $assignment->assigned_at?->format('c'),
            'status'               => $assignment->status,
        ];
    }
}
