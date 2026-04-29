<?php

namespace App\Actions\OpenCollab;

use App\Enums\OpenCollab\AdminAction;
use App\Repositories\OpenCollab\AdminActivityLogRepository;
use App\Repositories\OpenCollab\AdminContributorRepository;
use App\Services\Cms\UserService;

/**
 * Changes a contributor's role and writes an audit log with the before/after values.
 *
 * A reason is mandatory — silent role changes must not be possible.
 */
class ChangeContributorRoleAction
{
    public function __construct(
        private readonly AdminContributorRepository $contributorRepository,
        private readonly UserService                $userService,
        private readonly AdminActivityLogRepository $logger,
    )
    {
    }

    /**
     * @throws \InvalidArgumentException If contributor not found, role is blank, or reason is missing.
     */
    public function execute(
        int    $userId,
        int    $siteId,
        int    $adminId,
        string $newRole,
        string $reason,
    ): void
    {
        $this->requireReason($reason);

        if (trim($newRole) === '') {
            throw new \InvalidArgumentException('Role is required.');
        }

        $contributor = $this->contributorRepository->findContributorForSite($userId, $siteId);

        if ($contributor === null) {
            throw new \InvalidArgumentException('Contributor not found.');
        }

        $oldRole = is_array($contributor) ? ($contributor['role'] ?? null) : $contributor->role;

        $this->userService->updateUser($userId, ['role' => $newRole]);

        $this->logger->log(
            adminId: $adminId,
            targetUserId: $userId,
            action: AdminAction::CONTRIBUTOR_ROLE_CHANGED->value,
            payload: [
                'from' => $oldRole,
                'to' => $newRole,
            ],
            reason: $reason,
        );
    }

    private function requireReason(string $reason): void
    {
        if (trim($reason) === '') {
            throw new \InvalidArgumentException('Reason is required to change a contributor\'s role.');
        }
    }
}