<?php

namespace App\Services\OpenCollab;

use App\Models\Brief;
use App\Repositories\UserNotificationRepository;

class OpenCollabBriefNotificationService
{
    public function __construct(
        private readonly UserNotificationRepository $notifications,
    )
    {
    }

    public function notifyContributor(int $userId, Brief $brief, string $type, string $title, string $message): void
    {
        $this->notifications->create($userId, $type, [
            'site_id' => (int)$brief->site_id,
            'brief_id' => (int)$brief->id,
            'brief_title' => (string)$brief->title,
            'title' => $title,
            'message' => $message,
            'url' => '/' . ($brief->site?->slug ?? '') . '/open-collab/briefs/' . (int)$brief->id,
        ]);
    }
}
