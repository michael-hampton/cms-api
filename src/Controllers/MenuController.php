<?php

namespace App\Controllers;

use App\Framework\Exceptions\ValidationException;
use App\Framework\Http\JsonResponse;
use App\Framework\Http\Request;
use App\Repositories\MenuRepository;
use App\Requests\CreateMenuRequest;
use App\Requests\UpdateMenuRequest;
use App\Resources\MenuResource;
use App\Services\MenuService;

class MenuController extends Controller
{
    public function __construct(
        protected MenuService $menuService,
        private MenuRepository $menuRepository,
    ) {
        parent::__construct();
    }

    public function index(Request $request, string $siteName)
    {
        try {
            $type = $request->get('type'); // Get type from query params

            if ($type) {
                // Validate type
                if (!in_array($type, ['header', 'footer', 'sidebar'])) {
                    return $this->resourceResponse([
                        'success' => false,
                        'message' => 'Invalid menu type'
                    ], 422);
                }

                $menus = $this->menuRepository->getMenusByType($type, $siteName);
            } else {
                $menus = $this->menuRepository->getAllMenus($siteName);
            }

            return $this->jsonResponse([
                'success' => true,
                'data' => $menus->toArray()
            ]);
        } catch (\Exception $e) {
            return $this->jsonResponse([
                'success' => false,
                'message' => 'Failed to fetch menus',
                'error' => $e->getMessage()
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
                'menu' => MenuResource::make($menu)->toArray()
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

    public function store(CreateMenuRequest $request, string $siteName): JsonResponse
    {
        try {
            $menu = $this->menuService->createMenu($request->validated());
            return$this->jsonResponse([
                'success' => true,
                'menu' => $menu,
                'message' => 'Menu created successfully'
            ], 201);
        } catch (ValidationException $e) {
            return $this->errorResponse(
                'Validation failed',
                422,
                $e->getErrors()
            );
        }
    }

    public function update(UpdateMenuRequest $request, int $id, string $siteName): JsonResponse
    {
        try {
            $menu = $this->menuService->updateMenu($id, $request->validated());

            return $this->jsonResponse([
                'success' => true,
                'menu' => $menu->toArray(),
                'message' => 'Menu updated successfully'
            ]);
        } catch (ValidationException $e) {
            return $this->errorResponse(
                'Validation failed',
                422,
                $e->getErrors()
            );
        }
    }

    public function destroy(int $id, string $siteName): JsonResponse
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