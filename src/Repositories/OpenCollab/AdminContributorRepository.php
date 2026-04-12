<?php

namespace App\Repositories\OpenCollab;

use App\Models\User;
use App\Models\UserSite;
use App\Repositories\Repository;

/**
 * Read-side queries for the admin contributor management area.
 * Write operations go through UserRepositoryInterface.
 */
class AdminContributorRepository extends Repository
{
    /**
     * Paginated list of contributors for a site, optionally filtered by name/email.
     */
    public function searchForSite(int $siteId, ?string $query = null, int $perPage = 25): array
    {
        $userIds = $this->userIdsForSite($siteId);

        $builder = User::where('role', 'contributor')
            ->whereIn('id', $userIds ?: [-1])
            ->orderBy('name');

        if ($query) {
            $builder->where(function ($q) use ($query) {
                $q->where('name', 'like', "%{$query}%")
                    ->orWhere('email', 'like', "%{$query}%");
            });
        }

        return $builder->paginate($perPage);
    }

    private function userIdsForSite(int $siteId): array
    {
        return UserSite::where('site_id', $siteId)
            ->get()
            ->pluck('user_id')
            ->map(fn($id) => (int)$id)
            ->toArray();
    }

    /**
     * Single contributor with their profile data.
     * Returns null if the user is not a contributor on the given site.
     */
    public function findContributorForSite(int $userId, int $siteId): User|array|null
    {
        if (!UserSite::where('user_id', $userId)->where('site_id', $siteId)->exists()) {
            return null;
        }

        /** @var User|null */
        return User::where('id', $userId)
            ->where('role', 'contributor')
            ->first();
    }

    /**
     * All contributors pending account closure (is_active = false, not yet deleted).
     */
    public function pendingClosureForSite(int $siteId): \App\Framework\Support\Collection
    {
        $userIds = $this->userIdsForSite($siteId);

        return User::where('is_contributor', true)
            ->where('is_active', false)
            ->whereIn('id', $userIds ?: [-1])
            ->orderByDesc('updated_at')
            ->get();
    }

    protected function getModelClass(): string
    {
        return User::class;
    }
}
