<?php

namespace App\Jobs\OpenCollab;

use App\Framework\Notifications\NotificationDispatcher;
use App\Jobs\BaseJob;
use App\Repositories\Cms\UserRepository;
use App\Repositories\OpenCollab\ContractRepository;
use App\Repositories\OpenCollab\UserSiteRepository;
use App\Services\OpenCollab\Notifications\ContractPublishedNotification;

class ContractFanoutJob extends BaseJob
{
    private ContractRepository $contractRepo;
    private UserSiteRepository $userSiteRepo;
    private UserRepository $userRepo;
    private NotificationDispatcher $dispatcher;

    public function __construct(
        private readonly int $contractId,
        private readonly int $siteId,
    )
    {
    }

    public function handle(): void
    {
        $contract = $this->contractRepo->find($this->contractId);

        if (!$contract) {
            return;
        }

        $userIds = $this->userSiteRepo->userIdsForSite($this->siteId);
        $users = $this->userRepo->findMany($userIds);

        foreach ($users as $user) {
            $this->dispatcher->dispatch(
                new ContractPublishedNotification($contract, $user)
            );
        }
    }
}