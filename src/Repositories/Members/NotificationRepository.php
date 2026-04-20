<?php

namespace App\Repositories\Members;

use App\Framework\Support\Collection;
use App\Models\Notification;
use App\Repositories\Repository;

class NotificationRepository extends Repository
{
    public function findUnreadForMember(int $memberId): Collection
    {
        return Notification::where('member_id', $memberId)
            ->where('is_read', false)
            ->orderBy('created_at', 'desc')
            ->get();
    }

    public function findAllForMember(int $memberId, int $limit = 50): Collection
    {
        return Notification::where('member_id', $memberId)
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();
    }

    public function countUnread(int $memberId): int
    {
        return (int)Notification::where('member_id', $memberId)
            ->where('is_read', false)
            ->count();
    }

    public function markAsRead(int $memberId): void
    {
        Notification::where('member_id', $memberId)
            ->where('is_read', false)
            ->update(['is_read' => true, 'updated_at' => date('Y-m-d H:i:s')]);
    }

    protected function getModelClass(): string
    {
        return Notification::class;
    }
}