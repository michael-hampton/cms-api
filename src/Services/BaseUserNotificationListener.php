<?php

namespace App\Services;

use App\Repositories\OpenCollab\UserSiteRepository;
use Exception;

abstract class BaseUserNotificationListener
{
    public function __construct(
        protected UserNotificationService   $service,
        protected readonly UserSiteRepository $userSiteRepository,
    )
    {
    }

    protected function notifyAllContributors(
        int    $siteId,
        string $type,
        string $title,
        string $body,
        array  $data = [],
        string $consentType = ''
    ): void
    {
        $userIds = $this->userSiteRepository->userIdsForSite($siteId);

        foreach ($userIds as $userId) {
            $this->notify($userId, $type, $data, $consentType);
        }
    }

    protected function notify(
        int    $userId,
        string $type,
        array  $data = [],
        string $consentType = ''
    ): void
    {
        try {
            $user = new \App\Models\User(['id' => $userId]);

            $this->service->notify($user, $type, $data, 'in_app', $consentType);
        } catch (Exception $exception) {
            // silent fail
        }
    }

    protected function userIdsForSite(int $siteId): array
    {
        return $this->userSiteRepository->userIdsForSite($siteId);
    }
}
