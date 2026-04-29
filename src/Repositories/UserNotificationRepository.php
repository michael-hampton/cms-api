<?php

namespace App\Repositories;

use App\Framework\Database\QueryBuilder;
use App\Framework\Support\Collection;
use App\Models\Model;
use App\Models\UserNotification;

class UserNotificationRepository
{
    public function create(
        int    $userId,
        string $type,
        array  $data = []
    ): Model
    {
        return UserNotification::create([
            'user_id' => $userId,
            'type' => $type,
            'data' => $data,
        ]);
    }

    public function getForUser(int $userId, int $limit = 20): Collection
    {
        return UserNotification::query()
            ->where('user_id', $userId)
            ->latest()
            ->limit($limit)
            ->get();
    }

    public function getUnreadForUser(int $userId): Collection
    {
        return UserNotification::query()
            ->where('user_id', $userId)
            ->whereNull('read_at')
            ->get();
    }

    public function countUnread(int $userId): int
    {
        return UserNotification::query()
            ->where('user_id', $userId)
            ->whereNull('read_at')
            ->count();
    }

    public function markAsRead(int $notificationId, int $userId): void
    {
        UserNotification::query()
            ->where('id', $notificationId)
            ->where('user_id', $userId)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);
    }

    public function markAllAsRead(int $userId): void
    {
        UserNotification::query()
            ->where('user_id', $userId)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);
    }

    public function queryForUser(int $userId, bool $unreadOnly): QueryBuilder
    {
        $query = UserNotification::where('user_id', $userId);

        if ($unreadOnly) {
            $query->whereNull('read_at');
        }

        return $query;
    }
}