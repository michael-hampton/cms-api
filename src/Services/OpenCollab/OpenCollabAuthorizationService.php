<?php

namespace App\Services\OpenCollab;

use App\Framework\Exceptions\UnauthorizedException;

class OpenCollabAuthorizationService
{
    public function __construct(
        private readonly SitePermissionResolver $resolver,
    ) {
    }

    public function allows(int $userId, int $siteId, string $permission): bool
    {
        return $this->resolver->allows($userId, $siteId, $permission);
    }

    public function allowsAny(int $userId, int $siteId, array $permissions): bool
    {
        foreach ($permissions as $permission) {
            if ($this->resolver->allows($userId, $siteId, $permission)) {
                return true;
            }
        }

        return false;
    }

    public function assertAny(int $userId, int $siteId, array $permissions, string $message = 'Forbidden.'): void
    {
        if (!$this->allowsAny($userId, $siteId, $permissions)) {
            throw new UnauthorizedException($message);
        }
    }

    public function assert(int $userId, int $siteId, string $permission, string $message = 'Forbidden.'): void
    {
        if (!$this->allows($userId, $siteId, $permission)) {
            throw new UnauthorizedException($message);
        }
    }
}
