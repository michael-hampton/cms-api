<?php

namespace App\Services;

use App\Models\User;
use App\Repositories\UserNotificationRepository;
use App\Services\OpenCollab\UserConsentService;

class UserNotificationService
{
    public function __construct(
        private readonly UserNotificationRepository $repository,
        private readonly UserConsentService         $consentService,


    )
    {
    }

    public function notify(
        User   $user,
        string $type,
        array  $data = [],
        string $channel = 'in_app'
    ): void
    {
        // Later: plug in consent check here
        if (!$this->canNotify($user, $type, $channel)) {
            return;
        }

        $this->repository->create(
            userId: $user->id,
            type: $type,
            data: $data
        );
    }

    protected function canNotify(User $user, string $type, string $channel = 'in_app'): bool
    {
        return $this->consentService->isGranted(
            user: $user,
            code: $type,
            channel: $channel
        );
    }

    public function getNotifications(User $user, int $limit = 20)
    {
        return $this->repository->getForUser($user->id, $limit);
    }

    public function getUnreadCount(User $user): int
    {
        return $this->repository->countUnread($user->id);
    }

    public function markAsRead(User $user, int $notificationId): void
    {
        $this->repository->markAsRead($notificationId, $user->id);
    }

    public function markAllAsRead(User $user): void
    {
        $this->repository->markAllAsRead($user->id);
    }
}