<?php

namespace App\Jobs\OpenCollab;

use App\Framework\Notifications\NotificationDispatcher;
use App\Jobs\BaseJob;
use App\Repositories\Cms\UserRepository;
use App\Repositories\OpenCollab\GuidelinesContentRepository;
use App\Repositories\OpenCollab\UserSiteRepository;
use App\Services\OpenCollab\Notifications\GuidelineUpdatedNotification;

class GuidelineUpdatedFanoutJob extends BaseJob
{
    private GuidelinesContentRepository $guidelinesRepo;
    private UserSiteRepository $userSiteRepo;
    private UserRepository $userRepo;
    private NotificationDispatcher $dispatcher;

    public function __construct(
        private readonly int $guidelineId,
        private readonly int $siteId,
    )
    {
    }

    public function handle(): void
    {
        $guideline = $this->guidelinesRepo->find($this->guidelineId);

        if (!$guideline) {
            return;
        }

        $userIds = $this->userSiteRepo->userIdsForSite($this->siteId);
        $users = $this->userRepo->findMany($userIds);

        foreach ($users as $user) {
            $this->dispatcher->dispatch(
                new GuidelineUpdatedNotification($guideline, $user)
            );
        }
    }
}