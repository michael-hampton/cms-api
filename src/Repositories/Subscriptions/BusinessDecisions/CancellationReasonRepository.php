<?php

declare(strict_types=1);

namespace App\Repositories\Subscriptions\BusinessDecisions;

use App\Framework\Support\Collection;
use App\Models\CancellationReason;
use App\Repositories\Repository;

class CancellationReasonRepository extends Repository
{
    protected function getModelClass(): string
    {
        return CancellationReason::class;
    }

    /**
     * Active reasons in display order — the set the cancellation-options
     * endpoint and member-facing cancel flow iterate over.
     */
    public function listActive(): Collection
    {
        return CancellationReason::where('is_active', true)
            ->orderBy('sort_order', 'asc')
            ->orderBy('label', 'asc')
            ->get();
    }

    public function findByCode(string $code): ?CancellationReason
    {
        return CancellationReason::where('code', $code)->first();
    }

    public function findActive(int $id): ?CancellationReason
    {
        return CancellationReason::where('id', $id)
            ->where('is_active', true)
            ->first();
    }

    public function findActiveByCode(string $code): ?CancellationReason
    {
        return CancellationReason::where('code', $code)
            ->where('is_active', true)
            ->first();
    }

    public function existsByCode(string $code, ?int $exceptId = null): bool
    {
        $query = CancellationReason::where('code', $code);

        if ($exceptId !== null) {
            $query = $query->where('id', '!=', $exceptId);
        }

        return $query->first() !== null;
    }

    public function paginateAdmin(
        int $perPage = 20,
        int $page = 1,
        ?string $search = null,
        string $sortBy = 'sort_order',
        string $sortOrder = 'asc',
    ): array {
        $allowedSort = ['sort_order', 'code', 'label', 'created_at'];
        $sortBy = in_array($sortBy, $allowedSort, true) ? $sortBy : 'sort_order';
        $sortOrder = strtolower($sortOrder) === 'desc' ? 'desc' : 'asc';

        $query = CancellationReason::query();

        if (!empty($search)) {
            $term = '%' . $search . '%';
            $query->where(function ($q) use ($term) {
                $q->where('label', 'LIKE', $term)
                    ->orWhere('code', 'LIKE', $term);
            });
        }

        return $query->orderBy($sortBy, $sortOrder)->paginate($perPage, $page);
    }
}
