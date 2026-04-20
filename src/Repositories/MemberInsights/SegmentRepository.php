<?php

namespace App\Repositories\MemberInsights;

use App\Framework\Support\Collection;
use App\Models\Segment;
use App\Repositories\Repository;

class SegmentRepository extends Repository
{
    public function paginateAdmin(
        int     $perPage = 20,
        int     $page = 1,
        ?string $search = null,
        string  $sortBy = 'name',
        string  $sortOrder = 'asc',
    ): array
    {
        $allowedSort = ['name', 'created_at'];
        $sortBy = in_array($sortBy, $allowedSort, true) ? $sortBy : 'name';
        $sortOrder = strtolower($sortOrder) === 'desc' ? 'desc' : 'asc';

        $query = Segment::with('rules');

        if (!empty($search)) {
            $term = '%' . $search . '%';
            $query->where(function ($q) use ($term) {
                $q->where('name', 'LIKE', $term)
                    ->orWhere('key', 'LIKE', $term)
                    ->orWhere('description', 'LIKE', $term);
            });
        }

        return $query->orderBy($sortBy, $sortOrder)->paginate($perPage, $page);
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
