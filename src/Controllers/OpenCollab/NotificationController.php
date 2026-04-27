<?php

namespace App\Controllers\OpenCollab;

use App\Controllers\Controller;
use App\Framework\Authorization\Auth;
use App\Framework\Http\JsonResponse;
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

    public function index(): JsonResponse
    {
        $user = $this->user(); // however you resolve auth

        $notifications = $this->service->getNotifications($user);

        return $this->resourceResponse(
            [
                'data' => array_map(
                    fn($n) => $this->formatter->format($n) + [
                            'id' => $n->id,
                            'read' => $n->read_at !== null,
                            'created_at' => $n->created_at,
                        ],
                    $notifications->all()
                ),
            ]);
    }

    private function user(): User
    {
        // Replace with your actual auth resolver
        return User::find(Auth::id());
    }

    public function unreadCount(): JsonResponse
    {
        $user = $this->user();

        return $this->resourceResponse([
            'count' => $this->service->getUnreadCount($user),
        ]);
    }

    public function markAsRead(): JsonResponse
    {
        $user = $this->user();

        $notificationId = (int)($_POST['notification_id'] ?? 0);

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

    private function transform($notification): array
    {
        return [
            'id' => $notification->id,
            'type' => $notification->type,
            'data' => $notification->data,
            'read' => $notification->read_at !== null,
            'created_at' => $notification->created_at,
        ];
    }
}