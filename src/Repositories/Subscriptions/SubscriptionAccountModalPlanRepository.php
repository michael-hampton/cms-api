<?php

namespace App\Repositories\Subscriptions;

use App\Framework\Support\Collection;
use App\Models\SubscriptionPlan;
use App\Repositories\Repository;

class SubscriptionAccountModalPlanRepository extends Repository
{
    /**
     * @param array<int,int> $siteIds
     * @param array<int,int> $sourcePlanIds
     */
    public function findForAccountModal(array $siteIds = [], array $sourcePlanIds = []): Collection
    {
        $siteIds = $this->normaliseIds($siteIds);
        $sourcePlanIds = $this->normaliseIds($sourcePlanIds);

        if ($siteIds === [] && $sourcePlanIds === []) {
            return new Collection([]);
        }

        return SubscriptionPlan::with(['pricingTiers'])
            ->where(function ($query) use ($siteIds, $sourcePlanIds) {
                if ($siteIds !== []) {
                    $query->where(function ($siteQuery) use ($siteIds) {
                        $siteQuery
                            ->whereIn('site_id', $siteIds)
                            ->where('is_active', true);
                    });
                }

                if ($sourcePlanIds !== []) {
                    $query->orWhereIn('id', $sourcePlanIds);
                }
            })
            ->orderBy('site_id', 'asc')
            ->orderBy('sort_order', 'asc')
            ->orderBy('price', 'asc')
            ->get();
    }

    protected function getModelClass(): string
    {
        return SubscriptionPlan::class;
    }

    /**
     * @param array<int,int> $ids
     * @return array<int,int>
     */
    private function normaliseIds(array $ids): array
    {
        $normalised = [];

        foreach ($ids as $id) {
            $id = (int) $id;

            if ($id > 0) {
                $normalised[$id] = $id;
            }
        }

        return array_values($normalised);
    }
}
