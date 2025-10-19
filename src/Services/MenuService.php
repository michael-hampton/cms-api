<?php

namespace App\Services;

use App\Framework\Exceptions\ValidationException;
use App\Framework\Support\Collection;
use App\Framework\Support\Str;
use App\Models\Menu;
use App\Models\MenuItem;
use App\Models\Model;
use App\Repositories\MenuRepository;

class MenuService
{
    public function __construct(
        protected MenuRepository $menuRepository
    ) {}

    public function getMenuBySlug(string $slug): ?Model
    {
        return $this->menuRepository->findBySlug($slug);
    }

    public function getMenuById(int $id): ?Model
    {
        return $this->menuRepository->getMenuById($id);
    }

    public function getAllMenus(string $siteName): Collection
    {
        return $this->menuRepository->getAllMenus($siteName);
    }

    public function createMenu(array $data): Model
    {
        $slug = Str::slug($data['name']);
        $data['slug'] = $this->ensureUniqueSlug($slug);
        $data['layout_config'] = !empty($data['layout_config']) ? json_encode($data['layout_config']) : null;

        return $this->menuRepository->createMenu($data);
    }

    public function updateMenu(int $menuId, array $data): Model
    {
        $menu = $this->menuRepository->getMenuById($menuId);

        if (!empty($data['slug']) && $data['slug'] !== $menu->slug) {
            $data['slug'] = $this->ensureUniqueSlug($data['slug'], $menu->id);
        }

        unset($data['items']);

        $data['layout_config'] = !empty($data['layout_config']) ? json_encode($data['layout_config']) : null;

        return $this->menuRepository->updateMenu($menu, $data);
    }

    public function deleteMenu(int $menuId): bool
    {
        $menu = $this->menuRepository->getMenuById($menuId);
        return $this->menuRepository->deleteMenu($menu);
    }

    public function createMenuItem(array $data): Model
    {
        $this->validateMenuItemData($data);
        return $this->menuRepository->createMenuItem($data);
    }

    public function updateMenuItem(int $itemId, array $data): Model
    {
        $menuItem = $this->menuRepository->getMenuItemById($itemId);
        $this->validateMenuItemData($data, $menuItem->id);
        return $this->menuRepository->updateMenuItem($menuItem, $data);
    }

    public function deleteMenuItem(int $itemId): bool
    {
        $menuItem = MenuItem::findOrFail($itemId);
        return $this->menuRepository->deleteMenuItem($menuItem);
    }

    public function reorderMenuItems(array $items): bool
    {
        return $this->menuRepository->reorderMenuItems($items);
    }

    public function getMenuHierarchy(int $menuId): Collection
    {
        return $this->menuRepository->getMenuHierarchy($menuId);
    }

    public function renderMenu(string $slug, array $options = []): string
    {
        $menu = $this->getMenuBySlug($slug);
        if (!$menu) {
            return '';
        }

        $renderer = new MenuRenderer();
        return $renderer->render($menu, $options);
    }

    private function validateMenuItemData(array $data, ?int $excludeId = null): void
    {
        // Prevent circular references
        if (isset($data['parent_id']) && $excludeId) {
            $this->validateNoCircularReference($excludeId, $data['parent_id']);
        }
    }

    private function validateNoCircularReference(int $itemId, int $parentId): void
    {
        $currentParent = MenuItem::find($parentId);
        while ($currentParent) {
            if ($currentParent->id === $itemId) {
                throw new ValidationException('Cannot create circular reference in menu hierarchy.');
            }
            $currentParent = $currentParent->parent;
        }
    }

    private function ensureUniqueSlug(string $slug, ?int $excludeId = null): string
    {
        $originalSlug = $slug;
        $counter = 1;

        while (true) {

            $existing = $this->menuRepository->findBySlug($slug);

            if (!$existing || ($excludeId && $existing->id === $excludeId)) {
                return $slug;
            }

            $slug = $originalSlug . '-' . $counter++;
        }
    }

    private function generateSlug(string $name): string
    {
        $slug = strtolower(trim($name));
        $slug = preg_replace('/[^a-z0-9-]/', '-', $slug);
        $slug = preg_replace('/-+/', '-', $slug);
        return trim($slug, '-');
    }
}