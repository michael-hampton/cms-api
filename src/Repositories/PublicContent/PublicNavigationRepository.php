<?php

namespace App\Repositories\PublicContent;

use App\Models\Menu;
use App\Repositories\Repository;

final class PublicNavigationRepository extends Repository
{
    public function findActiveMenu(int $siteId, string $type): ?Menu
    {
        $menu = Menu::where('is_active', true)
            ->where('site_id', $siteId)
            ->where('menu_type', $type)
            ->with(['items'])
            ->first();

        return $menu instanceof Menu ? $menu : null;
    }

    protected function getModelClass(): string
    {
        return Menu::class;
    }
}
