<?php

namespace App\Services\OpenCollab;

use App\Framework\Support\Cache\Contracts\CacheInterface;
use App\Framework\Support\Logger;
use App\Repositories\OpenCollab\RbacRepository;

class SitePermissionResolver
{
    private const TTL_SECONDS = 900;

    public function __construct(
        private readonly RbacRepository $rbacRepository,
        private readonly SitePermissionBundleBuilder $bundleBuilder,
        private readonly PermissionCacheInvalidator $invalidator,
        private readonly CacheInterface $cache,
    ) {
    }

    public function forUser(int $userId, int $siteId): array
    {
        if (!$this->rbacRepository->siteExists($siteId)) {
            return [];
        }

        $bundle = $this->bundleForUser($userId);

        foreach ($bundle['assignments'] ?? [] as $assignment) {
            if ((int) ($assignment['site_id'] ?? 0) === $siteId) {
                return $assignment['permissions'] ?? [];
            }
        }

        return [];
    }

    public function allows(int $userId, int $siteId, string $permission): bool
    {
        $permissions = $this->forUser($userId, $siteId);

        return in_array('*', $permissions, true) || in_array($permission, $permissions, true);
    }

    public function invalidate(int $userId, int $siteId): void
    {
        $this->invalidator->invalidateUser($userId);
    }

    public function invalidateMany(array $userIds, ?int $siteId = null): int
    {
        return $this->invalidator->invalidateUsers($userIds, $siteId);
    }

    public function bundleForUser(int $userId): array
    {
        $key = $this->invalidator->keyForUser($userId);

        try {
            $bundle = $this->cache->get($key);

            if (is_array($bundle)) {
                return $bundle;
            }
        } catch (\Throwable $exception) {
            Logger::warning('Permission cache read failure', [
                'operation' => 'get',
                'user_id' => $userId,
                'cache_key' => $key,
                'error' => $exception->getMessage(),
            ]);
        }

        $bundle = $this->bundleBuilder->build($userId);

        try {
            $this->cache->put($key, $bundle, self::TTL_SECONDS);
        } catch (\Throwable $exception) {
            Logger::warning('Permission cache write failure', [
                'operation' => 'put',
                'user_id' => $userId,
                'cache_key' => $key,
                'error' => $exception->getMessage(),
            ]);
        }

        return $bundle;
    }
}
