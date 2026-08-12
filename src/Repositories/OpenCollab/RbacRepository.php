<?php

namespace App\Repositories\OpenCollab;

use App\Models\OpenCollabPermission;
use App\Models\OpenCollabRole;
use App\Models\OpenCollabRolePermission;
use App\Models\OpenCollabRbacAuditLog;
use App\Models\OpenCollabSiteRole;
use App\Models\Site;
use App\Models\OpenCollabSiteUserPermission;
use App\Models\OpenCollabSiteUserRole;
use App\Models\User;
use App\Models\UserSite;

class RbacRepository
{
    public function roleIdsForUser(int $siteId, int $userId): array
    {
        return OpenCollabSiteUserRole::where('site_id', $siteId)
            ->where('user_id', $userId)
            ->get()
            ->pluck('role_id')
            ->map(fn($id) => (int) $id)
            ->toArray();
    }

    public function roleSlugsForUser(int $siteId, int $userId): array
    {
        $roleIds = $this->roleIdsForUser($siteId, $userId);

        if ($roleIds === []) {
            return [];
        }

        return OpenCollabRole::whereIn('id', $roleIds)
            ->orderBy('slug')
            ->get()
            ->pluck('slug')
            ->toArray();
    }

    public function permissionIdsForRoles(array $roleIds): array
    {
        if ($roleIds === []) {
            return [];
        }

        return OpenCollabRolePermission::whereIn('role_id', $roleIds)
            ->get()
            ->pluck('permission_id')
            ->map(fn($id) => (int) $id)
            ->toArray();
    }

    public function permissionSlugsForIds(array $permissionIds): array
    {
        if ($permissionIds === []) {
            return [];
        }

        return OpenCollabPermission::whereIn('id', $permissionIds)
            ->get()
            ->pluck('slug')
            ->toArray();
    }

    public function overridesForUser(int $siteId, int $userId): array
    {
        return OpenCollabSiteUserPermission::where('site_id', $siteId)
            ->where('user_id', $userId)
            ->get()
            ->map(fn($override) => [
                'id' => (int) $override->id,
                'permission_id' => (int) $override->permission_id,
                'granted' => (bool) $override->granted,
            ])
            ->toArray();
    }

    public function permissionSlugForId(int $permissionId): ?string
    {
        return OpenCollabPermission::find($permissionId)?->slug;
    }

    public function permissions(): array
    {
        return OpenCollabPermission::orderBy('group')->orderBy('slug')->get()->toArray();
    }

    public function roles(): array
    {
        return OpenCollabRole::orderBy('name')->get()->toArray();
    }

    public function findRoleById(int $roleId): ?OpenCollabRole
    {
        return OpenCollabRole::find($roleId);
    }

    public function rolePermissionMap(): array
    {
        $map = [];
        foreach (OpenCollabRolePermission::all() as $mapping) {
            $map[(int) $mapping->role_id][] = (int) $mapping->permission_id;
        }

        return $map;
    }

    public function usersForSite(int $siteId): array
    {
        return User::whereIn('id', UserSite::where('site_id', $siteId)->get()->pluck('user_id')->toArray())
            ->orderBy('name')
            ->get()
            ->toArray();
    }

    public function userRoleMapForSite(int $siteId): array
    {
        $map = [];
        foreach (OpenCollabSiteUserRole::where('site_id', $siteId)->get() as $assignment) {
            $map[(int) $assignment->user_id][] = (int) $assignment->role_id;
        }

        return $map;
    }

    public function auditForSite(int $siteId, int $limit = 50): array
    {
        return OpenCollabRbacAuditLog::where('site_id', $siteId)
            ->orderBy('id', 'desc')
            ->limit($limit)
            ->get()
            ->toArray();
    }

    public function overridesForSite(int $siteId): array
    {
        return OpenCollabSiteUserPermission::where('site_id', $siteId)
            ->get()
            ->map(fn($override) => [
                'id' => (int) $override->id,
                'user_id' => (int) $override->user_id,
                'permission_id' => (int) $override->permission_id,
                'granted' => (bool) $override->granted,
            ])
            ->toArray();
    }

    public function replaceRolePermissions(int $roleId, array $permissionIds): void
    {
        OpenCollabRolePermission::where('role_id', $roleId)->delete();

        foreach ($permissionIds as $permissionId) {
            OpenCollabRolePermission::create([
                'role_id' => $roleId,
                'permission_id' => $permissionId,
            ]);
        }
    }

