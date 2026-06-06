<?php

namespace App\Tests\Unit\Services\OpenCollab;

use App\Framework\Support\Config;
use App\Models\OpenCollabPermission;
use App\Models\OpenCollabRole;
use App\Models\OpenCollabRolePermission;
use App\Models\OpenCollabSiteUserPermission;
use App\Models\OpenCollabSiteUserRole;
use App\Repositories\OpenCollab\RbacRepository;
use App\Services\OpenCollab\RbacAuditLogger;
use App\Services\OpenCollab\RbacBootstrapper;
use App\Services\OpenCollab\RbacManagementService;
use App\Services\OpenCollab\SitePermissionResolver;
use App\Tests\Unit\Repositories\RepositoryTestCase;
use App\Tests\Unit\Repositories\Concerns\CreatesTestData;
use Mockery;

class RbacManagementServiceTest extends RepositoryTestCase
{
    use CreatesTestData;

    private RbacManagementService $service;
    private RbacRepository $rbacRepository;
    private RbacBootstrapper $bootstrapper;

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('rbac', require __DIR__ . '/../../../../config/rbac.php');
        $this->rbacRepository = new RbacRepository();
        $this->bootstrapper = new RbacBootstrapper($this->rbacRepository);

        $resolver = Mockery::mock(SitePermissionResolver::class);
        $resolver->shouldReceive('invalidate')->byDefault();
        $resolver->shouldReceive('invalidateMany')->byDefault();

        $this->service = new RbacManagementService(
            $this->bootstrapper,
            $resolver,
            new RbacAuditLogger($this->rbacRepository),
            $this->rbacRepository,
        );
    }

    public function test_assign_user_roles_persists_assignments_and_audit(): void
    {
        $user = $this->createUser([
            'name' => 'Managed User',
            'email' => 'managed-user@example.com',
            'role' => 'admin',
        ]);

        $this->bootstrapper->ensureSeeded($this->siteId);
        $role = OpenCollabRole::where('slug', 'finance')->first();

        $this->service->assignUserRoles($this->siteId, $user->id, [$role->id], 99);

        $this->assertDatabaseHas('oc_site_user_roles', [
            'site_id' => $this->siteId,
            'user_id' => $user->id,
            'role_id' => $role->id,
        ]);
        $this->assertDatabaseHas('oc_rbac_audit_logs', [
            'site_id' => $this->siteId,
            'actor_user_id' => 99,
            'target_user_id' => $user->id,
            'action' => 'user_roles_assigned',
        ]);
    }

    public function test_sync_role_permissions_replaces_mappings_and_writes_audit(): void
    {
        $user = $this->createUser([
            'name' => 'Audit User',
            'email' => 'audit-user@example.com',
            'role' => 'admin',
        ]);

        $this->bootstrapper->ensureSeeded($this->siteId);
        $role = OpenCollabRole::where('slug', 'finance')->first();
        $permission = OpenCollabPermission::where('slug', 'ledger.view')->first();

        $this->service->syncRolePermissions($this->siteId, $role->id, ['ledger.view'], 88);

        $this->assertDatabaseHas('oc_role_permissions', [
            'role_id' => $role->id,
            'permission_id' => $permission->id,
        ]);
        $this->assertEquals(1, OpenCollabRolePermission::where('role_id', $role->id)->count());
        $this->assertDatabaseHas('oc_rbac_audit_logs', [
            'site_id' => $this->siteId,
            'actor_user_id' => 88,
            'action' => 'role_permissions_synced',
        ]);
    }

    public function test_set_user_override_persists_override_invalidates_cache_and_writes_audit(): void
    {
        $user = $this->createUser([
            'name' => 'Override User',
            'email' => 'override-user@example.com',
            'role' => 'admin',
        ]);

        $this->bootstrapper->ensureSeeded($this->siteId);

        $this->service->setUserOverride($this->siteId, $user->id, 'content.submit', false, 77);

        $permission = OpenCollabPermission::where('slug', 'content.submit')->first();

        $this->assertDatabaseHas('oc_site_user_permissions', [
            'site_id' => $this->siteId,
            'user_id' => $user->id,
            'permission_id' => $permission->id,
            'granted' => 0,
        ]);
        $this->assertDatabaseHas('oc_rbac_audit_logs', [
            'site_id' => $this->siteId,
            'actor_user_id' => 77,
            'target_user_id' => $user->id,
            'action' => 'user_permission_override_set',
        ]);
    }

    public function test_summary_for_site_includes_role_assignments_and_overrides(): void
    {
        $user = $this->createUser([
            'name' => 'Summary User',
            'email' => 'summary-user@example.com',
            'role' => 'admin',
        ]);

        $this->bootstrapper->ensureSeeded($this->siteId);
        $role = OpenCollabRole::where('slug', 'finance')->first();
        OpenCollabSiteUserRole::create([
            'site_id' => $this->siteId,
            'user_id' => $user->id,
            'role_id' => $role->id,
        ]);

        $permission = OpenCollabPermission::where('slug', 'ledger.view')->first();
        OpenCollabSiteUserPermission::create([
            'site_id' => $this->siteId,
            'user_id' => $user->id,
            'permission_id' => $permission->id,
            'granted' => true,
        ]);

        $summary = $this->service->summaryForSite($this->siteId);
        $member = collect($summary['members'])->firstWhere('id', $user->id);
        $override = collect($summary['overrides'])->firstWhere('user_id', $user->id);

        $this->assertContains($role->id, $member['role_ids']);
        $this->assertSame('ledger.view', $override['permission_slug']);
        $this->assertTrue($override['granted']);
    }

    public function test_create_role_creates_custom_role_site_assignment_and_audit(): void
    {
        $role = $this->service->createRole($this->siteId, 'Custom Reviewer', null, ['content.review'], 55);

        $createdRole = OpenCollabRole::find($role['id']);

        $this->assertSame('Custom Reviewer', $createdRole->name);
        $this->assertSame('custom_reviewer', $createdRole->slug);
        $this->assertDatabaseHas('oc_site_roles', [
            'site_id' => $this->siteId,
            'role_id' => $createdRole->id,
        ]);
        $this->assertDatabaseHas('oc_rbac_audit_logs', [
            'site_id' => $this->siteId,
            'actor_user_id' => 55,
            'action' => 'role_created',
        ]);
    }

    public function test_delete_role_removes_custom_role_and_audit(): void
    {
        $role = $this->service->createRole($this->siteId, 'Disposable Role', 'disposable_role', [], 55);

        $this->service->deleteRole($this->siteId, $role['id'], 66);

        $this->assertDatabaseMissing('oc_roles', ['id' => $role['id']]);
        $this->assertDatabaseMissing('oc_site_roles', [
            'site_id' => $this->siteId,
            'role_id' => $role['id'],
        ]);
        $this->assertDatabaseHas('oc_rbac_audit_logs', [
            'site_id' => $this->siteId,
            'actor_user_id' => 66,
            'action' => 'role_deleted',
        ]);
    }
}
