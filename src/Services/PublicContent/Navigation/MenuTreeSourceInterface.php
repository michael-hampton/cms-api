<?php

namespace App\Services\PublicContent\Navigation;

use App\Models\Menu;

/**
 * Boundary for "give me the menu tree". Navigation chrome reads through this
 * so the underlying source (search backend, curated lists, etc.) can change
 * without reshaping header/footer components.
 */
interface MenuTreeSourceInterface
{
    public function findTree(int $siteId, string $menuType, ?int $territoryId = null): ?Menu;
}
