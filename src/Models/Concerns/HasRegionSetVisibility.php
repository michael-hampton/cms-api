<?php

namespace App\Models\Concerns;

use App\Framework\Database\QueryBuilder;
use App\Models\Member;

trait HasRegionSetVisibility
{
    public function scopeVisibleToMember(QueryBuilder $query, ?Member $member = null): QueryBuilder
    {
        if (!$member || !$member->hasTerritoryId()) {
            return $query;
        }

        $territoryId = $member->getTerritoryId();

        return $query->where(function ($q) use ($territoryId) {
            $q->whereDoesntHave('regionSets')
                ->orWhereHas('regionSets', function ($subQ) use ($territoryId) {
                    $subQ->whereHas('territories', function ($tQ) use ($territoryId) {
                        $tQ->where('territories.id', $territoryId)
                            ->whereNull('territories.deleted_at');
                    });
                });
        });
    }

    public function isVisibleToMember(?Member $member): bool
    {
        if (!$member || !$member->hasTerritoryId()) {
            return true;
        }

        if (!$this->relationLoaded('regionSets')) {
            $this->load(['regionSets.territories']);
        }

        if ($this->regionSets->isEmpty()) {
            return true;
        }

        $territoryId = $member->getTerritoryId();

        foreach ($this->regionSets as $regionSet) {
            if (!$regionSet->relationLoaded('territories')) {
                $regionSet->load(['territories']);
            }

            foreach ($regionSet->territories as $territory) {
                if ($territory->id === $territoryId && is_null($territory->deleted_at)) {
                    return true;
                }
            }
        }

        return false;
    }
}