    public function siteMembershipUserIds(int $siteId): array
    {
        return UserSite::where('site_id', $siteId)->get()->pluck('user_id')->map(fn($id) => (int) $id)->toArray();
    }

    public function activeSiteAssignmentsForUser(int $userId): array
    {
        $activeSiteIds = Site::all()
            ->filter(fn($site) => (int) ($site->is_active ?? 1) === 1)
            ->pluck('id')
            ->map(fn($id) => (int) $id)
            ->toArray();

        if ($activeSiteIds === []) {
            return [];
        }

        return UserSite::where('user_id', $userId)
            ->whereIn('site_id', $activeSiteIds)
            ->get()
            ->map(fn($assignment) => [
                'user_id' => (int) $assignment->user_id,
                'site_id' => (int) $assignment->site_id,
            ])
            ->toArray();
    }

    public function replaceUserRoles(int $siteId, int $userId, array $roleIds): void
    {
        OpenCollabSiteUserRole::where('site_id', $siteId)->where('user_id', $userId)->delete();

        foreach ($roleIds as $roleId) {
            OpenCollabSiteUserRole::create([
                'site_id' => $siteId,
                'user_id' => $userId,
                'role_id' => $roleId,
            ]);
        }
    }

    public function replaceUserRoleWithSlug(int $siteId, int $userId, string $roleSlug): bool
    {
        $siteRole = OpenCollabRole::where('slug', $roleSlug)->first();

        if (!$siteRole) {
            return false;
        }

        $this->replaceUserRoles($siteId, $userId, [(int) $siteRole->id]);

        return true;
    }

    public function findPermissionBySlug(string $slug): ?OpenCollabPermission
    {
        return OpenCollabPermission::where('slug', $slug)->first();
    }

    public function findPermissionIdBySlug(string $slug): ?int
    {
        $permission = $this->findPermissionBySlug($slug);

        return $permission ? (int) $permission->id : null;
    }

    public function findRoleBySlug(string $slug): ?OpenCollabRole
    {
        return OpenCollabRole::where('slug', $slug)->first();
    }

    public function createPermissionIfMissing(array $attributes): void
    {
        if ($this->findPermissionBySlug($attributes['slug'])) {
            return;
        }

        $this->withDeadlockRetry(function () use ($attributes): void {
            if ($this->findPermissionBySlug($attributes['slug'])) {
                return;
            }

            try {
                OpenCollabPermission::create($attributes);
            } catch (\Throwable $exception) {
                if (!$this->isDuplicateKeyException($exception)) {
                    throw $exception;
                }
            }
        });
    }

    public function createOrUpdateRole(string $slug, array $attributes): OpenCollabRole
    {
        return $this->withDeadlockRetry(function () use ($slug, $attributes): OpenCollabRole {
            $role = $this->findRoleBySlug($slug);

            if ($role) {
                // Avoid rewriting system roles on every ensureSeeded() call — those
                // updates contended with concurrent catalogue seeds (InnoDB 1213).
                $name = $attributes['name'] ?? null;
                $isSystem = array_key_exists('is_system', $attributes)
                    ? (bool) $attributes['is_system']
                    : null;

                $needsUpdate = ($name !== null && (string) $role->name !== (string) $name)
                    || ($isSystem !== null && (bool) $role->is_system !== $isSystem);

                if ($needsUpdate) {
                    $role->update(array_filter(
                        [
                            'name' => $name,
                            'is_system' => $isSystem,
                        ],
                        static fn ($value) => $value !== null
                    ));
                }

                return $role;
            }

            try {
                return OpenCollabRole::create(array_merge(['slug' => $slug], $attributes));
            } catch (\Throwable $exception) {
                if (!$this->isDuplicateKeyException($exception)) {
                    throw $exception;
                }

                $existing = $this->findRoleBySlug($slug);
                if ($existing === null) {
                    throw $exception;
                }

                return $existing;
            }
        });
    }

    public function attachPermissionToRoleIfMissing(int $roleId, int $permissionId): void
    {
        $mapping = OpenCollabRolePermission::where('role_id', $roleId)
            ->where('permission_id', $permissionId)
            ->first();

        if ($mapping) {
            return;
        }

        try {
            OpenCollabRolePermission::create([
                'role_id' => $roleId,
                'permission_id' => $permissionId,
            ]);
        } catch (\Throwable $exception) {
            if (!$this->isDuplicateKeyException($exception)) {
                throw $exception;
            }
        }
    }

