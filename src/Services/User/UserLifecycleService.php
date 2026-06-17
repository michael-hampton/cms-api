<?php

namespace App\Services\User;

use App\Models\User;
use App\Repositories\Cms\UserRepositoryInterface;

final class UserLifecycleService implements UserLifecycleServiceInterface
{
    public function __construct(
        private readonly UserRepositoryInterface $users,
    ) {
    }

    public function findByEmail(string $email): ?User
    {
        return $this->users->findByEmail($this->normaliseEmail($email));
    }

    public function findById(int $userId): ?User
    {
        return $this->users->find($userId);
    }

    public function ensureContributorAccount(
        string $email,
        ?string $name = null,
        ?string $password = null,
        ?int $actorUserId = null,
        ?string $reason = null,
    ): User {
        $email = $this->normaliseEmail($email);
        $existing = $this->users->findByEmail($email);

        if ($existing) {
            return $this->reactivateContributor((int) $existing->id, $actorUserId, $reason);
        }

        return $this->users->create([
            'name' => $name,
            'email' => $email,
            'password' => $password,
            'role' => 'contributor',
            'is_contributor' => true,
            'is_active' => true,
        ]);
    }

    public function reactivateContributor(
        int $userId,
        ?int $actorUserId = null,
        ?string $reason = null,
    ): User {
        return $this->users->update($userId, [
            'is_active' => true,
            'is_contributor' => true,
        ]);
    }

    public function deactivateContributor(
        int $userId,
        ?int $actorUserId = null,
        ?string $reason = null,
    ): User {
        return $this->users->update($userId, ['is_active' => false]);
    }

    public function changeContributorRole(
        int $userId,
        string $role,
        ?int $actorUserId = null,
        ?string $reason = null,
    ): User {
        return $this->users->update($userId, ['role' => $role]);
    }

    private function normaliseEmail(string $email): string
    {
        return strtolower(trim($email));
    }
}
