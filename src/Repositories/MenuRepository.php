<?php

namespace App\Repositories;

use App\Framework\Database\Database;
use App\Framework\Support\Collection;
use App\Models\Menu;
use App\Models\MenuItem;
use App\Models\Model;
use App\Models\Page;

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

    public function getAllMenus(): Collection
    {
        return Menu::with(['items'])->where('is_active', true)->get();
    }

    public function createMenuItem(array $data): Model
    {
        if (!isset($data['sort_order'])) {
            $maxOrder = MenuItem::where('menu_id', $data['menu_id'])
                ->where('parent_id', $data['parent_id'] ?? null)
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
                    ->updateQuery([
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
        return Menu::with(['items', 'items.activeChildren'])->find($menuId);

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
}