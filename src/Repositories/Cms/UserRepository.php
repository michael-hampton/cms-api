<?php

namespace App\Repositories\Cms;

use App\Enums\UserRole;
use App\Models\Model;
use App\Models\User;
use App\Repositories\Repository;
use App\Search\PaginatedResult;
use App\Search\SearchConfigurationFactory;
use App\Search\SearchCriteria;
use App\Search\SearchEngine;
use App\Services\OpenCollab\PermissionCacheInvalidator;

class UserRepository extends Repository implements UserRepositoryInterface
{
    public function findByEmail(string $email, ?int $siteId = null): ?User
    {
        $user = User::where('email', $email)
            //->where('site_id', $siteId)
            ->first();

        if (empty($user)) return null;

        return new User($user);
    }

    public function search(SearchCriteria $criteria): PaginatedResult
    {
        $configuration = SearchConfigurationFactory::create('user');
        $engine = new SearchEngine($configuration);

        $query = User::query();

        return $engine->search($query, $criteria);
    }

    public function findById(int $id, int $siteId): ?User
    {
        $user = User::where('id', $id)
            // ->where('site_id', $siteId)
            ->first();

        return !empty($user) ? new User($user) : null;
    }

    public function create(array $data): Model
    {
        if (isset($data['password'])) {
            $data['password'] = password_hash($data['password'], PASSWORD_DEFAULT);
        }

        return parent::create($data);
    }

    protected function getModelClass(): string
    {
        return User::class;
    }

    public function update(int $id, array $data): ?Model
    {
        $existing = $this->find($id);
        $oldRole = $existing?->role;

        $updated = parent::update($id, $data);

        if ($updated && array_key_exists('role', $data) && $oldRole !== $updated->role) {
            try {
                app(PermissionCacheInvalidator::class)->invalidateUser($id);
            } catch (\Throwable) {
            }
        }

        return $updated;
    }

    public function updateUserWithPassword(int $id, array $data): Model
    {
        if (!empty($data['password'])) {
            $data['password'] = password_hash($data['password'], PASSWORD_DEFAULT);
        }

        return $this->update($id, $data);
    }

    /**
     * Search active users by name or email, excluding users already assigned
     * to the given site.
     *
     * Results are ordered by name and capped at $limit to keep the dropdown
     * responsive. The caller is responsible for access-checking before
     * exposing results.
     *
     * @param int[] $excludeUserIds User IDs to omit (i.e. already on the site)
     * @return User[]
     */
    public function searchForSiteAssignment(
        string $query,
        array  $excludeUserIds = [],
        int    $limit = 10,
    ): array
    {
        $q = User::where('is_active', true)
            ->where(function ($builder) use ($query) {
                $term = '%' . $this->escapeLike($query) . '%';
                $builder->where('name', 'like', $term)
                    ->orWhere('email', 'like', $term);
            });

        if (!empty($excludeUserIds)) {
            $q->whereNotIn('id', $excludeUserIds);
        }

        return $q->orderBy('name')
            ->limit($limit)
            ->get(['id', 'name', 'email'])
            ->all();
    }

    private function escapeLike(string $value): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $value);
    }

    public function findAdminUsers(): array
    {
        return User::where('role', UserRole::Admin->value)
            ->get()
            ->all();
    }
}
