<?php

namespace App\Services;

use App\Repositories\OpenCollab\UserSiteRepository;

abstract class BaseUserNotificationListener
{
    public function __construct(
        protected UserNotificationService   $service,
        private readonly UserSiteRepository $userSiteRepository,
    )
    {
    }

    protected function notify(
        int    $userId,
        string $type,
        array  $data = []
    ): void
    {
        $user = new \App\Models\User(['id' => $userId]);

        $this->service->notify($user, $type, $data);
    }

    protected function notifyAllContributors(
        int    $siteId,
        string $type,
        string $title,
        string $body,
        array  $data = [],
    ): void
    {
        $userIds = $this->userSiteRepository->userIdsForSite($siteId);

        foreach ($userIds as $userId) {
            $this->notify($userId, $type, $data);
        }
    }
}