<?php

namespace App\Repositories\MemberInsights;

use App\Enums\Member\SegmentSubjectType;
use App\Framework\Database\Database;
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
        string  $subjectType = SegmentSubjectType::Member->value
    ): array
    {
        $allowedSort = ['name', 'created_at'];
        $sortBy = in_array($sortBy, $allowedSort, true) ? $sortBy : 'name';
        $sortOrder = strtolower($sortOrder) === 'desc' ? 'desc' : 'asc';

        $query = Segment::with('rules')
            ->where('subject_type', $subjectType);

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

    /**
     * Returns subscription segments enriched with two derived counts:
     *
     *   - assigned_plan_count          COUNT of plan_segment rows
     *   - matching_subscription_count  COUNT of active subscription_segments rows
     *
     * Both are appended as virtual attributes via subqueries so a single
     * paginated query covers all data the listing page needs.
     *
     * NOTE: `matching_subscription_count` filters on status = 'active', which
     * must match SubscriptionSegmentStatus::Active->value. If the enum backing
     * value ever changes, update the literal below.
     *
     * @return array{
     *     items: \App\Framework\Support\Collection<Segment>,
     *     pagination: array{total: int, per_page: int, current_page: int, total_pages: int}
     * }
     */
    public function getSubscriptionSegmentsListing(
        int     $page = 1,
        int     $perPage = 20,
        ?string $search = null,
        ?bool   $isActive = null,
        string  $sortBy = 'name',
        string  $sortOrder = 'asc',
    ): array {
        $query = Segment::query()
            ->from('segments') // No LEFT JOIN needed anymore
            ->where('segments.subject_type', SegmentSubjectType::Subscription->value)
            ->selectRaw('segments.*')
            // Pull fields safely via subqueries to prevent 1-to-many duplication
            ->selectSub(
                'SELECT MAX(starts_at) FROM plan_segment WHERE plan_segment.segment_id = segments.id',
                'starts_at',
            )
            ->selectSub(
                'SELECT MAX(ends_at) FROM plan_segment WHERE plan_segment.segment_id = segments.id',
                'ends_at',
            )
            ->selectSub(
                'SELECT MAX(priority) FROM plan_segment WHERE plan_segment.segment_id = segments.id',
                'priority',
            )
            ->selectSub(
                'SELECT COUNT(*) FROM plan_segment WHERE plan_segment.segment_id = segments.id',
                'assigned_plan_count',
            )
            ->selectSub(
                "SELECT COUNT(*) FROM subscription_segments
             WHERE subscription_segments.segment_id = segments.id
             AND subscription_segments.status = 'active'",
                'matching_subscription_count',
            );

        if ($search !== null) {
            $query->where(function ($q) use ($search) {
                $q->where('segments.name', 'LIKE', "%{$search}%")
                    ->orWhere('segments.key', 'LIKE', "%{$search}%");
            });
        }

        if ($isActive !== null) {
            $query->where('segments.is_active', $isActive);
        }

        $allowedSorts = ['name', 'priority', 'is_active', 'starts_at', 'ends_at', 'last_recalculated_at', 'created_at'];
        $sortBy       = in_array($sortBy, $allowedSorts, true) ? $sortBy : 'name';
        $sortOrder    = strtolower($sortOrder) === 'desc' ? 'desc' : 'asc';

        // Since the fields are now select aliases or core columns, we can sort directly by them
        $sortPrefix = in_array($sortBy, ['priority', 'starts_at', 'ends_at'], true) ? '' : 'segments.';
        $query->orderBy("{$sortPrefix}{$sortBy}", $sortOrder);

        $total      = (clone $query)->count();
        $totalPages = (int) ceil($total / $perPage);
        $offset     = ($page - 1) * $perPage;

        $items = $query->limit($perPage)->offset($offset)->get();

        return [
            'items'      => $items,
            'pagination' => [
                'total'        => $total,
                'per_page'     => $perPage,
                'current_page' => $page,
                'total_pages'  => $totalPages,
            ],
        ];
    }

    protected function getModelClass(): string
    {
        return Segment::class;
    }
}
