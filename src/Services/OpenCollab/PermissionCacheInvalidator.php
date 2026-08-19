<?php

namespace App\Services\OpenCollab;

use App\Framework\Support\Cache\Contracts\CacheInterface;
use App\Framework\Support\Logger;

class PermissionCacheInvalidator
{
    public function __construct(
        private readonly CacheInterface $cache,
        private readonly Logger $logger,
    ) {
    }

    public function keyForUser(int $userId): string
    {
        return "site_permissions:{$userId}";
    }

    public function invalidateUser(int $userId): void
    {
        try {
            $this->cache->forget($this->keyForUser($userId));
        } catch (\Throwable $exception) {
            $this->logger->warning('Permission cache invalidation failure', [
                'operation' => 'forget',
                'user_id' => $userId,
                'cache_key' => $this->keyForUser($userId),
                'error' => $exception->getMessage(),
            ]);
        }
    }

    public function invalidateUsers(array $userIds, ?int $siteId = null): int
    {
        $userIds = array_values(array_unique(array_map('intval', $userIds)));

        if ($userIds === []) {
            return 0;
        }

        try {
            $this->cache->forgetMany(array_map(fn(int $userId) => $this->keyForUser($userId), $userIds));

            $this->logger->info('Permission cache invalidated', [
                'operation' => 'forgetMany',
                'site_id' => $siteId,
                'count' => count($userIds),
                'user_ids' => $userIds,
            ]);
        } catch (\Throwable $exception) {
            $this->logger->warning('Permission cache invalidation failure', [
                'operation' => 'forgetMany',
                'site_id' => $siteId,
                'count' => count($userIds),
                'error' => $exception->getMessage(),
            ]);
        }

        return count($userIds);
    }
}
