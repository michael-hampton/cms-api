<?php

namespace App\Tests\Unit\Services\OpenCollab;

use App\Framework\Support\Config;
use App\Models\OpenCollabPermission;
use App\Models\OpenCollabRole;
use App\Models\OpenCollabRolePermission;
use App\Models\OpenCollabSiteUserRole;
use App\Models\Site;
use App\Models\User;
use App\Models\UserSite;
use App\Services\OpenCollab\RbacAuditLogger;
use App\Services\OpenCollab\RbacBootstrapper;
use App\Services\OpenCollab\RbacManagementService;
use App\Services\OpenCollab\SitePermissionResolver;
use App\Tests\Unit\Repositories\RepositoryTestCase;
use Mockery;

class RbacManagementServiceTest extends RepositoryTestCase
{
    private RbacManagementService $service;

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('rbac', require __DIR__ . '/../../../../config/rbac.php');

        $resolver = Mockery::mock(SitePermissionResolver::class);
        $resolver->shouldReceive('invalidate')->byDefault();

        $this->service = new RbacManagementService(
            new RbacBootstrapper(),
            $resolver,
            new RbacAuditLogger(),
        );
    }

    public function test_assign_user_roles_persists_assignments_and_audit(): void
    {
        $site = Site::create(['name' => 'RBAC Mgmt', 'slug' => 'rbac-mgmt', 'is_default' => false]);
        $user = User::create([
            'name' => 'Managed User',
            'email' => 'managed-user@example.com',
            'password' => 'secret',
            'role' => 'admin',
        ]);
        UserSite::create(['user_id' => $user->id, 'site_id' => $site->id]);

        (new RbacBootstrapper())->ensureSeeded($site->id);
        $role = OpenCollabRole::where('slug', 'finance')->first();

        $this->service->assignUserRoles($site->id, $user->id, [$role->id], 99);

        $this->assertDatabaseHas('oc_site_user_roles', [
            'site_id' => $site->id,
            'user_id' => $user->id,
            'role_id' => $role->id,
        ]);
        $this->assertDatabaseHas('oc_rbac_audit_logs', [
            'site_id' => $site->id,
            'actor_user_id' => 99,
            'target_user_id' => $user->id,
            'action' => 'user_roles_assigned',
        ]);
    }

    public function test_sync_role_permissions_replaces_mappings_and_writes_audit(): void
    {
        $site = Site::create(['name' => 'RBAC Mgmt 2', 'slug' => 'rbac-mgmt-2', 'is_default' => false]);
        $user = User::create([
            'name' => 'Audit User',
            'email' => 'audit-user@example.com',
            'password' => 'secret',
            'role' => 'admin',
        ]);
        UserSite::create(['user_id' => $user->id, 'site_id' => $site->id]);

        (new RbacBootstrapper())->ensureSeeded($site->id);
        $role = OpenCollabRole::where('slug', 'finance')->first();
        $permission = OpenCollabPermission::where('slug', 'ledger.view')->first();

        $this->service->syncRolePermissions($site->id, $role->id, ['ledger.view'], 88);

        $this->assertDatabaseHas('oc_role_permissions', [
            'role_id' => $role->id,
            'permission_id' => $permission->id,
        ]);
        $this->assertEquals(1, OpenCollabRolePermission::where('role_id', $role->id)->count());
        $this->assertDatabaseHas('oc_rbac_audit_logs', [
            'site_id' => $site->id,
            'actor_user_id' => 88,
            'action' => 'role_permissions_synced',
        ]);
    }
}
