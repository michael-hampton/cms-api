<?php

namespace App\Controllers\OpenCollab;

use App\Controllers\Controller;
use App\Framework\Authorization\Auth;
use App\Framework\Http\JsonResponse;
use App\Framework\Http\Request;
use App\Framework\Support\SiteContext;
use App\Models\Model;
use App\Models\User;
use App\Services\NotificationFormatter;
use App\Services\UserNotificationService;

class NotificationController extends Controller
{
    public function __construct(
        protected UserNotificationService      $service,
        private readonly NotificationFormatter $formatter
    )
    {
        parent::__construct();
    }

    /**
     * GET /api/{site}/open-collab/notifications
     *
     * Query params:
     *   unread_only  bool   (default false)
     *   cursor       string opaque cursor returned from the previous page
     *   per_page     int    (default 15, max 50)
     *
     * Response:
     * {
     *   "notifications": [...],
     *   "next_cursor": "eyJpZCI6MTIzfQ=="|null,
     *   "unread_count": 4
     * }
     *
     * When next_cursor is null there are no further pages.
     */
    public function index(Request $request): JsonResponse
    {
        $unreadOnly = filter_var($request->get('unread_only', false), FILTER_VALIDATE_BOOLEAN);
        $cursor = $request->get('cursor') ?: null;
        $perPage = min((int)$request->get('per_page', 15), 50);
        $userId = Auth::id();

        $siteId = SiteContext::getId();
        $page = $this->service->getNotificationsCursor($userId, $cursor, $perPage, $unreadOnly);

        $items = array_map(
            fn($n) => $this->formatNotification($n),
            $this->filterNotificationsForSite($page['items'], $siteId),
        );

        return $this->resourceResponse([
            'data' => $items,
            'notifications' => $items,
            'next_cursor' => $page['next_cursor'],
            'unread_count' => $this->unreadCountForSite($userId, $siteId),
        ]);
    }

    private function formatNotification($notification): array
    {
        return array_merge($this->formatter->format($notification), [
            'id' => $notification->id,
            'type' => $notification->type,
            'data' => $notification->data,
            'is_read' => $notification->read_at !== null,
            'read_at' => $notification->read_at,
            'created_at' => $notification->created_at,
        ]);
    }

    private function filterNotificationsForSite(array $notifications, int $siteId): array
    {
        return array_values(array_filter($notifications, function ($notification) use ($siteId): bool {
            $data = is_array($notification)
                ? ($notification['data'] ?? [])
                : (is_array($notification->data) ? $notification->data : []);

            return isset($data['site_id']) && (int)$data['site_id'] === $siteId;
        }));
    }

    private function unreadCountForSite(int $userId, int $siteId): int
    {
        $unread = $this->service->getUnreadNotifications($userId);

        return count($this->filterNotificationsForSite($unread->toArray(), $siteId));
    }

    // ── Private ───────────────────────────────────────────────────────────────

    public function unreadCount(): JsonResponse
    {
        $userId = Auth::id();
        $siteId = SiteContext::getId();

        return $this->resourceResponse([
            'count' => $this->unreadCountForSite($userId, $siteId),
        ]);
    }

    public function markAsRead(Request $request): JsonResponse
    {
        $user = $this->resolveUser();
        $notificationId = (int)($request->get('notification_id') ?? 0);

        if ($notificationId <= 0) {
            return $this->errorResponse('Invalid notification_id');
        }

        $this->service->markAsRead($user, $notificationId);

        return $this->resourceResponse(['success' => true]);
    }

    public function markAsReadById(int $notification): JsonResponse
    {
        $this->service->markAsRead($this->resolveUser(), $notification);

        return $this->resourceResponse(['success' => true]);
    }

    private function resolveUser(): Model
    {
        return User::find(Auth::id());
    }

    public function markAllAsRead(): JsonResponse
    {
        $user = $this->resolveUser();

        $this->service->markAllAsRead($user);

        return $this->resourceResponse(['success' => true]);
    }
}