    public function permissionIds(): array
    {
        return OpenCollabPermission::all(['id'])
            ->pluck('id')
            ->map(fn($id) => (int) $id)
            ->toArray();
    }

    public function ensureSiteRole(int $siteId, int $roleId, string $name, bool $isActive = true): void
    {
        $this->withDeadlockRetry(function () use ($siteId, $roleId, $name, $isActive): void {
            try {
                OpenCollabSiteRole::firstOrCreate(
                    ['site_id' => $siteId, 'role_id' => $roleId],
                    ['name' => $name, 'is_active' => $isActive]
                );
            } catch (\Throwable $exception) {
                if (!$this->isDuplicateKeyException($exception)) {
                    throw $exception;
                }
            }
        });
    }

    public function createAuditLog(?int $siteId, ?int $actorUserId, ?int $targetUserId, string $action, ?array $payload = null): void
    {
        OpenCollabRbacAuditLog::create([
            'site_id' => $siteId,
            'actor_user_id' => $actorUserId,
            'target_user_id' => $targetUserId,
            'action' => $action,
            'payload' => $payload ? json_encode($payload) : null,
            'created_at' => date('Y-m-d H:i:s'),
        ]);
    }

    public function siteExists(int $siteId): bool
    {
        return Site::find($siteId) !== null;
    }

    public function siteIds(): array
    {
        return Site::all(['id'])
            ->pluck('id')
            ->map(fn($id) => (int) $id)
            ->toArray();
    }

    public function userExists(int $userId): bool
    {
        return User::find($userId) !== null;
    }

    public function legacyRoleForUser(int $userId): ?string
    {
        return User::find($userId)?->role;
    }

    public function upsertUserOverride(int $siteId, int $userId, int $permissionId, bool $granted): void
    {
        $override = OpenCollabSiteUserPermission::where('site_id', $siteId)
            ->where('user_id', $userId)
            ->where('permission_id', $permissionId)
            ->first();

        if ($override) {
            $override->update(['granted' => $granted]);
            return;
        }

        OpenCollabSiteUserPermission::create([
            'site_id' => $siteId,
            'user_id' => $userId,
            'permission_id' => $permissionId,
            'granted' => $granted,
        ]);
    }

    public function deleteUserOverride(int $siteId, int $userId, int $permissionId): bool
    {
        return OpenCollabSiteUserPermission::where('site_id', $siteId)
            ->where('user_id', $userId)
            ->where('permission_id', $permissionId)
            ->delete() > 0;
    }

    public function createRole(array $attributes): OpenCollabRole
    {
        return OpenCollabRole::create($attributes);
    }

    public function deleteUserRolesForRole(int $siteId, int $roleId): void
    {
        OpenCollabSiteUserRole::where('site_id', $siteId)
            ->where('role_id', $roleId)
            ->delete();
    }

    public function deleteSiteRole(int $siteId, int $roleId): void
    {
        OpenCollabSiteRole::where('site_id', $siteId)
            ->where('role_id', $roleId)
            ->delete();
    }

    public function siteRoleCountForRole(int $roleId): int
    {
        return OpenCollabSiteRole::where('role_id', $roleId)->count();
    }

    public function deleteRolePermissions(int $roleId): void
    {
        OpenCollabRolePermission::where('role_id', $roleId)->delete();
    }

    public function deleteRole(int $roleId): void
    {
        OpenCollabRole::where('id', $roleId)->delete();
    }

    private function isDuplicateKeyException(\Throwable $exception): bool
    {
        return str_contains($exception->getMessage(), 'Duplicate entry');
    }

    private function isDeadlockException(\Throwable $exception): bool
    {
        $message = $exception->getMessage();

        return str_contains($message, '1213')
            || str_contains($message, 'Deadlock found when trying to get lock');
    }

    /**
     * @template T
     * @param callable(): T $callback
     * @return T
     */
    private function withDeadlockRetry(callable $callback, int $attempts = 3): mixed
    {
        $last = null;

        for ($attempt = 1; $attempt <= $attempts; $attempt++) {
            try {
                return $callback();
            } catch (\Throwable $exception) {
                $last = $exception;

                if (!$this->isDeadlockException($exception) || $attempt === $attempts) {
                    throw $exception;
                }

                usleep(25_000 * $attempt);
            }
        }

        throw $last ?? new \RuntimeException('RBAC write failed after deadlock retries.');
    }
}
