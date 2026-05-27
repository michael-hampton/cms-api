<?php

namespace App\Tests\Unit\Repositories\OpenCollab;

use App\Framework\Support\Config;
use App\Models\OpenCollabPermission;
use App\Models\OpenCollabRbacAuditLog;
use App\Models\OpenCollabRole;
use App\Models\OpenCollabRolePermission;
use App\Models\OpenCollabSiteRole;
use App\Models\OpenCollabSiteUserPermission;
use App\Models\OpenCollabSiteUserRole;
use App\Models\UserSite;
use App\Repositories\OpenCollab\RbacRepository;
use App\Services\OpenCollab\RbacBootstrapper;
use App\Tests\Unit\Repositories\Concerns\CreatesTestData;
use App\Tests\Unit\Repositories\RepositoryTestCase;

class RbacRepositoryTest extends RepositoryTestCase
{
    use CreatesTestData;

    private RbacRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('rbac', require __DIR__ . '/../../../../config/rbac.php');
        Config::set('rbac.site_enabled', true);

        $this->repository = new RbacRepository();
        (new RbacBootstrapper($this->repository))->ensureSeeded($this->siteId);
    }

    public function test_permissions_returns_seeded_permissions(): void
    {
        $permissions = $this->repository->permissions();

        $this->assertNotEmpty($permissions);
        $this->assertContains('content.submit', array_column($permissions, 'slug'));
        $this->assertContains('site.roles.manage', array_column($permissions, 'slug'));
    }

    public function test_roles_returns_seeded_roles(): void
    {
        $roles = $this->repository->roles();

        $this->assertNotEmpty($roles);
        $this->assertContains('finance', array_column($roles, 'slug'));
        $this->assertContains('creator', array_column($roles, 'slug'));
    }

    public function test_find_role_by_slug_and_id_return_seeded_role(): void
    {
        $role = $this->repository->findRoleBySlug('finance');

        $this->assertNotNull($role);
        $this->assertSame($role->id, $this->repository->findRoleById((int) $role->id)?->id);
    }

    public function test_role_ids_for_user_and_permission_ids_for_roles_return_expected_mappings(): void
    {
        $user = $this->createUser();
        $role = $this->repository->findRoleBySlug('finance');

        OpenCollabSiteUserRole::create([
            'site_id' => $this->siteId,
            'user_id' => $user->id,
            'role_id' => $role->id,
        ]);

        $roleIds = $this->repository->roleIdsForUser($this->siteId, $user->id);
        $permissionIds = $this->repository->permissionIdsForRoles($roleIds);
        $permissionSlugs = $this->repository->permissionSlugsForIds($permissionIds);

        $this->assertSame([$role->id], $roleIds);
        $this->assertContains('payout.approve', $permissionSlugs);
        $this->assertContains('ledger.view', $permissionSlugs);
    }

    public function test_overrides_for_user_and_site_return_expected_rows(): void
    {
        $user = $this->createUser();
        $permission = $this->repository->findPermissionBySlug('content.submit');

        OpenCollabSiteUserPermission::create([
            'site_id' => $this->siteId,
            'user_id' => $user->id,
            'permission_id' => $permission->id,
            'granted' => false,
        ]);

        $userOverrides = $this->repository->overridesForUser($this->siteId, $user->id);
        $siteOverrides = $this->repository->overridesForSite($this->siteId);

        $this->assertCount(1, $userOverrides);
        $this->assertSame((int) $permission->id, $userOverrides[0]['permission_id']);
        $this->assertFalse($userOverrides[0]['granted']);
        $this->assertSame('content.submit', $this->repository->permissionSlugForId((int) $permission->id));
        $this->assertCount(1, $siteOverrides);
    }

    public function test_role_permission_map_reflects_existing_mappings(): void
    {
        $role = $this->repository->findRoleBySlug('legal');
        $permission = $this->repository->findPermissionBySlug('contract.publish');

        $map = $this->repository->rolePermissionMap();

        $this->assertContains((int) $permission->id, $map[(int) $role->id] ?? []);
    }

    public function test_users_for_site_and_site_membership_user_ids_return_only_site_users(): void
    {
        $userA = $this->createUser(['name' => 'Alpha']);
        $userB = $this->createUser(['name' => 'Beta']);
        $otherSite = $this->createSite(['slug' => 'other-site-' . uniqid()]);
        $otherUser = $this->factory(\App\Models\User::class)->forSite($otherSite->id)->create(['name' => 'Gamma']);
        UserSite::firstOrCreate(['user_id' => $otherUser->id, 'site_id' => $otherSite->id]);

        $users = $this->repository->usersForSite($this->siteId);
        $userIds = array_map(static fn(array $user) => (int) $user['id'], $users);
        $membershipIds = $this->repository->siteMembershipUserIds($this->siteId);

        $this->assertContains($userA->id, $userIds);
        $this->assertContains($userB->id, $userIds);
        $this->assertNotContains($otherUser->id, $userIds);
        $this->assertContains($userA->id, $membershipIds);
        $this->assertContains($userB->id, $membershipIds);
        $this->assertNotContains($otherUser->id, $membershipIds);
    }

    public function test_replace_role_permissions_rewrites_mappings(): void
    {
        $role = $this->repository->findRoleBySlug('reviewer');
        $permission = $this->repository->findPermissionBySlug('content.publish');

        $this->repository->replaceRolePermissions((int) $role->id, [(int) $permission->id]);

        $this->assertSame(1, OpenCollabRolePermission::where('role_id', $role->id)->count());
        $this->assertDatabaseHas('oc_role_permissions', [
            'role_id' => $role->id,
            'permission_id' => $permission->id,
        ]);
    }

    public function test_replace_user_roles_rewrites_assignments(): void
    {
        $user = $this->createUser();
        $reviewer = $this->repository->findRoleBySlug('reviewer');
        $finance = $this->repository->findRoleBySlug('finance');

        $this->repository->replaceUserRoles($this->siteId, $user->id, [(int) $reviewer->id]);
        $this->repository->replaceUserRoles($this->siteId, $user->id, [(int) $finance->id]);

        $this->assertSame([(int) $finance->id], $this->repository->roleIdsForUser($this->siteId, $user->id));
        $this->assertDatabaseMissing('oc_site_user_roles', [
            'site_id' => $this->siteId,
            'user_id' => $user->id,
            'role_id' => $reviewer->id,
        ]);
    }

    public function test_upsert_user_override_creates_and_updates_override(): void
    {
        $user = $this->createUser();
        $permission = $this->repository->findPermissionBySlug('content.submit');

        $this->repository->upsertUserOverride($this->siteId, $user->id, (int) $permission->id, true);
        $this->repository->upsertUserOverride($this->siteId, $user->id, (int) $permission->id, false);

        $override = OpenCollabSiteUserPermission::where('site_id', $this->siteId)
            ->where('user_id', $user->id)
            ->where('permission_id', $permission->id)
            ->first();

        $this->assertNotNull($override);
        $this->assertFalse((bool) $override->granted);
        $this->assertSame(1, OpenCollabSiteUserPermission::where('site_id', $this->siteId)->where('user_id', $user->id)->count());
    }

    public function test_create_role_ensure_site_role_and_delete_methods_manage_role_lifecycle(): void
    {
        $role = $this->repository->createRole([
            'name' => 'Temporary Role',
            'slug' => 'temporary_role_' . uniqid(),
            'is_system' => false,
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        $user = $this->createUser();

        $this->repository->ensureSiteRole($this->siteId, (int) $role->id, $role->name, true);
        OpenCollabSiteUserRole::create([
            'site_id' => $this->siteId,
            'user_id' => $user->id,
            'role_id' => $role->id,
        ]);

        $this->repository->deleteUserRolesForRole($this->siteId, (int) $role->id);
        $this->repository->deleteSiteRole($this->siteId, (int) $role->id);
        $this->repository->deleteRole((int) $role->id);

        $this->assertDatabaseMissing('oc_site_user_roles', ['role_id' => $role->id]);
        $this->assertDatabaseMissing('oc_site_roles', ['role_id' => $role->id]);
        $this->assertDatabaseMissing('oc_roles', ['id' => $role->id]);
    }

    public function test_site_role_count_for_role_returns_number_of_site_assignments(): void
    {
        $role = $this->repository->findRoleBySlug('creator');
        $otherSite = $this->createSite(['slug' => 'count-site-' . uniqid()]);
        $initialCount = $this->repository->siteRoleCountForRole((int) $role->id);

        $this->repository->ensureSiteRole($this->siteId, (int) $role->id, $role->name, true);
        $this->repository->ensureSiteRole($otherSite->id, (int) $role->id, $role->name, true);

        $this->assertSame($initialCount + 1, $this->repository->siteRoleCountForRole((int) $role->id));
    }

    public function test_attach_permission_to_role_if_missing_and_delete_role_permissions_work_together(): void
    {
        $role = $this->repository->createRole([
            'name' => 'Permission Test Role',
            'slug' => 'permission_test_role_' . uniqid(),
            'is_system' => false,
            'created_at' => date('Y-m-d H:i:s'),
        ]);
        $permission = $this->repository->findPermissionBySlug('violation.view');

        $this->repository->attachPermissionToRoleIfMissing((int) $role->id, (int) $permission->id);
        $this->repository->attachPermissionToRoleIfMissing((int) $role->id, (int) $permission->id);

        $this->assertSame(1, OpenCollabRolePermission::where('role_id', $role->id)->count());

        $this->repository->deleteRolePermissions((int) $role->id);

        $this->assertSame(0, OpenCollabRolePermission::where('role_id', $role->id)->count());
    }

    public function test_permission_ids_site_ids_user_exists_site_exists_and_legacy_role_queries_return_expected_values(): void
    {
        $user = $this->createUser(['role' => 'admin']);

        $permissionIds = $this->repository->permissionIds();
        $siteIds = $this->repository->siteIds();

        $this->assertNotEmpty($permissionIds);
        $this->assertContains($this->siteId, $siteIds);
        $this->assertTrue($this->repository->userExists($user->id));
        $this->assertTrue($this->repository->siteExists($this->siteId));
        $this->assertSame('admin', $this->repository->legacyRoleForUser($user->id));
    }

    public function test_create_permission_if_missing_and_create_or_update_role_are_idempotent(): void
    {
        $permissionSlug = 'custom.permission.' . uniqid();
        $this->repository->createPermissionIfMissing([
            'name' => 'Custom Permission',
            'slug' => $permissionSlug,
            'group' => 'custom',
        ]);
        $this->repository->createPermissionIfMissing([
            'name' => 'Custom Permission',
            'slug' => $permissionSlug,
            'group' => 'custom',
        ]);

        $roleSlug = 'custom_role_' . uniqid();
        $this->repository->createOrUpdateRole($roleSlug, [
            'name' => 'Custom Role',
            'is_system' => false,
            'created_at' => date('Y-m-d H:i:s'),
        ]);
        $updatedRole = $this->repository->createOrUpdateRole($roleSlug, [
            'name' => 'Updated Custom Role',
            'is_system' => false,
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        $this->assertSame(1, OpenCollabPermission::where('slug', $permissionSlug)->count());
        $this->assertSame('Updated Custom Role', $updatedRole->name);
        $this->assertSame(1, OpenCollabRole::where('slug', $roleSlug)->count());
    }

    public function test_audit_for_site_and_create_audit_log_return_recent_entries(): void
    {
        $user = $this->createUser();

        $this->repository->createAuditLog($this->siteId, $this->authenticatedUser?->id, $user->id, 'user_roles_assigned', [
            'role_ids' => [1],
        ]);

        $entries = $this->repository->auditForSite($this->siteId, 10);

        $this->assertNotEmpty($entries);
        $this->assertSame('user_roles_assigned', $entries[0]['action']);
        $this->assertDatabaseHas('oc_rbac_audit_logs', [
            'site_id' => $this->siteId,
            'target_user_id' => $user->id,
            'action' => 'user_roles_assigned',
        ]);
    }

    public function test_user_role_map_for_site_returns_expected_assignments(): void
    {
        $user = $this->createUser();
        $creator = $this->repository->findRoleBySlug('creator');
        $reviewer = $this->repository->findRoleBySlug('reviewer');

        OpenCollabSiteUserRole::create([
            'site_id' => $this->siteId,
            'user_id' => $user->id,
            'role_id' => $creator->id,
        ]);
        OpenCollabSiteUserRole::create([
            'site_id' => $this->siteId,
            'user_id' => $user->id,
            'role_id' => $reviewer->id,
        ]);

        $map = $this->repository->userRoleMapForSite($this->siteId);

        $this->assertEqualsCanonicalizing([(int) $creator->id, (int) $reviewer->id], $map[$user->id]);
    }
}
