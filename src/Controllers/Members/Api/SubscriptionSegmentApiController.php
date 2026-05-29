<?php

namespace App\Controllers\Members\Api;

use App\Controllers\Controller;
use App\Framework\Http\JsonResponse;
use App\Repositories\MemberInsights\SubscriptionSegmentRepository;
use App\Repositories\Subscriptions\SubscriptionRepository;

class SubscriptionSegmentApiController extends Controller
{
    public function __construct(
        private readonly SubscriptionSegmentRepository $subscriptionSegmentRepository,
        private readonly SubscriptionRepository        $subscriptionRepository,
    ) {
        parent::__construct();
    }

    /**
     * GET /api/subscriptions/{id}/segment
     */
    public function show(int $subscriptionId): JsonResponse
    {
        $subscription = $this->subscriptionRepository->find($subscriptionId);

        if ($subscription === null) {
            return $this->errorResponse("Subscription #{$subscriptionId} not found.", 404);
        }

        $assignment = $this->subscriptionSegmentRepository->findActive($subscriptionId);

        if ($assignment === null) {
            return $this->resourceResponse(['segment' => null]);
        }

        return $this->resourceResponse([
            'segment' => [
                'id'          => $assignment->segment->id,
                'key'         => $assignment->segment->key,
                'name'        => $assignment->segment->name,
                'assigned_at' => $assignment->assigned_at->format('c'),
                'status'      => $assignment->status->value,
            ],
        ]);
    }
}