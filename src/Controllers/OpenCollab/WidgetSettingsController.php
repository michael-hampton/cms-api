<?php

namespace App\Controllers\OpenCollab;

use App\Controllers\Controller;
use App\Framework\Authorization\Auth;
use App\Framework\Http\JsonResponse;
use App\Framework\Http\Request;
use App\Services\OpenCollab\Dashboard\WidgetSettingsService;
use InvalidArgumentException;

/**
 * Manages per-user widget configuration overrides.
 *
 * Routes:
 *   PUT  /api/{site}/open-collab/dashboard/widgets/{key}/settings
 *        Save a single widget override (enabled state, position, settings).
 *
 *   PUT  /api/{site}/open-collab/dashboard/widgets/positions
 *        Bulk-update widget positions (drag-and-drop reorder).
 *
 * Both endpoints scope writes to the authenticated user only.
 * A user cannot modify another user's widget settings.
 *
 * The resolved widget list (what the dashboard actually renders) is
 * controlled by WidgetResolver. These endpoints only persist preferences;
 * the next page load or widget refresh picks them up.
 */
class WidgetSettingsController extends Controller
{
    public function __construct(
        private readonly WidgetSettingsService $widgetSettingsService,
    )
    {
        parent::__construct();
    }

    /**
     * PUT /api/{site}/open-collab/dashboard/widgets/{key}/settings
     *
     * Request body (JSON):
     * {
     *   "enabled":  bool,           — required
     *   "position": int,            — required, >= 0
     *   "settings": object          — optional, widget-specific config
     * }
     *
     * Response 200:
     * { "saved": true }
     *
     * Response 422:
     * { "error": "..." }
     */
    public function saveWidgetConfig(Request $request, string $key): JsonResponse
    {
        $userId = Auth::id();
        $body = $request->all();

        if (!isset($body['enabled'], $body['position'])) {
            return $this->jsonResponse(['error' => 'enabled and position are required.'], 422);
        }

        try {
            $this->widgetSettingsService->saveWidgetConfig(
                userId: $userId,
                widgetKey: $key,
                enabled: (bool)$body['enabled'],
                position: (int)$body['position'],
                settings: is_array($body['settings'] ?? null) ? $body['settings'] : [],
            );
        } catch (InvalidArgumentException $e) {
            return $this->jsonResponse(['error' => $e->getMessage()], 422);
        }

        return $this->jsonResponse(['saved' => true]);
    }

    /**
     * PUT /api/{site}/open-collab/dashboard/widgets/positions
     *
     * Request body (JSON):
     * {
     *   "positions": [
     *     { "widget_key": "earnings", "position": 0 },
     *     { "widget_key": "drafts",   "position": 1 },
     *     ...
     *   ]
     * }
     *
     * Response 200:
     * { "saved": true }
     *
     * Response 422:
     * { "error": "..." }
     */
    public function updatePositions(Request $request): JsonResponse
    {
        $userId = Auth::id();
        $positions = $request->get('positions') ?? null;

        if (!is_array($positions) || empty($positions)) {
            return $this->jsonResponse(['error' => 'positions must be a non-empty array.'], 422);
        }

        try {
            $this->widgetSettingsService->updatePositions($userId, $positions);
        } catch (InvalidArgumentException $e) {
            return $this->jsonResponse(['error' => $e->getMessage()], 422);
        }

        return $this->jsonResponse(['saved' => true]);
    }
}