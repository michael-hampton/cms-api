<?php

namespace App\Controllers\Members\Api;

use App\Controllers\Controller;
use App\Framework\Exceptions\ValidationException;
use App\Framework\Http\JsonResponse;
use App\Models\PlanSegment;
use App\Repositories\MemberInsights\PlanSegmentRepository;
use App\Repositories\MemberInsights\SegmentRepository;
use App\Requests\Members\AssignPlansToSegmentRequest;
use App\Requests\Members\AssignSegmentToPlanRequest;
use App\Services\MemberInsights\Segmentation\PlanSegmentService;

class PlanSegmentApiController extends Controller
{
    public function __construct(
        private readonly PlanSegmentService    $service,
        private readonly SegmentRepository     $segmentRepository,
        private readonly PlanSegmentRepository $planSegmentRepository,
    ) {
        parent::__construct();
    }

    /**
     * POST /api/subscription-plans/{planId}/segments/assign
     */
    public function assign(int $planId, AssignSegmentToPlanRequest $request): JsonResponse
    {
        try {
            $assignment = $this->service->assign(
                $planId,
                (int) $request->validated()['segment_id'],
                $request->only(['priority', 'is_active', 'starts_at', 'ends_at']),
            );

            // plan relation is not eager-loaded after a create(); load it so format() can
            // include plan_name without a separate query per call-site.
            $assignment->load('plan');

            return $this->resourceResponse($this->format($assignment), 201);
        } catch (ValidationException $exception) {
            return $this->errorResponse($exception->getMessage(), 422, $exception->getErrors());
        } catch (\InvalidArgumentException $e) {
            $status = str_contains(strtolower($e->getMessage()), 'not found') ? 404 : 422;
            return $this->errorResponse($e->getMessage(), $status);
        }
    }

    /**
     * POST /api/segments/{segmentId}/subscription-plans/assign
     *
     * Assigns one or more plans to a segment.
     * Already-assigned plans are skipped and reported in the response.
     */
    public function assignPlansToSegment(int $segmentId, AssignPlansToSegmentRequest $request): JsonResponse
    {
        $segment = $this->segmentRepository->findWithRules($segmentId);

        if ($segment === null) {
            return $this->errorResponse("Segment #{$segmentId} not found.", 404);
        }

        $options = array_filter(
            $request->only(['priority', 'is_active', 'starts_at', 'ends_at']),
            fn ($v) => $v !== null,
        );

        try {
            $results = $this->service->assignPlans(
                $segmentId,
                $request->validated()['plan_ids'],
                $options,
            );
        } catch (\InvalidArgumentException $e) {
            $status = str_contains(strtolower($e->getMessage()), 'not found') ? 404 : 422;
            return $this->errorResponse($e->getMessage(), $status);
        }

        // Eager-load plan on every newly created assignment so format() can return plan_name.
        $assigned = array_map(function (PlanSegment $assignment) {
            $assignment->load('plan');
            return $this->format($assignment);
        }, $results['assigned']);

        return $this->resourceResponse([
            'assigned' => $assigned,
            'skipped'  => $results['skipped'],
        ], 201);
    }

    /**
     * GET /api/segments/{segmentId}/subscription-plans
     *
     * Returns the plans currently assigned to a segment.
     * The repository already eager-loads `plan` via with('plan'), so plan_name is always
     * available here without extra queries.
     */
    public function plansForSegment(int $segmentId): JsonResponse
    {
        $segment = $this->segmentRepository->findWithRules($segmentId);

        if ($segment === null) {
            return $this->errorResponse("Segment #{$segmentId} not found.", 404);
        }

        $assignments = $this->planSegmentRepository->getAssignmentsForSegment($segmentId);

        return $this->resourceResponse([
            'items' => array_map(
                fn (PlanSegment $assignment) => $this->format($assignment),
                $assignments instanceof \App\Framework\Support\Collection
                    ? $assignments->all()
                    : $assignments,
            ),
        ]);
    }

    /**
     * DELETE /api/subscription-plans/{planId}/segments/{segmentId}
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

    /**
     * Format a PlanSegment for API responses.
     *
     * `plan_name` is read from the eager-loaded `plan` relation.
     * Every code path that calls format() must ensure plan is loaded first
     * (either via with('plan') in the repository query, or via $assignment->load('plan')
     * after a create/update).
     */
    private function format(PlanSegment $assignment): array
    {
        return [
            'id'         => $assignment->id,
            'plan_id'    => $assignment->plan_id,
            'plan_name'  => $assignment->plan?->name,
            'segment_id' => $assignment->segment_id,
            'priority'   => $assignment->priority,
            'is_active'  => $assignment->is_active,
            'starts_at'  => $assignment->starts_at?->format('c'),
            'ends_at'    => $assignment->ends_at?->format('c'),
        ];
    }
}