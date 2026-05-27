<?php

namespace App\Tests\Functional\Controllers\OpenCollab;

use App\Framework\Support\Config;
use App\Models\UserSite;
use App\Tests\Functional\Controllers\FunctionalTestCase;
use App\Tests\Unit\Repositories\Concerns\CreatesTestData;

class SiteSettingsControllerTest extends FunctionalTestCase
{
    use CreatesTestData;

    public function test_show_renders_rbac_tabs_current_overrides_and_in_place_requests(): void
    {
        $this->enableSiteRbac();

        $response = $this->getForSite('/open-collab/admin/sites/settings');
        $content = $response->getContent();

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertStringContainsString('Roles', $content);
        $this->assertStringContainsString('Permissions Matrix', $content);
        $this->assertStringContainsString('Overrides', $content);
        $this->assertStringContainsString('Audit Log', $content);
        $this->assertStringContainsString("permissions: '/api/{$this->siteSlug}/open-collab/admin/rbac/permissions'", $content);
        $this->assertStringContainsString("roles: '/api/{$this->siteSlug}/open-collab/admin/rbac/roles'", $content);
        $this->assertStringContainsString("members: '/api/{$this->siteSlug}/open-collab/admin/rbac/members'", $content);
        $this->assertStringContainsString("overrides: '/api/{$this->siteSlug}/open-collab/admin/rbac/overrides'", $content);
        $this->assertStringContainsString("audit: '/api/{$this->siteSlug}/open-collab/admin/rbac/audit'", $content);
        $this->assertStringContainsString('Promise.all([', $content);
        $this->assertStringContainsString("fetchSegment('permissions')", $content);
        $this->assertStringContainsString("fetchSegment('roles')", $content);
        $this->assertStringContainsString("fetchSegment('members')", $content);
        $this->assertStringContainsString("fetchSegment('overrides')", $content);
        $this->assertStringContainsString("fetchSegment('audit')", $content);
        $this->assertStringContainsString('/open-collab/admin/rbac/role-permissions/${roleId}', $content);
        $this->assertStringContainsString('/open-collab/admin/rbac/overrides/${userId}', $content);
        $this->assertStringContainsString('/open-collab/admin/rbac/overrides/${userId}/${encodeURIComponent(permissionSlug)}', $content);
        $this->assertStringContainsString('async request(url, options)', $content);
        $this->assertStringContainsString('renderRoles()', $content);
        $this->assertStringContainsString('renderMatrix()', $content);
        $this->assertStringContainsString('renderOverrides()', $content);
        $this->assertStringContainsString('renderAudit()', $content);
        $this->assertStringNotContainsString('refreshRbacPanels()', $content);
        $this->assertStringNotContainsString('window.location.reload()', $content);
    }

    public function test_show_allows_legacy_admin_access_in_hybrid_mode(): void
    {
        Config::set('rbac.site_enabled', true);

        $restrictedUser = $this->createUser([
            'name' => 'Restricted Settings User',
            'email' => 'restricted-settings@example.com',
            'role' => 'admin',
        ]);
        $this->actingAs($restrictedUser);

        $response = $this->getForSite('/open-collab/admin/sites/settings');

        $this->assertEquals(200, $response->getStatusCode());
    }

    public function test_update_persists_site_settings_via_api(): void
    {
        $response = $this->postForSite('/api/open-collab/admin/sites/settings', [
            'guidelines_version' => 2,
            'require_payment_setup' => true,
            'require_contracts' => false,
            'require_guidelines_ack' => true,
        ], [], ['Accept' => 'application/json']);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertDatabaseHas('sites', [
            'id' => $this->siteId,
            'guidelines_version' => 2,
            'require_payment_setup' => 1,
            'require_contracts' => 0,
            'require_guidelines_ack' => 1,
        ]);
    }

    public function test_search_users_returns_matching_unassigned_users_only(): void
    {
        $match = $this->createUser([
            'name' => 'Search Match User',
            'email' => 'search-match@example.com',
            'is_active' => true,
        ]);
        UserSite::where('user_id', $match->id)->where('site_id', $this->siteId)->delete();

        $assigned = $this->createUser([
            'name' => 'Already Assigned User',
            'email' => 'already-assigned@example.com',
            'is_active' => true,
        ]);

        $otherSiteUser = $this->createUser([
            'name' => 'Outside Search User',
            'email' => 'outside-search@example.com',
            'is_active' => true,
        ]);
        UserSite::where('user_id', $otherSiteUser->id)->where('site_id', $this->siteId)->delete();

        $response = $this->getForSite('/api/open-collab/admin/users/search?q=Search%20Match%20User', [
            'Accept' => 'application/json',
        ]);
        $payload = json_decode($response->getContent(), true);
        $userIds = array_column($payload['users'] ?? $payload['data']['users'] ?? [], 'id');

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertContains($match->id, $userIds);
        $this->assertNotContains($assigned->id, $userIds);
    }

    public function test_assign_user_grants_site_access_and_returns_user_payload(): void
    {
        $user = $this->createUser([
            'name' => 'Assignable User',
            'email' => 'assignable-user@example.com',
        ]);
        UserSite::where('user_id', $user->id)->where('site_id', $this->siteId)->delete();

        $response = $this->postForSite('/api/open-collab/admin/sites/users', [
            'user_id' => $user->id,
        ], [], ['Accept' => 'application/json']);
        $payload = json_decode($response->getContent(), true);

        $this->assertEquals(201, $response->getStatusCode());
        $this->assertSame($user->id, $payload['user']['id']);
        $this->assertDatabaseHas('oc_user_sites', [
            'user_id' => $user->id,
            'site_id' => $this->siteId,
        ]);
    }

    public function test_assign_user_returns_existing_access_message_when_user_already_belongs_to_site(): void
    {
        $user = $this->createUser([
            'name' => 'Existing Access User',
            'email' => 'existing-access@example.com',
        ]);

        $response = $this->postForSite('/api/open-collab/admin/sites/users', [
            'user_id' => $user->id,
        ], [], ['Accept' => 'application/json']);
        $payload = json_decode($response->getContent(), true);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertSame('User already has access to this site.', $payload['message']);
    }

    public function test_remove_user_revokes_site_access(): void
    {
        $user = $this->createUser([
            'name' => 'Removable User',
            'email' => 'removable-user@example.com',
        ]);

        $response = $this->deleteForSite("/api/open-collab/admin/sites/users/{$user->id}", [
            'Accept' => 'application/json',
        ]);
        $payload = json_decode($response->getContent(), true);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertSame('User removed from site.', $payload['data']['message'] ?? null);
        $this->assertDatabaseMissing('oc_user_sites', [
            'user_id' => $user->id,
            'site_id' => $this->siteId,
        ]);
    }

    public function test_remove_user_returns_404_when_user_has_no_site_access(): void
    {
        $user = $this->createUser([
            'name' => 'Missing Access User',
            'email' => 'missing-access@example.com',
        ]);
        UserSite::where('user_id', $user->id)->where('site_id', $this->siteId)->delete();

        $response = $this->deleteForSite("/api/open-collab/admin/sites/users/{$user->id}", [
            'Accept' => 'application/json',
        ]);

        $this->assertEquals(404, $response->getStatusCode());
    }
}
