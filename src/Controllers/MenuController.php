<?php

namespace App\Controllers;

use App\Framework\Http\JsonResponse;
use App\Requests\CreateMenuRequest;
use App\Requests\UpdateMenuRequest;
use App\Services\MenuService;

class MenuController extends Controller
{
    public function __construct(
        protected MenuService $menuService
    ) {
        parent::__construct();
    }

    public function index(): JsonResponse
    {
        try {
            $menus = $this->menuService->getAllMenus();
            return $this->jsonResponse([
                'success' => true,
                'data' => $menus
            ]);
        } catch (\Exception $e) {
            return $this->jsonResponse([
                'success' => false,
                'message' => 'Failed to retrieve menus'
            ], 500);
        }
    }

    public function show(int $id): JsonResponse
    {
        try {
            $menu = $this->menuService->getMenuById($id);

            if (!$menu) {
                return $this->jsonResponse([
                    'success' => false,
                    'message' => 'Menu not found'
                ], 404);
            }

            return $this->jsonResponse([
                'success' => true,
                'menu' => $menu->toArray()
            ]);
        } catch (\Exception $e) {
            return $this->jsonResponse([
                'success' => false,
                'message' => 'Failed to retrieve menu'
            ], 500);
        }
    }

    public function getMenuBySlug(string $slug): JsonResponse
    {
        try {
            $menu = $this->menuService->getMenuBySlug($slug);

            if (!$menu) {
                return $this->jsonResponse([
                    'success' => false,
                    'message' => 'Menu not found'
                ], 404);
            }

            return $this->jsonResponse([
                'success' => true,
                'menu' => $menu->toArray()
            ]);
        } catch (\Exception $e) {
            return $this->jsonResponse([
                'success' => false,
                'message' => 'Failed to retrieve menu'
            ], 500);
        }
    }

    public function store(CreateMenuRequest $request): JsonResponse
    {
        try {
            $menu = $this->menuService->createMenu($request->validated());
            return$this->jsonResponse([
                'success' => true,
                'menu' => $menu,
                'message' => 'Menu created successfully'
            ], 201);
        } catch (\Exception $e) {
            return $this->jsonResponse([
                'success' => false,
                'message' => 'Failed to create menu'
            ], 422);
        }
    }

    public function update(UpdateMenuRequest $request, int $id): JsonResponse
    {
        try {
            $menu = $this->menuService->updateMenu($id, $request->validated());

            return $this->jsonResponse([
                'success' => true,
                'menu' => $menu->toArray(),
                'message' => 'Menu updated successfully'
            ]);
        } catch (\Exception $e) {
            return $this->jsonResponse([
                'success' => false,
                'message' => 'Failed to update menu'
            ], 500);
        }
    }

    public function destroy(int $id): JsonResponse
    {
        try {
            $this->menuService->deleteMenu($id);
            return $this->jsonResponse([
                'success' => true,
                'message' => 'Menu deleted successfully'
            ]);
        } catch (\Exception $e) {
            return $this->jsonResponse([
                'success' => false,
                'message' => 'Failed to delete menu'
            ], 500);
        }
    }

    public function hierarchy(int $id): JsonResponse
    {
        try {
            $hierarchy = $this->menuService->getMenuHierarchy($id);
            return $this->jsonResponse([
                'success' => true,
                'data' => $hierarchy
            ]);
        } catch (\Exception $e) {
            return $this->jsonResponse([
                'success' => false,
                'message' => 'Failed to retrieve menu hierarchy'
            ], 500);
        }
    }
}