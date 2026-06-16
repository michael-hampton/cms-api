<?php

namespace App\Repositories\PublicContent;

use App\Models\Menu;
use App\Repositories\Repository;

final class PublicNavigationRepository extends Repository
{
    public function findActiveMenu(int $siteId, string $type, ?int $territoryId = null): ?Menu
    {
        $query = Menu::where('is_active', true)
            ->where('site_id', $siteId)
            ->where('menu_type', $type);

        if ($territoryId !== null) {
            $query->whereHas('territories', function ($territories) use ($territoryId): void {
                $territories->where('territories.id', $territoryId);
            });
        }

        $menu = $query->with(['items'])->first();

        if (!$menu && $territoryId !== null) {
            return $this->findActiveMenu($siteId, $type);
        }

        return $menu instanceof Menu ? $menu : null;
    }

    protected function getModelClass(): string
    {
        return Menu::class;
    }
}
