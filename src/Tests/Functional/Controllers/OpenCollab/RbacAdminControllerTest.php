<?php

namespace App\Tests\Functional\Controllers\OpenCollab;

use App\Framework\Support\Config;
use App\Models\OpenCollabPermission;
use App\Models\OpenCollabRole;
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

        Config::set('rbac.site_enabled', true);

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
}
