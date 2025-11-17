<?php

namespace App\Repositories;

use App\Framework\Support\Collection;
use App\Models\Menu;
use App\Models\MenuItem;
use App\Models\Model;
use App\Models\Site;

class MenuRepository extends Repository
{

    protected function getModelClass(): string
    {
        return Menu::class;
    }

    public function createMenu(array $data): Model
    {
        return Menu::create($data);
    }

    public function updateMenu(Menu $menu, array $data): Model
    {
        $menu->update($data);
        return $menu->fresh();
    }

    public function getAllMenus(string $siteName): Collection
    {
        $site = Site::resolveSite($siteName);
        return Menu::with(['items', 'territories', 'items.children'])->where('site_id', $site)->where('is_active', true)->get();
    }

    public function createMenuItem(array $data, ?int $siteId = null): Model
    {
        if (!isset($data['sort_order'])) {
            $query = MenuItem::where('menu_id', $data['menu_id']);

            $query = !empty($data['parent_id']) ? $query->where('parent_id', $data['parent_id']) : $query;

            //->where('parent_id', $data['parent_id'] ?? null)
            $maxOrder = $query->where('column_group', $data['column_group'] ?? 0)
                ->max('sort_order');

            $data['sort_order'] = ($maxOrder ?? 0) + 1;
        }

        return MenuItem::create($data);
    }

    public function updateMenuItem(MenuItem $menuItem, array $data): Model
    {
        $menuItem->update($data);
        return $menuItem->fresh();
    }

    public function deleteMenuItem(MenuItem $menuItem): bool
    {
        return $menuItem->delete();
    }

    public function reorderMenuItems(array $items): bool
    {
        return $this->database->transaction(function () use ($items) {
            foreach ($items as $item) {
                MenuItem::where('id', $item['id'])
                    ->update([
                        'sort_order' => $item['sort_order'],
                        'parent_id' => $item['parent_id'] ?? null,
                    ]);
            }
            return true;
        });
    }

    public function deleteMenu(Menu $menu): bool
    {
        return $menu->delete();
    }

    public function getMenuById(int $menuId): ?Menu
    {
        return Menu::with(['items', 'items.children', 'territories'])->find($menuId);

    }

    public function findBySlug(string $slug): ?Model
    {
        return Menu::where('slug', $slug)->first();
    }

    public function getMenuItemById(int $id): Model
    {
        return MenuItem::findOrFail($id);
    }

    public function getMenuHierarchy(int $menuId): Collection
    {
        return MenuItem::where('menu_id', $menuId)
            ->whereNull('parent_id')
            ->with(['children' => function ($query) {
                $this->loadChildrenRecursively($query);
            }])
            ->orderBy('sort_order')
            ->get();
    }

    public function getMenusByType(string $type, string $siteName): Collection
    {
        $site = Site::resolveSite($siteName);

        return Menu::with(['items'])
            ->where('site_id', $site)
            ->where('menu_type', $type)
            ->where('is_active', true)
            ->get();
    }
}