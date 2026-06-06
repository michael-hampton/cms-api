<?php

namespace App\Services;

use App\Framework\Database\CursorPaginator;
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
        string $channel = 'in_app',
        string $consentType = ''
    ): void
    {
        // Later: plug in consent check here
        if (!$this->canNotify($user, $consentType ?: $type, $channel)) {
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
        return $this->consentService->hasConsent(
            userId: $user->id,
            consentTypeCode: $type,
            channel: $channel
        );
    }

    public function getNotifications(int $userId, int $limit = 20)
    {
        return $this->repository->getForUser($userId, $limit);
    }

    public function getUnreadCount(int $userId): int
    {
        return $this->repository->countUnread($userId);
    }

    public function getUnreadNotifications(int $userId)
    {
        return $this->repository->getUnreadForUser($userId);
    }

    public function markAsRead(User $user, int $notificationId): void
    {
        $this->repository->markAsRead($notificationId, $user->id);
    }

    public function markAllAsRead(User $user): void
    {
        $this->repository->markAllAsRead($user->id);
    }

    /**
     * Returns one page of notifications plus an opaque cursor for the next page.
     *
     * @return array{items: array, next_cursor: string|null}
     */
    public function getNotificationsCursor(
        int     $userId,
        ?string $cursor,
        int     $perPage,
        bool    $unreadOnly = false,
    ): array
    {
        $paginator = CursorPaginator::from($cursor);

        $query = $this->repository
            ->queryForUser($userId, $unreadOnly)
            ->orderBy('id', 'desc');

        $query = $paginator->apply($query);

        $rows = $query->limit($perPage + 1)->get();

        return $paginator->paginate($rows, $perPage);
    }
}
