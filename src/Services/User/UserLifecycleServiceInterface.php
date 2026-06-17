<?php

namespace App\Services\User;

use App\Models\User;

interface UserLifecycleServiceInterface
{
    public function findByEmail(string $email): ?User;

    public function findById(int $userId): ?User;

    public function ensureContributorAccount(
        string $email,
        ?string $name = null,
        ?string $password = null,
        ?int $actorUserId = null,
        ?string $reason = null,
    ): User;

    public function reactivateContributor(
        int $userId,
        ?int $actorUserId = null,
        ?string $reason = null,
    ): User;

    public function deactivateContributor(
        int $userId,
        ?int $actorUserId = null,
        ?string $reason = null,
    ): User;

    public function changeContributorRole(
        int $userId,
        string $role,
        ?int $actorUserId = null,
        ?string $reason = null,
    ): User;
}
