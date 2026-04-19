<?php

namespace App\Repositories\MemberInsights;

use App\Framework\Support\Collection;
use App\Models\Segment;
use App\Repositories\Repository;

class SegmentRepository extends Repository
{
    public function paginateAdmin(int $perPage = 20, int $page = 1): array
    {
        return Segment::with('rules')
            ->orderBy('name')
            ->paginate($perPage, $page);
    }

    public function findWithRules(int $id): ?Segment
    {
        return Segment::with('rules')->find($id);
    }

    public function existsByKey(string $key, ?int $excludeId = null): bool
    {
        $query = Segment::query()->where('key', trim($key));

        if ($excludeId !== null) {
            $query->where('id', '!=', $excludeId);
        }

        return $query->exists();
    }

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
