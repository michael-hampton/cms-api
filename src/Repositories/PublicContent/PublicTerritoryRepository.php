<?php

namespace App\Repositories\PublicContent;

use App\Framework\Support\Collection;
use App\Models\Territory;
use App\Repositories\Repository;

final class PublicTerritoryRepository extends Repository
{
    public function findActiveBySlug(int $siteId, string $slug): ?Territory
    {
        $territory = Territory::where('site_id', $siteId)
            ->where('slug', strtolower($slug))
            ->where('is_active', true)
            ->first();

        return $territory instanceof Territory ? $territory : null;
    }

    public function getActiveForSite(int $siteId): Collection
    {
        return Territory::where('site_id', $siteId)
            ->where('is_active', true)
            ->orderBy('sort_order', 'asc')
            ->orderBy('name', 'asc')
            ->get();
    }

    protected function getModelClass(): string
    {
        return Territory::class;
    }
}
