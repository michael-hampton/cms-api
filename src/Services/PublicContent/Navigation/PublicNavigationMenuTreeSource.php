<?php

namespace App\Services\PublicContent\Navigation;

use App\Models\Menu;
use App\Repositories\PublicContent\PublicNavigationRepository;

/**
 * Interim menu-tree source: curated menus via the existing public navigation repository.
 */
final class PublicNavigationMenuTreeSource implements MenuTreeSourceInterface
{
    public function __construct(
        private readonly PublicNavigationRepository $navigation,
    ) {
    }

    public function findTree(int $siteId, string $menuType, ?int $territoryId = null): ?Menu
    {
        return $this->navigation->findActiveMenu($siteId, $menuType, $territoryId);
    }
}
