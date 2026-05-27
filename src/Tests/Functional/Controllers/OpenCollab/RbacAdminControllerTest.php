<?php

namespace App\Tests\Functional\Controllers\OpenCollab;

use App\Framework\Support\Config;
use App\Models\OpenCollabPermission;
use App\Models\OpenCollabRole;
use App\Models\OpenCollabRolePermission;
use App\Models\OpenCollabSiteUserRole;
use App\Models\User;
use App\Tests\Functional\Controllers\FunctionalTestCase;
use App\Tests\Unit\Repositories\Concerns\CreatesTestData;

class RbacAdminControllerTest extends FunctionalTestCase
{
    use CreatesTestData;

    private User $member;

    protected function setUp(): void
    {
        parent::setUp();

        $this->enableSiteRbac();

        $this->member = $this->createUser([
            'name' => 'RBAC Member',
            'email' => 'rbac-member@example.com',
            'role' => 'user',
            'is_contributor' => true,
        ]);
    }

    public function test_summary_returns_seeded_roles_permissions_members_and_audit_feed(): void
    {
        $response = $this->getForSite('/api/open-collab/admin/rbac');
        $payload = json_decode($response->getContent(), true);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertNotEmpty($payload['roles']);
        $this->assertNotEmpty($payload['permissions']);
        $this->assertNotEmpty($payload['members']);
        $this->assertArrayHasKey('audit', $payload);
    }

    public function test_set_override_persists_user_override_and_audit_log(): void
    {
        $response = $this->postForSite("/api/open-collab/admin/rbac/overrides/{$this->member->id}", [
            'permission_slug' => 'content.submit',
            'granted' => false,
        ]);

        $permission = OpenCollabPermission::where('slug', 'content.submit')->first();

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertDatabaseHas('oc_site_user_permissions', [
            'site_id' => $this->siteId,
            'user_id' => $this->member->id,
            'permission_id' => $permission->id,
            'granted' => 0,
        ]);
        $this->assertDatabaseHas('oc_rbac_audit_logs', [
            'site_id' => $this->siteId,
            'target_user_id' => $this->member->id,
            'action' => 'user_permission_override_set',
        ]);
    }

    public function test_assign_member_roles_persists_role_assignments_and_audit_log(): void
    {
        $role = OpenCollabRole::where('slug', 'finance')->first();

        $response = $this->postForSite("/api/open-collab/admin/contributors/{$this->member->id}/roles", [
            'role_ids' => [$role->id],
        ]);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertDatabaseHas('oc_site_user_roles', [
            'site_id' => $this->siteId,
            'user_id' => $this->member->id,
            'role_id' => $role->id,
        ]);
        $this->assertDatabaseHas('oc_rbac_audit_logs', [
            'site_id' => $this->siteId,
            'target_user_id' => $this->member->id,
            'action' => 'user_roles_assigned',
        ]);
    }

    public function test_sync_role_permissions_replaces_mapping_and_writes_audit_log(): void
    {
        $role = OpenCollabRole::where('slug', 'finance')->first();
        $permission = OpenCollabPermission::where('slug', 'ledger.view')->first();

        $response = $this->postForSite("/api/open-collab/admin/rbac/role-permissions/{$role->id}", [
            'permission_slugs' => ['ledger.view'],
        ]);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals(1, OpenCollabRolePermission::where('role_id', $role->id)->count());
        $this->assertDatabaseHas('oc_role_permissions', [
            'role_id' => $role->id,
            'permission_id' => $permission->id,
        ]);
        $this->assertDatabaseHas('oc_rbac_audit_logs', [
            'site_id' => $this->siteId,
            'action' => 'role_permissions_synced',
        ]);
    }

    public function test_summary_returns_403_for_user_without_rbac_permissions(): void
    {
        $this->enableSiteRbac();

        $restrictedUser = $this->createUser([
            'email' => 'rbac-summary-restricted@example.com',
            'role' => 'user',
            'is_contributor' => true,
        ]);
        $this->actingAs($restrictedUser);

        $response = $this->getForSite('/api/open-collab/admin/rbac');

        $this->assertEquals(403, $response->getStatusCode());
    }

    public function test_assign_member_roles_returns_403_for_user_without_role_management_permission(): void
    {
        $this->enableSiteRbac();

        $restrictedUser = $this->createUser([
            'email' => 'rbac-assign-restricted@example.com',
            'role' => 'user',
        ]);
        $this->actingAs($restrictedUser);
        $this->grantSitePermission($restrictedUser, 'content.review');

        $role = OpenCollabRole::where('slug', 'finance')->first();
        $response = $this->postForSite("/api/open-collab/admin/contributors/{$this->member->id}/roles", [
            'role_ids' => [$role->id],
        ]);

        $this->assertEquals(403, $response->getStatusCode());
    }

    public function test_create_role_persists_custom_role(): void
    {
        $response = $this->postForSite('/api/open-collab/admin/rbac/roles', [
            'name' => 'Custom Finance Ops',
            'slug' => 'custom_finance_ops',
            'permission_slugs' => ['payout.view'],
        ]);

        $this->assertEquals(201, $response->getStatusCode());
        $this->assertDatabaseHas('oc_roles', [
            'slug' => 'custom_finance_ops',
            'name' => 'Custom Finance Ops',
        ]);
    }

    public function test_delete_role_removes_custom_role(): void
    {
        $this->postForSite('/api/open-collab/admin/rbac/roles', [
            'name' => 'Disposable',
            'slug' => 'disposable',
            'permission_slugs' => [],
        ]);

        $role = OpenCollabRole::where('slug', 'disposable')->first();

        $response = $this->deleteForSite("/api/open-collab/admin/rbac/roles/{$role->id}");

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertDatabaseMissing('oc_roles', ['id' => $role->id]);
    }
}
