<?php

namespace App\Repositories\MemberInsights;

use App\Framework\Support\Collection;
use App\Models\SegmentRule;
use App\Repositories\Repository;

class SegmentRuleRepository extends Repository
{
    public function findBySegmentId(int $segmentId): Collection
    {
        return SegmentRule::where('segment_id', $segmentId)
            ->orderBy('sort_order')
            ->get();
    }

    public function deleteBySegmentId(int $segmentId): void
    {
        SegmentRule::where('segment_id', $segmentId)->delete();
    }

    public function createManyForSegment(int $segmentId, array $rules): void
    {
        foreach ($rules as $index => $rule) {
            SegmentRule::create([
                'segment_id' => $segmentId,
                'field' => $rule['field'],
                'operator' => $rule['operator'],
                'value' => $rule['value'],
                'boolean' => $rule['boolean'] ?? 'AND',
                'sort_order' => $rule['sort_order'] ?? $index,
            ]);
        }
    }

    protected function getModelClass(): string
    {
        return SegmentRule::class;
    }
}
