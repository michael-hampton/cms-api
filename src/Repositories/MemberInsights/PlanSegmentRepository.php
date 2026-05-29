<?php

namespace App\Repositories\MemberInsights;

use App\Models\Model;
use App\Models\PlanSegment;
use App\Repositories\Repository;

class PlanSegmentRepository extends Repository
{
    public function findByPlanAndSegment(int $planId, int $segmentId): ?PlanSegment
    {
        return PlanSegment::where('plan_id', $planId)
            ->where('segment_id', $segmentId)
            ->first();
    }

    public function assign(int $planId, int $segmentId, array $attributes = []): Model
    {
        return PlanSegment::create([
            'plan_id'    => $planId,
            'segment_id' => $segmentId,
            'priority'   => $attributes['priority'] ?? 100,
            'is_active'  => $attributes['is_active'] ?? true,
            'starts_at'  => $attributes['starts_at'] ?? null,
            'ends_at'    => $attributes['ends_at'] ?? null,
        ]);
    }

    public function removeByPlanAndSegment(int $planId, int $segmentId): void
    {
        PlanSegment::where('plan_id', $planId)
            ->where('segment_id', $segmentId)
            ->delete();
    }

    /**
     * Returns all active plan-segment assignments for a plan,
     * ordered by priority ascending (lowest number = highest priority).
     */
    public function getActiveForPlan(int $planId): \App\Framework\Support\Collection
    {
        return PlanSegment::with('segment')
            ->where('plan_id', $planId)
            ->where('is_active', true)
            ->where(function ($q) {
                $now = now_datetime();
                $q->whereNull('starts_at')->orWhere('starts_at', '<=', $now);
            })
            ->where(function ($q) {
                $now = now_datetime();
                $q->whereNull('ends_at')->orWhere('ends_at', '>', $now);
            })
            ->orderBy('priority')
            ->get();
    }

    protected function getModelClass(): string
    {
        return PlanSegment::class;
    }
}