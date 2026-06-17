<?php

namespace App\Actions\OpenCollab;

use App\Enums\OpenCollab\AdminAction;
use App\Repositories\OpenCollab\AdminActivityLogRepository;
use App\Repositories\OpenCollab\AdminContributorRepository;
use App\Services\User\UserLifecycleServiceInterface;

/**
 * Re-activates a previously deactivated contributor and writes an audit log entry.
 *
 * A reason is mandatory for the same accountability reasons as deactivation.
 */
class ReactivateContributorAction
{
    public function __construct(
        private readonly AdminContributorRepository $contributorRepository,
        private readonly UserLifecycleServiceInterface $userLifecycle,
        private readonly AdminActivityLogRepository $logger,
    )
    {
    }

    /**
     * @throws \InvalidArgumentException If contributor not found or reason is missing.
     */
    public function execute(int $userId, int $siteId, int $adminId, string $reason): void
    {
        $this->requireReason($reason);

        $contributor = $this->contributorRepository->findContributorForSite($userId, $siteId);

        if ($contributor === null) {
            throw new \InvalidArgumentException('Contributor not found.');
        }

        $this->userLifecycle->reactivateContributor($userId, $adminId, $reason);

        $this->logger->log(
            adminId: $adminId,
            targetUserId: $userId,
            action: AdminAction::CONTRIBUTOR_REACTIVATED->value,
            payload: [],
            reason: $reason,
        );
    }

    private function requireReason(string $reason): void
    {
        if (trim($reason) === '') {
            throw new \InvalidArgumentException('Reason is required to reactivate a contributor.');
        }
    }
}
