<?php

namespace App\Controllers\Members\Api;

use App\Controllers\Controller;
use App\Framework\Http\JsonResponse;
use App\Framework\Http\Request;
use App\Models\Subscription;
use App\Repositories\MemberInsights\SegmentRepository;
use App\Services\MemberInsights\Segmentation\SegmentPreviewService;

class SegmentPreviewApiController extends Controller
{
    public function __construct(
        private readonly SegmentPreviewService $previewService,
        private readonly SegmentRepository     $segmentRepository,
    ) {
        parent::__construct();
    }

    /**
     * POST /api/segments/{id}/preview
     */
    public function preview(int $segmentId, Request $request): JsonResponse
    {
        $segment = $this->segmentRepository->findWithRules($segmentId);

        if ($segment === null) {
            return $this->errorResponse("Segment #{$segmentId} not found.", 404);
        }

        $sampleSize = (int) $request->get('sample_size', 10);

        $result = $this->previewService->preview($segment, $sampleSize);

        return $this->resourceResponse([
            'count'  => $result['count'],
            'sample' => array_map(
                fn(Subscription $sub) => $this->formatSubscription($sub),
                $result['sample']
            ),
        ]);
    }

    // -------------------------------------------------------------------------

    private function formatSubscription(Subscription $subscription): array
    {
        return [
            'subscription_id' => $subscription->id,
            'plan_id'         => $subscription->plan_id,
            'status'          => $subscription->status,
        ];
    }
}