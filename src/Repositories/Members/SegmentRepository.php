<?php

namespace App\Repositories\Members;

use App\Framework\Support\Collection;
use App\Models\Segment;
use App\Repositories\Repository;

class SegmentRepository extends Repository
{
    /**
     * @return Collection<Segment>
     */
    public function getActiveWithRules(): Collection
    {
        return Segment::with(['rules'])
            ->where('is_active', true)
            ->get()
            ->orderBy('sort_order');
    }

    /**
     * @param string[] $segmentKeys
     * @return Collection<int, int>
     */
    public function getActiveIdsByKeys(array $segmentKeys): Collection
    {
        if ($segmentKeys === []) {
            return collect();
        }

        return Segment::whereIn('key', $segmentKeys)
            ->where('is_active', true)
            ->get()
            ->pluck('id', 'key');
    }

    protected function getModelClass(): string
    {
        return Segment::class;
    }
}
