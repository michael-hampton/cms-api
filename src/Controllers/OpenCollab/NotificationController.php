<?php

namespace App\Controllers\OpenCollab;

use App\Controllers\Controller;
use App\Framework\Authorization\Auth;
use App\Framework\Http\JsonResponse;
use App\Framework\Http\Request;
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

    public function index(Request $request): JsonResponse
    {
        $unreadOnly = filter_var($request->get('unread_only', false), FILTER_VALIDATE_BOOLEAN);
        $userId = Auth::id();

        $notifications = $this->service->getNotifications($userId);

        $items = $notifications
            ->when($unreadOnly, fn($c) => $c->filter(fn($n) => !$n->isRead()))
            ->map(fn($n) => $this->formatNotification($n))
            ->values()
            ->toArray();

        return $this->resourceResponse([
            'notifications' => $items,
            'unread_count' => $this->service->getUnreadCount($userId),
        ]);
    }

    private function user(): User
    {
        // Replace with your actual auth resolver
        return User::find(Auth::id());
    }

    public function unreadCount(): JsonResponse
    {
        $userId = Auth::id();

        return $this->resourceResponse([
            'count' => $this->service->getUnreadCount($userId),
        ]);
    }

    public function markAsRead(Request $request): JsonResponse
    {
        $user = $this->user();

        $notificationId = (int)($request->get('notification_id') ?? 0);

        if ($notificationId <= 0) {
            return $this->errorResponse('Invalid notification_id');
        }

        $this->service->markAsRead($user, $notificationId);

        return $this->resourceResponse(['success' => true]);
    }

    // ─────────────────────────────

    public function markAllAsRead(): JsonResponse
    {
        $user = $this->user();

        $this->service->markAllAsRead($user);

        return $this->resourceResponse(['success' => true]);
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
}