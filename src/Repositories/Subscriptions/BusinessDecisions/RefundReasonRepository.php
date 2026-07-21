<?php

declare(strict_types=1);

namespace App\Repositories\Subscriptions\BusinessDecisions;

use App\Framework\Support\Collection;
use App\Models\RefundReason;
use App\Repositories\Repository;

class RefundReasonRepository extends Repository
{
    protected function getModelClass(): string
    {
        return RefundReason::class;
    }

    public function listActive(): Collection
    {
        return RefundReason::where('is_active', true)
            ->orderBy('sort_order', 'asc')
            ->orderBy('label', 'asc')
            ->get();
    }

    public function findActive(int $id): ?RefundReason
    {
        return RefundReason::where('id', $id)->where('is_active', true)->first();
    }

    public function findByCode(string $code): ?RefundReason
    {
        return RefundReason::where('code', $code)->first();
    }

    public function findActiveByCode(string $code): ?RefundReason
    {
        return RefundReason::where('code', $code)
            ->where('is_active', true)
            ->first();
    }

    public function existsByCode(string $code, ?int $exceptId = null): bool
    {
        $query = RefundReason::where('code', $code);
        if ($exceptId !== null) {
            $query->where('id', '!=', $exceptId);
        }

        return $query->first() !== null;
    }

    public function paginateAdmin(int $perPage = 20, int $page = 1, ?string $search = null, string $sortBy = 'sort_order', string $sortOrder = 'asc'): array
    {
        $sortBy = in_array($sortBy, ['sort_order', 'code', 'label', 'created_at'], true) ? $sortBy : 'sort_order';
        $sortOrder = strtolower($sortOrder) === 'desc' ? 'desc' : 'asc';
        $query = RefundReason::query();

        if ($search !== null && $search !== '') {
            $term = '%' . $search . '%';
            $query->where(function ($query) use ($term) {
                $query->where('label', 'LIKE', $term)->orWhere('code', 'LIKE', $term);
            });
        }

        return $query->orderBy($sortBy, $sortOrder)->paginate($perPage, $page);
    }
}
