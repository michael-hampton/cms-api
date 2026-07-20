<?php

declare(strict_types=1);

namespace App\Repositories\Subscriptions\BusinessDecisions;

use App\Framework\Support\Collection;
use App\Models\SuspensionReason;
use App\Repositories\Repository;

class SuspensionReasonRepository extends Repository
{
    protected function getModelClass(): string
    {
        return SuspensionReason::class;
    }

    public function listActive(): Collection
    {
        return SuspensionReason::where('is_active', true)
            ->orderBy('sort_order', 'asc')
            ->orderBy('label', 'asc')
            ->get();
    }

    public function findActive(int $id): ?SuspensionReason
    {
        return SuspensionReason::where('id', $id)->where('is_active', true)->first();
    }

    public function existsByCode(string $code, ?int $exceptId = null): bool
    {
        $query = SuspensionReason::where('code', $code);
        if ($exceptId !== null) {
            $query->where('id', '!=', $exceptId);
        }

        return $query->first() !== null;
    }

    public function paginateAdmin(int $perPage = 20, int $page = 1, ?string $search = null, string $sortBy = 'sort_order', string $sortOrder = 'asc'): array
    {
        $sortBy = in_array($sortBy, ['sort_order', 'code', 'label', 'created_at'], true) ? $sortBy : 'sort_order';
        $sortOrder = strtolower($sortOrder) === 'desc' ? 'desc' : 'asc';
        $query = SuspensionReason::query();

        if ($search !== null && $search !== '') {
            $term = '%' . $search . '%';
            $query->where(function ($query) use ($term) {
                $query->where('label', 'LIKE', $term)->orWhere('code', 'LIKE', $term);
            });
        }

        return $query->orderBy($sortBy, $sortOrder)->paginate($perPage, $page);
    }
}
