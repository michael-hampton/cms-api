<?php

namespace App\Services\OpenCollab;

use App\Enums\OpenCollab\ModerationPermission;
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

    public function canViewHighRisk(int $userId, int $siteId): bool
    {
        return $this->allowsAny($userId, $siteId, [
            ModerationPermission::PagesViewHighRisk->value,
            ModerationPermission::ContentViewHighRisk->value,
        ]);
    }

    public function canEscalate(int $userId, int $siteId): bool
    {
        return $this->allowsAny($userId, $siteId, [
            ModerationPermission::PagesEscalate->value,
            ModerationPermission::ContentEscalate->value,
        ]);
    }

    public function canResolveRisk(int $userId, int $siteId): bool
    {
        return $this->allowsAny($userId, $siteId, [
            ModerationPermission::PagesResolveRisk->value,
            ModerationPermission::ContentResolveRisk->value,
        ]);
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
