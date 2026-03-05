<?php

namespace App\Repositories\Adverts\Boost;

use App\Enums\Boost\BoostStatus;
use App\Framework\Support\Collection;
use App\Models\Boost;
use App\Models\Model;
use App\Repositories\Repository;

class BoostRepository extends Repository
{
    public function findActiveForTarget(string $boostableType, int $boostableId): ?Boost
    {
        return Boost::where('boostable_type', $boostableType)
            ->where('boostable_id', $boostableId)
            ->where('status', BoostStatus::Active->value)
            ->first();
    }

    public function hasActiveBoost(string $boostableType, int $boostableId): bool
    {
        return Boost::where('boostable_type', $boostableType)
            ->where('boostable_id', $boostableId)
            ->where('status', BoostStatus::Active->value)
            ->exists();
    }

    public function getExpiredBoosts(\DateTimeInterface $now): Collection
    {
        return Boost::where('status', BoostStatus::Active->value)
            ->where('ends_at', '<', $now->format('Y-m-d H:i:s'))
            ->get();
    }

    public function getActiveBoostsForContext(string $context): Collection
    {
        return Boost::where('status', BoostStatus::Active->value)
            ->where('context', $context)
            ->get();
    }

    public function getAllWithFilters(array $filters): array
    {
        $page = (int)($filters['page'] ?? 1);
        $perPage = (int)($filters['per_page'] ?? 20);

        $query = Boost::query();

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (!empty($filters['boostable_type'])) {
            $query->where('boostable_type', $filters['boostable_type']);
        }

        if (!empty($filters['context'])) {
            $query->where('context', $filters['context']);
        }

        if (!empty($filters['merchant_id'])) {
            $query->where('merchant_id', $filters['merchant_id']);
        }

        $total = $query->count();
        $boosts = $query->orderBy('created_at', 'desc')
            ->offset(($page - 1) * $perPage)
            ->limit($perPage)
            ->get();

        return [
            'data' => $boosts,
            'pagination' => [
                'current_page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'last_page' => (int)ceil($total / $perPage),
            ],
        ];
    }

    public function createLimit(int $boostId, array $limits): Model
    {
        return \App\Models\BoostLimit::create([
            'boost_id' => $boostId,
            'max_impressions' => $limits['max_impressions'] ?? null,
            'max_clicks' => $limits['max_clicks'] ?? null,
            'max_spend' => $limits['max_spend'] ?? null,
            'pause_on_breach' => $limits['pause_on_breach'] ?? true,
        ]);
    }

    public function getByStatus(BoostStatus $status): Collection
    {
        return Boost::where('status', $status->value)->get();
    }

    /**
     * Finds an active boost, or one that recently expired/was paused,
     * to support conversion attribution after a boost ends mid-window.
     */
    public function findActiveOrRecentForTarget(string $boostableType, int $boostableId): ?Boost
    {
        return Boost::where('boostable_type', $boostableType)
            ->where('boostable_id', $boostableId)
            ->whereIn('status', [
                BoostStatus::Active->value,
                BoostStatus::Paused->value,
                BoostStatus::Expired->value,
            ])
            ->orderByDesc('ends_at')
            ->first();
    }

    public function activeForMerchant(null $id) //todo
    {
        return collect();
    }

    protected function getModelClass(): string
    {
        return Boost::class;
    }
}