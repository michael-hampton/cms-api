<?php

namespace App\Controllers\Members\Api;

use App\Controllers\Controller;
use App\Framework\Exceptions\ValidationException;
use App\Framework\Http\JsonResponse;
use App\Framework\Http\Request;
use App\Framework\Support\SiteContext;
use App\Models\Badge;
use App\Requests\Badges\StoreBadgeRequest;
use App\Requests\Badges\UpdateBadgeRequest;
use App\Services\Members\BadgeService;

/**
 * Admin badge management.
 *
 * Routes:
 *   GET    /admin/badges              — list (paginated)
 *   GET    /admin/badges/{id}         — show
 *   POST   /admin/badges              — create
 *   PUT    /admin/badges/{id}         — update
 *   DELETE /admin/badges/{id}         — delete
 */
class BadgeAdminApiController extends Controller
{
    public function __construct(
        private readonly BadgeService $badgeService,
    )
    {
        parent::__construct();
    }

    public function index(Request $request): JsonResponse
    {
        // FIX: collect search/sort filters and pass them through
        $filters = array_filter([
            'search' => $request->get('search'),
            'sort_by' => $request->get('sort_by', 'name'),
            'sort_order' => $request->get('sort_order', 'asc'),
        ], fn($v) => $v !== null && $v !== '');

        $badges = $this->badgeService->listForSite(
            SiteContext::getId(),
            (int)$request->get('page', 1),
            20,
            $filters,
        );

        return $this->resourceResponse([
            'data' => $badges['data']->map(fn(Badge $b) => $this->formatBadge($b))->toArray(),
            'meta' => [
                'current_page' => $badges['pagination']['current_page'],
                'last_page' => $badges['pagination']['last_page'],
                'total' => $badges['pagination']['total'],
                'per_page' => $badges['pagination']['per_page'],
            ],
        ]);
    }

    private function formatBadge(Badge $badge): array
    {
        return [
            'id' => $badge->id,
            'name' => $badge->name,
            'description' => $badge->description,
            'icon' => $badge->icon,
            'criteria' => $badge->criteria,
            'points' => $badge->points,
            'is_active' => $badge->is_active,
            'created_at' => $badge->created_at,
            'updated_at' => $badge->updated_at,
            'slug' => $badge->slug,
            'category' => $badge->category,
        ];
    }

    public function show(int $id): JsonResponse
    {
        try {
            $badge = $this->badgeService->findForSite($id, SiteContext::getId());
            return $this->resourceResponse($this->formatBadge($badge));
        } catch (\InvalidArgumentException $e) {
            return $this->errorResponse($e->getMessage(), 404);
        }
    }

    public function store(StoreBadgeRequest $request): JsonResponse
    {
        try {
            $badge = $this->badgeService->createBadge($request->validated(), SiteContext::getId());
            return $this->resourceResponse($this->formatBadge($badge), 201);
        } catch (ValidationException $e) {
            return $this->errorResponse($e->getMessage(), 422, $e->getErrors());
        } catch (\InvalidArgumentException $e) {
            return $this->errorResponse($e->getMessage(), 422);
        }
    }

    public function update(int $id, UpdateBadgeRequest $request): JsonResponse
    {
        try {
            $badge = $this->badgeService->updateBadge($id, $request->validated(), SiteContext::getId());
            return $this->resourceResponse($this->formatBadge($badge));
        } catch (ValidationException $e) {
            return $this->errorResponse($e->getMessage(), 422, $e->getErrors());
        } catch (\InvalidArgumentException $exception) {
            return $this->errorResponse($exception->getMessage(), 404);
        }
    }

    public function destroy(int $id): JsonResponse
    {
        try {
            $this->badgeService->deleteBadge($id, SiteContext::getId());
            return $this->jsonResponse(['message' => 'Badge deleted.']);
        } catch (\InvalidArgumentException $e) {
            return $this->errorResponse($e->getMessage(), 404);
        }
    }
}