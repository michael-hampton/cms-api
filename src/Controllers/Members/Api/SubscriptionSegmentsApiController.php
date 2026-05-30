<?php

namespace App\Controllers\Members\Api;

use App\Controllers\Controller;
use App\Enums\Member\SegmentSubjectType;
use App\Framework\Http\JsonResponse;
use App\Framework\Http\Request;
use App\Repositories\MemberInsights\SegmentRepository;

/**
 * GET /api/segments/subscription
 *
 * Returns subscription segments enriched with derived counts that the
 * SegmentAdminApiController::index cannot provide because it reads from
 * the segments table alone.
 */
class SubscriptionSegmentsApiController extends Controller
{
    public function __construct(
        private readonly SegmentRepository $segmentRepository,
    ) {
        parent::__construct();
    }

    public function index(Request $request): JsonResponse
    {
        $result = $this->segmentRepository->getSubscriptionSegmentsListing(
            page:       (int) $request->get('page', 1),
            perPage:    (int) $request->get('per_page', 20),
            search:     $request->get('search') ?: null,
            isActive:   $this->parseIsActive($request->get('is_active')),
            sortBy:     $request->get('sort_by', 'name'),
            sortOrder:  $request->get('sort_order', 'asc'),
        );

        return $this->resourceResponse([
            // Changed the closure parameter type hint to array
            'items'      => array_map(
                fn (array $segment) => $this->format($segment),
                $result['items'] instanceof \App\Framework\Support\Collection
                    ? $result['items']->all()
                    : $result['items'],
            ),
            'pagination' => $result['pagination'],
        ]);
    }

    // -------------------------------------------------------------------------

    /**
     * Format raw database result array into API response format.
     */
    private function format(array $segment): array
    {
        // Safely format the date string if it exists from the DB raw array
        $lastRecalculated = null;
        if (!empty($segment['last_recalculated_at'])) {
            $lastRecalculated = is_string($segment['last_recalculated_at'])
                ? date('c', strtotime($segment['last_recalculated_at']))
                : $segment['last_recalculated_at'];
        }

        return [
            'id'                          => (int) $segment['id'],
            'key'                         => $segment['key'],
            'name'                        => $segment['name'],
            'description'                 => $segment['description'],
            'category'                    => $segment['category'],
            'subject_type'                => $segment['subject_type'],
            'priority'                    => $segment['priority'] !== null ? (int) $segment['priority'] : null,
            'is_active'                   => (bool) $segment['is_active'],
            'starts_at'                   => $segment['starts_at'] ?? null,
            'ends_at'                     => $segment['ends_at'] ?? null,
            'last_recalculated_at'        => $lastRecalculated,
            'assigned_plan_count'         => (int) ($segment['assigned_plan_count'] ?? 0),
            'matching_subscription_count' => (int) ($segment['matching_subscription_count'] ?? 0),
        ];
    }

    /**
     * Parse the is_active query param.
     * "1" / "true" → true, "0" / "false" → false, absent → null (no filter).
     */
    private function parseIsActive(mixed $value): ?bool
    {
        if ($value === null || $value === '') {
            return null;
        }

        return filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
    }
}