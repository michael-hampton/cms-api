<?php

namespace App\Controllers;

use App\Framework\Http\JsonResponse;
use App\Requests\CreateMenuItemRequest;
use App\Requests\ReorderMenuItemsRequest;
use App\Requests\UpdateMenuItemRequest;
use App\Services\MenuService;

class MenuItemController extends Controller
{
    public function __construct(
        protected MenuService $menuService
    ) {
        parent::__construct();
    }

    public function store(CreateMenuItemRequest $request): JsonResponse
    {
        try {
            $menuItem = $this->menuService->createMenuItem($request->validated());

            return $this->jsonResponse([
                'success' => true,
                'data' => $menuItem->with(['children'])->get(),
                'message' => 'Menu item created successfully'
            ], 201);
        } catch (\Exception $e) {
            return $this->jsonResponse([
                'success' => false,
                'message' => 'Failed to create menu item'
            ], 500);
        }
    }

    public function update(UpdateMenuItemRequest $request, int $id): JsonResponse
    {
        try {
            $menuItem = $this->menuService->updateMenuItem($id, $request->validated());
            return $this->jsonResponse([
                'success' => true,
                'data' => $menuItem->with(['children'])->get(),
                'message' => 'Menu item updated successfully'
            ]);
       } catch (\Exception $e) {
            return $this->jsonResponse([
                'success' => false,
                'message' => 'Failed to update menu item'
            ], 500);
        }
    }

    public function destroy(int $id): JsonResponse
    {
        try {
            $this->menuService->deleteMenuItem($id);
            return $this->jsonResponse([
                'success' => true,
                'message' => 'Menu item deleted successfully'
            ]);
        } catch (\Exception $e) {
            return $this->jsonResponse([
                'success' => false,
                'message' => 'Failed to delete menu item'
            ], 500);
        }
    }

    public function reorder(ReorderMenuItemsRequest $request): JsonResponse
    {
        try {
            $this->menuService->reorderMenuItems($request->validated()['items']);

            return $this->jsonResponse([
                'success' => true,
                'message' => 'Menu items reordered successfully'
            ]);
        } catch (\Exception $e) {
            return $this->jsonResponse([
                'success' => false,
                'message' => 'Failed to reorder menu items'
            ], 500);
        }
    }
}